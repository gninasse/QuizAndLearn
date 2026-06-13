# Keystone Boilerplate 🚀

Un template de démarrage moderne basé sur **Laravel 12** structuré en multi-modules, avec support natif de **PostgreSQL**, gestion avancée des permissions (Spatie), journalisation des activités et commandes interactives.

Ce dépôt est conçu pour vous permettre de lancer un nouveau projet en quelques minutes seulement.

---

## 📋 Prérequis

Avant de commencer, assurez-vous d'avoir installé les éléments suivants :
* **PHP 8.3+** (extensions requises: `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `xml`, `zip`)
* **PostgreSQL 15+**
* **Node.js 18+** & **npm**
* **Composer**

---

## ⚙️ Installation Rapide

Suivez ces étapes pour configurer le projet localement.

### 1. Cloner le Projet
Clonez ce dépôt et placez-vous dans son dossier racine :
```bash
git clone <url-du-depot>
cd Keystone
```

### 2. Configurer l'Environnement
Copiez le fichier de configuration exemple [.env.example](file:///home/ibrahim/projets/web/Keystone/.env.example) pour créer votre fichier `.env` :
```bash
cp .env.example .env
```
Ouvrez le fichier `.env` et ajustez les variables liées à votre base de données :
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=votre_base_de_donnees
DB_USERNAME=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe
```

> [!NOTE]
> L'URL de l'application est configurée par défaut sur `https://keystone.local`. Si vous utilisez un nom de domaine ou un port local différent, mettez à jour la clé `APP_URL`.

---

### 3. Configurer et Initialiser la Base de Données 🗄️

Vous avez deux méthodes pour initialiser le schéma et les données initiales de la base de données.

#### Option A : Importer le script SQL (Recommandé pour un démarrage immédiat)
Un script de base de données complet et portable contenant la structure et les configurations est disponible dans [database/sql/init.sql](file:///home/ibrahim/projets/web/Keystone/database/sql/init.sql).

Créez votre base de données dans PostgreSQL, puis importez le fichier :
```bash
# Se connecter à PostgreSQL et créer la base de données
createdb -U votre_utilisateur -h 127.0.0.1 -p 5432 votre_base_de_donnees

# Importer le script SQL d'initialisation
psql -U votre_utilisateur -h 127.0.0.1 -p 5432 -d votre_base_de_donnees -f database/sql/init.sql
```

#### Option B : Exécuter les Migrations et Seeders de Laravel
Si vous préférez générer la structure directement via le code :
```bash
php artisan migrate --seed
```

---

### 4. Installer les Dépendances

#### Dépendances PHP
Installez les packages requis via Composer :
```bash
composer install
```

#### Dépendances Frontend & Compilation
Installez les packages Node.js et compilez les assets CSS/JS pour le développement ou la production :
```bash
# Installer les dépendances npm
npm install

# Compiler les assets pour la production
npm run build

# OU lancer le serveur de développement à chaud
npm run dev
```

---

### 5. Générer la Clé de Sécurité de l'Application
Générez la clé unique de chiffrement de Laravel :
```bash
php artisan key:generate
```

---

### 6. Synchroniser les Modules et Permissions
Initialisez le statut des modules et synchronisez leurs permissions respectives dans la base de données :
```bash
php artisan cores:sync
```

---

### 7. Configurer les Permissions d'Accès aux Fichiers
Assurez-vous que le serveur web a les permissions d'écriture sur les dossiers de cache et de stockage :
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 👤 Créer le Premier Utilisateur

Pour commencer le développement, vous devez créer un compte utilisateur de type administrateur. Le projet intègre des commandes interactives pour vous guider :

### Étape 1 : Créer l'utilisateur interactivement
Lancez la commande interactive [CreateUserCommand.php](file:///home/ibrahim/projets/web/Keystone/Modules/Core/app/Console/Commands/CreateUserCommand.php) pour saisir les informations de l'utilisateur et lui attribuer un rôle initial s'il existe :
```bash
php artisan cores:create-user
```

### Étape 2 : Promouvoir en Super Admin (Optionnel)
Pour attribuer toutes les permissions et l'accès global à tous les modules, attribuez le rôle de Super Admin via l'email de l'utilisateur :
```bash
php artisan cores:make-superadmin votre.email@exemple.com
```

---

## 🌐 Configuration du Virtual Host

Pour faire tourner le projet sous l'adresse locale `https://keystone.local`, configurez votre serveur web.

### 🌐 Ajouter la Résolution DNS Locale
Éditez votre fichier d'hôtes système (ex: `/etc/hosts` sous Linux/macOS ou `C:\Windows\System32\drivers\etc\hosts` sous Windows) et ajoutez :
```text
127.0.0.1    keystone.local
```

### Nginx (Recommandé)
Créez un fichier de configuration pour le site sous `/etc/nginx/sites-available/keystone.local` :
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name keystone.local;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name keystone.local;
    root /home/ibrahim/projets/web/Keystone/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # Certificats SSL (remplacez par vos chemins vers vos certificats auto-signés locaux)
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Activez la configuration et redémarrez Nginx :
```bash
sudo ln -s /etc/nginx/sites-available/keystone.local /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Apache
Créez une configuration d'hôte virtuel Apache sous `/etc/apache2/sites-available/keystone.local.conf` :
```xml
<VirtualHost *:80>
    ServerName keystone.local
    ServerAdmin webmaster@localhost
    DocumentRoot /home/ibrahim/projets/web/Keystone/public

    <Directory /home/ibrahim/projets/web/Keystone/public>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/keystone_error.log
    CustomLog ${APACHE_LOG_DIR}/keystone_access.log combined
</VirtualHost>
```
Activez le site et le module de réécriture, puis redémarrez Apache :
```bash
sudo a2ensite keystone.local.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## 🛠️ Commandes Utiles de la Console

Le module **Core** met à votre disposition plusieurs commandes artisan sous le namespace `cores:` :

| Commande | Rôle |
| --- | --- |
| `php artisan cores:sync` | Synchronise tous les modules du système de fichiers et leurs permissions associées. |
| `php artisan cores:create-user` | Crée interactivement (via Laravel Prompts) un nouvel utilisateur. |
| `php artisan cores:make-superadmin {email}` | Promut ou crée un utilisateur comme super-admin. |
| `php artisan cores:reset-user-password {user_name}` | Réinitialise le mot de passe d'un utilisateur par son nom d'utilisateur. |
| `php artisan cores:stats` | Affiche l'état d'activité, les versions et statistiques de chaque module. |
| `php artisan cores:user-permissions {user}` | Liste l'ensemble des rôles et permissions attribués à un utilisateur spécifique. |
| `php artisan cores:cleanup-permissions` | Nettoie les permissions orphelines de la base de données. |

---

Développement agréable et productif avec **Keystone Boilerplate** ! 💻⚙️
