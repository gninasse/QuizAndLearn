# Pilotes vidéo configurables + vidéos YouTube / Vimeo de marque — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Choisir par configuration où sont stockées et comment sont diffusées les vidéos téléversées (`local`, `s3_hls`, `bunny`), et lire des vidéos YouTube / Vimeo dans un lecteur maîtrisé qui empêche d'en partager le lien ou d'en récupérer le contenu.

**Architecture:** Pattern Strategy + Manager : interface `VideoDriver`, `VideoManager` qui résout le pilote depuis `config('media.video.driver')`, table `videos` qui associe une référence opaque (UUID) à un pilote et à son emplacement. Les éditeurs insèrent `<video data-video-ref="…">` ou `<div data-embed="youtube|vimeo" data-id="…">` ; la PWA demande la source de lecture à l'API (URL signée courte pour les pilotes externes) et remplace les embeds par un composant `brand-video` à contrôles propres, couvert d'un calque bloquant portant le filigrane.

**Tech Stack:** Laravel 12 (Modules/Core), `league/flysystem-aws-s3-v3` (S3 compatible), ffmpeg via `Symfony\Component\Process`, client HTTP Laravel (Bunny Stream), PWA TypeScript, `hls.js` (MIT, nouvelle dépendance npm), API IFrame YouTube et SDK Player Vimeo chargés à la demande.

**Spec:** `docs/superpowers/specs/2026-10-18-pilotes-video-design.md`

## Global Constraints

- Pilote par défaut `local` : **aucun changement de comportement** sans configuration ; les contenus existants (`<video src="/storage/…">`) continuent de fonctionner tels quels.
- Variables : `MEDIA_VIDEO_DRIVER` (`local` | `s3_hls` | `bunny`) ; S3 : `VIDEO_S3_DISK` (défaut `videos`, disque déclaré dans `config/filesystems.php` à partir de `VIDEO_S3_KEY`, `VIDEO_S3_SECRET`, `VIDEO_S3_REGION`, `VIDEO_S3_BUCKET`, `VIDEO_S3_ENDPOINT`, `VIDEO_S3_PATH_STYLE`) et `FFMPEG_BINARY` (défaut `ffmpeg`) ; Bunny : `BUNNY_STREAM_LIBRARY_ID`, `BUNNY_STREAM_API_KEY`, `BUNNY_STREAM_CDN_HOST`, `BUNNY_STREAM_TOKEN_KEY`.
- URL de lecture signées pour les pilotes externes : durée **15 minutes** ; la PWA ne reçoit jamais l'URL brute du stockage ni la clé.
- Le contenu ne stocke **jamais** l'URL complète d'une vidéo externe : seulement `data-embed` (`youtube` | `vimeo`) et `data-id`.
- YouTube : `youtube-nocookie.com`, `controls=0`, `rel=0`, `modestbranding=1`, `disablekb=1`, `fs=0`, `playsinline=1`, `iv_load_policy=3`. Vimeo : `title=0&byline=0&portrait=0&controls=0&dnt=1`.
- Vidéos YouTube / Vimeo **en ligne uniquement** : jamais mises en cache par le service worker ; hors ligne, message « Vidéo disponible en ligne uniquement. »
- Règle existante « visible mais non récupérable » : `controlsList="nodownload …"`, pas de PiP ni Cast, filigrane nominatif (voir `resources/js/learner/ui/media-guard.ts`).
- Les secrets ne s'affichent jamais dans le back-office (seulement « configuré / manquant »).
- Mode invité : une vidéo référencée n'est lisible que si sa référence figure dans un contenu vitrine (même règle que `ShowcaseMedia` pour `/storage`). Mode consultation : la route de lecture est ajoutée à la liste blanche lecture seule.
- Textes FR accentués. Ne jamais lancer `php artisan storage:link`. Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Ancien contenu d'article avec `<iframe src="https://www.youtube.com/embed/ID…">` (inséré par l'ancien bouton) → converti en lecteur de marque dans la PWA, l'iframe brute n'est jamais affichée (test Task 5).
2. URL signée expirée pendant la lecture (pause de 20 min) → le lecteur redemande une source et reprend à la même position, sans message d'erreur (test Task 5).
3. Référence inconnue, vidéo encore en conversion ou en erreur → placeholder lisible (« Vidéo en cours de préparation » / « Vidéo indisponible »), pas d'élément cassé (tests Tasks 1 et 5).
4. URL YouTube collée sous ses formes courantes (`watch?v=`, `youtu.be/`, `shorts/`, `embed/`, avec `&t=` ou `?si=`) et Vimeo (`vimeo.com/123`, `vimeo.com/123/abcdef` non répertoriée avec hash, `player.vimeo.com/video/123?h=…`) → identifiant correct ; autre domaine → refus clair (test Task 4).
5. Changement de pilote en cours de vie (`local` → `bunny`) → les vidéos déjà enregistrées restent lues par leur pilote d'origine (colonne `driver`), les nouvelles vont au nouveau (test Task 1).

