# Mise à jour AI Studio — Dossiers de contenus et ressources (onglet « Cours »)

> À coller dans la MÊME conversation AI Studio que le prompt initial de
> l'application Learn&Quiz (`PROMPT_APP_MOBILE_KOTLIN.md`), comme demande
> d'évolution. Les règles du prompt initial restent valables (Kotlin 2, Compose,
> Retrofit, Room, offline-first, `FLAG_SECURE`, filigrane nominatif, lecteur de
> marque YouTube / Vimeo, outbox `POST /actions`).

---

Ajoute à l'application les **dossiers de contenus par groupe** et les **ressources**
(PDF, documents Word/PowerPoint convertis en PDF, audio, image, vidéo, lien).
Le formateur organise désormais les contenus d'un groupe dans une arborescence de
dossiers. L'apprenant la parcourt dans un nouvel onglet **« Cours »**, voit sur ses
cartes existantes une **puce de chemin** (« Groupe › Dossier »), et ouvre les ressources
dans un **lecteur protégé**. Le backend a évolué : tout ce qui suit existe déjà côté
serveur. N'invente aucun autre champ ni endpoint.

## 1. Changement de contrat API

`GET /bootstrap` **et** `GET /changes` (base `{{BASE_URL}}/api/mobile/v1`, Bearer)
renvoient trois nouveaux éléments : `resources`, `folders`, `placements`.

### 1.1 `GET /bootstrap` (extrait réel, champs inchangés omis)

```json
{
  "success": true,
  "cursor": "2026-10-20T10:00:00+00:00",
  "groups": [
    { "id": 1, "name": "Promo Dossiers", "status": "active", "start_date": null, "end_date": null }
  ],
  "resources": [
    { "id": 1, "kind": "video", "title": "Cours filmé", "description": null,
      "mime": "video/mp4", "size": 10485760, "allow_download": false,
      "video_ref": "e9416492-b2fa-4408-9ebd-689550e197e0", "url": null,
      "conversion_status": null, "consulted": true, "updated_at": "2026-10-20T10:00:00+00:00" },
    { "id": 2, "kind": "link", "title": "Site officiel", "description": null,
      "mime": null, "size": null, "allow_download": false,
      "video_ref": null, "url": "https://example.org/doc",
      "conversion_status": null, "consulted": false, "updated_at": "2026-10-20T10:00:00+00:00" },
    { "id": 3, "kind": "document", "title": "Support Word", "description": null,
      "mime": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "size": 52000, "allow_download": true, "video_ref": null, "url": null,
      "conversion_status": "ready", "consulted": false, "updated_at": "2026-10-20T10:00:00+00:00" }
  ],
  "folders": [
    { "id": 1, "group_id": 1, "parent_id": null, "name": "Semaine 1", "position": 0 },
    { "id": 2, "group_id": 1, "parent_id": 1,    "name": "Exercices", "position": 1 }
  ],
  "placements": [
    { "type": "article",  "id": 1, "group_id": 1, "folder_id": null, "position": 0 },
    { "type": "quiz",     "id": 1, "group_id": 1, "folder_id": 2,    "position": 0 },
    { "type": "exam",     "id": 1, "group_id": 1, "folder_id": null, "position": 0 },
    { "type": "deck",     "id": 1, "group_id": 1, "folder_id": 1,    "position": 0 },
    { "type": "resource", "id": 1, "group_id": 1, "folder_id": 1,    "position": 1 },
    { "type": "resource", "id": 2, "group_id": 1, "folder_id": 1,    "position": 2 },
    { "type": "resource", "id": 3, "group_id": 1, "folder_id": 2,    "position": 0 }
  ],
  "terms": { "group": "groupe", "groups": "groupes", "Group": "Groupe", "Groups": "Groupes",
             "a_group": "un groupe", "the_group": "le groupe", "of_group": "du groupe",
             "to_group": "au groupe", "this_group": "ce groupe", "no_group": "aucun groupe" }
}
```

