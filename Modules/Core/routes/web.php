<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ActivityController;
use Modules\Core\Http\Controllers\AdminController;
use Modules\Core\Http\Controllers\ArticleController;
use Modules\Core\Http\Controllers\ArticleEditorController;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Core\Http\Controllers\ExamController;
use Modules\Core\Http\Controllers\FlashcardDeckController;
use Modules\Core\Http\Controllers\GroupController;
use Modules\Core\Http\Controllers\LearnerController;
use Modules\Core\Http\Controllers\ModuleController;
use Modules\Core\Http\Controllers\PermissionController;
use Modules\Core\Http\Controllers\ProfileController;
use Modules\Core\Http\Controllers\QuizController;
use Modules\Core\Http\Controllers\QuizEditorController;
use Modules\Core\Http\Controllers\RoleController;
use Modules\Core\Http\Controllers\TrainerController;
use Modules\Core\Http\Controllers\UserController;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.post');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::prefix('cores')->name('cores.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/media/upload', [CoreController::class, 'uploadMedia'])->name('media.upload');

        // Routes pour la gestion des utilisateurs (général)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/data', [UserController::class, 'getData'])->name('data');
            Route::get('/{id}', [UserController::class, 'show'])->name('show');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{id}', [UserController::class, 'update'])->name('update');
            Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
            Route::post('/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
            Route::put('/{id}/profile', [UserController::class, 'updateProfile'])->name('update-profile');
            Route::post('/{id}/avatar', [UserController::class, 'updateAvatar'])->name('update-avatar');

            // Gestion des rôles via AJAX sur la page show
            Route::get('/{id}/roles/available', [UserController::class, 'getAvailableRoles'])->name('available-roles');
            Route::post('/{id}/roles', [UserController::class, 'assignRole'])->name('assign-role');
            Route::delete('/{id}/roles', [UserController::class, 'removeRole'])->name('remove-role');
            Route::delete('/{id}/permissions', [UserController::class, 'removePermission'])->name('remove-permission');
            Route::get('/{id}/permissions/available', [UserController::class, 'getAvailablePermissions'])->name('available-permissions');
            Route::post('/{id}/permissions', [UserController::class, 'assignPermissions'])->name('assign-permissions');
        });

        // Routes pour la gestion des administrateurs
        Route::prefix('admins')->name('admins.')->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::get('/data', [AdminController::class, 'getData'])->name('data');
            Route::get('/{id}', [AdminController::class, 'show'])->name('show');
            Route::post('/', [AdminController::class, 'store'])->name('store');
            Route::put('/{id}', [AdminController::class, 'update'])->name('update');
            Route::delete('/{id}', [AdminController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [AdminController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/{id}/reset-password', [AdminController::class, 'resetPassword'])->name('reset-password');
        });

        // Routes pour la gestion des formateurs
        Route::prefix('trainers')->name('trainers.')->group(function () {
            Route::get('/', [TrainerController::class, 'index'])->name('index');
            Route::get('/data', [TrainerController::class, 'getData'])->name('data');
            Route::get('/{id}', [TrainerController::class, 'show'])->name('show');
            Route::post('/', [TrainerController::class, 'store'])->name('store');
            Route::put('/{id}', [TrainerController::class, 'update'])->name('update');
            Route::delete('/{id}', [TrainerController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [TrainerController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/{id}/reset-password', [TrainerController::class, 'resetPassword'])->name('reset-password');
        });

        // Routes pour la gestion des apprenants
        Route::prefix('learners')->name('learners.')->group(function () {
            Route::get('/', [LearnerController::class, 'index'])->name('index');
            Route::get('/data', [LearnerController::class, 'getData'])->name('data');
            Route::get('/{id}', [LearnerController::class, 'show'])->name('show');
            Route::post('/', [LearnerController::class, 'store'])->name('store');
            Route::put('/{id}', [LearnerController::class, 'update'])->name('update');
            Route::delete('/{id}', [LearnerController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [LearnerController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/{id}/reset-password', [LearnerController::class, 'resetPassword'])->name('reset-password');
        });

        // Routes pour la gestion des groupes
        Route::prefix('groups')->name('groups.')->group(function () {
            Route::get('/', [GroupController::class, 'index'])->name('index');
            Route::get('/data', [GroupController::class, 'getData'])->name('data');
            Route::get('/{id}', [GroupController::class, 'show'])->name('show');
            Route::post('/', [GroupController::class, 'store'])->name('store');
            Route::put('/{id}', [GroupController::class, 'update'])->name('update');
            Route::delete('/{id}', [GroupController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [GroupController::class, 'toggleStatus'])->name('toggle-status');
            Route::get('/{id}/members', [GroupController::class, 'getMembers'])->name('members');
            Route::post('/{id}/members', [GroupController::class, 'assignMembers'])->name('members.assign');
        });

        // Routes pour la gestion des quiz
        Route::prefix('quizzes')->name('quizzes.')->group(function () {
            Route::get('/', [QuizController::class, 'index'])->name('index');
            Route::get('/data', [QuizController::class, 'getData'])->name('data');
            Route::get('/{id}', [QuizController::class, 'show'])->name('show');
            Route::post('/', [QuizController::class, 'store'])->name('store');
            Route::put('/{id}', [QuizController::class, 'update'])->name('update');
            Route::delete('/{id}', [QuizController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [QuizController::class, 'toggleStatus'])->name('toggle-status');

            // Quiz Builder (Conception de questions)
            Route::get('/{id}/builder', [QuizController::class, 'builder'])->name('builder');
            Route::post('/{id}/questions', [QuizController::class, 'storeQuestion'])->name('questions.store');
            Route::put('/{id}/questions/{questionId}', [QuizController::class, 'updateQuestion'])->name('questions.update');
            Route::delete('/{id}/questions/{questionId}', [QuizController::class, 'destroyQuestion'])->name('questions.destroy');
            Route::post('/{id}/questions/reorder', [QuizController::class, 'reorderQuestions'])->name('questions.reorder');
        });

        // Routes pour la gestion des examens
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::get('/', [ExamController::class, 'index'])->name('index');
            Route::get('/data', [ExamController::class, 'getData'])->name('data');
            Route::get('/{id}', [ExamController::class, 'show'])->name('show');
            Route::post('/', [ExamController::class, 'store'])->name('store');
            Route::put('/{id}', [ExamController::class, 'update'])->name('update');
            Route::delete('/{id}', [ExamController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [ExamController::class, 'toggleStatus'])->name('toggle-status');
            Route::get('/{id}/supervision-data', [ExamController::class, 'getSupervisionData'])->name('supervision-data');
        });

        // Routes pour la gestion des decks de flashcards
        Route::prefix('flashcard-decks')->name('flashcard-decks.')->group(function () {
            Route::get('/', [FlashcardDeckController::class, 'index'])->name('index');
            Route::get('/data', [FlashcardDeckController::class, 'getData'])->name('data');
            Route::get('/{id}', [FlashcardDeckController::class, 'show'])->name('show');
            Route::post('/', [FlashcardDeckController::class, 'store'])->name('store');
            Route::put('/{id}', [FlashcardDeckController::class, 'update'])->name('update');
            Route::delete('/{id}', [FlashcardDeckController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [FlashcardDeckController::class, 'toggleStatus'])->name('toggle-status');
        });

        // Routes pour la gestion des articles
        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [ArticleController::class, 'index'])->name('index');
            Route::get('/data', [ArticleController::class, 'getData'])->name('data');
            Route::get('/{id}', [ArticleController::class, 'show'])->name('show');
            Route::post('/', [ArticleController::class, 'store'])->name('store');
            Route::put('/{id}', [ArticleController::class, 'update'])->name('update');
            Route::delete('/{id}', [ArticleController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-status', [ArticleController::class, 'toggleStatus'])->name('toggle-status');
            Route::get('/{id}/export', [ArticleController::class, 'export'])->name('export');
        });

        // Routes pour la gestion des rôles
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('/data', [RoleController::class, 'getData'])->name('data');
            Route::get('/{id}', [RoleController::class, 'show'])->name('show');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::put('/{id}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');

            // Gestion des permissions du rôle
            Route::get('/{id}/permissions', [RoleController::class, 'getPermissions'])->name('permissions');
            Route::post('/{id}/toggle-permission', [RoleController::class, 'togglePermission'])->name('toggle-permission');
        });

        // Routes pour la gestion des permissions
        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])->name('index');
            Route::post('/toggle', [PermissionController::class, 'toggle'])->name('toggle');
            Route::post('/sync', [ModuleController::class, 'syncPermissions'])->name('sync');
        });

        // Routes pour la gestion des modules
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/', [ModuleController::class, 'index'])->name('index');
            Route::post('/install', [ModuleController::class, 'install'])->name('install');
            Route::get('/{slug}', [ModuleController::class, 'show'])->name('show');
            Route::post('/{slug}/enable', [ModuleController::class, 'enable'])->name('enable');
            Route::post('/{slug}/disable', [ModuleController::class, 'disable'])->name('disable');
            Route::delete('/{slug}', [ModuleController::class, 'uninstall'])->name('uninstall');
            Route::get('/{slug}/configure', [ModuleController::class, 'configure'])->name('configure');
            Route::post('/{slug}/configure', [ModuleController::class, 'updateConfiguration'])->name('configure.update');
        });

        // Routes pour la gestion des activités
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::get('/', [ActivityController::class, 'index'])->name('index');
            Route::get('/data', [ActivityController::class, 'getData'])->name('data');
            Route::get('/{id}', [ActivityController::class, 'show'])->name('show');
        });

        // Routes pour le profil utilisateur
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        // Routes de l'éditeur de contenu (Article / Quiz)
        Route::prefix('editor')->name('editor.')->group(function () {
            // Quiz Editor
            Route::get('/quizzes/{quiz}/edit', [QuizEditorController::class, 'edit'])->name('quizzes.edit');
            Route::get('/quizzes/{quiz}/preview', [QuizEditorController::class, 'preview'])->name('quizzes.preview');
            Route::get('/quizzes/{quiz}/preview-iframe', [QuizEditorController::class, 'previewIframe'])->name('quizzes.preview-iframe');
            Route::get('/quizzes/{quiz}/print-iframe', [QuizEditorController::class, 'printIframe'])->name('quizzes.print-iframe');
            Route::post('/quizzes/{quiz}/questions', [QuizEditorController::class, 'storeQuestion'])->name('quizzes.questions.store');
            Route::get('/quizzes/{quiz}/questions/{question}', [QuizEditorController::class, 'showQuestion'])->name('quizzes.questions.show');
            Route::put('/quizzes/{quiz}/questions/{q}', [QuizEditorController::class, 'updateQuestion'])->name('quizzes.questions.update');
            Route::delete('/quizzes/{quiz}/questions/{q}', [QuizEditorController::class, 'destroyQuestion'])->name('quizzes.questions.destroy');
            Route::patch('/quizzes/{quiz}/toggle-active', [QuizEditorController::class, 'toggleActive'])->name('quizzes.toggle-active');
            Route::post('/quizzes/{quiz}/reorder', [QuizEditorController::class, 'reorderQuestions'])->name('quizzes.reorder');
            Route::post('/quizzes/{quiz}/autosave', [QuizEditorController::class, 'autosave'])->name('quizzes.autosave');

            // Exam Editor
            Route::get('/exams/{exam}/edit', [ExamController::class, 'builder'])->name('exams.edit');
            Route::get('/exams/{exam}/preview', [ExamController::class, 'preview'])->name('exams.preview');
            Route::get('/exams/{exam}/preview-iframe', [ExamController::class, 'previewIframe'])->name('exams.preview-iframe');
            Route::get('/exams/{exam}/print-iframe', [ExamController::class, 'printIframe'])->name('exams.print-iframe');
            Route::post('/exams/{exam}/questions', [ExamController::class, 'storeQuestion'])->name('exams.questions.store');
            Route::get('/exams/{exam}/questions/{question}', [ExamController::class, 'showQuestion'])->name('exams.questions.show');
            Route::put('/exams/{exam}/questions/{q}', [ExamController::class, 'updateQuestion'])->name('exams.questions.update');
            Route::delete('/exams/{exam}/questions/{q}', [ExamController::class, 'destroyQuestion'])->name('exams.questions.destroy');
            Route::patch('/exams/{exam}/toggle-active', [ExamController::class, 'toggleStatus'])->name('exams.toggle-active');
            Route::post('/exams/{exam}/reorder', [ExamController::class, 'reorderQuestions'])->name('exams.reorder');
            Route::get('/exams/{exam}/supervision', [ExamController::class, 'supervision'])->name('exams.supervision');

            // Flashcard Deck Editor
            Route::get('/flashcards/{deck}/edit', [FlashcardDeckController::class, 'builder'])->name('flashcards.edit');
            Route::post('/flashcards/{deck}/cards', [FlashcardDeckController::class, 'storeCard'])->name('flashcards.cards.store');
            Route::get('/flashcards/{deck}/cards/{card}', [FlashcardDeckController::class, 'showCard'])->name('flashcards.cards.show');
            Route::put('/flashcards/{deck}/cards/{card}', [FlashcardDeckController::class, 'updateCard'])->name('flashcards.cards.update');
            Route::delete('/flashcards/{deck}/cards/{card}', [FlashcardDeckController::class, 'destroyCard'])->name('flashcards.cards.destroy');
            Route::post('/flashcards/{deck}/groups', [FlashcardDeckController::class, 'assignGroup'])->name('flashcards.groups.assign');
            Route::delete('/flashcards/{deck}/groups/{group}', [FlashcardDeckController::class, 'unassignGroup'])->name('flashcards.groups.unassign');
            Route::post('/flashcards/{deck}/generate', [FlashcardDeckController::class, 'autoGenerate'])->name('flashcards.generate');

            Route::get('/flashcards/{deck}/preview', [FlashcardDeckController::class, 'preview'])->name('flashcards.preview');
            Route::get('/flashcards/{deck}/preview-iframe', [FlashcardDeckController::class, 'previewIframe'])->name('flashcards.preview-iframe');
            Route::get('/flashcards/{deck}/print-iframe', [FlashcardDeckController::class, 'printIframe'])->name('flashcards.print-iframe');

            // Groups Assignments
            Route::get('/groups/search', [QuizEditorController::class, 'searchGroups'])->name('groups.search');
            Route::post('/quizzes/{quiz}/groups', [QuizEditorController::class, 'assignGroup'])->name('quizzes.groups.assign');
            Route::delete('/quizzes/{quiz}/groups/{group}', [QuizEditorController::class, 'unassignGroup'])->name('quizzes.groups.unassign');

            // Article Editor
            Route::post('/articles/upload-media', [ArticleEditorController::class, 'uploadMedia'])->name('articles.upload-media');
            Route::get('/articles/{article}/edit', [ArticleEditorController::class, 'edit'])->name('articles.edit');
            Route::post('/articles/{article}/autosave', [ArticleEditorController::class, 'autosave'])->name('articles.autosave');
            Route::patch('/articles/{article}/toggle-active', [ArticleEditorController::class, 'toggleActive'])->name('articles.toggle-active');
            Route::post('/articles/{article}/groups', [ArticleEditorController::class, 'assignGroup'])->name('articles.groups.assign');
            Route::delete('/articles/{article}/groups/{group}', [ArticleEditorController::class, 'unassignGroup'])->name('articles.groups.unassign');
            Route::get('/articles/quizzes/search', [ArticleEditorController::class, 'searchQuizzes'])->name('articles.quizzes.search');
        });
    });
});
Route::resource('cores', CoreController::class)->names('core');