---

### Task 1: Contrat, gestionnaire, pilote `local`, API de lecture

**Files:**
- Create: `Modules/Core/app/Services/Video/VideoDriver.php`, `VideoManager.php`, `PlaybackSource.php`, `Drivers/LocalVideoDriver.php`, `Modules/Core/app/Models/Video.php`, migration `Modules/Core/database/migrations/2026_10_22_100000_create_videos_table.php`, `Modules/Core/app/Http/Controllers/Learner/Api/VideoPlaybackController.php`
- Modify: `config/media.php` (clé `video` : `driver`, `signed_ttl` = 900, sous-tableaux `s3_hls` et `bunny`), `Modules/Core/app/Http/Controllers/CoreController.php` (`uploadMedia`, branche vidéo → `VideoManager::store()`, la réponse ajoute `ref` et `status`), `Modules/Core/routes/web.php` (`GET /api/learner/v1/videos/{ref}/playback`, nom `learn.v1.videos.playback`, dans le groupe `['impersonation','auth','learner']` ; variante invité : même contrôleur accessible avec le jeton invité comme `MediaController`), la liste blanche lecture seule de `ResolveImpersonation`, `Modules/Core/app/Services/ShowcaseMedia.php` (références `data-video-ref` des contenus vitrine)
- Test: `tests/Feature/VideoDriverLocalTest.php`

