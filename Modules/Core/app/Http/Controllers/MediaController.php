<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Services\GuestToken;
use Modules\Core\Services\ShowcaseMedia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert les médias uploadés (disque `public`) derrière une authentification.
 *
 * Remplace le lien symbolique public/storage : sans lui, n'importe qui
 * possédant l'URL d'une image ou d'une vidéo pouvait la télécharger sans
 * compte. Les URLs restent identiques (/storage/...), donc aucun contenu
 * existant n'est à réécrire.
 *
 * - Staff connecté (session) : accès.
 * - Apprenant : session (PWA) ou token Bearer (app mobile), compte actif
 *   et non suspendu.
 * - Invité (jeton vitrine) : uniquement les médias du contenu vitrine actif.
 * - Les requêtes Range (lecture vidéo/audio) sont gérées nativement.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user) {
            abort_unless($user->is_active, 403, 'Accès aux médias réservé aux utilisateurs connectés.');

            $learner = $user->learner;
            abort_if($learner && $learner->isSuspended(), 403, 'Compte suspendu.');
        } else {
            // Invité du mode découverte : uniquement les médias du contenu vitrine actif.
            $token = $request->cookie(GuestToken::COOKIE) ?? $request->header('X-Guest-Token');
            abort_unless(
                GuestToken::valid($token) && ShowcaseMedia::allows($path),
                403,
                'Accès aux médias réservé aux utilisateurs connectés.'
            );
        }

        // Jamais de remontée d'arborescence.
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $absolute = $disk->path($path);
        abort_unless(is_file($absolute), 404);

        $response = response()->file($absolute, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            // Un fichier ouvert directement ne peut exécuter aucun script.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);

        // Cache navigateur / service worker privé, jamais de cache partagé
        // (CDN, proxy) — BinaryFileResponse est « public » par défaut.
        $response->setPrivate();
        $response->setMaxAge(2592000);

        return $response;
    }
}
