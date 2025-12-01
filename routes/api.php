<?php

use App\Http\Controllers\Api\Groups\GroupController;
use App\Http\Controllers\Api\Groups\SubgroupController;
use App\Http\Controllers\Api\Groups\PromotionController;
use App\Http\Controllers\Api\TeacherTeachingController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\YearController;
use App\Http\Controllers\Api\UserControllerApi;
use App\Http\Controllers\Api\RoleControllerApi;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\TeachingController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.logger'])->group(function () {
    //Years

    Route::get('/years', [YearController::class, 'index']);
    Route::post('/years', [YearController::class, 'store']);
    Route::get('/years/{year}', [YearController::class, 'show']);
    Route::post('/years/{id}/clone', [YearController::class, 'clone']);

    Route::get('/periods/{year_id}', [PeriodController::class, 'getPeriodsByYear']);

    //Groupes

        //Récupération des données

        Route::get('/promotions/{year_id}', [PromotionController::class, 'getPromotionsByYear']);
        Route::get('/groups/{promotion_id}', [GroupController::class, 'getGroupsByPromotion']);
        Route::get('/subgroups/{group_id}', [SubgroupController::class, 'getSubgroupsByGroup']);

        Route::get('/promotion/{promotion_id}', [PromotionController::class, 'getPromotionById']);
        Route::get('/group/{group_id}', [GroupController::class, 'getGroupById']);
        Route::get('/subgroup/{subgroup_id}', [SubgroupController::class, 'getSubgroupById']);

        //Création des données

        Route::post('/promotion/{year}', [PromotionController::class, 'storePromotion']);
        Route::post('/group/{promotion}', [GroupController::class, 'storeGroup']);
        Route::post('/subgroup/{group}', [SubgroupController::class, 'storeSubgroup']);

        //Modification des données

        Route::put('/promotion/{promotion}', [PromotionController::class, 'updatePromotion']);
        Route::put('/group/{group}', [GroupController::class, 'updateGroup']);
        Route::put('/subgroup/{subgroup}', [SubgroupController::class, 'updateSubgroup']);

        //Suppression des données

        Route::delete('/promotion/{promotion}', [PromotionController::class, 'deletePromotion']);
        Route::delete('/group/{group}', [GroupController::class, 'deleteGroup']);
        Route::delete('/subgroup/{subgroup}', [SubgroupController::class, 'deleteSubgroup']);

    //Enseignants

        //Récupération des données

        Route::get('/teachers/{year}', [TeacherController::class, 'getTeachers']);
        Route::get('/teachings/{year}', [TeachingController::class, 'getTeachings']);
        Route::get('/teacher/teachings/{teacher}', [TeacherTeachingController::class, 'getTeachingsByTeacher']);
        Route::get('/teacher/{teacher}', [TeacherController::class, 'getTeacher']);
        Route::get('/teaching/{teaching}', [TeacherTeachingController::class, 'getTeaching']);
        Route::get('/teacher/teaching/{teacher}/{teaching}', [TeacherTeachingController::class, 'getTeacherTeaching']);

        //Création des données

        Route::post('/teacher/{year}', [TeacherController::class, 'storeTeacher']);
        Route::post('/teaching/{year}', [TeachingController::class, 'storeTeaching']);

        Route::post('/teacher/teaching/{teacher_id}/{teaching_id}', [TeacherTeachingController::class, 'storeTeacherTeaching']);


        //Modification des données

        Route::put('/teacher/{teacher}', [TeacherController::class, 'updateTeacher']);
        Route::put('/teaching/{teaching}', [TeachingController::class, 'updateTeaching']);

        //Suppression des données

        Route::delete('/teacher/{teacher}', [TeacherController::class, 'deleteTeacher']);
        Route::delete('/teaching/{teaching}', [TeachingController::class, 'deleteTeaching']);

        Route::delete('/teacher/teaching/{teacher}/{teaching}', [TeacherTeachingController::class, 'deleteTeacherTeaching']);

    //Labels

        Route::get('/labels', [LabelController::class, 'index']);
        Route::get('/labels/{label_id}', [LabelController::class, 'getLabel']);
        Route::put('/labels/{label_id}', [LabelController::class, 'updateLabel']);

        Route::get('/roles', [RoleControllerApi::class, 'index']);
        Route::get('/users', [UserControllerApi::class, 'index']);
        Route::post('/users', [UserControllerApi::class, 'store']);
        Route::put('/users/{user}', [UserControllerApi::class, 'update']);
        Route::delete('/users/{user}', [UserControllerApi::class, 'destroy']);
        Route::post('/users/{user}/create-or-reset-password', [UserControllerApi::class, 'createOrResetPassword']);

    //Calendrier
        Route::post('/calendrier', [CalendarController::class, 'storeSlot']);
        Route::post('/calendrier/bulk', [CalendarController::class, 'storeSlotsBulk']);
        Route::get('/calendrier/{id}', [CalendarController::class, 'getCalendarData']);
});
