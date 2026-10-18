# Notifications push — Conception

> Date : 2026-10-18 · Statut : à valider · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Faire revenir l'apprenant dans l'app au bon moment : nouveau contenu, cartes à réviser, examen qui ouvre, série en danger. Décision client : **Web Push standard, sans Firebase**.

## 2. Objectifs
- L'apprenant active les notifications depuis son profil (préférence « Notifications » existante, aujourd'hui sans effet) et reçoit des notifications système, même app fermée, sur la PWA installée (Android, desktop ; iOS 16.4+ quand l'app est ajoutée à l'écran d'accueil).
- L'admin choisit quels types de notifications sont envoyés, et à quelle heure partent les rappels quotidiens.
- Toucher une notification ouvre directement le bon écran.

## 3. Non-objectifs
- Notifications de l'app native Kotlin (elles passeront par FCM côté natif, dans un chantier séparé).
- Campagnes marketing libres envoyées par l'admin à tout le monde (possible plus tard).
- E-mail ou SMS de rappel.

## 4. Anti-objectifs
- Spam : plus de 2 notifications par jour et par apprenant, ou une notification la nuit.
- Une notification qui révèle un contenu d'examen ou une donnée personnelle sur l'écran verrouillé.
- Une dépendance à un service tiers payant ou propriétaire.

## 5. Types de notifications (v1)
| Type | Déclencheur | Texte (exemple) | Ouvre |
|---|---|---|---|
| Nouveau contenu | quiz, article ou deck publié et assigné à un groupe de l'apprenant | « Nouveau quiz disponible : {titre} » | l'écran du contenu |
| Cartes à réviser | rappel quotidien à l'heure réglée, s'il y a au moins 1 carte due | « 12 cartes vous attendent pour réviser » | `/reviser` |
| Série en danger | 20 h, si l'apprenant a une série ≥ 2 et aucune activité aujourd'hui | « Votre série de 5 jours se termine ce soir 🔥 » | `/` |
| Examen ouvert | à l'ouverture (`available_from`) d'un examen de ses groupes | « L'examen {titre} est ouvert » | `/examens` |

Les rappels sont regroupés : au plus 2 notifications par jour et par apprenant (priorité : examen > nouveau contenu > cartes > série). Plage silencieuse : rien entre 21 h et 7 h, sauf l'examen qui ouvre.

## 6. Conception technique
- **Clés VAPID** générées une fois (`php artisan push:vapid`), stockées dans `.env` (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT=mailto:…`).
- **Bibliothèque** : `minishlink/web-push` (PHP, MIT), pour le chiffrement et l'envoi aux services push des navigateurs.
- **Abonnements** : table `push_subscriptions` (`learner_id`, `endpoint` unique, `p256dh`, `auth`, `user_agent`, `last_used_at`). API apprenant : `POST /api/learner/v1/push-subscriptions` et `DELETE …`. Un abonnement expiré (réponse 404/410 du service push) est supprimé automatiquement.
- **Envoi** : job en file (`QUEUE_CONNECTION=database` existe déjà). Commande planifiée `push:daily-reminders` toutes les 15 min, qui envoie aux apprenants dont l'heure locale correspond (fuseau de l'app). Les événements « nouveau contenu » et « examen ouvert » partent par un écouteur sur la publication et l'assignation, et par une commande planifiée pour `available_from`.
- **Journal** : table `push_deliveries` (apprenant, type, date) pour le plafond quotidien et les KPIs.
- **PWA** : le service worker gère `push` (affiche la notification avec l'icône de la marque) et `notificationclick` (ouvre ou focalise l'onglet sur l'URL). Le profil propose le bouton « Activer les notifications » ; la permission n'est demandée **qu'au clic**, jamais au chargement. Un guide s'affiche sur iOS si l'app n'est pas installée.
- **Back-office** : section « Notifications » dans les Paramètres généraux : un interrupteur par type, l'heure du rappel quotidien (défaut 18 h), le plafond quotidien (défaut 2), et le bouton « Envoyer une notification de test » (vers le navigateur de l'admin, s'il est abonné).
- **Confidentialité** : textes génériques sur l'écran verrouillé (titre du contenu oui, contenu ou réponses jamais).

## 7. Décisions prises
**Par le client :** Web Push standard, sans Firebase.
**Par Claude (à vérifier) :** les 4 types ci-dessus ; plafond de 2 par jour ; plage silencieuse 21 h–7 h ; rappel à 18 h par défaut ; demande de permission au clic seulement ; la bibliothèque `minishlink/web-push`.

## 8. Laissé au builder
Textes exacts, structure des jobs, gestion des fuseaux par apprenant (v1 : fuseau de l'app).