// =========================================================================
// VOLET APPRENANT (PWA Mobile-First)
// =========================================================================

// Shell SPA (public : le client affiche login ou app selon la session locale)
Route::get('/', [\Modules\Core\Http\Controllers\Learner\ShellController::class, '__invoke'])->name('learn.login');
Route::get('/connexion', [\Modules\Core\Http\Controllers\Learner\ShellController::class, '__invoke'])->name('learn.spa.login');

// Service worker généré par Vite (public/build/sw.js), servi à la racine.
Route::get('/sw.js', function () {
    $path = public_path('build/sw.js');
    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('learn.sw');

// -------------------------------------------------------------------------
// API Apprenant v1 (session + cookie + CSRF, SPA same-origin)
// -------------------------------------------------------------------------
Route::prefix('api/learner/v1')->name('learn.v1.')->group(function () {
    Route::post('/session', [\Modules\Core\Http\Controllers\Learner\Api\SessionController::class, 'store'])->name('session.store');

    Route::middleware(['auth', 'learner'])->group(function () {
        Route::get('/me', [\Modules\Core\Http\Controllers\Learner\Api\SessionController::class, 'show'])->name('me');
        Route::delete('/session', [\Modules\Core\Http\Controllers\Learner\Api\SessionController::class, 'destroy'])->name('session.destroy');

        Route::get('/bootstrap', [\Modules\Core\Http\Controllers\Learner\Api\ContentController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/changes', [\Modules\Core\Http\Controllers\Learner\Api\ContentController::class, 'changes'])->name('changes');

        Route::post('/actions', [\Modules\Core\Http\Controllers\Learner\Api\ActionController::class, 'store'])->name('actions');

        Route::post('/exams/{exam}/attempts', [\Modules\Core\Http\Controllers\Learner\Api\ExamAttemptController::class, 'store'])->name('exams.attempts.store');
        Route::patch('/exams/{exam}/attempts/{attempt}', [\Modules\Core\Http\Controllers\Learner\Api\ExamAttemptController::class, 'update'])->name('exams.attempts.update');
        Route::post('/exams/{exam}/attempts/{attempt}/complete', [\Modules\Core\Http\Controllers\Learner\Api\ExamAttemptController::class, 'complete'])->name('exams.attempts.complete');
        Route::post('/exams/{exam}/attempts/{attempt}/violations', [\Modules\Core\Http\Controllers\Learner\Api\ExamAttemptController::class, 'violations'])->name('exams.attempts.violations');
    });
});

// Routes profondes du SPA (History API) : le serveur renvoie toujours le shell.
Route::middleware(['auth', 'learner'])->name('learn.')->group(function () {
    Route::get('/dashboard', fn () => redirect('/'))->name('spa-shell');

    foreach ([
        '/articles',
        '/articles/{id}',
        '/evaluations',
        '/quizzes/{id}',
        '/quizzes/{id}/play',
        '/exams/{id}/play',
        '/reviser',
        '/reviser/{deckId}',
        '/profil',
    ] as $spaPath) {
        Route::get($spaPath, [\Modules\Core\Http\Controllers\Learner\ShellController::class, '__invoke']);
    }
});
