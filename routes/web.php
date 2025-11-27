<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\ProvisionnalCalendarEditorController;
use App\Http\Controllers\ProvisionnalCalendarSettingsController;
use App\Http\Controllers\TeachersTeachingsController;
use App\Http\Controllers\ProvisionnalCalendarReaderController;
use App\Http\Controllers\WorkInProgressController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestEmailController;
use Inertia\Inertia;

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate']);

// Routes pour la création du mot de passe personnel
Route::get('/create-personal-password', [AuthController::class, 'showCreatePersonalPassword'])
    ->middleware(['auth'])
    ->name('create.personal.password');
Route::post('/store-personal-password', [AuthController::class, 'createPersonalPassword'])
    ->middleware(['auth'])
    ->name('store.personal.password');

Route::get('/test', function () {
    return Inertia::render('TestPage');
});

Route::get('/logout', [AuthController::class, 'logout'])->middleware(['auth'])->name('logout');

Route::get('/', [IndexController::class, 'show'])->middleware(['auth'])->name('index');

Route::get('/calendrier-previsionnel', [ProvisionnalCalendarReaderController::class, 'show'])
    ->middleware(['auth', 'role:reader', 'role:extended_reader'])
    ->name('provisionnal_calendar');

Route::get('/edt', [WorkInProgressController::class, 'show'])
    ->middleware(['auth', 'role:reader', 'role:extended_reader', 'role:admin'])
    ->name('edt');

Route::get('/service', [WorkInProgressController::class, 'show'])
    ->middleware(['auth', 'role:reader', 'role:extended_reader', 'role:admin'])
    ->name('service');

Route::get('/send-test-email', [TestEmailController::class, 'sendTestEmail']);

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/calendrier-previsionnel/groupes', [GroupsController::class, 'show'])
        ->name('provisionnal_calendar.groups');

    Route::get('/calendrier-previsionnel/enseignants-enseignements', [TeachersTeachingsController::class, 'show'])
        ->name('provisionnal_calendar.teachers_teachings');

    Route::get('/calendrier-previsionnel/editeur', [ProvisionnalCalendarEditorController::class, 'show'])
        ->name('provisionnal_calendar.editor');

    Route::get('/calendrier-previsionnel/edt', function () {
        return Inertia::render('ProvisionnalCalendarEdtPage');
    })->name('provisionnal_calendar.edt');

    Route::get('/calendrier-previsionnel/edt/modifier', function () {
        return Inertia::render('ProvisionnalCalendarEdtEditorPage');
    })->name('provisionnal_calendar.edt.edit');

    Route::get('/configurations', function () {
        return redirect()->route('provisionnal_calendar.settings.labels');
    })->name('provisionnal_calendar.settings');

    Route::get('/configurations/labels', [ProvisionnalCalendarSettingsController::class, 'showLabels'])
        ->name('provisionnal_calendar.settings.labels');

    Route::get('/configurations/utilisateurs', [ProvisionnalCalendarSettingsController::class, 'showUsers'])
        ->name('provisionnal_calendar.settings.users');
});