Règles de lecture :
- `resources[].kind` ∈ `video | audio | image | pdf | document | link`.
- `video_ref` n'est renseigné que pour `video` ; `url` seulement pour `link` ; sinon `null`.
  **Un fichier (pdf, audio, image, document) n'a jamais d'URL stable** : il faut passer
  par `GET /resources/{id}/view` (§2).
- `conversion_status` ∈ `pending | ready | failed | null` (non `null` pour un `document`
  seulement : Word / PowerPoint / OpenDocument convertis en PDF côté serveur).
- `consulted` (booléen, toujours présent) : l'apprenant connecté a déjà consulté cette
  ressource, **sur n'importe quel appareil** (progression serveur enregistrée par l'action
  `resource_view`, §2). C'est la source de vérité multi-appareils ; la table locale
  `consulted_resources` ne sert qu'à afficher la coche immédiatement, hors ligne, avant
  que l'outbox soit synchronisée.
- `terms` (terminologie du groupe, §7) est renvoyé par `bootstrap` **et** par `changes`.
- `placements[].type` ∈ `article | quiz | exam | deck | resource`. `folder_id: null` =
  racine du groupe. Un contenu peut avoir **plusieurs** placements (un par groupe) ;
  `position` ordonne les éléments d'un même dossier. Les dossiers aussi sont ordonnés
  par `position` (parmi leurs frères).
- Seuls les dossiers visibles sont envoyés : un dossier masqué ou programmé (et tout son
  sous-arbre) est **absent**, ainsi que les placements qu'il contient.
- Un contenu qui n'a aucun placement visible dans un groupe de l'apprenant reste dans les
  écrans existants (Articles, Entraînement, Examens) ; il n'apparaît simplement pas dans « Cours ».

### 1.2 `GET /changes?since={cursor}` (extrait réel, **abrégé**)

> Exemple raccourci pour la lisibilité : `articles`, `quizzes`, `decks`, `exams`, `badges`
> et `xp` sont omis (inchangés), et `placements` ne montre qu'une entrée alors que le
> serveur renvoie toujours la liste complète.

```json
{
  "success": true,
  "cursor": "2026-10-20T10:01:00+00:00",
  "resources": {
    "updated": [ { "id": 3, "kind": "document", "title": "Support Word", "description": null,
                   "mime": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                   "size": 52000, "allow_download": true, "video_ref": null, "url": null,
                   "conversion_status": "ready", "consulted": true,
                   "updated_at": "2026-10-20T10:00:00+00:00" } ],
    "authorized_ids": [1, 2, 3]
  },
  "folders": [
    { "id": 1, "group_id": 1, "parent_id": null, "name": "Semaine 1", "position": 0 },
    { "id": 2, "group_id": 1, "parent_id": 1,    "name": "Exercices", "position": 1 }
  ],
  "placements": [
    { "type": "resource", "id": 3, "group_id": 1, "folder_id": 2, "position": 0 }
  ],
  "groups": [ { "id": 1, "name": "Promo Dossiers", "status": "active", "start_date": null, "end_date": null } ],
  "terms": { "group": "groupe", "groups": "groupes", "Group": "Groupe", "Groups": "Groupes",
             "a_group": "un groupe", "the_group": "le groupe", "of_group": "du groupe",
             "to_group": "au groupe", "this_group": "ce groupe", "no_group": "aucun groupe" }
}
```
(`articles`, `quizzes`, `decks`, `exams`, `badges`, `xp` inchangés ; `placements` abrégé ici,
il contient toujours la liste complète.)

- `resources` suit la forme habituelle `{updated, authorized_ids}` : upsert des `updated`,
  suppression locale de tout id absent de `authorized_ids`. Une ressource consultée depuis
  le curseur (par exemple sur un autre appareil) est renvoyée dans `updated` avec
  `consulted: true`, même si son `updated_at` n'a pas changé.
- **`folders` et `placements` sont renvoyés EN ENTIER à chaque appel `changes`** (comme
  `groups` et `badges`). Un déplacement de dossier ne déclenche donc aucun re-téléchargement.

