<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route('cores.dashboard');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('core::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('core::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('core::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}

    /**
     * Upload d'un média pédagogique (image, audio, vidéo) sur le disque
     * `public` — servi ensuite par la route authentifiée /storage/{path}.
     */
    public function uploadMedia(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Image, audio ou vidéo — utilisé par les éditeurs de quiz,
            // d'examens et de flashcards (énoncés, recto et verso).
            $file = $request->file('file');
            $mime = (string) $file?->getMimeType();
            $type = match (true) {
                str_starts_with($mime, 'audio/') => 'audio',
                str_starts_with($mime, 'video/') => 'video',
                default => 'image',
            };

            $rules = [
                'image' => 'required|image|max:'.config('media.image_max_kb'),
                'audio' => 'required|file|mimes:mp3,wav,ogg,oga,m4a,flac|max:'.config('media.audio_max_kb'),
                'video' => 'required|file|mimes:mp4,m4v,webm,ogv,mov|max:'.config('media.video_max_kb'),
            ];
            $request->validate(['file' => $rules[$type]], [
                'file.max' => 'Fichier trop volumineux (maximum '.intdiv(config("media.{$type}_max_kb"), 1024).' Mo).',
                'file.uploaded' => 'Le fichier dépasse la taille autorisée par le serveur (upload_max_filesize).',
            ]);

            // Store the file in public/uploads/media
            $path = $file->store('uploads/media', 'public');
            $url = asset('storage/'.$path);

            return response()->json([
                'success' => true,
                'url' => $url,
                'type' => $type,
                'name' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
