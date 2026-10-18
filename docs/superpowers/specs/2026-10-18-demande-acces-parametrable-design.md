# Demande d'accès paramétrable — Conception

> Date : 2026-10-18 · Statut : validé le 2026-10-18 · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Aujourd'hui, le bouton « Demander un accès » du mode découverte ouvre toujours le même formulaire, avec des champs et des textes fixes. Le client veut que l'administration règle elle-même ce que fait ce bouton : par quel canal l'invité la contacte, quels champs il remplit, quels textes il lit, et si le bouton est visible.

## 2. Qui règle

Super admin **et** admins (décision client), dans la page existante **Mode découverte**, nouvelle section « Demande d'accès ». Les formateurs n'y ont pas accès.

## 3. Ce qui se règle

### 3.1 Activer ou masquer
- Interrupteur « Accepter les demandes d'accès » (défaut : activé).
- Désactivé : le bouton disparaît partout (bandeau invité, appels à l'action, profil invité), la page `/demande-acces` affiche « Les demandes d'accès sont fermées pour le moment. », l'API refuse l'envoi (403, code `access_requests_closed`). La vitrine elle-même reste ouverte.

### 3.2 Canaux de contact (un ou plusieurs, dans l'ordre choisi)
| Canal | Réglages | Effet pour l'invité |
|---|---|---|
| Formulaire (défaut) | champs (3.3) | la demande arrive dans « Demandes d'accès » comme aujourd'hui (suivi, paiement Mobile Money, création du compte) |
| WhatsApp | numéro, message pré-rempli | ouvre WhatsApp vers ce numéro avec le message |
| Appel | numéro | lance l'appel |
| E-mail | adresse, objet | ouvre la messagerie |
| Lien externe | URL `https`, libellé | ouvre le lien (ex. Google Form) dans un nouvel onglet |

- Un seul canal actif : le bouton y mène directement.
- Plusieurs : la page `/demande-acces` présente un choix (cartes avec icône), le formulaire en premier s'il est actif.
- Au moins un canal doit rester actif quand les demandes sont acceptées (validation à l'enregistrement).
- Seul le formulaire alimente le suivi interne ; les autres canaux sortent de l'application (dit clairement dans l'écran de réglage).

### 3.3 Champs du formulaire
- **Toujours présents et obligatoires** : prénom, nom, téléphone (le circuit actuel en dépend : doublons, compte, WhatsApp, Mobile Money) et le consentement (obligation légale).
- **Réglables** (masqué / facultatif / obligatoire) : WhatsApp, e-mail, organisation, choix du groupe (libellé via `term()`), message.
- **Champs personnalisés** : jusqu'à 5, chacun avec libellé, type (texte court, texte long, liste de choix), choix possibles pour une liste, obligatoire ou non. Réponses stockées avec la demande (nouvelle colonne JSON `extra_fields`) et affichées dans la fiche de la demande au back-office et dans l'export.
- Un champ personnalisé supprimé plus tard reste lisible sur les anciennes demandes (on enregistre le libellé avec la réponse).

### 3.4 Textes
- Libellé du bouton (défaut « Demander un accès »), titre de la page, texte d'introduction, message de confirmation après envoi.
- Champs vides → textes actuels.

## 4. Technique (résumé)
- Réglages dans `app_settings` via `ShowcaseSettings` (clés `showcase.access.*`), exposés dans `publicPayload()` sous `access_request` (jamais les e-mails internes).
- Validation serveur de l'envoi construite à partir des réglages (un champ masqué envoyé est ignoré ; un champ obligatoire vide → 422).
- PWA : bandeau, appels à l'action et page `/demande-acces` lisent ces réglages ; liens `https://wa.me/…`, `tel:`, `mailto:` construits côté client à partir des réglages publics.
- App Kotlin : même bloc dans `/api/showcase/v1/settings` ; consigne ajoutée à `PROMPT_APP_MOBILE_KOTLIN.md`.
- Migration : colonne `extra_fields` (JSON, nullable) sur `access_requests`.

## 5. Décisions
**Par le client :** canaux, champs, textes et activation réglables ; réglage ouvert au super admin et aux admins.
**Par Claude (à vérifier) :** prénom, nom, téléphone et consentement non désactivables ; 5 champs personnalisés au plus ; seul le formulaire alimente le suivi interne ; réglages dans la page Mode découverte.

## 6. Non-objectifs
Constructeur de formulaire libre (glisser-déposer, logique conditionnelle) ; paiement en ligne intégré ; plusieurs formulaires différents selon le groupe.
