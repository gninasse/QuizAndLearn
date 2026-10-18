# Mode découverte (invité) — Conception

> Date : 2026-10-18 · Statut : à valider par le client · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Permettre à une personne **sans compte** d'ouvrir l'application apprenant et
de découvrir en quelques minutes ce qu'elle propose (lire un article, faire un
quiz, réviser des flashcards), afin qu'elle **demande un accès**. C'est une
**vitrine commerciale**, pas un espace d'apprentissage durable.

Contexte métier : les comptes sont créés par les admins (pas d'inscription
libre). Le parcours visé est :

1. le visiteur découvre l'app en mode invité ;
2. convaincu, il remplit un **formulaire de demande d'accès** ;
3. l'admin reçoit la demande, contacte la personne et, **souvent, lui demande
   un transfert Mobile Money pour l'inscription** ;
4. une fois le paiement reçu, l'admin crée le compte apprenant (et l'affecte à
   un groupe).

## 2. Objectifs

- Un invité accède à l'app depuis l'écran de connexion, sans compte, en un toucher.
- Il vit l'expérience réelle (même interface, mêmes types de contenus, XP,
  sons) sur une sélection de contenus choisie par l'admin.
- Il voit que d'autres fonctions existent (examens, badges, classement) sans
  pouvoir les utiliser.
- Il peut à tout moment envoyer une demande d'accès.
- L'admin traite les demandes dans le back-office, suit le paiement Mobile
  Money déclaré, puis crée le compte en quelques clics.

## 3. Non-objectifs

- Inscription autonome ou création de mot de passe par l'invité.
- Paiement intégré dans l'app (API Orange Money, MTN MoMo, Wave, Moov…) : le
  transfert se fait **hors de l'app** ; l'app ne fait que **tracer** ce que
  l'admin constate. Une intégration API pourra venir plus tard.
