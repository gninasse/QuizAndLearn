# « Voir en tant que » (super admin) — Conception

> Date : 2026-10-18 · Statut : à valider · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Directive client : « le super administrateur doit pouvoir naviguer dans le
volet apprenant ». Choix client : **« Voir en tant que »**, réservé au
**super admin**. Il ouvre l'espace apprenant exactement comme un apprenant
donné le voit, pour le support (« je ne vois pas mon quiz », « mon score est
faux ») et la vérification des contenus, sans jamais rien modifier.

## 2. Objectifs

- Depuis le back-office, un super admin ouvre en un clic l'espace d'un apprenant précis.
- Il voit ce que voit l'apprenant : contenus de ses groupes, progression, XP, badges, résultats, examens disponibles.
- Rien n'est jamais enregistré au nom de l'apprenant.
- Chaque session est tracée.

## 3. Non-objectifs

- Agir à la place de l'apprenant (passer un quiz « pour lui », changer son mot de passe).
- Ouvrir ce droit aux admins ou aux formateurs.
- Impersonation dans l'application mobile native.

## 4. Anti-objectifs

- Une action faite pendant la consultation modifie les données de l'apprenant (XP, progression, tentatives, préférences, mot de passe, signalements, examens).
- La session back-office du super admin est perdue ou remplacée.
- Un jeton de consultation fuit et permet un accès durable ou réutilisable.
- Un non-super-admin parvient à démarrer une consultation.
- Les données locales d'un vrai apprenant sur le même navigateur sont écrasées.

## 5. Parcours

1. **Point d'entrée :** fiche apprenant dans la page « Membres du groupe » (panneau détail) et liste des apprenants (`cores/learners`). Bouton « Voir en tant que », visible pour le super admin uniquement.
2. **Démarrage :** le clic appelle `POST /cores/learners/{learnerId}/impersonate`, qui crée une **session de consultation** et renvoie une URL d'ouverture. Le navigateur ouvre l'URL dans un **nouvel onglet**.
3. **Dans cet onglet :**
   - l'app apprenant habituelle s'affiche, avec un **bandeau permanent** en haut : « Consultation de l'espace de {nom} — lecture seule » et un bouton « Quitter » ;
   - le filigrane des médias affiche « Consultation · {nom admin} ».
4. **Lecture seule :** toute action d'écriture est neutralisée côté client (pas d'outbox, pas d'envoi) et refusée côté serveur. Les boutons qui n'ont de sens qu'en écriture affichent un message « Action indisponible en consultation » au lieu d'agir : démarrer un examen, changer de mot de passe, déconnexion classique.
5. **Fin :** « Quitter » ou expiration au bout de **30 minutes** (non renouvelable). L'onglet affiche alors « Consultation terminée » et purge ses données locales.

> **Écart enregistré :** la fermeture de l'onglet ne termine pas la session immédiatement (aucun signal fiable côté serveur). La session expire au bout de 30 minutes ; le jeton reste inutilisable sans la session web du super admin créateur, et la durée journalisée est plafonnée à l'expiration.

## 6. Conception technique

### 6.1 Jeton de consultation (indépendant de la session admin)
L'onglet de consultation partage les cookies du back-office (même origine). On n'utilise donc **pas** la session web pour l'apprenant : celle de l'admin doit rester intacte.

- Table `impersonation_sessions` : `admin_id`, `learner_id`, `token_hash` (SHA-256), `expires_at`, `ended_at`, `ip`, `user_agent`, timestamps.
- Le jeton en clair (aléatoire, 40 caractères minimum) n'est renvoyé qu'une fois, dans l'URL d'ouverture, sous forme de **fragment** (`/#consultation=<jeton>`) : le fragment n'est jamais envoyé au serveur ni journalisé. La PWA le lit, le retire de l'URL (`history.replaceState`) et le garde en `sessionStorage`, propre à l'onglet.
- La PWA envoie ensuite l'en-tête `X-Impersonation-Token` sur chaque appel à l'API apprenant.
- Usage unique par onglet : le jeton reste valable jusqu'à expiration ou fin, mais il est lié au super admin qui l'a créé. Le serveur vérifie que le cookie de session du back-office appartient toujours à ce super admin actif. Le jeton seul, sans la session de cet admin, ne donne aucun accès.

### 6.2 Résolution côté serveur
Nouveau middleware `impersonation`, placé **avant** `auth` sur les routes `api/learner/v1/*`. Si l'en-tête est présent :
- le jeton doit être valide, ni expiré ni terminé ;
- l'utilisateur web courant doit être l'admin de la session et avoir toujours le rôle `super-admin` ;
- l'apprenant de la requête devient l'utilisateur, **pour cette requête seulement** (`Auth::setUser`, sans écriture de session) ;
- la requête est marquée en lecture seule (attribut de requête `impersonation`).

Le middleware `learner` existant s'applique ensuite normalement : actif, non suspendu.

