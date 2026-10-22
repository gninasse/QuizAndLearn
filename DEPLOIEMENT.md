# Procédure de déploiement — Learn&Quiz

> Guide pas à pas pour installer la plateforme sur un serveur de production
> (VPS Linux) et la mettre à jour. Public visé : administrateur système ou
> intégrateur chargé de déployer la solution pour une organisation.

---

## 1. Ce qui est déployé

| Brique | Rôle | Techno |
|---|---|---|
| Back-office | Administration, création de contenus, suivi des groupes | PHP 8.2+ (rendu serveur) |
| Volet apprenant | Application web installable (PWA), fonctionne hors-ligne | SPA TypeScript, buildée par Vite |
| API mobile | Application native (jetons Bearer) | `/api/mobile/v1` |
| Base de données | Comptes, contenus, résultats | PostgreSQL 15+ |
| Fichiers | Médias pédagogiques (images, audio, vidéo) | `storage/app/public` (privé, servi après authentification) |

## 2. Prérequis serveur

- **OS** : Ubuntu 22.04/24.04 LTS (ou équivalent).
- **PHP 8.2+** avec les extensions `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `zip`, `openssl`, `fileinfo`, `gd`, `bcmath`, `intl`, et PHP-FPM.
- **PostgreSQL 15+**.
- **Node.js 20+ et npm** (uniquement pour builder les assets, possible sur la CI).
- **Composer 2**.
- **Nginx** (recommandé) ou Apache 2.4.
- **HTTPS obligatoire** : le service worker, l'installation de la PWA et les cookies de session sécurisés ne fonctionnent qu'en HTTPS (Let's Encrypt / certbot).
- **Disque** : prévoir la volumétrie des vidéos (100 Mo max par vidéo par défaut).
- **LibreOffice** (sans interface) pour convertir en PDF les documents Word, PowerPoint
  et OpenDocument des ressources : voir §5 ter.

## 3. Installation initiale

```bash
# 1. Code
sudo mkdir -p /var/www/learnquiz && sudo chown $USER:www-data /var/www/learnquiz
git clone <url-du-depot> /var/www/learnquiz
cd /var/www/learnquiz

# 2. Dépendances
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # génère public/build (assets + service worker)

# 3. Configuration
cp .env.example .env
php artisan key:generate
# → éditer .env (voir §4)

# 4. Base de données
sudo -u postgres createuser -P learnquiz
sudo -u postgres createdb -O learnquiz learnquiz
php artisan migrate --force

# 4 bis. Données de référence (rôles, permissions, badges)
php artisan cores:init-roles
php artisan cores:sync                  # modules + permissions
php artisan db:seed --class="Modules\Core\Database\Seeders\BadgeSeeder" --force

# 5. Droits
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

# 6. Caches de production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ **Ne jamais lancer `php artisan storage:link`.** Les médias pédagogiques
> sont volontairement servis par la route authentifiée `/storage/{chemin}`
> (comptes connectés, actifs et non suspendus uniquement). Un lien symbolique
> `public/storage` les rendrait téléchargeables par n'importe qui.
> Si un ancien déploiement en a créé un : `rm public/storage`.

> ⚠️ **Ne lancez pas `php artisan db:seed` sans `--class` en production** : le
> seeder par défaut crée des formateurs, apprenants et contenus de démonstration
> dont le mot de passe est `password`.

**Compte super-administrateur** (une seule fois) :

```bash
php artisan cores:make-superadmin admin@mon-organisation.fr   # crée ou promeut le compte
```

Connectez-vous ensuite au back-office sur `/login`, **changez ce mot de passe**,
puis créez les formateurs, les apprenants et les groupes.

## 4. Variables d'environnement essentielles (`.env`)

```dotenv
APP_NAME="Learn&Quiz"
APP_ENV=production
APP_DEBUG=false                  # OBLIGATOIRE : true afficherait des traces techniques
APP_URL=https://formation.mon-organisation.fr
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

# Marque blanche (back-office, pages d'erreur, e-mails)
BRAND_NAME="Learn&Quiz"
BRAND_OWNER="Mon Organisation"
BRAND_LOGO=/icons/icon-192x192.png
BRAND_TAGLINE="Apprendre, s'entraîner, réussir."

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=learnquiz
DB_USERNAME=learnquiz
DB_PASSWORD=********

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true       # cookies uniquement en HTTPS
SESSION_COOKIE=learnquiz-session
CACHE_STORE=database
QUEUE_CONNECTION=database

# Médias pédagogiques : taille max à l'upload (Mo)
MEDIA_IMAGE_MAX_MB=5
MEDIA_AUDIO_MAX_MB=10
MEDIA_VIDEO_MAX_MB=100

MAIL_MAILER=smtp
MAIL_HOST=smtp.mon-fournisseur.fr
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@mon-organisation.fr
MAIL_FROM_NAME="${BRAND_NAME}"
```

