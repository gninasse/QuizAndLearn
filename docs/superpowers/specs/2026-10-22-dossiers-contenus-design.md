# Dossiers de contenus par groupe (lycées, universités, centres de formation) — Conception

> Date : 2026-10-22 · Statut : à valider · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Ouvrir l'app aux **lycées et universités**. Dans ces établissements, les supports s'organisent comme un cours (chapitres, séances, thèmes), pas en listes plates par type.

Chaque groupe reçoit donc une **arborescence de dossiers**. Un groupe est une classe (« Terminale S2 ») ou une matière (« SVT »), au choix de l'établissement. Un dossier peut contenir :
- des sous-dossiers ;
- des articles, des quiz, des examens et des decks de flashcards ;
- des **ressources** : vidéo, audio, image, PDF, Word, PowerPoint, lien externe.

Le fonctionnement actuel, où un contenu est partagé entre plusieurs groupes, reste bon pour les **centres de formation**. Le comportement est donc **paramétrable pour toute la plateforme** (une installation correspond à un établissement).

## 2. Objectifs

- Chaque groupe a sa racine et une arborescence de sous-dossiers, sans profondeur limitée.
- **Dans un groupe, chaque contenu occupe une seule place**, c'est-à-dire un seul dossier ou la racine.
- Deux modes, réglés pour toute la plateforme :
  - **`shared` (partagé)**, comportement actuel et valeur par défaut : un contenu peut être attribué à plusieurs groupes et il a une place dans chacun. Modifier l'original met à jour tous les groupes.
  - **`isolated` (cloisonné)**, pour les lycées et universités : un contenu appartient à **un seul groupe**, donc il n'a qu'une seule place sur toute la plateforme. Pour le réutiliser ailleurs, on le **copie** vers un autre groupe. La copie est indépendante de l'original.
- Côté élève, un **nouvel onglet « Cours »** sert d'explorateur par groupe et mélange tous les types (option A retenue). Les écrans par type (Articles, Entraînement, Réviser, Examens) **restent inchangés**. Chaque carte de ces écrans affiche en plus le **chemin du dossier** (« SVT › Ch.3 »), qui ouvre le dossier quand on appuie dessus (option C).
- Les Word et PowerPoint sont **lus dans l'app** après conversion en PDF sur le serveur. Les PDF passent par une visionneuse intégrée avec filigrane. Le **téléchargement de l'original** est possible fichier par fichier, sur décision de l'enseignant, et désactivé par défaut.
- La PWA et l'app mobile Kotlin affichent la même arborescence, **y compris hors ligne**.

## 3. Non-objectifs

