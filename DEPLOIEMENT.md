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

Si des files d'attente sont utilisées (`QUEUE_CONNECTION=database`), lancer un
worker supervisé (systemd ou supervisor) : `php artisan queue:work --tries=3`.

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
   `VAPID_SUBJECT=mailto:contact@votre-domaine` (et au besoin `PUSH_TIMEZONE`, par défaut le
   fuseau de l'application), puis `php artisan config:cache`. Ne jamais régénérer les clés en
   production : tous les abonnements existants deviendraient invalides.
3. Le planificateur (`schedule:run`, voir §7) doit tourner chaque minute.
4. Un worker de file doit tourner en continu : `php artisan queue:work --tries=3` (supervisé).
5. Migrer : `php artisan migrate` (tables `push_subscriptions`, `push_deliveries`,
   `push_assignment_snapshots`).

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
- [ ] Consultation en lecture seule : en super admin, « Voir en tant que » sur un apprenant ouvre la PWA avec le bandeau et le filigrane, toute action d'écriture est refusée, et la consultation est journalisée.

## 11. Application mobile native

L'application Android/iOS consomme `/api/mobile/v1` (jetons Bearer). Les
médias (`/storage/...`) exigent désormais une authentification : l'application
doit envoyer l'en-tête `Authorization: Bearer <jeton>` aussi lors du
téléchargement des images, audios et vidéos.

Pour le mode découverte, l'application native appelle `/api/showcase/v1` sans
jeton Bearer et charge les médias vitrine avec l'en-tête `X-Guest-Token`.
La connexion accepte e-mail, téléphone ou identifiant (voir `PROMPT_APP_MOBILE_KOTLIN.md`).
