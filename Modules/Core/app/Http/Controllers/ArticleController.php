<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Requests\StoreArticleRequest;
use Modules\Core\Http\Requests\UpdateArticleRequest;
use Modules\Core\Models\Article;
use Modules\Core\Models\Group;
use Modules\Core\Services\ContentEditAccess;
use Modules\Core\Services\ContentSharing;
use Modules\Core\Services\FolderTree;

class ArticleController extends Controller
{
    private function authorizeArticle(int $id): Article
    {
        $article = Article::findOrFail($id);
        ContentEditAccess::authorize($article);

        return $article;
    }

    public function index(): \Illuminate\Contracts\View\View
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin')) {
            $groups = Group::active()->get();
        } elseif ($user->hasRole('trainer') || $user->trainer) {
            $groups = $user->trainer ? $user->trainer->groups()->active()->get() : collect();
        } else {
            $groups = collect();
        }

        return view('core::articles.index', compact('groups'));
    }

    /**
     * Récupérer les données pour Bootstrap Table (AJAX)
     */
    public function getData(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Article::with(['creator', 'groups']);

        $user = auth()->user();
        if (! ($user->hasRole('super-admin') || $user->hasRole('Admin') || $user->hasRole('admin'))) {
            $query->where('created_by', $user->id);
        }

        // Filtre vitrine (?showcase=1)
        if ($request->boolean('showcase')) {
            $query->where('is_showcase', true);
        }

        // Recherche
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $articles = $query->offset($offset)->limit($limit)->get();

        // Formater les lignes
        $rows = $articles->map(function ($article) {
            $article->creator_name = $article->creator ? $article->creator->name.' '.$article->creator->last_name : 'Système';
            $article->groups_list = $article->groups->pluck('name')->toArray() ?? [];
            $article->content_excerpt = Str::limit(strip_tags($article->content), 100);

            return $article;
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Récupérer un article spécifique (AJAX)
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $article = $this->authorizeArticle($id);
            $article->load('groups');

            $data = $article->toArray();
            $data['group_ids'] = $article->groups->pluck('id')->toArray() ?? [];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé',
            ], 404);
        }
    }

    /**
     * Créer un nouvel article (AJAX)
     */
    public function store(StoreArticleRequest $request, FolderTree $tree): \Illuminate\Http\JsonResponse
    {
        try {
            ContentSharing::assertCanSync('article', null, $request->group_ids ?? []);
            $article = Article::create([
                'title' => $request->title,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'created_by' => auth()->id(),
            ]);

            // Synchroniser les groupes
            if ($request->has('group_ids')) {
                $tree->syncGroups('article', $article->id, $request->group_ids ?? []);
            }

            return response()->json([
                'success' => true,
                'message' => 'Article créé avec succès',
                'data' => $article,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un article (AJAX)
     */
    public function update(UpdateArticleRequest $request, int $id, FolderTree $tree): \Illuminate\Http\JsonResponse
    {
        try {
            $article = $this->authorizeArticle($id);
            ContentSharing::assertCanSync('article', $article->id, $request->group_ids ?? []);
            $article->update([
                'title' => $request->title,
                'content' => $request->content,
                'is_active' => $request->has('is_active') ? $request->is_active : $article->is_active,
            ]);

            // Synchroniser les groupes
            $tree->syncGroups('article', $article->id, $request->group_ids ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Article modified successfully',
                'data' => $article,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un article (AJAX)
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $article = $this->authorizeArticle($id);
            $article->delete();

            return response()->json([
                'success' => true,
                'message' => 'Article supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un article (AJAX)
     */
    public function toggleStatus(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $article = $this->authorizeArticle($id);
            $article->is_active = ! $article->is_active;
            $article->save();

            $status = $article->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'success' => true,
                'message' => "Article $status avec succès",
                'is_active' => $article->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter un article en HTML autonome avec images embarquées en Base64 (téléchargement)
     */
    public function export(int $id): \Illuminate\Http\Response
    {
        $article = $this->authorizeArticle($id);
        $content = $article->content;

        // Sécuriser les éléments audio et vidéo dans le contenu (attributs anti-téléchargement)
        $content = preg_replace('/<audio(?![^>]*controlsList)/i', '<audio controlsList="nodownload nofullscreen noremoteplayback" disablePictureInPicture="true" disableRemotePlayback="true" oncontextmenu="return false;"', $content);
        $content = preg_replace('/<video(?![^>]*controlsList)/i', '<video controlsList="nodownload nofullscreen noremoteplayback" disablePictureInPicture="true" disableRemotePlayback="true" oncontextmenu="return false;"', $content);

        // Convertir les images locales du contenu en Base64 inline pour l'autonomie offline
        $content = preg_replace_callback('/<img[^>]+src="([^"]+)"/i', function ($matches) {
            $src = $matches[1];
            // Médias uploadés (/storage/… relatif ou absolu) : lus directement
            // sur le disque — il n'y a plus de lien symbolique public/storage.
            $local = preg_replace('#^https?://[^/]+#i', '', $src);
            if (str_starts_with($local, '/storage/')) {
                $path = \Illuminate\Support\Facades\Storage::disk('public')->path(substr($local, strlen('/storage/')));
            } elseif (str_starts_with($src, '/') && ! str_starts_with($src, '//')) {
                $path = public_path(ltrim($src, '/'));
            } else {
                $path = null;
            }
            if ($path && ! str_contains($src, '..')) {
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/'.$type.';base64,'.base64_encode($data);

                    return str_replace($src, $base64, $matches[0]);
                }
            }

            return $matches[0];
        }, $content);

        $html = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>".htmlspecialchars($article->title)."</title>
            <style>
                body {
                    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                    line-height: 1.6;
                    color: #1a2c3e;
                    max-width: 800px;
                    margin: 40px auto;
                    padding: 0 20px;
                    background-color: #f8fafc;
                    user-select: none;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #0d6efd;
                    border-bottom: 2px solid #e2e8f0;
                    padding-bottom: 15px;
                    margin-top: 0;
                }
                .meta {
                    color: #64748b;
                    font-size: 0.9rem;
                    margin-bottom: 30px;
                }
                .content {
                    font-size: 1.1rem;
                }
                img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                audio::-internal-media-controls-download-button,
                video::-internal-media-controls-download-button {
                    display:none !important;
                }
                audio::-webkit-media-controls-panel-menu,
                video::-webkit-media-controls-panel-menu {
                    display:none !important;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>".htmlspecialchars($article->title)."</h1>
                <div class='meta'>
                    Publié le : ".$article->created_at->format('d/m/Y H:i')."
                </div>
                <div class='content'>
                    ".$content."
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const mediaElements = document.querySelectorAll('audio, video');
                    mediaElements.forEach(el => {
                        el.setAttribute('controlsList', 'nodownload nofullscreen noremoteplayback');
                        el.setAttribute('disablePictureInPicture', 'true');
                        el.setAttribute('disableRemotePlayback', 'true');
                        el.addEventListener('contextmenu', e => e.preventDefault());
                        el.addEventListener('dragstart', e => e.preventDefault());
                    });
                    document.addEventListener('contextmenu', e => e.preventDefault());
                    document.addEventListener('keydown', e => {
                        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'u')) e.preventDefault();
                        if (e.key === 'F12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'C' || e.key === 'c'))) e.preventDefault();
                    });
                });
            </script>
        </body>
        </html>
        ";

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($article->title).'.html"',
        ]);
    }
}