- Reprise de la progression de l'invité dans son futur compte.
- Notifications push pour l'invité.
- Contenus créés spécialement pour la vitrine (on réutilise l'existant).

## 4. Anti-objectifs (échec même si « ça marche »)

- Un invité accède à un **examen**, à un contenu de groupe non coché, aux
  données d'un autre apprenant ou à un média hors vitrine.
- Le mode invité permet de **récupérer** les médias (mêmes règles que les
  apprenants : visibles, non téléchargeables).
- Le formulaire devient une **porte à spam** ou à collecte de données sans
  consentement.
- L'invité **ne comprend pas** qu'il est en mode découverte ni comment obtenir
  un vrai accès.
- La moindre régression du parcours apprenant connecté (sync, outbox, quiz).

## 5. Parcours invité (PWA)

### 5.1 Entrée
- Écran `/connexion` : sous « Se connecter », un bouton secondaire
  **« Découvrir sans compte »**.
- Le mode découverte est une **session locale « invité »** : aucun compte,
  aucun cookie d'authentification. L'app charge le contenu vitrine via une API
  publique (§7).
- Quitter le mode découverte (bouton dans le bandeau ou le profil) revient à
  `/connexion` et **efface** toutes les données locales de l'invité.

### 5.2 Ce qu'il voit
- **Même shell** (sidebar / tab bar, thème, sons), titre adapté.
- **Bandeau permanent** discret : « Mode découverte — Demander un accès ».
- **Accueil vitrine** : présentation courte (nom et accroche de la marque),
  contenus vitrine mis en avant, et cartes « Disponible avec un compte » pour
  les fonctions verrouillées.
- **Articles, Entraînement (quiz + flashcards)** : uniquement les contenus
  vitrine, avec le même rendu et les mêmes interactions que pour un apprenant.
- **Examens** : l'onglet reste présent mais affiche un état verrouillé
  expliquant la fonction (examens surveillés, note sur 20, classement) + appel
  à demander un accès. Aucun examen n'est chargé.
- **Badges / classement / progression** : aperçus verrouillés.
- **Profil** : réduit à « Préférences » (thème, taille du texte, sons) et
  « Quitter le mode découverte ». Pas de mot de passe ni d'identité.

### 5.3 Progression
- XP, scores de quiz, cartes révisées : calculés **localement** (même moteur
  que l'app), pour que l'invité ressente la gamification.
- **Rien n'est envoyé au serveur** : pas d'outbox ni de sync d'actions.
- Données effacées à la sortie du mode découverte.

### 5.4 Hors-ligne
- Une fois le mode découverte ouvert en ligne, ses contenus et médias restent
  disponibles hors-ligne (même mécanisme que la PWA). Vidéos : en ligne
  uniquement, comme pour les apprenants.

### 5.5 Moments d'appel à la demande d'accès
- Bandeau permanent ;
- chaque carte verrouillée ;
- fin d'un quiz et fin d'une session de révision (« Vous avez aimé ? Demandez
  votre accès ») ;
- profil.

## 6. Formulaire de demande d'accès

### 6.1 Champs
| Champ | Obligatoire | Remarque |
|---|---|---|
| Prénom | oui | |
| Nom | oui | |
| Téléphone | **oui** | indispensable pour le contact et le Mobile Money ; format international, indicatif pays présélectionné (configurable) |
| WhatsApp identique au téléphone | case cochée par défaut | sinon champ « numéro WhatsApp » |
| E-mail | non | |
| Organisation / structure | non | |
| Formation souhaitée | non | liste des groupes **ouverts aux demandes** (§8.3), ou texte libre si aucun |
| Message | non | |
| Consentement | **oui** | « J'accepte que mes données soient utilisées pour traiter ma demande » + lien vers la politique de confidentialité |

### 6.2 Anti-abus
- Champ piège invisible (honeypot) + délai minimal de remplissage.
- Limite de fréquence par IP (ex. 5 demandes / heure) et par téléphone (une
  demande ouverte à la fois : une nouvelle demande sur un téléphone déjà en
  attente met à jour la demande existante au lieu d'en créer une autre).
- Pas de CAPTCHA (interdit par l'expérience voulue et incompatible hors-ligne).

### 6.3 Après l'envoi
- Écran de confirmation : « Demande envoyée. Nous vous contacterons au
  +225 … ».
- **Instructions d'inscription** (si l'admin les a configurées, §8.4) :
  montant, numéros Mobile Money par opérateur, mention à indiquer dans le
  transfert. Elles sont affichées à titre informatif ; l'admin confirme
  toujours par téléphone.
- Envoi **en ligne uniquement**. Hors-ligne, le formulaire reste rempli et
  l'envoi est proposé au retour du réseau (pas d'outbox silencieuse : l'invité
  doit voir que c'est parti).

## 7. API publique « vitrine »

- Préfixe dédié, **sans authentification**, lecture seule :
  `GET /api/showcase/v1/content` → articles, quiz (avec questions), decks
  (avec cartes) cochés vitrine, actifs/publiés, au **même format** que le
  bootstrap apprenant (pour réutiliser les vues PWA), sans champs propres à un
  apprenant (progression, révisions).
- `POST /api/showcase/v1/access-requests` → crée une demande (§6).
- `GET /api/showcase/v1/settings` → nom/accroche, instructions d'inscription,
  formations ouvertes aux demandes, indicatif pays par défaut.
- Réponses cachables publiquement quelques minutes ; limite de fréquence.
- **Jamais** d'examens, de données apprenants, de contenus non cochés.

### 7.1 Médias
La route protégée `/storage/{path}` (déjà en place) accepte aussi un visiteur
non connecté **si et seulement si** :
1. il présente une **session invité** : cookie signé, httpOnly, durée courte
   (ex. 24 h, renouvelé), délivré par `GET /api/showcase/v1/content` ;
2. le fichier figure dans la **liste des médias vitrine**, maintenue côté
   serveur (recalculée à l'enregistrement / au cochage / décochage d'un
   contenu), et non en parsant le HTML à chaque requête.

Les URLs restent identiques (`/storage/...`) : le cache hors-ligne du service
worker fonctionne sans changement. Une requête `curl` sans session invité
reçoit 403. Limite assumée : un fichier partagé entre un contenu vitrine et
un contenu de groupe devient lisible par les invités, ce qui est cohérent
puisque l'admin l'a choisi pour la vitrine. Le filigrane affiche « Mode
découverte » au lieu du nom de l'apprenant.

### 7.2 Application mobile native (Kotlin)
Le mode découverte est **aussi livré dans l'app native**, avec le même
parcours (§5) et le même formulaire (§6).
- Même API vitrine pour les deux clients. La session invité est délivrée
  **sous deux formes** : cookie signé pour la PWA, et **jeton invité** (même
  valeur signée, renvoyée dans la réponse) que l'app native envoie en en-tête
  `X-Guest-Token` pour charger les médias vitrine.
- L'app native applique les mêmes règles de protection que pour les apprenants
  (`FLAG_SECURE`, pas d'action Enregistrer/Partager, cache privé).
- Livrable : mise à jour de `PROMPT_APP_MOBILE_KOTLIN.md` (écrans, endpoints,
  stockage local isolé de la session apprenant, purge à la sortie du mode).

## 8. Back-office

### 8.1 Marquage vitrine
- Case **« Visible en mode découverte »** dans les éditeurs de **quiz,
  articles et decks** (pas les examens).
- **Admins uniquement** (super-admin, admin) ; un formateur voit l'état mais ne
  peut pas le modifier.
- Nouveau champ dédié `is_showcase` sur `quizzes`, `articles`,
  `flashcard_decks`. **Ne pas réutiliser `flashcard_decks.is_public`**, qui
  signifie déjà « visible par tous les apprenants connectés ».
- Badge / filtre « Vitrine » dans les listes de quiz, articles et decks.
- Un contenu vitrine reste aussi affecté à ses groupes habituels.

### 8.2 Écran « Demandes d'accès »
- Entrée de menu avec **compteur des nouvelles demandes**.
- Liste : date, nom, téléphone (lien `tel:` et lien WhatsApp), formation
  souhaitée, statut, recherche et filtres par statut.
- **Statuts** :
  `nouvelle` → `contactée` → `paiement en attente` → `payée` → `compte créé`,
  plus `refusée` / `sans suite` (fin possible à toute étape).
- Fiche demande : toutes les infos, message, historique des changements de
  statut (qui, quand), notes internes.
- **Enregistrer un paiement** (manuel) : montant, devise (défaut configurable,
  ex. XOF), opérateur (Orange Money, MTN MoMo, Moov Money, Wave, autre),
  référence de transaction, date, numéro payeur, note. Passe la demande à
  `payée`. Une demande peut avoir plusieurs paiements (versements).
- **Créer le compte apprenant** : ouvre la création d'apprenant existante
  pré-remplie (nom, prénom, téléphone, e-mail) avec choix du groupe ; à la
  création, la demande passe à `compte créé` et garde le lien vers le compte.
  Le paiement **n'est pas bloquant** (l'admin peut créer un compte sans
  paiement enregistré, ex. bourse, partenariat).
- Accès : admins ; formateurs **non** (les demandes contiennent des données
  personnelles et financières).
- Journal d'activité pour chaque action (changement de statut, paiement,
  création de compte).

### 8.3 Formations ouvertes aux demandes
- Sur un groupe, case « Proposé dans le formulaire de demande d'accès ».
- Le formulaire liste ces groupes (nom + dates) ; l'admin voit le choix sur la
  demande et le retrouve pré-sélectionné à la création du compte.

### 8.4 Réglages du mode découverte
Page de réglages (admins) :
- activer / désactiver le mode découverte (bouton masqué si désactivé) ;
- accroche affichée sur l'accueil vitrine ;
- instructions d'inscription : montant, devise, liste « opérateur → numéro →
  nom du bénéficiaire », mention à indiquer, texte libre ;
- indicatif pays par défaut du formulaire ;
- e-mail(s) de notification des nouvelles demandes ;
- **texte de la politique de confidentialité** (éditable, pré-rempli par un
  modèle) affiché sur une page publique `/confidentialite` et lié depuis la
  case de consentement ; ou, à la place, une URL externe.

### 8.5 Notifications admin
- Compteur dans le back-office (toujours).
- E-mail aux adresses configurées si l'envoi de mails est configuré.

### 8.6 Comptes créés depuis une demande : connexion par téléphone
Décision client : **un apprenant peut se connecter avec son numéro de
téléphone ou son e-mail** (l'identifiant `user_name` existant reste accepté
pour ne casser aucun compte actuel).
- **E-mail facultatif** pour les apprenants (reste obligatoire pour le staff).
- **Téléphone** stocké normalisé au format international (E.164, ex.
  `+2250700000000`), **unique** parmi les utilisateurs quand il est renseigné ;
  la saisie accepte espaces, tirets et format local (indicatif par défaut des
  réglages).
- **Connexion** (PWA `/connexion`, API mobile, login back-office) : un seul
  champ « E-mail, téléphone ou identifiant ». Détection : contient `@` → e-mail ;
  composé de chiffres/`+`/espaces → téléphone normalisé ; sinon identifiant.
  Libellés et messages mis à jour.
- **Identifiant** (`user_name`) généré automatiquement s'il n'est pas saisi
  (ex. `prenom.nom` + suffixe si doublon).
- **Mot de passe temporaire** : à la création depuis une demande, l'app génère
  un mot de passe lisible, l'affiche **une seule fois** à l'admin avec un bouton
  « Copier le message WhatsApp » (texte prêt : lien de l'app, identifiant de
  connexion, mot de passe) et un lien `wa.me`. L'apprenant doit **changer son
  mot de passe à la première connexion** (PWA et app native).
- Le formulaire de création d'apprenant existant (back-office) adopte les
  mêmes règles (e-mail facultatif, téléphone normalisé et unique).
- **Non-régression** : les connexions actuelles par e-mail et par identifiant
  continuent de fonctionner à l'identique (tests dédiés).

## 9. Données (indicatif)

- `quizzes.is_showcase`, `articles.is_showcase`, `flashcard_decks.is_showcase` (booléen, défaut faux).
- `groups.open_for_requests` (booléen).
- `access_requests` : identité, téléphone, WhatsApp, e-mail, organisation,
  groupe souhaité, message, consentement (date + texte accepté), statut,
  notes, IP/user-agent, `learner_id` une fois créé, horodatages.
- `access_request_payments` : demande, montant, devise, opérateur, référence,
  date, numéro payeur, saisi par, note.
- `access_request_events` (ou journal d'activité) : historique des statuts.
- Réglages : table clé/valeur ou équivalent existant.
- `users.email` nullable (apprenants), `users.phone` normalisé + index unique
  (valeurs nulles autorisées), `users.must_change_password` (booléen).

## 10. Confidentialité et sécurité

- Données personnelles collectées avec consentement explicite et finalité
  limitée au traitement de la demande.
- Purge ou anonymisation des demandes `refusée` / `sans suite` après un délai
  configurable (défaut proposé : 12 mois).
- Les paiements ne contiennent aucune donnée sensible au-delà de la référence
  de transaction et du numéro payeur ; aucun code PIN ou identifiant Mobile
  Money n'est jamais demandé ni stocké.
- L'API vitrine ne doit exposer que les contenus cochés : tests dédiés.

## 11. Décisions prises

**Par le client** : option « vitrine » ; inclus dans la PWA **et** l'app Kotlin ; connexion apprenant par téléphone ou e-mail ; contenus = existants cochés par
l'admin ; conversion = formulaire de demande envoyé à l'admin ; inscription
souvent conditionnée à un transfert Mobile Money.

**Par Claude (à vérifier)** : même interface que l'app ; examens/badges/
classement montrés verrouillés ; progression locale effacée à la sortie ;
filigrane « Mode découverte » ; médias vitrine accessibles via une session
invité signée + liste blanche serveur ; politique de confidentialité éditable
dans les réglages ; `user_name` conservé comme 3e mode de connexion ;
téléphone unique et normalisé E.164 ; mot de passe temporaire affiché une
fois + message WhatsApp prêt à copier ; changement de mot de passe forcé à la
première connexion ; jeton invité en en-tête pour l'app native ; hors-ligne supporté, pas de push ; téléphone
obligatoire ; paiement tracé manuellement et non bloquant ; formateurs exclus
des demandes ; anti-spam sans CAPTCHA ; champ `is_showcase` distinct de
`is_public`.

## 12. Laissé au builder

Structure exacte des tables et des réglages, découpage des composants PWA,
format du sélecteur d'indicatif, design précis des écrans (dans le style
existant), mécanisme de calcul de la liste des médias vitrine, contenu des
e-mails.