## 2. Endpoints ressources et codes d'erreur

Les deux routes sont Bearer, sous `{{BASE_URL}}/api/mobile/v1`.

### `GET /resources/{id}/view`

| Statut | Corps (réel) | Comportement |
|---|---|---|
| 200 fichier | `{"type":"pdf","url":"https://…/api/learner/v1/resources/1/file?expires=1792491300&signature=3c34…","expires_at":"2026-10-20T10:15:00+00:00"}` | `type` ∈ `pdf` (un `document` converti répond aussi `pdf`), `image`, `audio`. URL **signée, 15 min**, sans jeton : la donner telle quelle au lecteur. |
| 200 lien | `{"type":"link","url":"https://example.org/cours"}` | Voir §5.5. |
| 200 vidéo | `{"type":"video","video_ref":"9d114a22-5d69-44fb-be93-7f30ed102291"}` | Lecture via le mécanisme existant `GET /videos/{ref}/playback` (prompt initial, §2-bis / section vidéo). |
| 202 | `{"status":"pending"}` | Document en cours de conversion : « Document en préparation… » + bouton « Réessayer ». |
| 409 | `{"status":"failed"}` | Conversion échouée : « Document indisponible ». |
| 403 | `{"message":"Ressource indisponible."}` | Ressource masquée, retirée, groupe inactif : « Ressource indisponible. » et purger la copie hors ligne (§6). |
| 404 | corps non garanti | Vidéo dont la référence n'existe plus côté serveur : « Ressource indisponible. ». (Une ressource inexistante ou invisible répond 403, jamais 404 : le serveur ne révèle pas son existence.) |
| 401 | `{"message":"Unauthenticated."}` | Bearer expiré ou révoqué : même traitement que partout ailleurs (purge du token, de Room et des fichiers hors ligne, retour à la connexion — prompt initial). |

L'URL signée (`…/file?expires=…&signature=…`) peut elle-même répondre **403** si elle a
expiré ou été altérée, et **404** si le fichier n'existe plus sur le serveur : dans les deux
cas, redemander `view` une fois, puis afficher « Ressource indisponible. ».

L'URL signée **expire 15 minutes après sa délivrance** : ne jamais la stocker ; la
redemander via `view` si elle a expiré (le fichier est servi avec `Range`, en `inline`).

### `GET /resources/{id}/download`

- 200 : fichier d'origine en pièce jointe (`Content-Disposition: attachment`).
- **403** `{"message":"Téléchargement non autorisé pour cette ressource."}` si
  `allow_download` est faux.
- **404** si le fichier d'origine est absent du serveur, et **toujours pour une vidéo**
  (une vidéo se regarde en streaming, elle ne se télécharge jamais) : ne jamais afficher
  « Télécharger » pour `kind = video`, même si `allow_download` est vrai.
- **401** : Bearer expiré, traitement global (purge + reconnexion).
- En mode consultation « Voir en tant que » : `view` est autorisé, `download` refusé (403).
- N'afficher un bouton « Télécharger » **que si `allow_download` est vrai et `kind != video`**. Le fichier
  téléchargé va dans l'espace de téléchargement choisi par l'utilisateur (`DownloadManager`
  avec le Bearer, ou flux enregistré via `MediaStore`), jamais dans le cache chiffré du §6.

### Nouvelle action de l'outbox : `resource_view`

