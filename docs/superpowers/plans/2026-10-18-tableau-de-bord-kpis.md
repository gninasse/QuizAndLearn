# Tableau de bord KPIs — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer la page `cores.dashboard` par un tableau de bord d'indicateurs pédagogiques filtrable (période, groupe), avec export CSV, limité à ses groupes pour un formateur.

**Architecture:** Un `KpiScope` (valeur immuable : ensemble d'apprenants + bornes de période) est construit à partir de l'utilisateur et des filtres ; un `KpiService` calcule chaque indicateur par requêtes agrégées Eloquent/Query Builder portables (SQLite en test, PostgreSQL en dev) ; le contrôleur sert la page, un JSON `/cores/dashboard/kpis` (cache 10 min) et des CSV. La page Blade consomme le JSON avec un script jQuery et ApexCharts.

**Tech Stack:** Laravel 12 (Modules/Core), PHPUnit, Blade AdminLTE 4 / Bootstrap 5 / Bootstrap Icons, jQuery, ApexCharts (`public/plugins/apexcharts`, déjà présent).

**Spec:** `docs/superpowers/specs/2026-10-18-tableau-de-bord-kpis-design.md`

## Global Constraints

- Admin / super admin (`hasAnyRole(['super-admin','admin','Admin'])`) : tous les apprenants, filtre groupe optionnel. Formateur : uniquement les apprenants de ses groupes (`group_trainer`), contrôlé côté serveur ; un `group` hors de ses groupes → 403.
- Périodes acceptées : `7`, `30`, `90` (jours, défaut `30`) ou `custom` avec `from`/`to` (dates `Y-m-d`, `to` ≥ `from`, au plus 366 jours). Toute autre valeur → 422.
- Cache : 10 minutes par (portée utilisateur, période, groupe) — `Cache::remember`, clé incluant l'id utilisateur si formateur, `admin` sinon.
- Requêtes portables : aucune fonction de date propre à un SGBD (`DATE_TRUNC`, `strftime`…). Les regroupements par semaine se font en PHP.
- Textes en français accentué ; le mot « groupe » passe par `term()` côté Blade et `window.T` côté JS.
- Graphiques : ApexCharts déjà vendu dans `public/plugins/apexcharts` (Ruling : remplace Chart.js de la spec — aucune nouvelle dépendance, même rendu).
- Pas de nouvelle table, pas de migration.
- Ne jamais lancer `php artisan storage:link`. Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Définitions des indicateurs (valeurs exactes)

Une **activité** d'un apprenant est l'un de ces horodatages : `quiz_attempts.completed_at`, `exam_attempts.date_debut`, `flashcard_item_reviews.last_reviewed`, `learner_progress.last_accessed_at`.

| Clé JSON | Définition |
|---|---|
| `active_7d`, `active_30d` | nb d'apprenants distincts du périmètre avec ≥ 1 activité dans les 7 / 30 derniers jours (fin = `to`). `trend` = valeur de la fenêtre précédente de même longueur. |
| `activation_rate` | parmi les apprenants du périmètre créés (`learners.created_at`) dans la période, % ayant une activité dans les 7 jours suivant leur création. `null` si aucun créé. |
| `retention_30d` | parmi les apprenants dont la **première** activité est ≥ 30 jours avant `to`, % ayant une activité ≥ 30 jours après cette première activité. `null` si cohorte vide. |
| `review_regularity` | `reviewed / (reviewed + overdue)` où `reviewed` = lignes `flashcard_item_reviews` avec `last_reviewed` dans la période, `overdue` = lignes avec `next_review < to` et (`last_reviewed` null ou `< next_review`). `null` si dénominateur 0. |
| `quiz_avg_score` | moyenne de `quiz_attempts.score` (status `completed`, `completed_at` dans la période), arrondie à 1 décimale. |
| `exam_avg_note` | moyenne de `exam_attempts.note_sur_vingt` (status `termine` ou `time_up`, `date_debut` dans la période), 1 décimale. |
| `completion` | par contenu assigné aux groupes du périmètre (`group_quiz`, `group_article`, `group_flashcard_deck`) : `{type, id, title, assigned, completed, rate}` ; `assigned` = apprenants distincts du périmètre membres d'un groupe auquel le contenu est assigné ; `completed` = parmi eux, `learner_progress.status = completed` (quiz, article) ou ≥ 1 revue d'une carte du deck (deck). Trié par `rate` croissant, 20 lignes max. |
| `failed_questions` | top 10 des questions de quiz (`quiz_answers` ⋈ `quiz_attempts` dans la période et du périmètre) avec ≥ 5 réponses, triées par taux d'échec décroissant : `{question_id, quiz_title, label, answers, fail_rate}`. Les questions d'examen sont hors v1. |
| `inactive` | apprenants actifs du périmètre (`users.is_active`, non suspendus) sans activité depuis 7 jours (ou jamais) : `{learner_id, name, matricule, last_activity, group_id}` ; tri par `last_activity` (null en premier), 50 max. `group_id` = premier groupe du périmètre de l'apprenant (lien vers `cores.groups.members`). |
| `incidents` | `{screenshots, exam_captures, exam_navigation, exams_cancelled}` sur la période : nb `screenshot_attempts` (`detected_at`), somme `capture_attempts`, somme `navigation_violations`, nb `exam_attempts` status `annule`. |
| `showcase` | si `ShowcaseSettings::enabled()` et admin : `{requests, converted, rate}` (`access_requests` créées dans la période ; `converted` = status `account_created`). Sinon `null`. Non filtré par groupe. |
| `weekly_active` | 12 points `{week_start: 'Y-m-d', active}` : apprenants actifs distincts par semaine ISO (lundi), la dernière contenant `to`. |

## Review Focus

1. Formateur sans aucun groupe → tous les indicateurs à 0/null/listes vides, jamais tous les apprenants (test dans Task 1).
2. Base vide (aucune tentative, aucune revue) → JSON valide, pas de division par zéro (test dans Task 2).
3. Apprenant membre de deux groupes du périmètre → compté une seule fois dans `active_*`, `completion.assigned`, `inactive` (test dans Tasks 2 et 3).
4. Période `custom` invalide (`to` < `from`, > 366 jours, format faux) → 422 (test dans Task 4).
5. Contenu assigné supprimé (soft delete s'il existe) ou sans titre → la ligne de complétion n'explose pas (titre `—`) (test dans Task 3).

---

### Task 1: KpiScope (périmètre apprenants + période)

**Files:**
- Create: `Modules/Core/app/Services/Kpi/KpiScope.php`
- Test: `tests/Feature/KpiScopeTest.php`

**Interfaces:**
- Produces: `final class KpiScope` avec propriétés publiques readonly `Carbon $from`, `Carbon $to` (fin de journée), `?array $learnerIds` (`null` = tous les apprenants, sinon liste d'ids, éventuellement vide), `array $groupIds` (groupes du périmètre ; `[]` pour admin sans filtre = tous) , `bool $isAdmin`, `string $cacheKey`.
- `static KpiScope::for(User $user, string $period = '30', ?string $from = null, ?string $to = null, ?int $groupId = null): KpiScope` — lève `Illuminate\Validation\ValidationException` (période invalide) ou `Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException` (groupe hors périmètre formateur).
- `KpiScope::previous(): KpiScope` — même périmètre, fenêtre précédente de même longueur.
- `KpiScope::applyLearners(Builder|QueryBuilder $q, string $column): Builder|QueryBuilder` — ajoute `whereIn($column, learnerIds)` si non null.

- [ ] **Step 1: Tests** — `test_admin_without_group_sees_all_learners` (`learnerIds === null`), `test_admin_with_group_is_limited_to_group_members`, `test_trainer_is_limited_to_own_groups` (deux groupes, formateur sur un seul → ids du seul groupe), `test_trainer_without_groups_has_empty_scope` (`learnerIds === []`), `test_trainer_foreign_group_is_forbidden`, `test_period_bounds` (`'7'` → `from` = début du jour J-6, `to` = fin du jour J), `test_custom_period_validation` (`to<from`, 400 jours, `'abc'` → ValidationException), `test_cache_key_differs_per_trainer_and_filters`. Réutiliser le style `makeUser()` de `tests/Feature/GroupMemberPageTest.php`.
- [ ] **Step 2:** `php artisan test --filter=KpiScopeTest` → FAIL.
- [ ] **Step 3:** Implémenter. Un formateur = `$user->trainer` ; ses groupes via `Group::whereHas('trainers', …)`. Learner ids via table `group_learner`, `distinct`.
- [ ] **Step 4:** `php artisan test --filter=KpiScopeTest` → PASS.
- [ ] **Step 5:** Commit `feat(kpi): learner scope and period for dashboard KPIs`.

### Task 2: KpiService — engagement

**Files:**
- Create: `Modules/Core/app/Services/Kpi/KpiService.php`, `Modules/Core/app/Services/Kpi/ActivityLog.php`
- Test: `tests/Feature/KpiEngagementTest.php`

**Interfaces:**
- Consumes: `KpiScope`.
- Produces: `ActivityLog::events(KpiScope $scope, Carbon $from, Carbon $to): Collection` de lignes `{learner_id:int, at:Carbon}` (union des 4 sources, filtrée au périmètre) ; `ActivityLog::lastActivity(KpiScope $scope): array<int learner_id, ?Carbon>` ; `ActivityLog::firstActivity(KpiScope $scope): array<int, Carbon>`.
- `KpiService::engagement(KpiScope $s): array{active_7d: array{value:int, trend:int}, active_30d: array{value:int, trend:int}, activation_rate: ?float, retention_30d: ?float, review_regularity: ?float}` (taux en %, 1 décimale).
- `KpiService::weeklyActive(KpiScope $s): array<int, array{week_start:string, active:int}>` (12 entrées).

- [ ] **Step 1: Tests** (fixtures créées directement par `DB::table()->insert` ou modèles) : `test_active_counts_distinct_learners_and_trend` (un apprenant avec quiz + revue le même jour compte 1 ; activité J-10 compte dans 30d, pas 7d ; trend 7d = actifs J-13..J-7), `test_learner_in_two_groups_counted_once`, `test_activation_rate`, `test_retention_30d`, `test_review_regularity`, `test_empty_database_returns_zeros_and_nulls`, `test_weekly_active_has_12_weeks_ending_this_week`. Figer l'horloge avec `$this->travelTo('2026-10-18 12:00')`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter selon le tableau « Définitions ». `events()` : 4 requêtes `select learner_id, <col> as at` bornées par dates, `unionAll`, puis dédoublonnage en PHP si nécessaire.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(kpi): engagement indicators`.

### Task 3: KpiService — apprentissage et suivi

**Files:**
- Modify: `Modules/Core/app/Services/Kpi/KpiService.php`
- Test: `tests/Feature/KpiLearningTest.php`

**Interfaces:**
- `KpiService::learning(KpiScope $s): array{quiz_avg_score: ?float, exam_avg_note: ?float, completion: list<array>, failed_questions: list<array>}`
- `KpiService::followUp(KpiScope $s): array{inactive: list<array>, incidents: array, showcase: ?array}`
- `KpiService::all(KpiScope $s): array` = `engagement + learning + followUp + ['weekly_active' => …, 'generated_at' => ISO8601]`, mis en cache 10 min sous `'kpi:'.$s->cacheKey`.

- [ ] **Step 1: Tests** : `test_quiz_and_exam_averages`, `test_completion_rates_per_assigned_content` (quiz 2/4 = 50.0, article, deck ; apprenant dans 2 groupes compté une fois dans `assigned`), `test_completion_handles_missing_title`, `test_failed_questions_requires_5_answers_and_sorts_by_fail_rate`, `test_inactive_lists_never_active_first_and_excludes_suspended`, `test_incidents_sums`, `test_showcase_null_when_disabled_or_trainer`, `test_all_is_cached_10_minutes` (`Cache::spy()` ou deux appels avec donnée ajoutée entre les deux → même résultat).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter. Libellé de question : colonne de texte de `questions` (vérifier le nom dans sa migration), `Str::limit(strip_tags(...), 120)`. Suspension : `Learner::isSuspended()` existe ; filtrer en SQL sur les colonnes de suspension de `2026_10_18_100000_add_suspension_to_learners_table.php`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat(kpi): learning and follow-up indicators`.

### Task 4: Contrôleur, routes, export CSV

**Files:**
- Modify: `Modules/Core/app/Http/Controllers/DashboardController.php`, `Modules/Core/routes/web.php` (groupe `cores.`, à côté de `dashboard`)
- Test: `tests/Feature/KpiDashboardTest.php`

**Interfaces:**
- Routes : `GET /cores/dashboard` → `index` (vue, passe `groups` du périmètre pour le filtre) ; `GET /cores/dashboard/kpis` → `kpis` (`cores.dashboard.kpis`, JSON `KpiService::all`) ; `GET /cores/dashboard/kpis/export/{table}` → `export` (`cores.dashboard.export`), `table ∈ {completion, failed_questions, inactive}` sinon 404.
- Query string commune : `period`, `from`, `to`, `group`.
- CSV : `text/csv; charset=UTF-8`, BOM UTF-8, séparateur `;`, nom `kpi-{table}-{Y-m-d}.csv`, en-têtes français (`Type;Contenu;Assignés;Terminé;Taux (%)` / `Question;Quiz;Réponses;Taux d'échec (%)` / `Apprenant;Matricule;Dernière activité`).

- [ ] **Step 1: Tests** : `test_admin_gets_kpis_json` (structure : toutes les clés du tableau Définitions), `test_trainer_foreign_group_is_403`, `test_invalid_period_is_422`, `test_learner_cannot_access` (403/redirect, comme les autres pages staff), `test_csv_export_has_bom_semicolons_and_scope` (formateur : CSV `inactive` ne contient pas un apprenant d'un autre groupe), `test_unknown_export_table_404`, `test_dashboard_page_renders` (`assertSee('Tableau de bord')`).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter. `ValidationException` → 422 JSON naturellement ; `AccessDeniedHttpException` → 403.
- [ ] **Step 4:** Run → PASS, puis `php artisan test` complet → PASS.
- [ ] **Step 5:** Commit `feat(kpi): dashboard endpoints and CSV export`.

### Task 5: Page Blade et graphiques

**Files:**
- Rewrite: `Modules/Core/resources/views/dashboard/index.blade.php`
- Create: `public/js/modules/core/dashboard/kpis.js`
- Test: `tests/Feature/KpiDashboardTest.php` (ajouts)

**Interfaces:**
- Consumes: `cores.dashboard.kpis`, `cores.dashboard.export`, variable `$groups` (`id`, `name`).

- [ ] **Step 1: Tests** : `test_page_contains_filters_and_containers` (`assertSee` des ids `kpi-period`, `kpi-group`, `kpi-weekly-chart`, `kpi-completion-chart`, `kpi-failed-table`, `kpi-inactive-table`, et du libellé `term('Group')`), `test_trainer_filter_lists_only_own_groups`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implémenter la vue (titre « Tableau de bord », fil d'Ariane vers `cores.dashboard`) : rangée de 6 tuiles `small-box` (Actifs 7 j avec flèche de tendance, Actifs 30 j, Activation, Rétention 30 j, Score moyen quiz, Moyenne examens /20), puis courbe `weekly_active` (ApexCharts line), barres horizontales `completion` (rate), tableaux « Questions les plus ratées » et « Apprenants à relancer » (lien `cores.groups.members` du `group_id`), carte « Incidents d'examen », carte « Mode découverte » masquée si `showcase` null. Chaque tableau a un bouton « Exporter CSV » (icône `bi-filetype-csv`) qui ouvre l'URL d'export avec les filtres courants. Filtres : `select#kpi-period` (7 j / 30 j / 90 j / Personnalisée → deux `input[type=date]`), `select#kpi-group` (« Tous les … » via `term`), rechargement AJAX sans recharger la page, indicateur de chargement, message d'erreur lisible. Valeurs `null` affichées `—`. ApexCharts chargé via `@push('js')` depuis `asset('plugins/apexcharts/apexcharts.min.js')`. Échapper tout texte inséré (`$('<td>').text(...)`).
- [ ] **Step 4:** Run → PASS ; `php artisan test` complet → PASS.
- [ ] **Step 5:** Commit `feat(kpi): dashboard page with charts and filters`.
