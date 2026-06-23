<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\Article;
use Modules\Core\Models\ArticleMedia;
use Modules\Core\Models\Quiz;

class ArticleEditorController extends Controller
{
    public function edit(int $articleId): View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $article = Article::with(['creator', 'groups'])->findOrFail($articleId);
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $article = Article::with(['creator', 'groups' => function ($query) use ($user) {
                $query->whereHas('trainers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }])->findOrFail($articleId);
        } else {
            $article = Article::with(['creator', 'groups' => function ($query) {
                $query->whereRaw('1 = 0');
            }])->findOrFail($articleId);
        }

        return view('core::admin.article.editor', compact('article'));
    }

    /**
     * Enregistrement automatique des modifications de l'article via AJAX.
     */
    public function autosave(Request $request, int $articleId): JsonResponse
    {
        try {
            $article = Article::findOrFail($articleId);

            $article->update([
                'title' => $request->title,
                'content' => $request->content,
                'category' => $request->category,
                'seo_description' => $request->seo_description,
                'seo_keywords' => $request->seo_keywords,
                'is_active' => $request->has('is_active') ? $request->is_active : $article->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Article enregistré avec succès.',
                'data' => $article,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement automatique : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer ou désactiver (passer en brouillon) l'article.
     */
    public function toggleActive(int $articleId): JsonResponse
    {
        try {
            $article = Article::findOrFail($articleId);
            $article->is_active = ! $article->is_active;
            $article->save();

            $statusMessage = $article->is_active
                ? 'L\'article a été publié avec succès.'
                : 'L\'article a été repassé en mode brouillon.';

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'is_active' => $article->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut : '.$e->getMessage(),
            ], 500);
        }
    }

    public function assignGroup(Request $request, int $articleId): JsonResponse
    {
        try {
            $request->validate([
                'group_id' => 'required|integer|exists:groups,id',
            ]);

            $user = auth()->user();
            $groupId = $request->group_id;

            // Enforce profile boundaries
            if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
                if ($user->hasRole('trainer') || $user->trainer) {
                    $hasGroup = $user->trainer && $user->trainer->groups()->where('groups.id', $groupId)->exists();
                    if (! $hasGroup) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Vous n\'êtes pas autorisé à assigner ce groupe.',
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Action non autorisée.',
                    ], 403);
                }
            }

            $article = Article::findOrFail($articleId);
            $article->groups()->syncWithoutDetaching([$groupId]);

            return response()->json([
                'success' => true,
                'message' => 'Le groupe a été autorisé à accéder à l\'article.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation du groupe : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retirer l'accès d'un groupe d'apprenants à l'article.
     */
    public function unassignGroup(int $articleId, int $groupId): JsonResponse
    {
        try {
            $article = Article::findOrFail($articleId);
            $article->groups()->detach($groupId);

            return response()->json([
                'success' => true,
                'message' => 'Le groupe a été retiré des accès de l\'article.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait du groupe : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rechercher les quiz actifs pour l'intégration (widget) dans l'éditeur.
     */
    public function searchQuizzes(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');

            $quizzes = Quiz::where('is_active', true)
                ->where('title', 'like', "%{$query}%")
                ->limit(10)
                ->get();

            $formattedQuizzes = $quizzes->map(function (Quiz $quiz) {
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedQuizzes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des quiz : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload an article media file (image or audio) to the server.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'type' => 'required|string|in:image,audio',
                'article_id' => 'required|integer|exists:articles,id',
            ]);

            $type = $request->input('type');
            $file = $request->file('file');
            $articleId = $request->input('article_id');

            if ($type === 'image') {
                $request->validate([
                    'file' => 'image|max:5120', // 5MB max for images
                ]);
            } elseif ($type === 'audio') {
                $request->validate([
                    'file' => 'mimes:mp3,wav,ogg,m4a,flac|max:10240', // 10MB max for audio
                ]);
            }

            // Store the file in public/articles/media
            $path = $file->store('articles/media', 'public');
            $url = asset('storage/'.$path);

            // Create database entry for media attachment
            ArticleMedia::create([
                'article_id' => $articleId,
                'file_path' => $path,
                'file_type' => $type,
                'original_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => true,
                'url' => $url,
                'name' => $file->getClientOriginalName(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload : '.$e->getMessage(),
            ], 500);
        }
    }
}