Après toute modification du `.env` : `php artisan config:cache`.

### Personnaliser la marque

- **Nom / mentions** : variables `BRAND_*` ci-dessus.
- **Logo du back-office et favicon** : remplacer `public/icons/icon-192x192.png`
  (ou pointer `BRAND_LOGO` vers un autre fichier de `public/`).
- **Application apprenant (PWA)** : nom, couleurs et icônes dans
  `public/manifest.json` et `public/icons/*`, puis `npm run build`.

## 5. Limites d'upload (vidéos)

Les trois couches doivent accepter la taille vidéo choisie (`MEDIA_VIDEO_MAX_MB`) :

```ini
; /etc/php/8.2/fpm/conf.d/99-learnquiz.ini
upload_max_filesize = 110M
post_max_size = 120M
memory_limit = 256M
max_execution_time = 300
expose_php = Off
```

```nginx
client_max_body_size 120M;
```

Puis `sudo systemctl reload php8.2-fpm nginx`.

## 5 bis. Vidéos (pilotes d'hébergement)

Le pilote se choisit par `MEDIA_VIDEO_DRIVER` (`local` par défaut). Chaque vidéo
mémorise son pilote d'origine : changer de pilote n'affecte que les nouveaux
envois, les vidéos existantes restent lues par leur pilote.

| Pilote | Stockage | Lecture |
|---|---|---|
| `local` | disque `public` (`/storage/…`) | fichier MP4 direct, aucun service externe |
| `s3_hls` | bucket S3 compatible (AWS, Scaleway, MinIO…) | HLS généré par ffmpeg, segments en URL temporaires (15 min) |
| `bunny` | Bunny Stream | HLS du CDN Bunny, URL signées par jeton (15 min) |

### Pilote `s3_hls`

```dotenv
MEDIA_VIDEO_DRIVER=s3_hls
VIDEO_S3_DISK=videos
VIDEO_S3_KEY=…
VIDEO_S3_SECRET=…
VIDEO_S3_REGION=…
VIDEO_S3_BUCKET=…
VIDEO_S3_ENDPOINT=https://…      # vide pour AWS
VIDEO_S3_PATH_STYLE=false        # true pour MinIO
FFMPEG_BINARY=ffmpeg
VIDEO_QUEUE=videos               # file dédiée aux conversions HLS
DB_QUEUE_RETRY_AFTER=4200        # > durée max du job (4000 s) ; REDIS_QUEUE_RETRY_AFTER si redis
```

- **ffmpeg** doit être installé sur le serveur qui exécute le worker :
  `sudo apt install ffmpeg` puis `ffmpeg -version` (ou indiquer le chemin dans `FFMPEG_BINARY`).
  Le bouton « Tester la connexion » du back-office vérifie le bucket **et** `ffmpeg -version`.
- **Worker dédié obligatoire** : la conversion HLS est un job (`QUEUE_CONNECTION=database`
  ou `redis`, jamais `sync` en production) placé sur la file **`videos`** (`VIDEO_QUEUE`),
  afin qu'une conversion longue ne retarde jamais les notifications push (file `default`).
  Délais : ffmpeg s'arrête au bout de 3600 s, le job au bout de 4000 s ; le worker doit
  utiliser `--timeout=4000` et **`DB_QUEUE_RETRY_AFTER=4200`** (ou `REDIS_QUEUE_RETRY_AFTER`),
  sinon le job serait relancé pendant qu'il tourne encore. Voir §7 :
  `php artisan queue:work --queue=videos --tries=1 --timeout=4000`.
  Ce `retry_after` s'applique à toute la connexion : le worker `default` garde `--timeout=90`.
- Un job interrompu (délai dépassé, worker tué) marque la vidéo « échouée » et supprime
  les fichiers temporaires et le HLS partiel ; renvoyer la vidéo depuis l'éditeur.
- Accès S3 : délais généreux pour l'envoi et la conversion (disque `videos` : connexion 10 s,
  requête 600 s) ; le test de connexion utilise le disque `videos_health` (5 s / 15 s).
  Avec un disque personnalisé (`VIDEO_S3_DISK`), définir `VIDEO_S3_HEALTH_DISK` si besoin.