### 6.3 Lecture seule garantie par le serveur
Toute route d'écriture de l'API apprenant renvoie **403** `{code: 'read_only', message: 'Action indisponible en consultation.'}` quand la requête est en consultation : `POST /actions`, `PUT /password`, `DELETE /session`, `POST/PATCH /exams/...`.

C'est une **liste blanche de lecture** : seules `GET /me`, `GET /bootstrap`, `GET /changes` et `GET /leaderboard` passent. Toute nouvelle route est refusée par défaut.

### 6.4 Fin et journal
- `DELETE /cores/impersonations/{id}` (bouton « Quitter », appelé depuis l'onglet avec l'en-tête) pose `ended_at`.
- Journal d'activité (module core) : `impersonation_started` et `impersonation_ended`, avec l'admin, l'apprenant et la durée.
- Le back-office affiche dans la fiche apprenant la date de la dernière consultation et son auteur.

### 6.5 PWA
- Le mode consultation est détecté au démarrage : fragment `#consultation=`, ou jeton déjà présent en `sessionStorage`.
- Il utilise une **base IndexedDB séparée** (`LearnQuizDB-v2026-consultation`), vidée au démarrage et à la fin, pour ne jamais toucher aux données d'un vrai apprenant sur ce navigateur.
- `dispatch()` ne fait rien (comme en mode invité). La synchronisation se limite au bootstrap puis aux deltas.
- Le service worker et le cache des médias restent partagés : ils ne contiennent pas de données propres à l'apprenant.


### 6.6 Précisions (relecture)
- **Shell et page d'accueil :** les routes du shell SPA (`/`, `/articles`, `/profil`…) sont publiques et renvoient une vue sans données. La session staff du super admin n'y est jamais inspectée, donc l'onglet de consultation se charge sans redirection.
- **Médias :** `MediaController` accepte déjà tout utilisateur staff actif. La session du super admin suffit pour charger les images, l'audio et la vidéo de l'onglet, sans jeton dans l'URL. Il n'y a pas de filtre par groupe, comme pour les apprenants.
- **Session admin terminée :** si le super admin se déconnecte, perd son rôle ou voit sa session expirer, l'API répond 403 `{code:'impersonation_ended'}` et l'onglet affiche « Consultation terminée ».
- **Plusieurs onglets :** un même jeton peut servir à un onglet dupliqué tant que la consultation est valide. Démarrer une nouvelle consultation termine les consultations encore ouvertes **de ce super admin**.
- **Lectures avec effets techniques :** `bootstrap` peut créer les lignes par défaut de préférences et d'XP (`firstOrCreate`) et recalculer l'état de la série. Ce sont des normalisations système, pas des actions de l'apprenant : elles sont tolérées.
- **Apprenant suspendu ou au mot de passe temporaire :** en consultation, les verrous `account_suspended` et `password_change_required` sont **contournés en lecture**, car le support doit pouvoir voir l'espace. Un bandeau secondaire signale l'état (« Compte suspendu jusqu'au… » / « Mot de passe provisoire non encore changé »). Un compte désactivé (`is_active = false`) reste refusé.
- **Écrans de pratique :** le lecteur de quiz et la révision de flashcards restent utilisables **localement**, pour vérifier un contenu ; rien n'est envoyé. Les écrans qui exigent le serveur sont remplacés par « Action indisponible en consultation » : démarrage d'examen, changement de mot de passe, signalement d'erreur, notes et favoris d'article.
- **API vitrine :** joignable depuis l'onglet, comme pour tout visiteur ; sans effet sur la consultation.
- **Base locale :** `db` est un singleton de module. Le nom de base est choisi à l'import selon la présence d'un jeton de consultation (fragment ou `sessionStorage`), avant toute ouverture. Le service worker ne lit ni n'écrit IndexedDB.
- **Rôle :** le droit est le rôle Spatie `super-admin` (pas une permission). Le journal utilise `activity('core')`, comme les suspensions.

## 7. Décisions prises

**Par le client :** « Voir en tant que » en lecture seule ; super admin uniquement.

**Par Claude (à vérifier) :**
- nouvel onglet avec bandeau ;
- expiration à 30 minutes, non renouvelable ;
- jeton lié à la session web du super admin créateur (inutilisable seul) ;
- jeton passé par fragment d'URL puis `sessionStorage` ;
- lecture seule par liste blanche de routes côté serveur ;
- base locale séparée ;
- PWA uniquement (pas l'app native) ;
- dernière consultation affichée dans la fiche apprenant ; verrous suspension et mot de passe temporaire contournés en lecture (compte désactivé toujours refusé) ; quiz et flashcards jouables localement ; nouvelle consultation = fin des précédentes du même admin.

## 8. Laissé au builder

Disposition exacte du bandeau, libellés secondaires, structure du middleware et des tests, emplacement du bouton dans la liste des apprenants.
