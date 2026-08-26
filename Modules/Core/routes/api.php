<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Learner\Api\ActionController;
use Modules\Core\Http\Controllers\Learner\Api\ContentController;
use Modules\Core\Http\Controllers\Learner\Api\ExamAttemptController;
use Modules\Core\Http\Controllers\Learner\Api\MobileAuthController;

/*
 |--------------------------------------------------------------------------
 | API mobile apprenant (stateless, Sanctum Bearer tokens)
 |--------------------------------------------------------------------------
 | Montée par le RouteServiceProvider sous le préfixe /api (middleware
 | `api`) → URLs finales : /api/mobile/v1/...
 |
 | Réutilise les mêmes contrôleurs/services que la PWA (bootstrap, delta
 | sync, actions idempotentes, cycle examen) : une seule logique métier,
 | deux transports (session pour la PWA same-origin, Bearer pour le natif).
 */
Route::prefix('mobile/v1')->name('mobile.v1.')->middleware('throttle:api')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum', 'learner'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
        Route::put('/password', [MobileAuthController::class, 'updatePassword'])->name('password.update');
        Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [MobileAuthController::class, 'logoutAll'])->name('logout-all');

        Route::get('/bootstrap', [ContentController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/changes', [ContentController::class, 'changes'])->name('changes');
        Route::get('/leaderboard', [ContentController::class, 'leaderboard'])->name('leaderboard');

        Route::post('/actions', [ActionController::class, 'store'])->name('actions');

        Route::post('/exams/{exam}/attempts', [ExamAttemptController::class, 'store'])->name('exams.attempts.store');
        Route::patch('/exams/{exam}/attempts/{attempt}', [ExamAttemptController::class, 'update'])->name('exams.attempts.update');
        Route::post('/exams/{exam}/attempts/{attempt}/complete', [ExamAttemptController::class, 'complete'])->name('exams.attempts.complete');
        Route::post('/exams/{exam}/attempts/{attempt}/violations', [ExamAttemptController::class, 'violations'])->name('exams.attempts.violations');
    });
});