- Tant que la conversion n'est pas terminée, la vidéo affiche « Vidéo en cours de préparation ».
- **CORS du bucket** : le lecteur charge les segments directement depuis le bucket.
  Autoriser `GET` (et `HEAD`) depuis l'origine de l'application :

```json
[{"AllowedOrigins": ["https://learn.example.com"],
  "AllowedMethods": ["GET", "HEAD"],
  "AllowedHeaders": ["*"],
  "MaxAgeSeconds": 3000}]
```

Le bucket doit rester **privé** (pas de lecture publique) : seules les URL temporaires y donnent accès.

### Pilote `bunny`

```dotenv
MEDIA_VIDEO_DRIVER=bunny
BUNNY_STREAM_LIBRARY_ID=…   # identifiant de la bibliothèque Stream
BUNNY_STREAM_API_KEY=…      # clé d'API de la bibliothèque (jamais exposée)
BUNNY_STREAM_CDN_HOST=vz-xxxx.b-cdn.net   # nom d'hôte CDN de la bibliothèque
BUNNY_STREAM_TOKEN_KEY=…    # « URL Token Authentication Key » de la Pull Zone
```

Dans la console Bunny, sur la bibliothèque Stream :

1. **Security → Token Authentication** : activer (méthode avancée, SHA-256) et copier la clé dans `BUNNY_STREAM_TOKEN_KEY`.
   L'application signe un jeton de **répertoire** (`token_path=/{guid}/`) qui couvre la playlist et tous les segments.
2. **Security → Allowed Domains** (restriction de domaine / referrer) : n'autoriser que le domaine de l'application,
   afin que les liens ne soient pas utilisables depuis un autre site. Laisser **« Block empty referrer »
   désactivé** si l'application mobile native lit les vidéos : ExoPlayer n'envoie pas de `Referer`
   et serait refusé (la signature par jeton protège déjà les URL).
3. Désactiver le téléchargement MP4 d'origine (**Encoding → MP4 Fallback**) pour ne servir que le HLS.

La conversion est faite par Bunny : aucun ffmpeg ni worker n'est nécessaire. Le statut des vidéos en
cours de traitement est interrogé à la lecture (au plus une fois par minute et par vidéo).

Les clés ne s'affichent jamais dans le back-office (seulement « configuré / manquant »).

## 5 ter. Ressources et conversion des documents (LibreOffice)

Les ressources des dossiers (PDF, images, audio, documents) sont stockées sur le disque
**privé** `local` (`storage/app/private/resources/{id}/`), jamais sous `public/`. L'apprenant
ne reçoit qu'une URL signée valable **15 min** ; le téléchargement n'est possible que si la
ressource l'autorise. Taille maximale : 50 Mo par fichier (`media.documents.max_size_kb`),
à garder cohérente avec `upload_max_filesize` / `post_max_size` et `client_max_body_size` (§5).

Les documents Word, PowerPoint et OpenDocument (`doc`, `docx`, `odt`, `ppt`, `pptx`, `odp`)
sont convertis en PDF par LibreOffice :

```bash
sudo apt install --no-install-recommends libreoffice-core libreoffice-writer libreoffice-impress
soffice --version
```

```dotenv
SOFFICE_BINARY=soffice           # ou chemin absolu, ex. /usr/bin/soffice
DOCUMENT_QUEUE=documents         # file dédiée aux conversions
```

- **Worker dédié** sur la file **`documents`** (voir §7) :
  `php artisan queue:work --queue=documents --tries=2 --timeout=300`.
  LibreOffice s'arrête au bout de 270 s, le job au bout de 300 s, deux essais au plus.
  **`retry_after` doit dépasser 300 s sur la connexion de file utilisée par ce worker**
  (`DB_QUEUE_RETRY_AFTER` pour `database`, `REDIS_QUEUE_RETRY_AFTER` pour `redis` ; la
  valeur par défaut, 90 s, ne suffit pas) : sinon une conversion encore en cours serait
  relancée par un second worker. Exemple : `DB_QUEUE_RETRY_AFTER=360` (ou `4200` si le
  pilote vidéo `s3_hls` partage la connexion, voir §5 bis).
- Le worker doit pouvoir écrire dans `storage/app/tmp` (profil LibreOffice et fichiers
  temporaires propres à chaque conversion, supprimés ensuite).
