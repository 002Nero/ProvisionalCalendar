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
        // Salles (simple endpoint exposant toutes les salles)
        Route::get('/rooms', function() {
            return \App\Models\Room::orderBy('name')->get(['id','name']);
        });
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
            // edt_slot endpoints: fetch and save placements (edt_slot rows)
            Route::get('/edt/{year}/{week}', [CalendarController::class, 'getEdtSlots']);
            Route::post('/edt/bulk', [CalendarController::class, 'storeEdtSlotsBulk']);
            Route::post('/edt/create', [CalendarController::class, 'createEdtSlot']);
            Route::delete('/edt/{id}', [CalendarController::class, 'deleteEdtSlot']);

        // Constraints endpoints (minimal closures using DB)
        Route::get('/room-constraints', function() {
            return \Illuminate\Support\Facades\DB::table('room_constraints')
                ->select('id','room_id','constraint_type','day_of_week','start_time','end_time','reason','week_id','priority','active','created_at')
                ->get();
        });
        Route::post('/room-constraints', function(\Illuminate\Http\Request $request) {
            $id = \Illuminate\Support\Facades\DB::table('room_constraints')->insertGetId([
                'room_id' => $request->input('room_id'),
                'constraint_type' => $request->input('constraint_type','unavailable'),
                'day_of_week' => $request->input('day_of_week'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'reason' => $request->input('reason'),
                'priority' => $request->input('priority','hard'),
                'week_id' => $request->input('week_id'),
                'active' => $request->input('active', true),
                'created_at' => now()
            ]);
            return response()->json(['id' => $id], 201);
        });
        Route::delete('/room-constraints/{id}', function($id) {
            \Illuminate\Support\Facades\DB::table('room_constraints')->where('id',$id)->delete();
            return response()->json([], 204);
        });

        Route::get('/teacher-constraints', function() {
            return \Illuminate\Support\Facades\DB::table('teacher_constraints')->get();
        });
        Route::post('/teacher-constraints', function(\Illuminate\Http\Request $request) {
            $id = \Illuminate\Support\Facades\DB::table('teacher_constraints')->insertGetId([
                'teacher_id' => $request->input('teacher_id'),
                'constraint_type' => $request->input('constraint_type','unavailable'),
                'day_of_week' => $request->input('day_of_week'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'reason' => $request->input('reason'),
                'priority' => $request->input('priority','hard'),
                'week_id' => $request->input('week_id'),
                'active' => $request->input('active', true),
                'created_at' => now()
            ]);
            return response()->json(['id' => $id], 201);
        });
        Route::delete('/teacher-constraints/{id}', function($id) {
            \Illuminate\Support\Facades\DB::table('teacher_constraints')->where('id',$id)->delete();
            return response()->json([], 204);
        });

        Route::get('/group-constraints', function() {
            return \Illuminate\Support\Facades\DB::table('group_constraints')->get();
        });
            // Update existing group constraint
            Route::put('/group-constraints/{id}', function(\Illuminate\Http\Request $request, $id) {
                $data = [
                    'group_id' => $request->input('group_id'),
                    'constraint_type' => $request->input('constraint_type','unavailable'),
                    'day_of_week' => $request->input('day_of_week'),
                    'start_time' => $request->input('start_time'),
                    'end_time' => $request->input('end_time'),
                    'reason' => $request->input('reason'),
                    'priority' => $request->input('priority','hard'),
                    'week_id' => $request->input('week_id'),
                    'active' => $request->input('active', true)
                ];
                \Illuminate\Support\Facades\DB::table('group_constraints')->where('id', $id)->update($data);
                return response()->json(['ok' => true], 200);
            });
        Route::post('/group-constraints', function(\Illuminate\Http\Request $request) {
            $id = \Illuminate\Support\Facades\DB::table('group_constraints')->insertGetId([
                'group_id' => $request->input('group_id'),
                'constraint_type' => $request->input('constraint_type','unavailable'),
                'day_of_week' => $request->input('day_of_week'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'reason' => $request->input('reason'),
                'priority' => $request->input('priority','hard'),
                'week_id' => $request->input('week_id'),
                'active' => $request->input('active', true),
                'created_at' => now()
            ]);
            return response()->json(['id' => $id], 201);
        });
        Route::delete('/group-constraints/{id}', function($id) {
            \Illuminate\Support\Facades\DB::table('group_constraints')->where('id',$id)->delete();
            return response()->json([], 204);
        });

        Route::get('/slot-constraints', function() {
            return \Illuminate\Support\Facades\DB::table('slot_constraints')->get();
        });
        Route::post('/slot-constraints', function(\Illuminate\Http\Request $request) {
            $id = \Illuminate\Support\Facades\DB::table('slot_constraints')->insertGetId([
                'slot_id' => $request->input('slot_id'),
                'constraint_type' => $request->input('constraint_type','unavailable'),
                'day_of_week' => $request->input('day_of_week'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'reason' => $request->input('reason'),
                'priority' => $request->input('priority','hard'),
                'week_id' => $request->input('week_id'),
                'active' => $request->input('active', true),
                'created_at' => now()
            ]);
            return response()->json(['id' => $id], 201);
        });
        Route::put('/slot-constraints/{id}', function(\Illuminate\Http\Request $request, $id) {
            $data = [
                'slot_id' => $request->input('slot_id'),
                'constraint_type' => $request->input('constraint_type','unavailable'),
                'day_of_week' => $request->input('day_of_week'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'reason' => $request->input('reason'),
                'priority' => $request->input('priority','hard'),
                'week_id' => $request->input('week_id'),
                'active' => $request->input('active', true)
            ];
            \Illuminate\Support\Facades\DB::table('slot_constraints')->where('id', $id)->update($data);
            return response()->json(['ok' => true], 200);
        });
        Route::delete('/slot-constraints/{id}', function($id) {
            \Illuminate\Support\Facades\DB::table('slot_constraints')->where('id',$id)->delete();
            return response()->json([], 204);
        });

            // Update endpoints for room and teacher constraints
            Route::put('/room-constraints/{id}', function(\Illuminate\Http\Request $request, $id) {
                $data = [
                    'room_id' => $request->input('room_id'),
                    'constraint_type' => $request->input('constraint_type','unavailable'),
                    'day_of_week' => $request->input('day_of_week'),
                    'start_time' => $request->input('start_time'),
                    'end_time' => $request->input('end_time'),
                    'reason' => $request->input('reason'),
                    'priority' => $request->input('priority','hard'),
                    'week_id' => $request->input('week_id'),
                    'active' => $request->input('active', true)
                ];
                \Illuminate\Support\Facades\DB::table('room_constraints')->where('id', $id)->update($data);
                return response()->json(['ok' => true], 200);
            });
            Route::put('/teacher-constraints/{id}', function(\Illuminate\Http\Request $request, $id) {
                try {
                    $teacherId = $request->input('teacher_id');
                    if ($teacherId && !\Illuminate\Support\Facades\DB::table('teachers')->where('id', $teacherId)->exists()) {
                        return response()->json(['message' => 'Teacher not found'], 400);
                    }
                    $weekId = $request->input('week_id');
                    if ($weekId && !\Illuminate\Support\Facades\DB::table('weeks')->where('id', $weekId)->exists()) {
                        return response()->json(['message' => 'Week not found'], 400);
                    }

                    $data = [
                        'teacher_id' => $teacherId,
                        'constraint_type' => $request->input('constraint_type','unavailable'),
                        'day_of_week' => $request->input('day_of_week'),
                        'start_time' => $request->input('start_time'),
                        'end_time' => $request->input('end_time'),
                        'reason' => $request->input('reason'),
                        'priority' => $request->input('priority','hard'),
                        'week_id' => $weekId,
                        'active' => $request->input('active', true)
                    ];

                    \Illuminate\Support\Facades\DB::table('teacher_constraints')->where('id', $id)->update($data);
                    return response()->json(['ok' => true], 200);
                } catch (\Illuminate\Database\QueryException $qe) {
                    return response()->json(['message' => 'Database error', 'error' => $qe->getMessage()], 500);
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
                }
            });
});