- Aucun dépôt par les élèves (devoirs, notes partagées). Les élèves consultent seulement, et jouent les quiz et les flashcards.
- Aucun rôle « admin de groupe » distinct : on garde les rôles globaux et le rattachement des formateurs aux groupes.
- Pas de dossier partagé entre groupes, pas de raccourcis (un même contenu à deux endroits d'un même groupe), pas d'historique de versions.
- Pas de recherche plein texte dans les PDF ou les documents.
- Pas de bascule « Liste / Dossiers » dans chaque écran par type (option B). Elle pourra venir plus tard.
- Pas d'entité « établissement » ou « organisation » au-dessus des groupes.

## 4. Anti-objectifs (échecs même si « ça marche »)

- Un centre de formation existant voit son comportement changer, ou perd des attributions, lors de la mise à jour.
- Un fichier « non téléchargeable » peut être récupéré par un geste normal : bouton télécharger, appui long, URL publique stable. La règle « consultable, non récupérable » (`SPECIFICATIONS_VOLET_APPRENANT.md` §0.5, `resources/js/learner/ui/media-guard.ts`) doit continuer de s'appliquer.
- Un contenu apparaît à deux endroits d'un même groupe, ou disparaît pour l'élève parce qu'il n'est pas encore rangé.
- La synchronisation hors ligne devient lente ou lourde : l'arborescence ne doit pas re-télécharger tous les contenus à chaque déplacement.

## 5. Existant sur lequel on s'appuie

- Il n'y a qu'un module, `Modules/Core`. Les liens contenu ↔ groupe sont des pivots plusieurs-à-plusieurs : `group_article`, `group_quiz`, `group_exam`, `group_flashcard_deck`. Chacun a sa migration : `2026_06_13_163302_create_group_quiz_pivot_table.php`, `2026_06_13_164708_create_group_article_pivot_table.php`, `2026_06_28_150231_create_group_exam_table.php` (avec `id`, timestamps et un index unique) et `2026_06_28_160040_create_group_flashcard_deck_table.php`. Les trois autres pivots ont une clé primaire composite.
- Les formateurs sont limités aux groupes auxquels ils sont rattachés (`$user->trainer->groups()`). Les admins et super-admins voient tout.
- Les vidéos sont une entité à part : `Video`, avec les pilotes local, s3_hls et bunny, et la lecture via `/api/learner/v1/videos/{ref}/playback`. Les images et l'audio sont aujourd'hui de simples fichiers intégrés dans le HTML. Rien ne permet encore de téléverser des documents.
- La synchronisation de l'élève passe par `LearnerContentService` : `bootstrap` envoie tout, puis `changes?since=` envoie la liste des éléments modifiés et des `authorized_ids` par collection. La PWA stocke tout dans Dexie (`resources/js/learner/db/schema.ts`, actuellement en version 3).
- Les réglages de la plateforme sont dans `AppSetting` (clé/valeur JSON, en cache). Le terme « groupe » est configurable (`core/terms.ts`, `Terminology`), et l'interface utilise ces libellés.
- Le suivi de l'élève passe par `learner_progress` (`content_type`, `content_id`, `status`, `progress_percentage`, …).
- Le back-office est en Blade et contrôleurs, sans Filament : `GroupController`, les éditeurs `*EditorController`, et les routes d'attribution `editor/{type}/{id}/groups`.
- L'app Kotlin n'est pas dans ce dépôt. Elle consomme `/api/mobile/v1/bootstrap` et `/changes` (Sanctum), et ses consignes sont dans `PROMPT_APP_MOBILE_KOTLIN.md`.

## 6. Modèle de données

### 6.1 Réglage de la plateforme
- `AppSetting` `content.sharing_mode`, avec pour valeurs `shared` (défaut) ou `isolated`.

### 6.2 Dossiers — table `folders`
- `id`, `group_id` (FK, cascade), `parent_id` (FK `folders`, nullable : null signifie que le dossier est à la racine du groupe), `name`, `position` (int), `is_visible` (bool, défaut true), `available_from`, `available_until` (nullable), `created_by`, timestamps.
- La racine d'un groupe est **implicite** : ce sont les dossiers et contenus sans parent. On ne crée pas de ligne « racine ».
- Contrainte métier : un dossier et son parent appartiennent au même groupe, et un déplacement ne doit pas créer de cycle.

### 6.3 Placement — colonnes ajoutées aux pivots existants
- Les pivots `group_article`, `group_quiz`, `group_exam`, `group_flashcard_deck` et le nouveau `group_resource` reçoivent les colonnes `folder_id` (FK `folders`, nullable, `nullOnDelete` ; null signifie racine) et `position` (int).
- **Le pivot reste la seule source de vérité** pour savoir qui voit quoi. Comme un pivot est unique par couple (groupe, contenu), la règle « une seule place par groupe » est garantie par la structure. Retirer un contenu d'un groupe supprime aussi son placement.
- Un contenu attribué sans dossier va à la racine. Ainsi, **les données existantes n'ont pas besoin d'être migrées** : tout apparaît à la racine de chaque groupe.

### 6.4 Ressources — table `resources` et pivot `group_resource`
- `id`, `kind` (`video` | `audio` | `image` | `pdf` | `document` | `link`), `title`, `description` (nullable).
- Fichier : `disk`, `path`, `mime`, `size`, `original_name`. Pour `document` (Word/PowerPoint), on stocke aussi `pdf_path` et `conversion_status` (`pending` | `ready` | `failed`).
- `video_id` (FK `videos`, pour `kind = video` : on **réutilise** la chaîne vidéo existante et ses pilotes).
- `url` (pour `link`), `allow_download` (bool, défaut false), `is_active`, `created_by`, timestamps.
- Le pivot `group_resource` (group_id, resource_id, folder_id, position) suit les mêmes règles que les autres contenus.

## 7. Règles par mode

| | `shared` | `isolated` |
|---|---|---|
| Attribuer un contenu à un 2ᵉ groupe | Autorisé (comportement actuel) | **Refusé** (422). L'interface propose « Copier vers un autre groupe ». |
| Copier vers un groupe | Disponible aussi | Action principale |
| Place par groupe | 1 | 1, et 1 seul groupe |

- **Copie** : copie profonde de l'entité. Un quiz est copié avec ses questions, un deck avec ses cartes, un examen avec ses questions, un article avec son HTML. Les **fichiers lourds sont partagés, pas dupliqués** : même `Video`, même chemin de média. La suppression d'un fichier physique n'a donc lieu que lorsque plus aucun contenu ne le référence. La copie arrive à la racine du groupe cible, ou dans un dossier choisi au moment de la copie.
- **Passage de `shared` à `isolated`** : refusé tant qu'un contenu est attribué à plus d'un groupe. L'écran de réglage affiche le nombre de contenus concernés et propose « Dupliquer automatiquement » : l'original reste dans un groupe et chaque autre groupe reçoit une copie au **même emplacement**. Le passage d'`isolated` à `shared` est toujours permis.
- **Historique des élèves lors de la duplication automatique** (décidé) : l'historique est **transféré sur la copie** pour les élèves du groupe qui la reçoit. Cela concerne `quiz_attempts`, `exam_attempts` (avec leurs réponses), `learner_progress`, les révisions et sessions de flashcards (`flashcard_item_reviews`, `flashcard_sessions`, état SM-2) et les instantanés push. Les identifiants des questions et des cartes sont **renumérotés** au moyen d'une table de correspondance entre original et copie, construite pendant la copie. Ainsi le nombre de tentatives d'un examen est conservé et un examen déjà passé ne peut pas être repassé. **L'original reste dans le groupe qui compte le plus d'élèves**, et l'id de groupe le plus petit départage les égalités. Toute l'opération se fait dans une transaction par contenu. Un mode simulation (« dry-run ») affiche le bilan avant l'exécution.
- La copie manuelle (« Copier vers… ») ne transfère **aucun** historique : c'est un nouveau contenu.
- **Decks publics** (`is_public`) : ils restent hors des dossiers et visibles partout dans l'écran Réviser, comme aujourd'hui. *(Choix du concepteur)* Ils échappent à la règle « un seul groupe ».
- *(Choix du concepteur)* **Contenus sans groupe** : un contenu peut exister sans aucun groupe, comme brouillon ou banque personnelle de l'enseignant, dans les deux modes. En mode `isolated`, le refus 422 ne s'applique qu'à l'ajout d'un **deuxième** groupe. Le parcours de création dans les éditeurs ne change pas.

## 8. Droits

- Les admins et super-admins gèrent les dossiers de tous les groupes.
- Un formateur gère les dossiers et placements des groupes auxquels il est rattaché.
- Mode `shared` : les règles actuelles de modification ne changent pas. Seul le créateur modifie (`created_by`), ainsi que les admins.
- Mode `isolated` (décidé) : **tous les formateurs rattachés au groupe propriétaire** peuvent modifier ses contenus, en plus du créateur et des admins. Un contenu sans groupe reste modifiable par son seul créateur. Cette règle s'applique dans tous les `*EditorController` (`authorizeQuiz` et ses équivalents), de préférence via une policy commune.
- Une copie a pour `created_by` la personne qui a fait la copie.
- Les élèves sont en lecture seule.

## 9. Visibilité

- Un dossier masqué (`is_visible = false`) ou hors de sa fenêtre `available_from` / `available_until` cache **tout son sous-arbre** aux élèves de **ce groupe**.
- La visibilité propre du contenu (`is_active`, dates des articles, fenêtres des examens) s'ajoute à celle du dossier, sans la remplacer.
- En mode `shared`, un contenu caché par le dossier d'un groupe reste visible dans un autre groupe où il est placé dans un dossier visible. Les écrans par type n'affichent un contenu que s'il a **au moins un placement visible** pour l'élève.
- Ces calculs sont faits **côté serveur**. Le client affiche ce qu'il reçoit.
- *(Choix du concepteur)* La visibilité par placement est une **vérification d'autorisation commune**, pas seulement un filtre sur les listes. Elle s'applique à `resources/{id}/view` et `/download`, au démarrage d'un quiz ou d'un examen, et à la révision d'un deck. Un contenu dont aucun placement n'est visible sort des `authorized_ids` et le client le supprime, comme aujourd'hui quand un groupe est fermé. Une tentative faite hors ligne **avant** le masquage est quand même acceptée à la synchronisation, en s'appuyant sur l'horodatage de la tentative.
- *(Choix du concepteur)* **Notifications push** : `PushNewContent` et `PushExamOpen` ne notifient que les contenus dont le placement est visible. Quand un dossier devient visible (bascule de `is_visible` ou arrivée de `available_from`), une tâche planifiée envoie une notification « nouveau contenu » pour ce dossier, et une seule, même si le dossier contient plusieurs éléments.

## 10. API élève (PWA `api/learner/v1` et mobile `api/mobile/v1`)

- `bootstrap` ajoute :
  - `folders` : les dossiers visibles des groupes courants de l'élève (id, group_id, parent_id, name, position) ;
  - `resources` : les ressources visibles, sérialisées (voir plus bas) ;
  - `placements` : la liste compacte `{type, id, group_id, folder_id, position}` pour tous les contenus visibles.
- `changes` renvoie `resources` sous la forme habituelle `updated` + `authorized_ids`. **`folders` et `placements` sont renvoyés en entier à chaque appel**, comme `groups`, car ils sont petits. Un déplacement ne déclenche donc aucun re-téléchargement de contenu.
- Sérialisation d'une ressource :
  - `kind`, `title`, `description`, `mime`, `size`, `allow_download` ;
  - `video_ref` pour une vidéo, `url` pour un lien ;
  - pour un fichier : pas d'URL stable. La PWA demande une source temporaire via `GET /resources/{id}/view`, qui vérifie les droits et renvoie une URL signée de courte durée (aux alentours de 15 min, sur le modèle de la vidéo) vers le PDF (original ou converti), l'audio ou l'image ;
  - `GET /resources/{id}/download` n'existe que si `allow_download` est vrai.
- Suivi : quand une ressource est ouverte, un événement est enregistré dans `learner_progress` (`content_type = resource`, `status = completed` à l'ouverture), en passant par l'outbox existante.

## 11. PWA élève

- Nouvelle entrée de navigation **« Cours »**, et routes `/cours` (choix du groupe, ou accès direct s'il n'y en a qu'un) et `/cours/:groupId/:folderId?`.
- La vue Cours affiche le fil d'Ariane, puis les sous-dossiers, puis les éléments triés par `position`, avec une icône par type et la **progression du dossier** (« 5/8 consultés », sous-dossiers compris). *(Choix du concepteur)* « Consulté » se calcule avec les sources existantes de chaque type, sans nouvel événement :
- article, quiz et ressource : `learner_progress` ;
- examen : au moins une `exam_attempts` ;
- deck : au moins une révision ou une session.
- Un appui sur un élément ouvre l'écran existant (`/articles/:id`, `/quizzes/:id`, `/reviser/:deckId`, examens) ou le nouveau **lecteur de ressource** `/ressources/:id` :
  - PDF et documents convertis : visionneuse pdf.js, filigrane nominatif, gestes de sauvegarde neutralisés comme dans `media-guard` ;
  - audio : lecteur durci existant ;
  - image : visionneuse plein écran existante ;
  - vidéo : `video-ref` existant ;
  - lien : les liens YouTube et Vimeo passent par `brand-video`, les autres s'ouvrent dans le navigateur.
- Les cartes des écrans Articles, Entraînement, Réviser et Examens affichent une puce avec le chemin (« SVT › Ch.3 »). Un appui ouvre le dossier. Si le contenu a plusieurs placements (mode `shared`), on affiche le premier groupe dans l'ordre alphabétique.
- Dexie passe en version 4 avec les tables `folders`, `resources` et `placements`.
- **Hors ligne** : l'arborescence et les métadonnées sont toujours disponibles. Les fichiers (PDF, audio, image) se téléchargent à la demande via un bouton « Disponible hors ligne », stockés dans le cache du service worker, sur le modèle de `sw-video.ts`. Ils restent soumis aux mêmes protections.
- Toute l'interface utilise la terminologie configurable du groupe.

## 12. Back-office (enseignant, admin)

- Dans la page d'un groupe, un onglet **« Contenus »** affiche l'arbre du groupe et permet de :
  - créer, renommer, masquer ou programmer et supprimer un dossier ;
  - glisser-déposer pour ordonner et déplacer dossiers et éléments ;
  - ajouter un contenu existant ;
  - téléverser une ressource (audio, image, PDF, Word, PowerPoint ou vidéo via la chaîne existante) ;
  - ajouter un lien ;
  - copier un élément vers un autre groupe.
- Supprimer un dossier non vide demande ce qu'il faut faire de son contenu : le remonter dans le dossier parent, ou retirer ces contenus du groupe.
- Dans les éditeurs de contenu, la section d'attribution aux groupes ajoute un **choix du dossier** pour chaque groupe. En mode `isolated`, elle devient un choix unique (un groupe et un dossier) et ajoute le bouton « Copier vers… ».
- Le réglage `content.sharing_mode` se fait dans les réglages de la plateforme (admins), avec la vérification et l'assistant de duplication décrits au §7.

## 13. Conversion des documents

- Les Word (`.doc`, `.docx`, `.odt`) et PowerPoint (`.ppt`, `.pptx`, `.odp`) sont convertis en PDF par un job en file d'attente qui lance LibreOffice sans interface (`soffice --headless --convert-to pdf`). Le job tourne sur une file dédiée, sur le modèle de la file `videos` (timeout et `retry_after` adaptés).
- Tant que `conversion_status` vaut `pending`, l'élève voit « En préparation ». Si la conversion échoue, l'enseignant voit l'erreur dans le back-office et peut réessayer ou activer le téléchargement. L'élève voit « Document indisponible ».
- Si LibreOffice est absent du serveur, le téléversement de Word et PowerPoint reste possible et le back-office affiche un avertissement clair. Ajouter LibreOffice aux consignes de déploiement (`DEPLOIEMENT.md`).
- La taille maximale des fichiers se règle dans `config/media.php`.

## 14. App mobile Kotlin

- `api/mobile/v1` expose les mêmes `folders`, `resources` et `placements`.
- Mettre à jour `PROMPT_APP_MOBILE_KOTLIN.md` (ou ajouter un prompt dédié, comme pour les mises à jour précédentes) : onglet Cours, lecteur de ressources (PDF natif ou WebView, avec `FLAG_SECURE` et filigrane), puce de chemin sur les cartes, hors ligne à la demande.

## 15. Laissé au constructeur

- Le découpage en lots et l'ordre de livraison. Suggestion : modèle, API et PWA en lecture d'abord, puis back-office, ressources et conversion, puis mode `isolated` et copie.
- La bibliothèque de glisser-déposer du back-office, le style exact des écrans (cohérent avec la PWA actuelle), la forme des icônes.
- Le mécanisme de comptage des références pour les fichiers partagés entre copies.
- Les index SQL et l'optimisation des requêtes de visibilité.
