# Dossiers de contenus par groupe — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner à chaque groupe une arborescence de dossiers contenant articles, quiz, examens, decks et ressources (vidéo, audio, image, PDF, Word/PowerPoint convertis, liens). Deux modes de partage sont réglables pour toute la plateforme : `shared` et `isolated`.

**Architecture :**
- Le placement est porté par les pivots existants `group_*`, auxquels on ajoute `folder_id` et `position`. Le pivot reste la seule source de vérité de l'accès.
- Un service `ContentPlacement` calcule la visibilité pour un apprenant : dossiers masqués ou programmés, avec héritage dans le sous-arbre.
- L'API apprenant, partagée entre la PWA et le mobile, envoie `folders`, `resources` et `placements`.
- La PWA ajoute l'onglet « Cours » et un lecteur de ressources protégé.
- Le mode `isolated` repose sur un service de copie profonde et sur un assistant de bascule qui transfère l'historique.

**Tech Stack:** Laravel 12 (`Modules/Core`), files Laravel, LibreOffice headless via `Symfony\Component\Process`, PWA TypeScript + Dexie, `pdfjs-dist` (Apache-2.0, nouvelle dépendance npm), Vitest, PHPUnit (SQLite en mémoire).

**Spec:** `docs/superpowers/specs/2026-10-22-dossiers-contenus-design.md`

## Global Constraints

- `AppSetting` `content.sharing_mode` ∈ {`shared`, `isolated`}, défaut **`shared`** : **aucun changement de comportement** sans réglage. Les contenus existants apparaissent à la racine de chaque groupe (`folder_id` null) **sans migration de données**.
- Dans un groupe, un contenu a une seule place. Le pivot est unique par couple (groupe, contenu) et ne change pas.
- La visibilité d'un dossier (`is_visible`, `available_from`, `available_until`) s'applique à tout son sous-arbre, pour les apprenants de **ce groupe** seulement. Elle est calculée côté serveur.
- Règle « consultable, non récupérable » : jamais d'URL stable vers un fichier de ressource. On utilise une URL signée de **15 min** (`config('media.video.signed_ttl')`, soit 900 s). `download` répond 403 si `allow_download` est faux.
- Les decks `is_public` restent hors dossiers et ne sont pas soumis à la règle d'un seul groupe.
- Un contenu peut n'avoir aucun groupe. En mode `isolated`, le 422 ne s'applique qu'à un **deuxième** groupe, avec le message « Ce contenu appartient déjà à un autre groupe. Utilisez « Copier vers… ». ».
- Droits en `isolated` : le créateur, les admins et les formateurs du groupe propriétaire. En `shared`, rien ne change (créateur et admins).
- La terminologie du groupe passe par `Terminology` côté serveur et `core/terms.ts` côté PWA. Les textes sont en français, accentués.
- Ne jamais lancer `php artisan storage:link`. Les commits se terminent par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Dossier masqué dans un groupe, visible dans un autre** (mode `shared`) : le contenu reste visible pour l'apprenant membre des deux groupes et disparaît pour celui qui n'est que dans le premier. Test en Task 2.
2. **Déplacement d'un dossier dans son propre descendant** : refusé en 422 (cycle). Un dossier ou un contenu placé dans un dossier d'un **autre** groupe : refusé en 422. Tests en Task 5.
3. **Suppression d'un dossier non vide** : avec `mode=lift`, le contenu remonte dans le parent avec des positions ajoutées à la suite ; avec `mode=detach`, le contenu est retiré du groupe. Sans paramètre, la réponse est 422 et rien n'est supprimé. Test en Task 5.
4. **Examen dupliqué à la bascule vers `isolated`** : l'apprenant du groupe qui reçoit la copie et qui avait atteint `max_attempts` ne peut pas repasser (POST attempts → refus existant). Test en Task 8.
5. **Document Word dont la conversion échoue ou dont LibreOffice est absent** : `conversion_status=failed`, l'apprenant reçoit 409 `{status:'failed'}` sur `view` et la PWA affiche « Document indisponible ». Le back-office montre l'erreur et propose un bouton « Réessayer ». Tests en Task 4 et Task 11.

---

### Task 1: Réglage de mode, schéma et modèles

