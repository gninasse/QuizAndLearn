# Mise à jour AI Studio — Cycle de vie des groupes d'apprenants

> À coller dans la MÊME conversation AI Studio que le prompt initial de
> l'application Learn&Quiz, comme demande d'évolution.

---

Ajoute à l'application la gestion du **cycle de vie des groupes d'apprenants**.
Le backend a évolué : un groupe peut être **suspendu** (désactivé par
l'administration), **fermé** (sa date de fin est dépassée), **à venir** (sa date
de début n'est pas atteinte) ou **supprimé**. Dans tous ces cas, son contenu
(quiz, articles, decks, examens) n'est plus délivré par l'API — mais
l'historique de l'apprenant (tentatives, XP, badges) est toujours conservé.

## 1. Changement de contrat API

`GET /bootstrap` **et** `GET /changes` renvoient désormais un champ
supplémentaire `groups` — la liste des groupes de l'apprenant avec leur statut :

```json
"groups": [
  { "id": 224, "name": "Design UI/UX - Soirée", "status": "active",
    "start_date": "2026-06-01", "end_date": null }
]
```
`status` ∈ `active | upcoming | suspended | closed`. Un groupe **supprimé**
disparaît simplement de cette liste.

À faire : table Room `groups` (id, name, status, start_date, end_date),
remplacée intégralement à chaque bootstrap ET à chaque delta (comme `badges`).

## 2. Comportements à implémenter

1. **Purge automatique (déjà en place si le §2 du prompt initial est respecté)** :
   quand un groupe cesse d'être actif, les ids de son contenu sortent de
   `authorized_ids` dans le delta → supprimer ces contenus de Room. Rien
   d'autre à coder si la règle « tout id absent de authorized_ids est
   supprimé » est déjà appliquée.

2. **RÈGLE NOUVELLE ET CRITIQUE — réautorisation** : quand un groupe est
   **réactivé**, ses contenus reviennent dans `authorized_ids` **mais PAS dans
   `updated[]`** (ils n'ont pas été modifiés, leur `updated_at` est ancien).
   Après application d'un delta, si **au moins un id de `authorized_ids` est
   absent de Room** (toutes collections confondues), déclencher immédiatement
   un **re-bootstrap complet** (`GET /bootstrap`) pour récupérer les objets
   manquants. Sans cette règle, un groupe réactivé resterait vide côté app.

3. **Bannières d'information sur l'Accueil** : pour chaque groupe non-actif,
   afficher une carte informative en haut du tableau de bord :
   - `closed` (ambre, icône drapeau) : « La formation “{name}” est terminée
     depuis le {end_date} — son contenu n'est plus disponible, votre historique
     est conservé. »
   - `suspended` (ambre, icône pause) : « Le groupe “{name}” est momentanément
     suspendu — son contenu réapparaîtra à sa réactivation. »
   - `upcoming` (bleu, icône sablier) : « La formation “{name}” ouvrira le
     {start_date} — son contenu apparaîtra à ce moment-là. »

4. **Actions refusées** : une tentative de quiz (`quiz_attempt`) ou une
   révision (`card_review`) portant sur le contenu d'un groupe devenu inactif
   est renvoyée `rejected` par `POST /actions` — l'app la retire de l'outbox et
   affiche le message dans une snackbar (comportement générique des `rejected`,
   déjà spécifié). Un démarrage d'examen renvoie `404/403`.

5. **L'historique ne bouge jamais** : les tentatives locales, l'XP, les badges
   et les statistiques de « Ma progression » restent affichés même si le
   contenu source a disparu (ne pas supprimer les données d'historique lors de
   la purge d'un contenu).

## 3. Tests attendus

- Delta avec `authorized_ids` rétréci → contenus supprimés de Room, historique
  conservé.
- Delta où un id autorisé est absent de Room → un appel `GET /bootstrap` est
  déclenché et les contenus réapparaissent.
- `groups` avec un statut non-actif → la bannière correspondante s'affiche
  sur l'Accueil, avec le bon libellé.