`POST /actions`, `data` = `{ "resource_id": 12 }`. Elle marque la ressource comme
**consultée** (progression serveur `completed`, sans XP, idempotente).
Réponse par action : `applied` (`result: {"resource_id":12,"status":"completed"}`),
`duplicate` ou `rejected` avec `message` (« Ce contenu n'est pas disponible. » si la
ressource n'est plus visible, ou `resource_id` manquant). Comme toutes les actions :
`rejected` → retirer de l'outbox et afficher le message dans une snackbar.

Ajoute `resource_view` à la liste des types gérés par l'outbox et au parseur des `results`.

## 3. Modèle local (Room)

Nouvelles tables, à ajouter avec une migration Room (incrémenter la version, migration
explicite, jamais de `fallbackToDestructiveMigration` sur les tables d'historique) :

```kotlin
@Entity(tableName = "folders", indices = [Index("groupId"), Index("parentId")])
data class FolderEntity(
    @PrimaryKey val id: Long,
    val groupId: Long,
    val parentId: Long?,          // null = racine du groupe
    val name: String,
    val position: Int,
)

@Entity(tableName = "resources")
data class ResourceEntity(
    @PrimaryKey val id: Long,
    val kind: String,             // video|audio|image|pdf|document|link
    val title: String,
    val description: String?,
    val mime: String?,
    val size: Long?,
    val allowDownload: Boolean,
    val videoRef: String?,
    val url: String?,             // lien uniquement
    val conversionStatus: String?, // pending|ready|failed|null
    val consulted: Boolean,       // drapeau serveur, tous appareils confondus
    val updatedAt: String,
)

@Entity(
    tableName = "placements",
    primaryKeys = ["type", "id", "groupId"],
    indices = [Index("groupId"), Index("folderId")],
)
data class PlacementEntity(
    val type: String,             // article|quiz|exam|deck|resource
    val id: Long,
    val groupId: Long,
    val folderId: Long?,          // null = racine
    val position: Int,
)

// Marquage local « consulté » d'une ressource (utile hors ligne, avant la sync de l'outbox)
@Entity(tableName = "consulted_resources")
data class ConsultedResourceEntity(@PrimaryKey val resourceId: Long)
```

Règles de synchronisation (OBLIGATOIRES) :

1. **Remplacement complet de `folders` et `placements`.** À chaque `bootstrap` ET à chaque
   `changes`, dans UNE transaction Room : `DELETE FROM folders` + `DELETE FROM placements`
   puis insertion des listes reçues. Ne jamais fusionner ni appliquer de diff : un dossier
   masqué disparaît, un déplacement change `folder_id` / `position`, un retrait supprime
   le placement.
2. `resources` : upsert des `updated`, puis suppression de tout id absent de
   `authorized_ids` (même règle que pour articles, quiz, decks, examens).
3. **Règle de réautorisation (critique).** Un contenu masqué puis de nouveau affiché
   revient dans `authorized_ids` mais **pas dans `updated[]`** (son `updated_at` est
   ancien). Après application d'un delta, si **au moins un id de `authorized_ids`**
   (ressources comprises) est absent de Room, déclencher immédiatement un
   **re-bootstrap complet** (`GET /bootstrap`). Cette règle existe déjà pour les autres
   contenus : l'étendre à `resources`.
4. Un contenu absent de Room mais présent dans `placements` est ignoré à l'affichage (pas
   d'élément fantôme).
5. Déconnexion / token invalide : vider ces tables, `consulted_resources` et les fichiers
   hors ligne (§6), comme le reste de Room.
6. Les tentatives, l'XP et les badges ne sont jamais touchés par ces remplacements.

## 4. Onglet « Cours »

Nouvelle destination de navigation **« Cours »** (icône dossier ouvert), placée juste après
Accueil. Routes : `cours` (choix du groupe), `cours/{groupId}` et `cours/{groupId}/{folderId}`.

1. **Choix du groupe** (`cours`) : un seul groupe → ouvrir directement sa racine (remplacer
   l'entrée dans la pile : le Retour ne revient pas sur un écran intermédiaire). Plusieurs
   groupes → une carte par groupe (nom + « n/m consultés »). Aucun groupe → état vide
   « Aucun contenu pour le moment. » (le mot « groupe » vient de `terms`, §7).
   Seuls les groupes `active` de `groups` sont listés (un groupe suspendu/fermé n'a plus
   de contenu).
2. **Dossier** : fil d'Ariane cliquable (Cours › Groupe › Dossier › …, dernier élément non
   cliquable, défilement horizontal si long) ; puis les **sous-dossiers** (icône dossier,
   nom, « n/m consultés »), puis les **éléments** triés par `position`. Chaque élément :
   icône par type (article, quiz, examen, deck ; pour une ressource, icône par `kind` :
   vidéo, audio, image, pdf, document, lien), titre, coche « Consulté » si consulté.
   En tête : barre de progression « n/m consultés » du dossier courant.
3. **Destination d'un appui** : article → lecture d'article ; quiz → fiche quiz ; deck →
   session de révision de ce deck ; examen → liste des Examens (pas de page de détail) ;
   ressource → lecteur (§5).
4. État vide d'un dossier : « Aucun contenu dans ce dossier pour le moment. »
5. La liste se rafraîchit après chaque synchronisation (Flow Room). Bouton Retour système :
   remonte d'un niveau de dossier avant de quitter l'onglet.
6. Écran 100 % hors ligne : l'arborescence et les métadonnées viennent de Room.

### Règle « consulté » et progression d'un dossier

Progression `n/m` = parmi **tous** les éléments du sous-arbre (dossier courant et
sous-dossiers, récursivement ; pour un groupe entier : tout le groupe), `m` = nombre
d'éléments (dossiers exclus), `n` = nombre d'éléments consultés. Un élément est consulté si :

| Type | Consulté quand |
|---|---|
| article | `status != "unread"` ou `progress_percentage > 0` |
| quiz | `status != "unread"` ou au moins une tentative (`attempts` non vide) |
| examen | au moins une tentative (`attempts` non vide) |
| deck | au moins une carte avec une révision enregistrée |
| ressource | `consulted` vrai (serveur, tous appareils) **ou** id présent dans `consulted_resources` (marquage local à l'ouverture effective, voir §5) |

Le parcours du sous-arbre doit être protégé contre un cycle (garde de profondeur ou
ensemble des dossiers déjà vus).

## 5. Puce de chemin et lecteur de ressources

### 5.1 Puce de chemin sur les cartes existantes

Sur les cartes des écrans Articles, Entraînement (quiz et decks), Examens, et en tête de la
session de révision, afficher une puce discrète « **Groupe › Dossier** » :
- Si le contenu a plusieurs placements, prendre **le premier groupe dans l'ordre
  alphabétique** du nom (insensible à la casse et aux accents, locale française), parmi les
  groupes présents dans `groups`.
- Le dossier affiché est **le plus profond** (celui du placement). À la racine du groupe :
  le nom du groupe seul.
- Libellé tronqué à **24 caractères au total, points de suspension compris** (23
  caractères + « … »).
- Contenu sans placement dans un groupe connu : pas de puce.
- Appui sur la puce : ouvre `cours/{groupId}/{folderId}` (ou `cours/{groupId}` à la
  racine) **sans ouvrir la carte** (la puce capte le toucher, la carte reste cliquable
  ailleurs). `contentDescription` en français : « Ouvrir le dossier {libellé complet} ».

### 5.2 Ouverture d'une ressource — écran `ressources/{id}`

À l'ouverture, selon `kind` :

| kind | Rendu |
|---|---|
| `pdf`, `document` (`ready`) | `GET /resources/{id}/view` → télécharger le fichier signé dans un **fichier temporaire privé de `cacheDir`** puis le rendre avec `PdfRenderer` (un `Bitmap` par page, rendu paresseux en `LazyColumn`, zoom pincé). Voir §5.3. |
| `image` | Visionneuse plein écran existante (zoom au toucher), filigrane. |
| `audio` | Lecteur audio durci existant (Media3), sans « Télécharger » ni Cast. |
| `video` | Lecteur vidéo par référence existant (`/videos/{ref}/playback`, renouvellement 15 min, filigrane). |
| `link` | Voir §5.5. |

Pour un `document` : `conversion_status` local peut être périmé, c'est la réponse de `view`
qui fait foi (202 → « Document en préparation… » avec bouton « Réessayer » ; 409 →
« Document indisponible » ; 403 → « Ressource indisponible. »).

**Consultation.** Une ressource n'est « consultée » que **lorsqu'elle est réellement
affichée** : réponse 200 (ou copie hors ligne lue). À ce moment seulement : insérer son id
dans `consulted_resources` ET mettre `resource_view` `{resource_id}` dans l'outbox.
Pas de consultation sur 202, 409, 403 ou erreur réseau sans copie locale. Hors ligne avec
copie chiffrée : consultée (l'action partira à la prochaine synchronisation).

### 5.3 Lecteur PDF (`PdfRenderer`) et protections

- `PdfRenderer` exige un `ParcelFileDescriptor` **sur lequel on peut se déplacer
  (seekable)** : un tube (`createPipe`) ou un flux en mémoire ne fonctionne pas. Dans les
  deux cas on passe donc par un **fichier temporaire privé de `cacheDir`** :
  - **en ligne** : le flux de l'URL signée est écrit dans ce fichier temporaire ;
  - **hors ligne** : la copie chiffrée (§6) est déchiffrée dans ce fichier temporaire.
  Le fichier est supprimé à la fermeture du lecteur (et au démarrage de l'application,
  purge des restes éventuels de `cacheDir`). Pas de couche de texte, pas de barre
  d'outils, aucun lien sortant, aucune application externe ouverte.
- **`FLAG_SECURE`** sur l'activité tant que l'écran ressource est affiché (captures et
  enregistrement d'écran bloqués), ainsi que sur la visionneuse d'image et le plein écran
  vidéo.
- **Filigrane nominatif** (nom · matricule · date du jour), répété en mosaïque
  semi-transparente **sur chaque page** du PDF (calque Compose au-dessus du `Bitmap`,
  même texte et même rendu que celui des vidéos / images existantes).
- Aucun bouton de partage, d'impression, « Ouvrir avec… » ni de copie de texte. Pas de
  glisser-déposer. L'URL signée n'est jamais affichée ni journalisée.
- Si l'URL signée expire pendant la lecture (réseau lent, 15 min), redemander `view` ;
  ne jamais conserver l'URL.
- Boutons : « Disponible hors ligne » (bascule) pour `pdf`, `document`, `audio`, `image` ;
  « Télécharger » **seulement si `allow_download`** et jamais pour une vidéo. En mode consultation (« Voir en tant
  que ») ces deux boutons sont masqués.

### 5.4 Messages d'état

202 « Document en préparation… » (+ « Réessayer ») · 409 « Document indisponible » ·
403 « Ressource indisponible. » · hors ligne sans copie « Ressource disponible en ligne
uniquement. » (vidéo : « Vidéo disponible en ligne uniquement. »).

### 5.5 Liens

`GET /resources/{id}/view` → `{"type":"link","url":"…"}`.
- Si l'URL est une vidéo **YouTube ou Vimeo** (extraire l'identifiant, et le hash Vimeo si
  présent, comme pour les contenus : voir « B. Vidéo YouTube / Vimeo » du prompt initial)
  → lecteur de marque (WebView dédiée, calque transparent, commandes natives, filigrane,
  `FLAG_SECURE`). Jamais l'URL brute ni l'iframe d'origine.
- Sinon : écran avec le titre, la description et un bouton « Ouvrir le lien » qui ouvre
  l'URL dans le navigateur (`Intent.ACTION_VIEW`, schémas `http`/`https` uniquement).
  Cette ouverture compte comme consultation (le lien est réellement présenté).
- Un lien reste « en ligne uniquement » (pas de copie hors ligne).

## 6. Hors ligne à la demande, chiffré

Seuls `pdf`, `document` (PDF converti), `audio` et `image` peuvent être gardés hors ligne,
**uniquement sur action de l'utilisateur** (bouton « Disponible hors ligne »). Jamais pour
la vidéo ni les liens.

1. Télécharger le fichier via l'URL signée de `view`, puis l'**écrire chiffré** dans le
   stockage **privé de l'application** (`context.filesDir/resources/{id}.enc`), jamais sur
   le stockage partagé ni la carte SD, jamais dans `MediaStore`. Chiffrement
   `EncryptedFile` (Jetpack Security, AES-256-GCM-HKDF) avec une clé du Keystore Android
   (`MasterKey`, `AES256_GCM`). Jetpack Security (`EncryptedFile`) est **déprécié** :
   une clé AES générée dans l'Android Keystore et un chiffrement en flux AES-GCM
   (`Cipher` + `CipherOutputStream` / `CipherInputStream`, IV stocké en tête du fichier)
   est une alternative acceptable. Conserver à côté (Room ou fichier méta) : id, `kind`, `mime`.
   Aucune URL signée n'est stockée.
2. Lecture hors ligne : déchiffrer en flux vers un fichier temporaire de `cacheDir`
   (PDF) ou un flux mémoire (image, audio), le supprimer à la fermeture. Mêmes protections
   (`FLAG_SECURE`, filigrane) que la lecture en ligne.
3. Au démarrage du lecteur : si le réseau est disponible, tenter `view` ; hors ligne ou
   erreur réseau → copie locale si elle existe ; sinon « Ressource disponible en ligne
   uniquement. ». Un 403 / 409 du serveur supprime la copie locale.
4. **Règle de purge** : après chaque synchronisation (bootstrap ou changes), supprimer
   tout fichier hors ligne dont l'id de ressource **n'est plus dans `authorized_ids`**
   de `resources` (ressource retirée, masquée, groupe suspendu ou fermé). À la
   déconnexion, à la purge de session (401) et au changement de compte : supprimer
   **tous** les fichiers hors ligne et la clé associée. Désinstallation = données privées
   effacées par Android.
5. Ne pas inclure ces fichiers dans les sauvegardes : `android:allowBackup="false"` (ou
   règles `dataExtractionRules` / `fullBackupContent` excluant `resources/`).
6. Afficher la taille (`size`) avant le téléchargement hors ligne ; erreur claire si espace
   insuffisant ou réseau coupé (« Téléchargement impossible. Réessayez avec une connexion. »),
   sans laisser de fichier partiel.

## 7. Terminologie du groupe

Le serveur peut appeler le groupe autrement (classe, promotion, cohorte…). Les libellés
viennent de l'objet `terms` renvoyé par `bootstrap` et par `changes` (clés : `group`, `groups`, `Group`,
`Groups`, `a_group`, `the_group`, `of_group`, `to_group`, `this_group`, `no_group`),
stocké dans Room avec les préférences. Dans tous les écrans de ce prompt, **ne jamais écrire
« groupe » en dur** : « Aucun {terms.group} » / `terms.no_group`, titres « {terms.Groups} »,
etc. Valeur par défaut si `terms` est absent : « groupe ».
Tous les textes sont en français, accentués.

## 7-bis. Choix de conception (non imposés par le serveur)

> Les points suivants sont des **choix côté application**, repris de la PWA ou proposés
> pour Android. Le serveur ne les vérifie pas : ils peuvent être adaptés s'il existe une
> meilleure solution Android, tant que les règles de sécurité (§5.3, §6) sont tenues.

- L'onglet « Cours » ne liste que les groupes au statut `active` (§4.1).
- La table locale `consulted_resources` (affichage immédiat et hors ligne de la coche
  « Consulté », en plus du drapeau serveur `consulted`).
- Le stockage hors ligne chiffré dans `filesDir` (§6). `EncryptedFile` de Jetpack
  Security est déprécié : clé Android Keystore + flux AES-GCM est acceptable.
- `android:allowBackup="false"` (ou règles d'exclusion de sauvegarde équivalentes).
- Le téléchargement via `DownloadManager` (avec le Bearer) ou un flux enregistré via
  `MediaStore` (§2).
- Les règles de la puce de chemin (24 caractères points de suspension compris, premier
  groupe dans l'ordre alphabétique, dossier le plus profond) : reprises de la PWA.

**Sans impact mobile :** le back-office permet désormais de modifier les paramètres d'une
ressource (titre, description, téléchargement autorisé, activation) et de la supprimer.
Ces opérations sont réservées au back-office (aucun endpoint mobile) ; l'application en
voit seulement l'effet après synchronisation (`updated`, ou id absent de
`authorized_ids` → suppression locale et purge de la copie hors ligne).

## 8. Tests attendus et checklist de QA

Tests automatisés (JUnit / Room en mémoire / MockWebServer) :
- `bootstrap` puis `changes` : `folders` et `placements` sont **remplacés en entier** (un
  dossier absent du second appel disparaît ; un placement déplacé change de `folderId`).
- `changes` avec un id de `resources.authorized_ids` absent de Room → un `GET /bootstrap`
  est déclenché.
- `resources` : un id absent de `authorized_ids` est supprimé de Room, et son fichier hors
  ligne est supprimé après la sync.
- Libellé de puce : premier groupe alphabétique, dossier le plus profond, 24 caractères
  maximum « … » compris, absent si pas de placement.
- Progression `n/m` récursive (sous-dossiers compris), avec les règles « consulté » par type.
- `view` : 200 pdf / link / video, 202, 409, 403 → bons écrans ; `resource_view` n'est
  mis en outbox que sur 200 (ou copie locale lue).
- `download` : bouton absent si `allow_download` est faux, et toujours absent pour une vidéo.
- `consulted: true` reçu du serveur sans marquage local → l'élément est coché « Consulté ».

Checklist de QA manuelle :
- [ ] Onglet « Cours » après Accueil ; un seul groupe → ouverture directe de sa racine.
- [ ] Plusieurs groupes → cartes avec « n/m consultés » ; aucun groupe → état vide avec la bonne terminologie.
- [ ] Fil d'Ariane cliquable ; dossiers puis éléments triés par `position` ; Retour système remonte d'un niveau.
- [ ] Dossier masqué par le formateur : disparaît après synchronisation (et son contenu avec lui) ; réaffiché : revient, sans écran vide (re-bootstrap).
- [ ] Puce de chemin présente sur Articles, Entraînement, Examens et en tête de révision ; un appui ouvre le dossier sans ouvrir la carte ; libellé tronqué à 24 caractères.
- [ ] PDF : rendu `PdfRenderer`, filigrane nominatif sur chaque page, capture d'écran impossible (`FLAG_SECURE`), aucun partage / impression / ouverture externe.
- [ ] Word / PowerPoint converti : lisible comme un PDF ; 202 → « Document en préparation… » ; 409 → « Document indisponible ».
- [ ] Image, audio, vidéo : lecteurs protégés existants ; lien YouTube / Vimeo → lecteur de marque ; autre lien → « Ouvrir le lien ».
- [ ] « Télécharger » visible seulement avec `allow_download` ; un 403 de `download` affiche « Téléchargement non autorisé pour cette ressource. ».
- [ ] « Disponible hors ligne » : mode avion → lecture OK ; le fichier `.enc` est dans `filesDir` privé, illisible hors de l'app ; absent des sauvegardes.
- [ ] Ressource retirée / masquée côté serveur : après sync, copie hors ligne supprimée ; un 403 sur `view` supprime aussi la copie.
- [ ] Déconnexion : fichiers hors ligne, tables `folders` / `resources` / `placements` / `consulted_resources` vidés.
- [ ] Une ressource n'est cochée « Consulté » qu'après affichage effectif (pas sur 202 / 409 / 403 / hors ligne sans copie) ; l'action `resource_view` part et est idempotente.
- [ ] URL signée expirée (> 15 min d'ouverture) : le lecteur redemande `view` sans erreur visible.
- [ ] Mode « Voir en tant que » : lecture seule, boutons « Disponible hors ligne » et « Télécharger » masqués.
- [ ] Aucun libellé « groupe » en dur : changer la terminologie côté admin la répercute après sync.
