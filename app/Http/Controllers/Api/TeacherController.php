<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Year;
use App\Models\Teaching;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    public function getTeachers($year): JsonResponse
    {
        Log::debug('TeacherController: getTeachers called', ['year' => $year]);

        try {
            // Vérifie si l'année existe
            $yearExists = Year::find($year);
            if (!$yearExists) {
                Log::warning('TeacherController: year not found', ['year' => $year]);
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            // Récupère les enseignants dont l'utilisateur n'est pas suspendu
            $teachers = Teacher::whereHas('user', function ($q) {
                    $q->where('suspended', false);
                })
                ->with(['user'])
                ->get()
                ->map(function ($teacher) {
                    return [
                        'id' => $teacher->id,
                        'code' => $teacher->user->acronym,
                        'first_name' => $teacher->user->first_name,
                        'last_name' => $teacher->user->last_name,
                        'type' => $teacher->type,
                    ];
                });

            Log::info('TeacherController: teachers retrieved successfully', [
                'year' => $year,
                'count' => $teachers->count()
            ]);

            return response()->json($teachers);

        } catch (\Exception $e) {
            Log::error('TeacherController: getTeachers failed', [
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeachersByTeaching($teaching_id): JsonResponse
    {
        Log::debug('TeacherController: getTeachersByTeaching called', ['teaching_id' => $teaching_id]);

        try {
            // Vérifie si l'enseignement existe
            $teaching = Teaching::find($teaching_id);
            if (!$teaching) {
                Log::warning('TeacherController: teaching not found', ['teaching_id' => $teaching_id]);
                return response()->json([
                    'error' => 'Enseignement non trouvé'
                ], 404);
            }

            // Récupère les enseignants assignés à cet enseignement
            $teachers = $teaching->teachers->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'acronym' => $teacher->acronym,
                    'first_name' => $teacher->first_name,
                    'last_name' => $teacher->last_name
                ];
            });

            Log::debug('TeacherController: teachers for teaching retrieved', [
                'teaching_id' => $teaching_id,
                'count' => $teachers->count()
            ]);

            return response()->json($teachers);

        } catch (\Exception $e) {
            Log::error('TeacherController: getTeachersByTeaching failed', [
                'teaching_id' => $teaching_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeacher($teacher_id): JsonResponse
    {
        Log::debug('TeacherController: getTeacher called', ['teacher_id' => $teacher_id]);

        try {
            // Vérifie si l'enseignant existe
            $teacher = Teacher::with(['teachings', 'year', 'user'])
                ->find($teacher_id);

            if (!$teacher) {
                Log::warning('TeacherController: teacher not found', ['teacher_id' => $teacher_id]);
                return response()->json([
                    'error' => 'Enseignant non trouvé'
                ], 404);
            }

            // Prépare la réponse avec les données de l'enseignant
            $response = [
                'id' => $teacher->id,
                'acronym' => $teacher->user->acronym,
                'user_id' => $teacher->user_id
            ];

            Log::debug('TeacherController: teacher retrieved successfully', ['teacher_id' => $teacher_id]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('TeacherController: getTeacher failed', [
                'teacher_id' => $teacher_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeTeacher(Request $request, $year): JsonResponse
    {
        Log::debug('TeacherController: storeTeacher called', [
            'year' => $year,
            'acronym' => $request->acronym,
            'user_id' => $request->user_id
        ]);

        try {
            $request->validate([
                'acronym' => 'required|string|max:10',
                'user_id' => 'required|exists:users,id'
            ]);

            // Vérifie si l'année existe
            $yearExists = Year::find($year);
            if (!$yearExists) {
                Log::warning('TeacherController: year not found for teacher creation', ['year' => $year]);
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            // Vérifie si un utilisateur avec le même acronyme existe déjà
            $existingUser = User::where('acronym', $request->acronym)->first();
            if ($existingUser && $existingUser->id != $request->user_id) {
                Log::warning('TeacherController: duplicate acronym detected', [
                    'acronym' => $request->acronym,
                    'existing_user_id' => $existingUser->id
                ]);
                return response()->json([
                    'error' => 'Un utilisateur avec cet acronyme existe déjà'
                ], 422);
            }

            // Met à jour l'acronyme de l'utilisateur fourni
            $user = User::find($request->user_id);
            if (!$user) {
                Log::warning('TeacherController: user not found for teacher creation', ['user_id' => $request->user_id]);
                return response()->json([
                    'error' => 'Utilisateur non trouvé'
                ], 404);
            }
            $user->acronym = $request->acronym;
            $user->save();

            // Crée l'enseignant (associe l'utilisateur)
            $teacher = Teacher::create([
                'user_id' => $request->user_id,
                'year_id' => $year
            ]);

            Log::info('TeacherController: teacher created successfully', [
                'teacher_id' => $teacher->id,
                'user_id' => $request->user_id,
                'year' => $year
            ]);

            return response()->json([
                'message' => 'Enseignant créé avec succès',
                'teacher' => [
                    'id' => $teacher->id,
                    'acronym' => $user->acronym,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_id' => $teacher->user_id,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('TeacherController: storeTeacher failed', [
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTeacher(Request $request, $teacher_id): JsonResponse
    {
        Log::debug('TeacherController: updateTeacher called', [
            'teacher_id' => $teacher_id,
            'acronym' => $request->acronym
        ]);

        try {
            $request->validate([
                'acronym' => 'required|string|max:10',
                'user_id' => 'required|exists:users,id'
            ]);

            // Vérifie si l'enseignant existe
            $teacher = Teacher::with('user')
                ->find($teacher_id);
            if (!$teacher) {
                Log::warning('TeacherController: teacher not found for update', ['teacher_id' => $teacher_id]);
                return response()->json([
                    'error' => 'Enseignant non trouvé'
                ], 404);
            }

            // Vérifie si un autre utilisateur avec le même acronyme existe déjà
            $existingUserWithAcronym = User::where('acronym', $request->acronym)
                ->where('id', '!=', $request->user_id)
                ->first();

            if ($existingUserWithAcronym) {
                Log::warning('TeacherController: duplicate acronym on update', [
                    'acronym' => $request->acronym,
                    'existing_user_id' => $existingUserWithAcronym->id
                ]);
                return response()->json([
                    'error' => 'Un utilisateur avec cet acronyme existe déjà pour cette année'
                ], 422);
            }

            // Met à jour l'acronyme sur l'utilisateur cible
            $newUser = User::find($request->user_id);
            if (!$newUser) {
                Log::warning('TeacherController: user not found for teacher update', ['user_id' => $request->user_id]);
                return response()->json([
                    'error' => 'Utilisateur non trouvé'
                ], 404);
            }
            $newUser->acronym = $request->acronym;
            $newUser->save();

            // Met à jour l'association teacher->user_id
            $teacher->user_id = $request->user_id;
            $teacher->save();

            Log::info('TeacherController: teacher updated successfully', ['teacher_id' => $teacher_id]);

            return response()->json([
                'id' => $teacher->id,
                'acronym' => $newUser->acronym,
                'user_id' => $teacher->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('TeacherController: updateTeacher failed', [
                'teacher_id' => $teacher_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteTeacher($teacher_id): JsonResponse
    {
        Log::debug('TeacherController: deleteTeacher called', ['teacher_id' => $teacher_id]);

        try {
            $teacher = Teacher::find($teacher_id);
            if (!$teacher) {
                Log::warning('TeacherController: teacher not found for deletion', ['teacher_id' => $teacher_id]);
                return response()->json([
                    'error' => 'Enseignant non trouvé'
                ], 404);
            }
            $teacher->delete();

            Log::info('TeacherController: teacher deleted successfully', ['teacher_id' => $teacher_id]);

            return response()->json([
                'message' => 'Enseignant supprimé avec succès'
            ], 200);
        } catch (\Exception $e) {
            Log::error('TeacherController: deleteTeacher failed', [
                'teacher_id' => $teacher_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
