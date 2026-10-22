<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Core\Models\Resource;
use Modules\Core\Services\ContentPlacement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lecture protégée des ressources par l'apprenant (PWA : session ; mobile :
 * Bearer). Règle « consultable, non récupérable » : `view` ne livre qu'une
 * URL signée de courte durée vers `file` ; `download` exige allow_download.
 */
class ResourceController extends Controller
{
    public function view(Request $request, int $resource): JsonResponse
    {
        $resource = $this->authorizeLearner($request, $resource);

        if ($resource->kind === 'link') {
            return response()->json(['type' => 'link', 'url' => $resource->url]);
        }

        if ($resource->kind === 'video') {
            $ref = $resource->video?->ref;
            abort_unless($ref, 404);

            return response()->json(['type' => 'video', 'video_ref' => $ref]);
        }

        if ($resource->kind === 'document') {
            if ($resource->conversion_status === 'failed') {
                return response()->json(['status' => 'failed'], 409);
            }
            if ($resource->conversion_status !== 'ready' || ! $resource->pdf_path) {
                return response()->json(['status' => 'pending'], 202);
            }
        }

        $expires = now()->addSeconds((int) config('media.video.signed_ttl', 900));

        return response()->json([
            'type' => $resource->kind === 'document' ? 'pdf' : $resource->kind,
            'url' => URL::temporarySignedRoute('learn.v1.resources.file', $expires, ['resource' => $resource->id]),
            'expires_at' => $expires->toIso8601String(),
        ]);
    }

    public function download(Request $request, int $resource): StreamedResponse
    {
        $resource = $this->authorizeLearner($request, $resource);
        abort_unless($resource->allow_download, 403, 'Téléchargement non autorisé pour cette ressource.');
        abort_unless($resource->disk && $resource->path, 404);

        $disk = Storage::disk($resource->disk);
        abort_unless($disk->exists($resource->path), 404);

        $response = $disk->download($resource->path, $resource->original_name ?: basename($resource->path), [
            'Content-Type' => $resource->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Fichier d'une ressource (original, ou PDF converti pour un document).
     * Protégé par le middleware `signed` seulement (voir routes) : fonctionne
     * sans cookie ni jeton (lecteur PDF, <img>, <audio>, application mobile).
     */
    public function file(int $resource): BinaryFileResponse
    {
        $resource = Resource::find($resource);
        abort_unless($resource && $resource->is_active && $resource->disk, 404);

        [$path, $mime] = match ($resource->kind) {
            'pdf', 'image', 'audio' => [$resource->path, $resource->mime],
            'document' => [$resource->conversion_status === 'ready' ? $resource->pdf_path : null, 'application/pdf'],
            default => [null, null],
        };
        abort_unless($path, 404);

        $disk = Storage::disk($resource->disk);
        abort_unless($disk->exists($path), 404);
        $absolute = $disk->path($path);
        abort_unless(is_file($absolute), 404);

        $headers = [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ];
        // Comme MediaController, sauf pour les PDF : la directive `sandbox`
        // empêche le lecteur PDF intégré du navigateur de s'afficher.
        if ($mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        $response = response()->file($absolute, $headers);
        // URL signée de courte durée : jamais de cache partagé ni persistant.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Identifiant et non liaison de modèle : la liaison s'exécuterait avant
     * le middleware de consultation (404 au lieu de 403 read_only) et
     * révélerait l'existence d'une ressource non visible.
     */
    private function authorizeLearner(Request $request, int $id): Resource
    {
        $learner = $request->user()?->learner;
        $resource = $learner && app(ContentPlacement::class)->isVisible($learner, 'resource', $id)
            ? Resource::find($id)
            : null;
        abort_unless($resource && $resource->is_active, 403, 'Ressource indisponible.');

        return $resource;
    }
}