- **LibreOffice absent** (ou `SOFFICE_BINARY` erroné) : le document passe en « échec » avec le
  message « LibreOffice n'est pas installé sur le serveur. », l'apprenant voit « Document
  indisponible ». Après installation, relancer la conversion depuis le back-office (« Réessayer »).
- Sans worker `documents`, les documents restent « en préparation » (l'apprenant reçoit 202).
- **Durcissement (SSRF)** : LibreOffice ouvre des fichiers fournis par des utilisateurs. Exécuter
  le worker `documents` avec un utilisateur système dédié, sans droits d'écriture hors de
  `storage/`, et **sans accès réseau sortant** (pare-feu, `PrivateNetwork=yes` sous systemd ou
  réseau Docker isolé). Un document ne doit jamais pouvoir charger une ressource distante ni
  atteindre le réseau interne.
- **Migrations** : cette fonctionnalité ajoute des tables et colonnes (dossiers,
  ressources ; colonnes de placement sur les pivots groupe-contenu).
  `php artisan migrate --force` (voir §8) ; les contenus existants apparaissent à la racine de chaque groupe, sans migration de données, et le réglage
  `content.sharing_mode` reste `shared` par défaut. Redémarrer ensuite les workers
  (`php artisan queue:restart`).

## 6. Serveur web

### Nginx (recommandé)

```nginx
server {
    listen 80;
    server_name formation.mon-organisation.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name formation.mon-organisation.fr;
    root /var/www/learnquiz/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/formation.mon-organisation.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/formation.mon-organisation.fr/privkey.pem;

    server_tokens off;               # n'affiche pas la version du serveur
    client_max_body_size 120M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Assets buildés (noms hachés) : cache long.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Scripts et styles du back-office (noms non hachés) : revalidation à chaque
    # chargement, sinon les navigateurs gardent une version périmée après une mise à jour.
    location ~ ^/(js|css)/ {
        add_header Cache-Control "no-cache";
        try_files $uri =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

Le service worker est servi par l'application elle-même à `/sw.js` : aucune
règle spécifique n'est nécessaire. Ne mettez **pas** de cache long sur `/sw.js`
ni sur `/`.

### Apache 2.4

`DocumentRoot` → `/var/www/learnquiz/public`, `AllowOverride All` (le
`.htaccess` de Laravel gère la réécriture), modules `rewrite`, `headers` et
`ssl` activés. Ajouter dans la configuration globale :

```apache
ServerTokens Prod
ServerSignature Off
LimitRequestBody 125829120
```

## 7. Tâches de fond

```bash
# Crontab de l'utilisateur web (crontab -e -u www-data)
* * * * * cd /var/www/learnquiz && php artisan schedule:run >> /dev/null 2>&1
```

Purge périodique du journal d'activité (selon la politique de rétention) :

```bash
php artisan activities:cleanup-expired
```

Si des files d'attente sont utilisées (`QUEUE_CONNECTION=database`), lancer des
workers supervisés (systemd ou supervisor) — **les mêmes commandes partout** :

```bash
# File par défaut (notifications push, tâches courtes)
php artisan queue:work --queue=default --tries=3 --timeout=90
# File vidéo (pilote s3_hls uniquement) : conversions HLS longues, un seul essai
php artisan queue:work --queue=videos --tries=1 --timeout=4000
# File documents : conversions PDF par LibreOffice (§5 ter)
php artisan queue:work --queue=documents --tries=2 --timeout=300
```

`retry_after` vaut pour toute une connexion de file : il doit dépasser le `--timeout` le
plus long des workers de cette connexion. Avec le worker `documents`, au moins `360`
(timeout 300 s, voir §5 ter) ; avec le pilote `s3_hls`, au moins `4200`, voir §5 bis
(`DB_QUEUE_RETRY_AFTER` ou `REDIS_QUEUE_RETRY_AFTER`). Sans worker `videos`,
les vidéos `s3_hls` restent indéfiniment « en cours de préparation ».

Le planificateur lance aussi chaque jour `php artisan showcase:purge-requests`
(suppression des demandes d'accès `rejected` / `abandoned` plus anciennes que
`showcase.retention_months`, 12 mois par défaut) : aucune entrée cron
supplémentaire n'est nécessaire, la ligne `schedule:run` ci-dessus suffit.

## 7 ter. Notifications push

Désactivées tant que les clés VAPID sont absentes (aucun envoi, bouton masqué dans la PWA).

1. **HTTPS obligatoire** : les navigateurs refusent le Web Push hors contexte sécurisé.
2. Générer les clés (rien n'est écrit sur le disque) :
   ```bash
   php artisan push:vapid
   ```
   Copier les deux lignes `VAPID_PUBLIC_KEY=` et `VAPID_PRIVATE_KEY=` dans le `.env`, ajouter
   `VAPID_SUBJECT=mailto:contact@votre-domaine` (obligatoire : sans sujet, rien n'est envoyé)
   et **`PUSH_TIMEZONE` explicitement** (ex. `PUSH_TIMEZONE=Africa/Abidjan`) : le fuseau de
   l'application vaut `UTC`, et c'est ce fuseau qui fixe la plage silencieuse (21 h–7 h) et
   l'heure des rappels. Puis `php artisan config:cache`. Ne jamais régénérer les clés en
   production : tous les abonnements existants deviendraient invalides.
3. Le planificateur (`schedule:run`, voir §7) doit tourner chaque minute.
4. Un worker de file doit tourner en continu sur la file par défaut :
   `php artisan queue:work --queue=default --tries=3 --timeout=90` (supervisé, voir §7).
   Les conversions vidéo ont leur propre worker (`--queue=videos`) : elles ne retardent pas les push.
5. Migrer : `php artisan migrate` (tables `push_subscriptions`, `push_deliveries`,
   `push_assignment_snapshots`).
6. Seuls les services push des navigateurs sont acceptés à l'abonnement (liste
   `allowed_hosts` de `config/push.php` : Google/FCM, Mozilla, Apple, Windows). Un
   apprenant garde au plus 10 navigateurs abonnés (les moins récemment utilisés sont retirés).
   Une notification restée plus d'une heure en file, ou qui arriverait de nuit (sauf
   examen), est abandonnée.

**Recette manuelle (après déploiement, sur un vrai téléphone et un navigateur de bureau) :**

- [ ] Profil apprenant → « Recevoir les notifications » : la permission n'est demandée qu'au clic ;
      l'interrupteur passe à « activé ».
- [ ] Back-office, Réglages généraux → « Envoyer une notification de test » : la notification
      « Notification de test » arrive sur l'appareil (application fermée comprise).
- [ ] Clic sur la notification, PWA déjà ouverte : l'onglet apprenant passe au premier plan et
      affiche la page visée ; un onglet back-office ouvert dans le même navigateur n'est **pas** détourné.
- [ ] Clic sur la notification, PWA fermée : une fenêtre s'ouvre sur la page visée.
- [ ] Charge utile invalide (DevTools → Application → Service workers → « Push » avec un texte
      quelconque) : une notification générique « Learn&Quiz — Nouvelle notification » s'affiche,
      le clic ouvre l'accueil.
- [ ] Déconnexion puis connexion d'un autre apprenant sur le même navigateur : les notifications
      suivantes vont au nouvel apprenant uniquement.
- [ ] Désactivation dans le profil : plus aucune notification (nouvel envoi de test depuis ce
      compte → « Aucun abonnement »).

## 7 bis. Mode découverte

Désactivé par défaut. Pour l'activer, en tant que `super-admin` ou `admin` :

1. Back-office, page des réglages du mode découverte : activer le mode, renseigner
   la marque, le code pays par défaut (`225`), les frais d'inscription, les comptes
   Mobile Money, la référence et les instructions de paiement, le texte de
   confidentialité et les **e-mails de notification**.
2. Cocher « Visible en mode découverte » sur les quiz, articles et decks à montrer, et
   « Proposé dans le formulaire de demande d'accès » sur les groupes concernés.
3. Configuration mail : les notifications de nouvelle demande utilisent le mailer du
   `.env` (voir §4, `MAIL_*`) ; sans e-mail de notification renseigné, rien n'est
   envoyé. Les demandes restent visibles dans « Demandes d'accès ».
4. Vérifier : `https://…/api/showcase/v1/content` répond `200` (ou `404` si désactivé),
   `https://…/confidentialite` est publique, et un média vitrine se charge avec le cookie `lq_guest`
   alors qu'un média non coché répond `403`.

Aucun lien symbolique `public/storage` ne doit exister : les médias passent par la route `/storage/{path}`.

## 8. Mise à jour d'une version

```bash
cd /var/www/learnquiz
php artisan down --render="errors::503"     # page de maintenance à la marque
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Les apprenants reçoivent la nouvelle version de la PWA automatiquement au
chargement suivant (message « Application mise à jour ✨ »).

### Passage à la version « mode découverte / connexion par téléphone »

**Avant** le `php artisan migrate --force` sur une installation existante :

1. **Sauvegarder la base** (`pg_dump -Fc learnquiz > avant-telephone-$(date +%F).dump`, voir §9).
2. **Contrôler les téléphones** des utilisateurs : la migration
   `2026_10_19_100000_phone_login_on_users` les normalise au format E.164
   (indicatif par défaut 225). Un numéro illisible ou en doublon après normalisation
   est **remis à NULL** ; ce retrait n'est tracé que dans `storage/logs/laravel.log`
   (« Téléphone invalide… » / « Téléphone en doublon… ») et le `down()` **ne peut pas
   restaurer** les valeurs. Repérer au préalable les numéros à corriger :

   ```sql
   SELECT id, phone FROM users WHERE phone IS NOT NULL AND phone <> '' ORDER BY phone;
   ```

   Après migration, relire les avertissements du journal et ressaisir les numéros
   retirés depuis le back-office ; en cas de problème, restaurer la sauvegarde.

## 9. Sauvegardes

À planifier quotidiennement et à copier hors du serveur :

| Quoi | Commande |
|---|---|
| Base de données | `pg_dump -Fc learnquiz > learnquiz-$(date +%F).dump` |
| Médias pédagogiques | `tar czf media-$(date +%F).tgz storage/app/public` |
| Ressources (fichiers privés) | `tar czf resources-$(date +%F).tgz storage/app/private/resources` |
| Configuration | copie chiffrée du `.env` |

Restauration : `pg_restore -d learnquiz <fichier.dump>` puis extraction de
l'archive des médias dans `storage/app/public`.

## 10. Vérifications après déploiement

- [ ] `https://…/login` affiche le nom et le logo de la marque, sans mention technique.
- [ ] Une URL inexistante affiche la page 404 à la marque (et non une trace d'erreur).
- [ ] `curl -I https://…/storage/uploads/media/<fichier>` sans être connecté → **403**.
- [ ] Les réponses ne contiennent pas d'en-tête `X-Powered-By` (`curl -I https://…/login`).
- [ ] Connexion apprenant sur `/connexion`, installation de la PWA proposée, contenu visible hors-ligne après une première synchronisation.
- [ ] Upload d'une image, d'un audio et d'une vidéo depuis l'éditeur de quiz.
- [ ] `APP_DEBUG=false` dans le `.env`.
- [ ] Vidéo `s3_hls` : `php artisan queue:work --queue=videos …` tourne, une vidéo envoyée passe de « en cours de préparation » à lue.
- [ ] Ressources : `soffice --version` répond et `php artisan queue:work --queue=documents …` tourne ; un fichier `.docx` envoyé passe de « en préparation » à lisible (PDF).
- [ ] **Lecteur de marque YouTube / Vimeo sur appareils réels** (à faire par l'équipe, non couvert par les tests automatisés) :
  - [ ] Safari iOS (iPhone) et Chrome Android : toucher « Lire la vidéo » démarre la lecture ; si le navigateur exige un geste dans le lecteur, l'indication « Touchez la vidéo pour lancer la lecture » apparaît et un toucher sur la vidéo la lance.
  - [ ] Une fois la lecture lancée, l'indication disparaît et un toucher sur la vidéo met en pause (calque rétabli) : aucun lien « Regarder sur YouTube », titre, logo ni menu du fournisseur n'est atteignable.
  - [ ] Pause, progression, muet, volume et plein écran (pseudo plein écran sur iPhone) fonctionnent ; le filigrane reste visible.
  - [ ] PWA installée (mode autonome) sur iOS et Android : mêmes vérifications.
  - [ ] Hors ligne : « Vidéo disponible en ligne uniquement. », puis montage automatique au retour du réseau.
- [ ] Consultation en lecture seule : en super admin, « Voir en tant que » sur un apprenant ouvre la PWA avec le bandeau et le filigrane, toute action d'écriture est refusée, et la consultation est journalisée.

## 11. Application mobile native

L'application Android/iOS consomme `/api/mobile/v1` (jetons Bearer). Les
médias (`/storage/...`) exigent désormais une authentification : l'application
doit envoyer l'en-tête `Authorization: Bearer <jeton>` aussi lors du
téléchargement des images, audios et vidéos.

Pour le mode découverte, l'application native appelle `/api/showcase/v1` sans
jeton Bearer et charge les médias vitrine avec l'en-tête `X-Guest-Token`.
La connexion accepte e-mail, téléphone ou identifiant (voir `PROMPT_APP_MOBILE_KOTLIN.md`).
