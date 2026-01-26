<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Year;
use App\Models\Teaching;
use App\Models\Semester;
use App\Models\Trimester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeachingController extends Controller
{
    public function getTeachings($year): JsonResponse
    {
        try {
            // Vérifie si l'année existe
            $yearExists = Year::find($year);
            if (!$yearExists) {
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            // Récupère les enseignements pour l'année spécifiée
            $teachings = Teaching::with('semester')->get()
                ->map(function ($teaching) {
                    // Extraire le numéro de semestre du titre
                    // Formats supportés: R1.xx, S1.xx, SAE 1.xx, SAÉ 1.xx
                    $semesterNumber = null;
                    if (preg_match('/^R(\d)\./', $teaching->title, $matches)) {
                        $semesterNumber = (int)$matches[1];
                    } elseif (preg_match('/^S(?:A[EÉ])?\s*(\d)\./', $teaching->title, $matches)) {
                        // S1, SA1, SAE1, SAÉ1 (groupe non capturant pour A[EÉ])
                        $semesterNumber = (int)$matches[1];
                    }
                    
                    // Utiliser semester_number de la relation si disponible, sinon le calculé, sinon null
                    $semester = $teaching->semester?->semester_number ?? $semesterNumber;
                    
                    return [
                        'id' => $teaching->id,
                        'name' => $teaching->title,
                        'apogee_code' => $teaching->apogee_code,
                        'tp_hours_initial' => $teaching->tp_hours_initial,
                        'tp_hours_continued' => $teaching->tp_hours_continued,
                        'td_hours_intial' => $teaching->td_hours_intial,
                        'td_hours_continued' => $teaching->td_hours_continued,
                        'cm_hours' => $teaching->cm_hours,
                        'semester_id' => $teaching->semester_id,
                        'semester' => $semester,
                    ];
                });

            return response()->json($teachings);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeachingsByTeacher($teacher_id): JsonResponse
    {
        try {
            // Vérifie si l'enseignant existe
            $teacher = Teacher::find($teacher_id);
            if (!$teacher) {
                return response()->json([
                    'error' => 'Enseignant non trouvé'
                ], 404);
            }

            // Récupère les enseignements assignés à cet enseignant
            $teachings = $teacher->teachings->map(function ($teaching) {
                return [
                    'id' => $teaching->id,
                    'title' => $teaching->title,
                    'apogee_code' => $teaching->apogee_code,
                ];
            });

            return response()->json($teachings);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeaching($teaching_id): JsonResponse
    {
        try {
            // Vérifie si l'enseignement existe
            $teaching = Teaching::with(['teachers', 'year'])
                ->find($teaching_id);

            if (!$teaching) {
                return response()->json([
                    'error' => 'Enseignement non trouvé'
                ], 404);
            }

            // Prépare la réponse avec les données de l'enseignement
            $response = [
                'id' => $teaching->id,
                'title' => $teaching->title,
                'apogee_code' => $teaching->apogee_code,
                'tp_hours_initial' => $teaching->tp_hours_initial,
                'tp_hours_continued' => $teaching->tp_hours_continued,
                'td_hours_initial' => $teaching->td_hours_initial,
                'td_hours_continued' => $teaching->td_hours_continued,
                'cm_hours_initial' => $teaching->cm_hours_initial,
                'cm_hours_continued' => $teaching->cm_hours_continued,
                'semester' => $teaching->semester?->semester_number,
                'trimester' => $teaching->trimester?->trimester_number,
                'year' => [
                    'id' => $teaching->year->id,
                    'name' => $teaching->year->name
                ],
                'teachers' => $teaching->teachers->map(function ($teacher) {
                    return [
                        'id' => $teacher->id,
                        'acronym' => $teacher->acronym,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name
                    ];
                })
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeTeaching(Request $request, $year): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'apogee_code' => 'required|string|max:50',
                'tp_hours_initial' => 'nullable|numeric|min:0',
                'tp_hours_continued' => 'nullable|numeric|min:0',
                'td_hours_initial' => 'nullable|numeric|min:0',
                'td_hours_continued' => 'nullable|numeric|min:0',
                'cm_hours_initial' => 'nullable|numeric|min:0',
                'cm_hours_continued' => 'nullable|numeric|min:0',
                'semester' => 'nullable|integer|min:1|max:6',
                'trimester' => 'nullable|integer|min:1|max:4'
            ]);

            // Vérifie qu'un seul des deux est spécifié
            if ($request->semester && $request->trimester) {
                return response()->json([
                    'error' => 'Un enseignement ne peut pas être lié à la fois à un semestre et à un trimestre'
                ], 422);
            }

            if (!$request->semester && !$request->trimester) {
                return response()->json([
                    'error' => 'Un enseignement doit être lié soit à un semestre, soit à un trimestre'
                ], 422);
            }

            // Vérifie si l'année existe
            $yearExists = Year::find($year);
            if (!$yearExists) {
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            // Vérifie si un enseignement avec le même code apogée existe déjà pour cette année
            $existingTeaching = Teaching::where('apogee_code', $request->apogee_code)
                ->where('year_id', $year)
                ->first();

            if ($existingTeaching) {
                return response()->json([
                    'error' => 'Un enseignement avec ce code apogée existe déjà pour cette année'
                ], 422);
            }

            // Trouve le semestre ou le trimestre correspondant
            $semester_id = null;
            $trimester_id = null;

            if ($request->semester) {
                $semester = Semester::where('semester_number', $request->semester)
                    ->where('year_id', $year)
                    ->first();

                if (!$semester) {
                    return response()->json([
                        'error' => 'Le semestre spécifié n\'existe pas pour cette année'
                    ], 422);
                }

                $semester_id = $semester->id;
            } else {
                $trimester = Trimester::where('trimester_number', $request->trimester)
                    ->where('year_id', $year)
                    ->first();

                if (!$trimester) {
                    return response()->json([
                        'error' => 'Le trimestre spécifié n\'existe pas pour cette année'
                    ], 422);
                }

                $trimester_id = $trimester->id;
            }

            $teaching = Teaching::create([
                'title' => $request->title,
                'apogee_code' => $request->apogee_code,
                'tp_hours_initial' => $request->tp_hours_initial,
                'tp_hours_continued' => $request->tp_hours_continued,
                'td_hours_initial' => $request->td_hours_initial,
                'td_hours_continued' => $request->td_hours_continued,
                'cm_hours_initial' => $request->cm_hours_initial,
                'cm_hours_continued' => $request->cm_hours_continued,
                'semester_id' => $semester_id,
                'trimester_id' => $trimester_id,
                'year_id' => $year
            ]);

            return response()->json([
                'message' => 'Enseignement créé avec succès',
                'teaching' => [
                    'id' => $teaching->id,
                    'title' => $teaching->title,
                    'apogee_code' => $teaching->apogee_code,
                    'tp_hours_initial' => $teaching->tp_hours_initial,
                    'tp_hours_continued' => $teaching->tp_hours_continued,
                    'td_hours_initial' => $teaching->td_hours_initial,
                    'td_hours_continued' => $teaching->td_hours_continued,
                    'cm_hours_initial' => $teaching->cm_hours_initial,
                    'cm_hours_continued' => $teaching->cm_hours_continued,
                    'semester_id' => $teaching->semester_id,
                    'trimester_id' => $teaching->trimester_id,
                    'year_id' => $teaching->year_id
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTeaching(Request $request, $teaching_id): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'apogee_code' => 'required|string|max:50',
                'tp_hours_initial' => 'nullable|numeric|min:0',
                'tp_hours_continued' => 'nullable|numeric|min:0',
                'td_hours_initial' => 'nullable|numeric|min:0',
                'td_hours_continued' => 'nullable|numeric|min:0',
                'cm_hours_initial' => 'nullable|numeric|min:0',
                'cm_hours_continued' => 'nullable|numeric|min:0',
                'semester' => 'nullable|integer|min:1|max:6',
                'trimester' => 'nullable|integer|min:1|max:4'
            ]);

            // Vérifie si l'enseignement existe
            $teaching = Teaching::with(['teachers', 'year'])->find($teaching_id);
            if (!$teaching) {
                return response()->json([
                    'error' => 'Enseignement non trouvé'
                ], 404);
            }

            // Vérifie si un autre enseignement avec le même code apogée existe déjà pour cette année
            $existingTeaching = Teaching::where('apogee_code', $request->apogee_code)
            ->where('id', '!=', $teaching->id)
                ->where('year_id', $teaching->year_id)
                ->first();

            if ($existingTeaching) {
                return response()->json([
                    'error' => 'Un enseignement avec ce code apogée existe déjà pour cette année'
                ], 422);
            }

            // Trouve le semestre ou le trimestre correspondant
            $semester_id = null;
            $trimester_id = null;

            if ($request->semester) {
                $semester = Semester::where('semester_number', $request->semester)
                    ->where('year_id', $teaching->year_id)
                    ->first();

                if (!$semester) {
                    return response()->json([
                        'error' => 'Le semestre spécifié n\'existe pas pour cette année'
                    ], 422);
                }

                $semester_id = $semester->id;
            } else {
                $trimester = Trimester::where('trimester_number', $request->trimester)
                    ->where('year_id', $teaching->year_id)
                    ->first();

                if (!$trimester) {
                    return response()->json([
                        'error' => 'Le trimestre spécifié n\'existe pas pour cette année'
                    ], 422);
                }

                $trimester_id = $trimester->id;
            }

            $teaching->update([
                'title' => $request->title,
                'apogee_code' => $request->apogee_code,
                'tp_hours_initial' => $request->tp_hours_initial,
                'tp_hours_continued' => $request->tp_hours_continued,
                'td_hours_initial' => $request->td_hours_initial,
                'td_hours_continued' => $request->td_hours_continued,
                'cm_hours_initial' => $request->cm_hours_initial,
                'cm_hours_continued' => $request->cm_hours_continued,
                'semester_id' => $semester_id,
                'trimester_id' => $trimester_id,
            ]);

            // Recharge les relations pour la réponse
            $teaching->load(['teachers', 'year']);

            return response()->json([
                'message' => 'Enseignement modifié avec succès',
                'teaching' => [
                    'id' => $teaching->id,
                    'title' => $teaching->title,
                    'apogee_code' => $teaching->apogee_code,
                    'tp_hours_initial' => $teaching->tp_hours_initial,
                    'tp_hours_continued' => $teaching->tp_hours_continued,
                    'td_hours_initial' => $teaching->td_hours_initial,
                    'td_hours_continued' => $teaching->td_hours_continued,
                    'cm_hours_initial' => $teaching->cm_hours_initial,
                    'cm_hours_continued' => $teaching->cm_hours_continued,
                    'semester_id' => $teaching->semester_id,
                    'trimester_id' => $teaching->trimester_id,
                    'year' => [
                        'id' => $teaching->year->id,
                        'name' => $teaching->year->name
                    ],
                    'teachers' => $teaching->teachers->map(function ($teacher) {
                        return [
                            'id' => $teacher->id,
                            'acronym' => $teacher->acronym,
                            'first_name' => $teacher->first_name,
                            'last_name' => $teacher->last_name
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], 500);
        }
    }

    public function deleteTeaching($teaching_id): JsonResponse
    {
        try {
            // Vérifie si l'enseignement existe
            $teaching = Teaching::with(['teachers', 'year'])->find($teaching_id);
            if (!$teaching) {
                return response()->json([
                    'error' => 'Enseignement non trouvé'
                ], 404);
            }

            $teaching->delete();
            return response()->json([]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], 500);
        }
    }
}