**Files:**
- Create: migrations `Modules/Core/database/migrations/2026_10_22_120000_create_folders_table.php`, `…_120100_add_placement_to_group_pivots.php` (sur `group_article`, `group_quiz`, `group_exam`, `group_flashcard_deck`), `…_120200_create_resources_tables.php` (`resources` + `group_resource`) ; `Modules/Core/app/Models/Folder.php`, `Resource.php` ; `Modules/Core/app/Services/ContentSharing.php`
- Modify: `Group.php`, `Article.php`, `Quiz.php`, `Exam.php`, `FlashcardDeck.php` (relations `groups()`/`articles()`… `->withPivot('folder_id','position')`), `config/media.php` (clé `documents` : `max_size_kb` = 51200, `soffice_binary` = env `SOFFICE_BINARY`, défaut `soffice`, `queue` = `documents`)
- Test: `tests/Feature/FolderModelTest.php`

**Interfaces:**
- `folders(id, group_id FK cascade, parent_id FK folders null cascade, name string(150), position int default 0, is_visible bool default true, available_from datetime null, available_until datetime null, created_by FK users null, timestamps)`, avec un index sur `(group_id, parent_id, position)`.
- Pivots : `folder_id FK folders null nullOnDelete`, `position int default 0`.
- `resources(id, kind string(20), title, description text null, disk string null, path string null, mime null, size bigint null, original_name null, pdf_path null, conversion_status string(20) null, conversion_error text null, video_id FK videos null nullOnDelete, url string(2048) null, allow_download bool default false, is_active bool default true, created_by FK users null, timestamps)`.
- `group_resource(group_id, resource_id, folder_id, position, timestamps)` avec une clé primaire composite `(group_id, resource_id)`.
- `Resource::KINDS = ['video','audio','image','pdf','document','link']` ; `Resource::groups()`, `Group::resources()`, `Group::folders()` ; `Folder::group()`, `parent()`, `children()`, `descendantIds(): array<int>` (inclut lui-même).
- `ContentSharing::mode(): string`, `isolated(): bool`, `setMode(string $mode): void` (valeur invalide → `InvalidArgumentException`). `ContentSharing::TYPES` associe `article|quiz|exam|deck|resource` à `[modelClass, pivotTable, foreignKey]` :
  - `article` : `group_article.article_id`
  - `quiz` : `group_quiz.quiz_id`
  - `exam` : `group_exam.exam_id`
  - `deck` : `group_flashcard_deck.flashcard_deck_id`
  - `resource` : `group_resource.resource_id`

  Les tâches suivantes réutilisent cette table de correspondance.

- [ ] **Step 1: Tests** :
  - `test_default_mode_is_shared` et `test_invalid_mode_throws` ;
  - `test_existing_assignment_gets_null_folder_and_zero_position` ;
  - `test_deleting_folder_nulls_pivot_folder_id` ;
  - `test_descendant_ids` (3 niveaux) ;
  - `test_deleting_group_cascades_folders`.
- [ ] **Step 2:** Run `php artisan test --filter=FolderModelTest` → FAIL.
- [ ] **Step 3:** Implémenter. Dans les migrations de pivots, vérifier `Schema::hasColumn` pour pouvoir les rejouer.
- [ ] **Step 4:** Run → PASS, puis `php artisan test` → PASS (aucune régression).
- [ ] **Step 5:** Commit `feat(folders): sharing mode setting, folders/resources schema, pivot placement`.

### Task 2: Visibilité des placements (`ContentPlacement`)

**Files:**
- Create: `Modules/Core/app/Services/ContentPlacement.php`
- Test: `tests/Feature/ContentPlacementTest.php`

