# Pilotes vidéo configurables (+ vidéos YouTube / Vimeo de marque) — Conception

> Date : 2026-10-18 · Statut : à valider · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Directive client : gérer les vidéos volumineuses avec une architecture « paramétrable par un système de proxy ou un design pattern », et intégrer des vidéos YouTube et équivalentes traitées comme des **vidéos de marque** : on ne doit pas pouvoir partager leur lien ni récupérer leur contenu.

## 2. Objectifs
- Choisir, **par configuration**, où et comment les vidéos uploadées sont stockées et diffusées, sans toucher au code métier ni aux contenus existants.
- Ajouter dans les éditeurs (quiz, examens, flashcards, articles) l'insertion d'une vidéo **YouTube ou Vimeo**, lue dans un lecteur maîtrisé : sans bouton de partage, sans lien vers la plateforme, avec le filigrane nominatif.
- Conserver la règle « visible mais non récupérable » déjà en place pour les médias.

## 3. Architecture : pattern Strategy + Manager (comme les disques Laravel)
- Interface `VideoDriver` :
  - `store(UploadedFile): VideoRef` (enregistre la vidéo et renvoie une référence opaque) ;
  - `playback(VideoRef, User): PlaybackSource` (URL de lecture signée et temporaire, type `mp4` ou `hls`) ;
  - `delete(VideoRef)` ;
  - `status(VideoRef)` (prête, en cours de traitement, en erreur).
- `VideoManager` choisit le pilote selon `config/media.php` → `video.driver` (`MEDIA_VIDEO_DRIVER`).
- **Pilotes livrés :**
  - `local` *(défaut, comportement actuel)* : fichier sur le disque `public`, lu en streaming par la route protégée `/storage` ;
  - `s3_hls` : stockage compatible S3 (MinIO, Cloudflare R2, Backblaze B2, AWS), conversion HLS par un job ffmpeg en file, URLs signées et expirantes. Il permet de gros fichiers, l'upload découpé et le hors-ligne à la demande ;
  - `bunny` : Bunny Stream, service clé en main et économique : API d'upload, lecture HLS signée par jeton, restriction au domaine.
- D'autres pilotes (Cloudflare Stream, Mux) pourront s'ajouter sans changer le reste.
- **Contenus :** l'éditeur insère `<video data-video-ref="…">` au lieu d'une URL en dur. La PWA demande la source de lecture à l'API (`GET /api/learner/v1/videos/{ref}/playback`), avec les droits vérifiés. Les vidéos déjà insérées (`<video src="/storage/…">`) continuent de marcher avec le pilote `local`.
- **Proxy :** pour les pilotes externes, la PWA ne voit jamais l'URL brute du stockage. Elle reçoit une URL signée de courte durée (15 min), renouvelée par le lecteur.

## 4. Vidéos YouTube / Vimeo « de marque »
- Dans l'éditeur, le bouton « Vidéo YouTube / Vimeo » demande l'URL, extrait le **fournisseur et l'identifiant**, puis insère `<div data-embed="youtube" data-id="…">`. **L'URL complète n'est jamais stockée dans le contenu.**
- Dans la PWA, un composant `brand-video` construit le lecteur :
  - YouTube : API IFrame officielle sur `youtube-nocookie.com`, avec `controls=0`, `rel=0`, `modestbranding`, `disablekb=1`, `fs=0`. **Nos propres contrôles** (lecture, pause, barre de progression, volume, plein écran de notre conteneur) remplacent ceux de YouTube ;
  - un calque transparent couvre le lecteur : il bloque les clics vers YouTube (titre, logo, « Regarder sur YouTube », menu contextuel) et porte le filigrane nominatif ;
  - Vimeo : lecteur Vimeo avec `title=0&byline=0&portrait=0&controls=0&dnt=1`, mêmes contrôles et même calque.
- **Limites, dites franchement :**
  - YouTube : une personne déterminée peut retrouver l'identifiant avec les outils du navigateur. Il faut donc **publier ces vidéos en « non répertoriée »**.
  - Vimeo (offre payante) apporte une vraie protection : la **restriction au domaine** empêche la lecture partout ailleurs que sur la plateforme. C'est l'option recommandée pour un contenu à forte valeur.
  - Ces vidéos sont **en ligne uniquement** : les conditions de YouTube et Vimeo interdisent la mise en cache.
- Côté app native Kotlin : même principe, avec un WebView aux mêmes paramètres, plus `FLAG_SECURE` (consigne ajoutée au prompt).

## 5. Back-office
Section « Vidéos » dans les Paramètres généraux :
- le pilote actif et ses réglages (lus depuis `.env`, affichés en lecture seule par sécurité) ;
- un test de connexion au stockage ;
- l'activation des vidéos YouTube et Vimeo, avec la liste des fournisseurs autorisés.

## 6. Décisions prises
**Par le client :** architecture paramétrable par pattern ; vidéos YouTube et équivalentes de marque, non partageables.
**Par Claude (à vérifier) :**
- pattern Strategy + Manager ;
- pilotes livrés `local` (défaut), `s3_hls` et `bunny` ;
- référence opaque `data-video-ref` et source de lecture servie par l'API ;
- YouTube et Vimeo uniquement en v1 ;
- nos propres contrôles avec calque bloquant ;
- en ligne uniquement pour les vidéos externes.

## 7. Non-objectifs
Édition ou montage vidéo ; sous-titres automatiques ; diffusion en direct.
