# Tableau de bord KPIs — Conception

> Date : 2026-10-18 · Statut : validé le 2026-10-18 · Projet : QuizAndLearn (Learn&Quiz)

## 1. Intention

Donner aux formateurs et aux responsables (et aux futurs acheteurs) une vue chiffrée de l'engagement et de la réussite des apprenants, pour décider vite : relancer un apprenant, revoir une question mal conçue, montrer la valeur de la plateforme.

## 2. Publics et périmètre
- **Admin / super admin** : tous les groupes, avec un filtre par groupe.
- **Formateur** : uniquement ses groupes.
- Page « Tableau de bord » du back-office : elle remplace la page `cores.dashboard` actuelle, qui est générique.

## 3. Indicateurs (v1)
**Engagement**
- Apprenants actifs : sur 7 jours et sur 30 jours, avec la tendance par rapport à la période précédente.
- Taux d'activation : part des apprenants ayant fait au moins une activité dans les 7 jours suivant leur inscription.
- Rétention à 30 jours : part des apprenants actifs 30 jours après leur première activité.
- Régularité des révisions : part des cartes dues effectivement révisées.

**Apprentissage**
- Complétion par contenu (quiz, articles, decks), en %, par groupe.
- Score moyen des quiz et moyenne des examens sur 20.
- **Questions les plus ratées** (top 10, avec leur taux d'échec) : elles signalent un contenu à revoir.

**Suivi**
- Apprenants à relancer : aucune activité depuis 7 jours (liste cliquable vers la fiche).
- Incidents d'examen : captures et sorties de page détectées, examens annulés.
- Mode découverte : demandes d'accès reçues, converties en compte et taux de conversion (si le mode est activé).

## 4. Présentation
- Rangée de tuiles (valeur, tendance), puis graphiques : courbe des actifs sur 12 semaines, barres de complétion par contenu, tableau des questions ratées, liste « à relancer ».
- Filtres : période (7 j, 30 j, 90 j, personnalisée) et groupe.
- Export CSV de chaque tableau.
- Bibliothèque de graphiques : Chart.js (déjà courant avec AdminLTE), chargée localement.

## 5. Conception technique
- Un service `KpiService` calcule chaque indicateur par requêtes agrégées sur les tables existantes (`quiz_attempts`, `exam_attempts`, `learner_xp`, `flashcard_item_reviews`, `learner_progress`, `access_requests`, `push_deliveries` si le chantier push est fait).
- Mise en cache de 10 minutes par filtre. Pas de table d'agrégats en v1 ; on ajoutera des tables de synthèse nocturnes si les volumes l'exigent.
- Endpoint JSON `GET /cores/dashboard/kpis?period=&group=` ; la page le consomme.
- Droits : formateur limité à ses groupes, contrôlé côté serveur.

## 6. Décisions prises
**Par le client :** lancer le tableau de bord KPIs.
**Par Claude (à vérifier) :** la liste d'indicateurs ci-dessus ; il remplace le dashboard actuel ; formateurs limités à leurs groupes ; Chart.js ; cache de 10 min ; export CSV.

## 7. Non-objectifs
KPIs dans la PWA apprenant (la page « Ma progression » existe déjà) ; rapports PDF planifiés par e-mail (plus tard).
