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
        return view('core::index');
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
     * Upload generic media (images) to the public storage.
     */
    public function uploadMedia(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Image (≤5 Mo) ou audio (≤10 Mo) — utilisé par les éditeurs
            // de quiz, d'examens et de flashcards.
            $file = $request->file('file');
            $isAudio = $file && str_starts_with((string) $file->getMimeType(), 'audio/');

            if ($isAudio) {
                $request->validate([
                    'file' => 'required|file|mimes:mp3,wav,ogg,oga,m4a,flac|max:10240',
                ]);
            } else {
                $request->validate([
                    'file' => 'required|image|max:5120',
                ]);
            }

            // Store the file in public/uploads/media
            $path = $file->store('uploads/media', 'public');
            $url = asset('storage/'.$path);

            return response()->json([
                'success' => true,
                'url' => $url,
                'type' => $isAudio ? 'audio' : 'image',
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