**Interfaces:**
- Table `videos(id, ref uuid unique, driver string(20), location string (chemin, clé S3 ou GUID Bunny), status string(20) 'ready'|'processing'|'failed', mime string null, size bigint null, original_name string null, duration int null, uploaded_by FK users null, timestamps)`.
- `interface VideoDriver { public function name(): string; public function store(UploadedFile $file): array /* {location, status} */; public function playback(Video $video, User $viewer): PlaybackSource; public function delete(Video $video): void; public function refreshStatus(Video $video): string; public function healthCheck(): array /* {ok: bool, message: string} */; }`
- `final class PlaybackSource { __construct(public string $type /* 'mp4'|'hls' */, public string $url, public ?Carbon $expiresAt) }` ; `toArray(): array{type, url, expires_at: ?string}`.
- `VideoManager::driver(?string $name = null): VideoDriver` (défaut = config ; nom inconnu → `InvalidArgumentException`) ; `VideoManager::store(UploadedFile, User): Video` ; `VideoManager::playback(Video, User): PlaybackSource` (utilise `$video->driver`, pas la config courante).
- `LocalVideoDriver` : `store` → disque `public`, `uploads/media` (comme aujourd'hui), status `ready` ; `playback` → `PlaybackSource('mp4', '/storage/'.$location, null)`.
- API : `200 {type, url, expires_at}` ; `202 {status: 'processing'}` ; `410 {status: 'failed'}` ; `404` référence inconnue ; invité hors vitrine → 403.

- [ ] **Step 1: Tests** : `test_upload_video_creates_video_row_and_returns_ref` (la réponse garde `url` pour compatibilité), `test_playback_local_returns_storage_url`, `test_playback_statuses_202_410_404`, `test_existing_video_keeps_its_driver_after_config_change` (Review Focus 5, avec un pilote factice enregistré dans le manager), `test_guest_playback_only_for_showcase_refs`, `test_impersonation_can_read_playback`, `test_unknown_driver_throws`.
- [ ] **Step 2:** Run `php artisan test --filter=VideoDriverLocalTest` → FAIL.
- [ ] **Step 3:** Implémenter. Le manager accepte `extend(string $name, Closure $factory)` (comme les gestionnaires Laravel), utilisé par les tests et pour de futurs pilotes.
- [ ] **Step 4:** Run → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(video): driver contract, manager, local driver and playback API`.

### Task 2: Pilote `s3_hls` (stockage S3 compatible + conversion HLS)

**Files:**
- Modify: `composer.json` (`league/flysystem-aws-s3-v3` ^3), `config/filesystems.php` (disque `videos`), `.env.example`
- Create: `Modules/Core/app/Services/Video/Drivers/S3HlsVideoDriver.php`, `Modules/Core/app/Services/Video/Transcoder.php` (interface), `FfmpegTranscoder.php`, `Modules/Core/app/Jobs/TranscodeVideoToHls.php`, contrôleur ou méthode `VideoPlaybackController::hlsPlaylist` (route `GET /api/learner/v1/videos/{ref}/hls/{file}` nom `learn.v1.videos.hls`, `file` = `[A-Za-z0-9_-]+\.m3u8`)
- Test: `tests/Feature/VideoDriverS3HlsTest.php`

**Interfaces:**
- `interface Transcoder { /** @return string[] chemins locaux produits (playlist + segments) */ public function toHls(string $inputPath, string $outputDir): array; }` ; `FfmpegTranscoder` : `-c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 128k -hls_time 6 -hls_playlist_type vod -hls_segment_filename seg_%04d.ts index.m3u8`, timeout 3600 s.
- `store` : original envoyé sur le disque `videos` sous `originals/{ref}.{ext}`, status `processing`, `TranscodeVideoToHls::dispatch($video)`. Le job télécharge l'original dans un dossier temporaire, convertit, envoie `hls/{ref}/…`, passe `ready` (ou `failed` + log), supprime le temporaire.
- `playback` : `PlaybackSource('hls', route('learn.v1.videos.hls', [ref, 'index.m3u8']) avec signature Laravel temporaire 15 min, expiresAt)`. Le contrôleur de playlist relit la playlist sur le disque et **réécrit chaque ligne de segment** en `Storage::disk('videos')->temporaryUrl("hls/{ref}/{seg}", now()+15 min)` ; la PWA ne voit donc que des URL signées et éphémères.
- `delete` supprime `originals/{ref}.*` et `hls/{ref}/`. `healthCheck` écrit puis supprime un petit fichier `healthcheck/{uuid}.txt`.

- [ ] **Step 1: Tests** (`Storage::fake('videos')` + `buildTemporaryUrlsUsing`, faux `Transcoder` lié dans le conteneur, `Queue::fake()` puis exécution manuelle du job) : `test_store_uploads_original_and_queues_transcode`, `test_job_uploads_hls_and_marks_ready`, `test_job_failure_marks_failed`, `test_playlist_rewrites_segments_to_signed_urls`, `test_playlist_route_rejects_bad_signature_and_bad_filename`, `test_health_check`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(video): S3-compatible HLS driver with ffmpeg transcoding`.

### Task 3: Pilote `bunny` (Bunny Stream)

**Files:**
- Create: `Modules/Core/app/Services/Video/Drivers/BunnyVideoDriver.php`
- Modify: `.env.example`, `DEPLOIEMENT.md` (section « Vidéos » : les trois pilotes, variables, worker de file, ffmpeg, CORS du bucket pour HLS, restriction de domaine Bunny)
- Test: `tests/Feature/VideoDriverBunnyTest.php`

**Interfaces:**
- API Bunny Stream (`https://video.bunnycdn.com`, en-tête `AccessKey`) : `POST /library/{libraryId}/videos` `{title}` → `guid` ; `PUT /library/{libraryId}/videos/{guid}` avec le binaire ; `GET …/videos/{guid}` → `status` (`4` = terminé, `5`/`6` = erreur, autres = en cours) ; `DELETE …/videos/{guid}`. `healthCheck` : `GET /library/{libraryId}/videos?page=1&itemsPerPage=1`.
- `playback` : URL `https://{cdn_host}/{guid}/playlist.m3u8` signée par l'authentification par jeton du CDN Bunny (`token` = SHA-256 de `token_key + chemin + expiration`, encodé base64url sans remplissage, paramètres `token` et `expires`, signature de répertoire `token_path=/{guid}/` pour couvrir les segments) — vérifier la formule exacte dans la documentation Bunny « Token Authentication » et la citer en commentaire.
- `refreshStatus` est appelé par l'API de lecture quand `status = processing` (au plus une fois par minute et par vidéo, via `Cache::add`).

- [ ] **Step 1: Tests** (`Http::fake`) : `test_store_creates_then_uploads`, `test_status_mapping`, `test_playback_url_is_signed_and_expires_in_15_minutes` (formule recalculée dans le test), `test_api_error_marks_failed_without_leaking_key` (le message ne contient pas la clé), `test_health_check`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** Run → PASS ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(video): Bunny Stream driver`.

### Task 4: Éditeurs et réglages back-office

**Files:**
- Modify: `public/js/admin/utils.js` (bouton vidéo : insérer `<video data-video-ref="{ref}" controls playsinline preload="metadata" class="my-2 w-100"></video>` ; nouveau bouton « Vidéo YouTube / Vimeo »), `public/js/admin/article-editor.js` (`video-placeholder` → même parseur, insère le `div` d'embed), les barres d'outils Blade des éditeurs quiz / examen / flashcard / article qui listent les boutons de média, `Modules/Core/app/Http/Controllers/GeneralSettingsController.php`, `Modules/Core/resources/views/settings/general.blade.php`, `Modules/Core/routes/web.php` (`POST /cores/parametres/videos/test` nom `cores.settings.video.test`)
- Create: `public/js/admin/video-embed.js` (parseur partagé, chargé par les gabarits qui chargent `utils.js` et `article-editor.js`), `Modules/Core/app/Services/Video/VideoSettings.php`
- Test: `tests/Feature/VideoSettingsTest.php`, `tests/js/video-embed.test.ts` (vitest, importe le fichier JS via `vm`/`happy-dom` ou en faisant de `video-embed.js` un module UMD minuscule exposant `window.parseVideoEmbed`)

**Interfaces:**
- `window.parseVideoEmbed(url: string): {provider: 'youtube'|'vimeo', id: string, hash?: string} | null` ; HTML inséré : `<div class="brand-video" data-embed="{provider}" data-id="{id}"[ data-hash="{hash}"] contenteditable="false"><span>Vidéo {YouTube|Vimeo}</span></div><p><br></p>`.
- `VideoSettings` (AppSetting `video.embeds.enabled` défaut `true`, `video.embeds.providers` défaut `['youtube','vimeo']`) : `embedsEnabled(): bool`, `providers(): array`, `save(array)`, `toArray()` ; exposé à la PWA dans `/me` ou `bootstrap` sous `video.embeds`.
- Section « Vidéos » : pilote actif, état de chaque variable requise (« configurée » / « manquante »), bouton « Tester la connexion » (`VideoManager::driver()->healthCheck()`), interrupteurs YouTube / Vimeo. Lecture seule pour le pilote (il vient de `.env`).

- [ ] **Step 1: Tests** : parseur (Review Focus 4 — toutes les formes listées, plus `https://evil.com/watch?v=abc` → `null`) ; `test_admin_saves_embed_settings`, `test_trainer_forbidden`, `test_health_check_endpoint_returns_driver_result`, `test_settings_page_never_shows_secrets` (la valeur de `BUNNY_STREAM_API_KEY` mise en config ne figure pas dans le HTML).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter. Si les embeds sont désactivés, le bouton est masqué dans les éditeurs.
- [ ] **Step 4:** Run `php artisan test` et `npx vitest run` → PASS.
- [ ] **Step 5:** Commit `feat(video): editor insertion by reference, brand embeds and settings`.

### Task 5: PWA — lecture par référence et lecteur de marque

**Files:**
- Modify: `package.json` (`hls.js` ^1.5), `resources/js/learner/ui/media-guard.ts` (appeler les deux résolveurs sur chaque `.rich-content` monté), `resources/js/learner/sw.ts` (ne jamais mettre en cache `youtube`, `vimeo`, `.m3u8`, `.ts`, ni l'API `videos/*/playback`), `resources/js/learner/api/` (client `videoPlayback(ref)`), `PROMPT_APP_MOBILE_KOTLIN.md` (WebView aux mêmes paramètres, `FLAG_SECURE`, endpoint de lecture), `SPECIFICATIONS_VOLET_APPRENANT.md` (section vidéos)
- Create: `resources/js/learner/ui/video-ref.ts`, `resources/js/learner/ui/brand-video.ts`, `resources/js/learner/ui/video-ref.test.ts`, `resources/js/learner/ui/brand-video.test.ts`

**Interfaces:**
- `video-ref.ts` : `resolveVideoRefs(scope: ParentNode): void` — pour chaque `video[data-video-ref]` sans `src` : appel API ; `mp4` → `src` ; `hls` → lecture native si `canPlayType('application/vnd.apple.mpegurl')`, sinon `hls.js` importé dynamiquement ; 202 → placeholder « Vidéo en cours de préparation » et nouvel essai après 30 s (3 fois max) ; 404/410 → « Vidéo indisponible ». Renouvellement : sur erreur réseau/403 du média ou 60 s avant `expires_at`, redemander une source et reprendre à `currentTime` (Review Focus 2). Le durcissement existant (`hardenPlayer`) s'applique toujours.
- `brand-video.ts` : `mountBrandVideos(scope: ParentNode, watermark: string | null): void` — traite `[data-embed]` **et** les anciennes `iframe[src*="youtube.com/embed/"], iframe[src*="youtube-nocookie.com/embed/"], iframe[src*="player.vimeo.com/video/"]` (extraire l'id, retirer l'iframe d'origine — Review Focus 1). Construit : conteneur 16:9, lecteur (API IFrame YouTube via `https://www.youtube.com/iframe_api` avec `host: 'https://www.youtube-nocookie.com'` ; Vimeo via `https://player.vimeo.com/api/player.js`) aux paramètres des Global Constraints, calque transparent plein cadre (bloque clics et menu contextuel, porte `--wm-tile`), barre de contrôles maison : lecture/pause, barre de progression cliquable, temps écoulé/total, muet + volume, plein écran **du conteneur** (`requestFullscreen` sur notre div). `aria-label` en français sur chaque bouton. Hors ligne ou embeds désactivés / fournisseur non autorisé → placeholder « Vidéo disponible en ligne uniquement. » / « Vidéo indisponible ». Script du fournisseur chargé une seule fois.
- Mode invité et consultation : fonctionnement identique (lecture seule).

- [ ] **Step 1: Tests vitest** (happy-dom, `fetch`/API simulés, fournisseurs simulés via faux `window.YT` / `window.Vimeo`) : `resolves_mp4_ref`, `shows_processing_then_retries`, `shows_unavailable_on_404`, `renews_expired_source_and_keeps_position`, `legacy_youtube_iframe_replaced_by_brand_player`, `brand_player_has_overlay_and_no_provider_links` (aucun `<a>` vers youtube/vimeo, iframe créée avec `controls=0` et l'hôte nocookie), `offline_shows_online_only_message`, `vimeo_hash_passed_as_h_param`.
- [ ] **Step 2:** `npx vitest run resources/js/learner/ui` → FAIL.
- [ ] **Step 3:** Implémenter.
- [ ] **Step 4:** `npx vitest run` → PASS ; `npm run typecheck` → propre ; `npm run build` → OK ; `php artisan test` → PASS.
- [ ] **Step 5:** Commit `feat(pwa): video playback by reference and branded YouTube/Vimeo player`.