**Interfaces:**
- `ContentPlacement::visibleFolderIds(Collection $groupIds, ?CarbonInterface $at = null): array<int>` : un dossier est visible s'il l'est lui-même et que tous ses ancêtres le sont aussi (`is_visible` et fenêtre de dates).
- `ContentPlacement::placementsFor(Learner $learner): Collection<array{type:string,id:int,group_id:int,folder_id:?int,position:int}>` : placements des groupes courants (`groups()->current()`) dont le dossier est null ou visible.
- `ContentPlacement::visibleIds(Learner $learner, string $type): array<int>`
- `ContentPlacement::isVisible(Learner $learner, string $type, int $id): bool`
- Le résultat est mis en cache pour la durée de la requête (mémoïsation d'instance). Le service est enregistré en `scoped` dans le provider du module.

- [ ] **Step 1: Tests** :
  - `test_root_placement_visible` ;
  - `test_hidden_parent_hides_subtree` ;
  - `test_future_available_from_hides_then_shows` (avec `travelTo`) ;
  - `test_hidden_in_one_group_visible_in_other` (Review Focus 1) ;
  - `test_non_current_group_excluded`.
- [ ] **Step 2:** Run `php artisan test --filter=ContentPlacementTest` → FAIL.
- [ ] **Step 3:** Implémenter. Charger tous les dossiers des groupes en une seule requête et parcourir l'arbre en mémoire.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(folders): placement visibility service`.

### Task 3: API apprenant (PWA et mobile) : dossiers, ressources, placements et contrôles d'accès

**Files:**
- Modify:
  - `Modules/Core/app/Services/LearnerContentService.php` : filtrer `articlesFor`, `quizzesFor` et `examsFor` par `ContentPlacement::visibleIds`. `decksFor` garde les decks `is_public`, et ses decks de groupe sont filtrés. Ajouter `foldersFor`, `resourcesFor` et `placements`.
  - `Modules/Core/app/Services/ContentSerializer.php` : ajouter `resource(Resource $r): array`.
  - `Modules/Core/app/Http/Controllers/Learner/Api/ExamAttemptController.php` (`store`) et `ActionController.php` : refuser en 403 un contenu non visible. Exception : une tentative de quiz dont `completed_at` ou `started_at` est antérieur à `max(updated_at)` du dossier fautif est acceptée.
  - `Modules/Core/app/Console/Commands/PushNewContent.php` et `PushExamOpen.php` : `currentPairs()` ne garde que les placements visibles. Ainsi, un dossier qui devient visible déclenche le push au passage suivant, une seule fois par groupe.
- Test: `tests/Feature/LearnerFoldersApiTest.php` (PWA et `api/mobile/v1` avec Sanctum), et ajouts dans `tests/Feature/PushCommandsTest.php`.

**Interfaces:**
- `bootstrap` ajoute :
  - `folders: [{id, group_id, parent_id, name, position}]` ;
  - `resources: [Resource sérialisée]` ;
  - `placements: [{type, id, group_id, folder_id, position}]`.
- `changes` ajoute :
  - `resources: {updated, authorized_ids}` ;
  - `folders` et `placements` **complets**.
- Ressource sérialisée : `{id, kind, title, description, mime, size, allow_download, video_ref: ?string, url: ?string (lien seulement), conversion_status: ?string, updated_at}`.

- [ ] **Step 1: Tests** :
  - `test_bootstrap_includes_folders_resources_placements` ;
  - `test_hidden_folder_removes_content_from_lists_and_authorized_ids` ;
  - `test_public_deck_unaffected` ;
  - `test_mobile_bootstrap_has_same_keys` ;
  - `test_exam_start_forbidden_when_hidden` ;
  - `test_offline_quiz_attempt_before_hiding_accepted` ;
  - `test_push_waits_until_folder_visible` (PushCommandsTest).
- [ ] **Step 2:** Run `php artisan test --filter='LearnerFoldersApiTest|PushCommandsTest'` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS. Puis `php artisan test --filter='LearnerApiV1|MobileApi|Push'` → PASS.
- [ ] **Step 5:** Commit `feat(folders): learner API folders/resources/placements + visibility enforcement`.

### Task 4: Ressources : stockage, conversion, lecture protégée

**Files:**
- Create:
  - `Modules/Core/app/Services/ResourceStorage.php`
  - `Modules/Core/app/Jobs/ConvertDocumentToPdf.php`
  - `Modules/Core/app/Http/Controllers/Learner/Api/ResourceController.php`
- Modify:
  - `Modules/Core/routes/web.php` : `GET /api/learner/v1/resources/{resource}/view` (`learn.v1.resources.view`), `/download` (`learn.v1.resources.download`), et un fichier signé `GET /api/learner/v1/resources/{resource}/file` (`signed`, hors groupe auth, comme `videos.hls`).
  - `Modules/Core/routes/api.php` : `view` et `download` côté mobile.
  - Liste blanche lecture seule de `ResolveImpersonation` : ajouter `view`.
  - `.env.example` : `SOFFICE_BINARY`, `QUEUE` documents.
  - `DEPLOIEMENT.md` : installation de LibreOffice et worker `--queue=documents`.
- Test: `tests/Feature/ResourceStorageTest.php`

**Interfaces:**
- `ResourceStorage::store(UploadedFile $file, array $attrs, User $by): Resource`. Le `kind` est déduit du type MIME :
  - `image/*` → `image` ;
  - `audio/*` → `audio` ;
  - `application/pdf` → `pdf` ;
  - doc, docx, odt, ppt, pptx, odp → `document`, `conversion_status=pending`, puis dispatch du job sur la file `documents` ;
  - `video/*` → `VideoManager::store` puis `kind=video` avec `video_id`.

  Stockage sur le disque `local` (privé), sous `resources/{id}/`. Taille au-delà de `media.documents.max_size_kb` → `ValidationException`.
- `ResourceStorage::createLink(string $url, string $title, User $by): Resource` : `http` ou `https` uniquement.
- `ResourceStorage::release(Resource $r): void` : supprime le fichier physique seulement si aucune autre ressource ne partage le même `path` (comptage des références par `path`).
- `ConvertDocumentToPdf(int $resourceId)` : `soffice --headless --convert-to pdf --outdir <tmp>` ; `timeout` 300 ; `tries` 2 ; `failed()` passe `conversion_status=failed` et `conversion_error`. Binaire introuvable → `failed` avec « LibreOffice n'est pas installé sur le serveur. ».
- `view` renvoie :
  - `200 {type: 'pdf'|'audio'|'image'|'video'|'link', url, expires_at}` (vidéo → `{type:'video', video_ref}` ; lien → `{type:'link', url}`) ;
  - `202 {status:'pending'}` ;
  - `409 {status:'failed'}` ;
  - `403` si la ressource n'est pas visible (`ContentPlacement`).
- `download` → 403 si `!allow_download`, sinon l'original avec `Content-Disposition: attachment`.

- [ ] **Step 1: Tests** :
  - `test_upload_kinds_detected` ;
  - `test_document_dispatches_conversion_job` (`Queue::fake`) ;
  - `test_conversion_success_sets_pdf_path` et `test_conversion_missing_binary_fails` (`Process::fake`, Review Focus 5) ;
  - `test_view_returns_short_signed_url` ;
  - `test_signed_file_expires` ;
  - `test_download_forbidden_by_default` ;
  - `test_view_forbidden_when_hidden` ;
  - `test_mobile_view_with_sanctum` ;
  - `test_release_keeps_shared_file`.
- [ ] **Step 2:** Run `php artisan test --filter=ResourceStorageTest` → FAIL.
- [ ] **Step 3:** Implémenter. La réponse `file` porte les mêmes en-têtes que `MediaController` (`inline`, `nosniff`) et respecte les requêtes Range.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(resources): storage, document conversion job, protected view/download`.

### Task 5: Back-office : API de l'arbre d'un groupe

**Files:**
- Create:
  - `Modules/Core/app/Http/Controllers/GroupContentController.php`
  - `Modules/Core/app/Services/FolderTree.php`
- Modify: `Modules/Core/routes/web.php`, groupe `cores.groups`, sous `/{id}/contenus` :
  - `GET` page (`groups.contents.page`) ;
  - `GET /data` (arbre et éléments) ;
  - `POST /folders` ;
  - `PATCH /folders/{folder}` (nom, visibilité, dates) ;
  - `POST /folders/{folder}/move` (`parent_id`, `position`) ;
  - `DELETE /folders/{folder}?mode=lift|detach` ;
  - `POST /items/place` (`type`, `id`, `folder_id`, `position`) ;
  - `POST /items/reorder` (`folder_id`, `items[]`) ;
  - `POST /resources` (téléversement) ;
  - `POST /links` ;
  - `POST /items/attach` (contenu existant) ;
  - `DELETE /items/{type}/{itemId}` ;
  - `POST /resources/{resource}/retry-conversion`.
- Test: `tests/Feature/GroupContentApiTest.php`

**Interfaces:**
- Accès :
  - admin ou super-admin ;
  - formateur via `$user->trainer->groups()->where('groups.id', $id)->exists()` ;
  - sinon 403.
- `FolderTree::move(Folder $f, ?int $parentId, int $position): void` : 422 en cas de cycle ou de groupe différent.
- `FolderTree::place(Group $g, string $type, int $id, ?int $folderId, int $position): void` : 422 si le dossier est d'un autre groupe ; 404 si le contenu n'est pas attribué au groupe.
- `FolderTree::delete(Folder $f, string $mode): void` :
  - `lift` : sous-dossiers et éléments remontent dans le parent, à la suite ;
  - `detach` : les contenus du sous-arbre sont détachés du groupe et les dossiers supprimés.
- `FolderTree::reorder(Group $g, ?int $folderId, array $items /* [{type,id}|{type:'folder',id}] */): void` : positions 0..n.
- `GET /data` renvoie `{folders:[…], items:[{type,id,title,folder_id,position,kind?,conversion_status?}]}`.
- `POST /items/attach` applique la règle de mode (Task 6) via `ContentSharing::assertCanAttach`.

- [ ] **Step 1: Tests** :
  - `test_trainer_of_group_can_create_folder`, `test_other_trainer_forbidden` ;
  - `test_move_into_descendant_rejected` et `test_place_into_other_group_folder_rejected` (Review Focus 2) ;
  - `test_delete_requires_mode`, `test_delete_lift`, `test_delete_detach` (Review Focus 3) ;
  - `test_reorder_mixed_items` ;
  - `test_upload_resource_attaches_to_group_folder` ;
  - `test_retry_conversion_requeues`.
- [ ] **Step 2:** Run `php artisan test --filter=GroupContentApiTest` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(folders): group content tree back-office API`.

### Task 6: Règles de mode et droits de modification dans les éditeurs

**Files:**
- Create: `Modules/Core/app/Policies/ContentEditPolicy.php` (ou service `ContentEditAccess`)
- Modify:
  - `ContentSharing` : `assertCanAttach(string $type, int $id, int $groupId): void`. En mode `isolated`, si le contenu a déjà un autre groupe et n'est pas un deck public → `ValidationException` avec le message des Global Constraints.
  - Points d'attribution :
    - `ArticleEditorController::assignGroup`, `QuizEditorController::assignGroup`, `FlashcardDeckController::assignGroup` ;
    - les `sync($request->group_ids)` de `FlashcardDeckController` (store, update) et `ExamController` (store, update). En `isolated`, plus d'un id → 422.
    - chaque `assignGroup` accepte en option `folder_id`, validé comme en Task 5.
  - `authorizeArticle`, `authorizeQuiz`, `authorizeExam`, `authorizeDeck` délèguent à `ContentEditAccess::canEdit(User $u, Model $content): bool` (créateur, admin ; en `isolated`, aussi formateur d'un groupe du contenu).
  - Vues `admin/{article,quiz,flashcard,exam}/editor.blade.php` et modales d'attribution : sélecteur de dossier par groupe (alimenté par `GET groups.contents.data`) ; en `isolated`, sélection unique et bouton « Copier vers… » (Task 7).
- Test: `tests/Feature/SharingModeRulesTest.php`

- [ ] **Step 1: Tests** :
  - `test_shared_allows_second_group` ;
  - `test_isolated_rejects_second_group_all_endpoints`, avec un data provider sur les 4 types et les 2 formes (assign, sync) ;
  - `test_isolated_public_deck_exempt` ;
  - `test_ungrouped_content_attach_ok` ;
  - `test_isolated_cotrainer_can_edit`, `test_shared_cotrainer_cannot_edit` ;
  - `test_assign_with_folder_id`.
- [ ] **Step 2:** Run `php artisan test --filter=SharingModeRulesTest` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS, puis `php artisan test --filter='Editor|FlashcardDeckAdmin'` → PASS.
- [ ] **Step 5:** Commit `feat(folders): isolated mode attach rule and group-trainer edit rights`.

### Task 7: Copie profonde (« Copier vers… »)

**Files:**
- Create: `Modules/Core/app/Services/ContentCopier.php`
- Modify:
  - `GroupContentController` : `POST /{id}/contenus/items/{type}/{itemId}/copy` avec `target_group_id` et `target_folder_id?`. Accès : peut éditer la source et gère le groupe cible.
  - Éditeurs : bouton « Copier vers… ».
- Test: `tests/Feature/ContentCopierTest.php`

**Interfaces:**
- `ContentCopier::copy(string $type, int $id, int $targetGroupId, ?int $targetFolderId, User $by): array{model: Model, map: array{questions: array<int,int>, items: array<int,int>, exam_questions: array<int,int>}}` :
  - quiz → questions ;
  - exam → `ExamQuestion` ;
  - deck → `FlashcardItem` ;
  - article → `ArticleMedia` (les chemins sont partagés, pas recopiés) ;
  - resource → nouvelle ligne avec le même `path`, `pdf_path` et `video_id`.

  La copie a `created_by = $by`. Elle est attachée au seul groupe cible, à la position de fin. Le titre est inchangé. Aucun historique n'est copié.

- [ ] **Step 1: Tests** :
  - `test_copy_quiz_with_questions_and_map` ;
  - `test_copy_deck_items` ;
  - `test_copy_exam_questions` ;
  - `test_copy_resource_shares_file` ;
  - `test_copy_is_independent` (modifier la copie ne change pas l'original) ;
  - `test_copy_requires_rights`.
- [ ] **Step 2:** Run `php artisan test --filter=ContentCopierTest` → FAIL.
- [ ] **Step 3:** Implémenter, dans une transaction.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(folders): deep copy to another group`.

### Task 8: Bascule `shared` → `isolated` avec transfert d'historique

**Files:**
- Create:
  - `Modules/Core/app/Services/IsolationMigrator.php`
  - `Modules/Core/app/Http/Controllers/ContentSharingSettingsController.php`
  - vue `Modules/Core/resources/views/settings/content-sharing.blade.php`
- Modify: routes staff réservées aux admins (`settings/partage-contenus` : `GET` page, `POST /simulate`, `POST /apply`, `PUT /mode`), lien dans le menu des réglages existant.
- Test: `tests/Feature/IsolationMigratorTest.php`

**Interfaces:**
- `IsolationMigrator::conflicts(): Collection<array{type,id,title,group_ids:int[]}>` : contenus (hors decks publics) attribués à plusieurs groupes.
- `IsolationMigrator::plan(): array{type,id,keep_group_id,copies:[{group_id, learners:int}]}[]` : l'original reste dans le groupe qui a le plus d'apprenants (`group_learner`), le plus petit id départage.
- `IsolationMigrator::apply(): array{copied:int, moved_rows:int}` : pour chaque contenu, dans une transaction :
  1. `ContentCopier::copy` vers chaque autre groupe, au **même `folder_id` et à la même `position`** ;
  2. détacher l'original de ces groupes ;
  3. pour les apprenants du groupe cible, re-pointer l'historique :
     - `quiz_attempts.quiz_id`, avec `quiz_answers.question_id` et les clés de `quiz_attempts.answers` remappées via `map.questions` ;
     - `exam_attempts.exam_id`, avec les clés de `answers` remappées via `map.exam_questions` ;
     - `learner_progress` (`content_type`, `content_id`) ;
     - `flashcard_item_reviews.flashcard_item_id` via `map.items` ;
     - `flashcard_sessions.deck_id` ;
     - `flashcards.question_id` (legacy) ;
     - `push_assignment_snapshots` (group_id, type, id).
- `PUT /mode isolated` → 422 tant que `conflicts()` n'est pas vide. `shared` est toujours accepté.
- Avant d'écrire le remappage des clés JSON, vérifier le format réel de `answers` en lisant `ExamAttemptController` et `ActionController`.

- [ ] **Step 1: Tests** :
  - `test_conflicts_exclude_public_decks` ;
  - `test_plan_keeps_largest_group` ;
  - `test_apply_copies_into_same_folder` ;
  - `test_exam_attempts_follow_copy_and_max_attempts_preserved` (Review Focus 4) ;
  - `test_quiz_answers_question_ids_remapped` ;
  - `test_deck_reviews_remapped` ;
  - `test_learners_of_kept_group_untouched` ;
  - `test_mode_switch_blocked_with_conflicts` ;
  - `test_simulate_writes_nothing`.
- [ ] **Step 2:** Run `php artisan test --filter=IsolationMigratorTest` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS, puis `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(folders): shared→isolated switch with history transfer`.

### Task 9: Back-office : onglet « Contenus » d'un groupe (UI)

**Files:**
- Create: `Modules/Core/resources/views/groups/contents.blade.php` (et partials).
- Modify: `Modules/Core/resources/views/groups/index.blade.php` (action « Contenus » à côté de « Membres »).
- Test: `tests/Feature/GroupContentApiTest.php` → `test_contents_page_renders_for_trainer`.

Arbre à gauche, éléments du dossier à droite. Le glisser-déposer utilise SortableJS s'il est déjà présent dans le back-office (vérifier `package.json` et les vues des éditeurs) ; sinon, l'API HTML5 native. Fonctions de la page :
- modales pour créer ou renommer un dossier, avec visibilité et dates ;
- téléverser une ressource ou ajouter un lien ;
- ajouter un contenu existant (recherche des contenus de l'utilisateur) ;
- copier vers… ;
- supprimer, avec le choix lift ou detach ;
- badge « En préparation » ou « Échec » avec le bouton « Réessayer » pour les documents.

Suivre le style Blade et JS des pages `members.blade.php`.

- [ ] **Step 1:** Test de rendu → FAIL. **Step 2:** Implémenter. **Step 3:** PASS et vérification manuelle avec le skill `run` (création d'un dossier, glisser-déposer, upload PDF). **Step 4:** Commit `feat(folders): group contents back-office page`.

### Task 10: PWA : données et synchronisation

**Files:**
- Modify:
  - `resources/js/learner/domain/types.ts` : `Folder`, `Resource`, `Placement`, `ContentType = 'article'|'quiz'|'exam'|'deck'|'resource'`.
  - `resources/js/learner/db/schema.ts` : `version(4)` avec `folders: 'id, group_id, parent_id'`, `resources: 'id, updated_at'`, `placements: '[type+id+group_id], group_id, folder_id'`.
  - `resources/js/learner/sync/engine.ts` : appliquer les trois collections dans `bootstrap` et `changes`. `folders` et `placements` sont **remplacés** en entier, `resources` utilise `apply()`.
  - `resources/js/learner/stores/index.ts` : `foldersStore`, `resourcesStore`, `placementsStore`.
- Create: `resources/js/learner/domain/folders.ts`, `folders.test.ts`

**Interfaces (`domain/folders.ts`) :**
- `childrenOf(groupId: number, folderId: number|null, folders: Folder[], placements: Placement[]): {folders: Folder[], items: Placement[]}` : triés par `position`, dossiers d'abord.
- `breadcrumb(folderId: number|null, folders: Folder[]): Folder[]`
- `pathLabel(type: ContentType, id: number, placements: Placement[], folders: Folder[], groups: Group[]): {groupId: number, folderId: number|null, label: string} | null` : premier groupe dans l'ordre alphabétique, avec un libellé du type « SVT › Ch.3 » : nom du groupe puis dossier le plus profond, tronqué à 24 caractères.
- `folderProgress(groupId, folderId, …, consulted: (type, id) => boolean): {done: number, total: number}` : récursif.
- `isConsulted(type, id)` dans `stores` : article, quiz et ressource → progression locale ; exam → une tentative ; deck → une révision ou une session.

- [ ] **Step 1: Tests** : `childrenOf` (tri), `breadcrumb`, `pathLabel` (plusieurs groupes → ordre alphabétique), `folderProgress` (récursif).
- [ ] **Step 2:** Run `npx vitest run resources/js/learner/domain/folders.test.ts` → FAIL.
- [ ] **Step 3:** Implémenter les fonctions et la synchronisation.
- [ ] **Step 4:** Run `npm test` → PASS ; `npx tsc --noEmit` → aucune erreur.
- [ ] **Step 5:** Commit `feat(pwa): folders/resources/placements sync and tree helpers`.

### Task 11: PWA : onglet Cours, puce de chemin, lecteur de ressources

**Files:**
- Create:
  - `resources/js/learner/views/cours.ts`
  - `resources/js/learner/views/ressource.ts`
  - `resources/js/learner/ui/pdf-viewer.ts` et `pdf-viewer.test.ts`
  - `resources/js/learner/ui/path-chip.ts`
- Modify:
  - `main.ts` : routes `/cours`, `/cours/:groupId`, `/cours/:groupId/:folderId`, `/ressources/:id`.
  - `components/app-shell.ts` : entrée NAV « Cours » (`bi-folder2-open`) après Accueil.
  - `views/articles.ts`, `entrainement.ts`, `reviser.ts`, `examens.ts` : puce de chemin sur les cartes.
  - `ui/media-guard.ts` : `PROTECTED_SELECTOR` inclut `.pdf-viewer`.
  - `sw.ts` / `sw-video.ts` : cache « disponible hors ligne » des fichiers de ressources sous la clé `resource:{id}`, sans dépendre de l'URL signée.
  - `package.json` : ajout de `pdfjs-dist`.

Comportements :
- Vue Cours :
  - un seul groupe → redirection vers `/cours/:groupId` ;
  - fil d'Ariane, puis dossiers, puis éléments avec icône par `kind`, puis progression « n/m consultés » ;
  - état vide : « Aucun contenu dans ce dossier pour le moment. ».
- Lecteur `/ressources/:id`, selon `view` :
  - pdf → `pdf-viewer` (canvas par page, filigrane nominatif, aucun lien de téléchargement) ;
  - audio et image → composants protégés existants ;
  - video → `video-ref` ;
  - link → `brand-video` si YouTube ou Vimeo (`video-embed-parser`), sinon `window.open(url, '_blank', 'noopener')` ;
  - 202 → « Document en préparation… » ;
  - 409 → « Document indisponible ».
- Bouton « Disponible hors ligne », et « Télécharger » seulement si `allow_download`.
- À l'ouverture, `dispatch('progress', {content_type:'resource', content_id, status:'completed'})`, avec le type d'action existant pour la progression.

- [ ] **Step 1: Tests Vitest** :
  - `pdf-viewer` : rend le filigrane et n'expose aucun `a[download]` ;
  - `cours` : redirection avec un seul groupe ; état vide ;
  - `ressource` : 202 et 409 affichent les bons messages (Review Focus 5) ;
  - `path-chip` : un clic navigue vers `/cours/:g/:f`.
- [ ] **Step 2:** Run `npm test` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** `npm test` et `npx tsc --noEmit` → PASS ; `npm run build` → OK ; vérification manuelle avec le skill `run` (onglet Cours, ouverture d'un PDF, puce sur une carte).
- [ ] **Step 5:** Commit `feat(pwa): Cours tab, path chips, protected resource viewer`.

### Task 12: Volet mobile (prompt Kotlin) et documentation

**Files:**
- Create: `PROMPT_DOSSIERS_MOBILE_AI_STUDIO.md`, sur le modèle de `PROMPT_MAJ_GROUPES_AI_STUDIO.md`.
- Modify:
  - `PROMPT_APP_MOBILE_KOTLIN.md` : court renvoi vers ce prompt.
  - `SPECIFICATIONS_VOLET_APPRENANT.md` : section Cours et ressources.
  - `DEPLOIEMENT.md`, si ce n'est pas fait en Task 4.

Contenu exigé par la spec §14 :
- exemples JSON **réels**, capturés à partir des tests de Task 3 et Task 4 : `bootstrap` (`folders`, `resources`, `placements`), `changes`, `view` en 200, 202, 409 et 403, `download` en 403 ;
- entités Room, avec la règle « remplacement complet de `folders` et `placements` » ;
- écrans Cours, puce de chemin et lecteur (`PdfRenderer`, `FLAG_SECURE`, filigrane) ;
- hors ligne chiffré dans le stockage privé ;
- terminologie du groupe ;
- checklist de QA.

- [ ] **Step 1:** Rédiger. **Step 2:** Vérifier que chaque champ cité existe dans `ContentSerializer::resource` et `LearnerContentService` (grep). **Step 3:** Commit `docs(mobile): Kotlin prompt for folders and resources`.
