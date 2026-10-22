<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;
use Modules\Core\Models\User;

/**
 * « Copier vers… » : copie profonde d'un contenu vers un groupe.
 *
 *  - quiz     → questions ;
 *  - exam     → questions d'examen ;
 *  - deck     → cartes (médias JSON repris tels quels, statistiques remises à zéro) ;
 *  - article  → médias (mêmes fichiers, chemins partagés) ;
 *  - resource → nouvelle ligne sur les mêmes `path`, `pdf_path` et `video_id`.
 *
 * La copie appartient à $by, garde son titre et n'est rattachée qu'au groupe cible.
 * Aucun historique (tentatives, progression, révisions) n'est copié.
 */
class ContentCopier
{
    public function __construct(private FolderTree $tree) {}

    /**
     * @param  ?int  $position  rang dans le conteneur cible (null = en dernier)
     * @return array{model: Model, map: array{questions: array<int,int>, items: array<int,int>, exam_questions: array<int,int>}}
     *
     * @throws \Illuminate\Validation\ValidationException dossier cible d'un autre groupe
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException contenu ou groupe introuvable
     */
    public function copy(string $type, int $id, int $targetGroupId, ?int $targetFolderId, User $by, ?int $position = null): array
    {
        if (! isset(ContentSharing::TYPES[$type])) {
            throw new InvalidArgumentException("Type de contenu inconnu : {$type}");
        }

        $source = ContentSharing::TYPES[$type][0]::findOrFail($id);
        $group = Group::findOrFail($targetGroupId);
        $this->tree->assertFolderOfGroup($group->id, $targetFolderId);

        $result = DB::transaction(function () use ($type, $source, $group, $targetFolderId, $by, $position) {
            $map = ['questions' => [], 'items' => [], 'exam_questions' => []];
            $copy = $this->replicate($source, $by);

            match ($type) {
                'quiz' => $map['questions'] = $this->children($source->questions()->get(), 'quiz_id', $copy->id),
                'exam' => $map['exam_questions'] = $this->children($source->questions()->get(), 'exam_id', $copy->id),
                'deck' => $map['items'] = $this->children($source->cards()->get(), 'deck_id', $copy->id,
                    ['total_revisions' => 0, 'taux_reussite' => 0]),
                'article' => $this->children($source->media()->get(), 'article_id', $copy->id),
                'resource' => null,
            };

            $this->tree->attach($group, $type, $copy->id, $targetFolderId);
            if ($position !== null) {
                $this->tree->place($group, $type, $copy->id, $targetFolderId, $position);
            }

            return ['model' => $copy, 'map' => $map];
        });

        // Document encore en conversion : la copie reçoit son propre job (même PDF cible).
        $copy = $result['model'];
        if ($copy instanceof Resource && $copy->kind === 'document' && $copy->conversion_status === 'pending') {
            ConvertDocumentToPdf::dispatch($copy->id)->afterCommit();
        }

        return $result;
    }

    private function replicate(Model $source, User $by): Model
    {
        $copy = $source->replicate();
        $copy->created_by = $by->id;
        // La vitrine publique reste réservée à l'original.
        if ($source instanceof Quiz || $source instanceof Article || $source instanceof FlashcardDeck) {
            $copy->is_showcase = false;
        }
        // Une copie vit dans un groupe (et peut-être un dossier) : jamais un deck public.
        if ($source instanceof FlashcardDeck) {
            $copy->is_public = false;
        }
        $copy->save();

        return $copy;
    }

    /**
     * Copie des enfants rattachés à $parentId ; renvoie la table ancien id → nouvel id.
     *
     * @param  iterable<Model>  $children
     * @param  array<string, mixed>  $overrides
     * @return array<int, int>
     */
    private function children(iterable $children, string $foreignKey, int $parentId, array $overrides = []): array
    {
        $map = [];
        foreach ($children as $child) {
            $copy = $child->replicate();
            $copy->{$foreignKey} = $parentId;
            $copy->forceFill($overrides);
            $copy->save();
            $map[(int) $child->getKey()] = (int) $copy->getKey();
        }

        return $map;
    }
}
