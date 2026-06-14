<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ActivityController;
use Modules\Core\Http\Controllers\AdminController;
use Modules\Core\Http\Controllers\ArticleController;
use Modules\Core\Http\Controllers\ArticleEditorController;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Controllers\DashboardController;
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
    });

    // Quiz Editor
    Route::get('/admin/quizzes/{quiz}/edit', [QuizEditorController::class, 'edit'])->name('admin.quizzes.edit');
    Route::get('/admin/quizzes/{quiz}/preview', [QuizEditorController::class, 'preview'])->name('admin.quizzes.preview');
    Route::get('/admin/quizzes/{quiz}/preview-iframe', [QuizEditorController::class, 'previewIframe'])->name('admin.quizzes.preview-iframe');
    Route::post('/admin/quizzes/{quiz}/questions', [QuizEditorController::class, 'storeQuestion'])->name('admin.quizzes.questions.store');
    Route::get('/admin/quizzes/{quiz}/questions/{question}', [QuizEditorController::class, 'showQuestion'])->name('admin.quizzes.questions.show');
    Route::put('/admin/quizzes/{quiz}/questions/{q}', [QuizEditorController::class, 'updateQuestion'])->name('admin.quizzes.questions.update');
    Route::delete('/admin/quizzes/{quiz}/questions/{q}', [QuizEditorController::class, 'destroyQuestion'])->name('admin.quizzes.questions.destroy');
    Route::patch('/admin/quizzes/{quiz}/toggle-active', [QuizEditorController::class, 'toggleActive'])->name('admin.quizzes.toggle-active');
    Route::post('/admin/quizzes/{quiz}/reorder', [QuizEditorController::class, 'reorderQuestions'])->name('admin.quizzes.reorder');
    Route::post('/admin/quizzes/{quiz}/autosave', [QuizEditorController::class, 'autosave'])->name('admin.quizzes.autosave');

    // Groups Assignments
    Route::get('/admin/groups/search', [QuizEditorController::class, 'searchGroups'])->name('admin.groups.search');
    Route::post('/admin/quizzes/{quiz}/groups', [QuizEditorController::class, 'assignGroup'])->name('admin.quizzes.groups.assign');
    Route::delete('/admin/quizzes/{quiz}/groups/{group}', [QuizEditorController::class, 'unassignGroup'])->name('admin.quizzes.groups.unassign');

    // Article Editor
    Route::post('/admin/articles/upload-media', [ArticleEditorController::class, 'uploadMedia'])->name('admin.articles.upload-media');
    Route::get('/admin/articles/{article}/edit', [ArticleEditorController::class, 'edit'])->name('admin.articles.edit');
    Route::post('/admin/articles/{article}/autosave', [ArticleEditorController::class, 'autosave'])->name('admin.articles.autosave');
    Route::patch('/admin/articles/{article}/toggle-active', [ArticleEditorController::class, 'toggleActive'])->name('admin.articles.toggle-active');
    Route::post('/admin/articles/{article}/groups', [ArticleEditorController::class, 'assignGroup'])->name('admin.articles.groups.assign');
    Route::delete('/admin/articles/{article}/groups/{group}', [ArticleEditorController::class, 'unassignGroup'])->name('admin.articles.groups.unassign');
    Route::get('/admin/articles/quizzes/search', [ArticleEditorController::class, 'searchQuizzes'])->name('admin.articles.quizzes.search');
});
Route::resource('cores', CoreController::class)->names('core');
