<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Week;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Slot;
use App\Models\Teaching;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CalendarController extends Controller
{

    /**
     * Crée un nouveau slot dans l'emploi du temps
     */
    public function storeSlot(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'duration' => 'required|numeric|min:0',
                'teacher_id' => 'required|exists:teachers,id',
                'teaching_id' => 'required|exists:teachings,id',
                'substitute_teacher_id' => 'nullable|exists:teachers,id',
                'promotion_id' => 'nullable|exists:promotions,id',
                'group_id' => 'nullable|exists:groups,id',
                'subgroup_id' => 'nullable|exists:subgroups,id',
                'is_neutralized' => 'boolean',
                'week_id' => 'required|exists:weeks,id',
                'type' => 'required|in:CM,TD,TP'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Données invalides',
                    'messages' => $validator->errors()
                ], 422);
            }

            // Vérifier que l'enseignant n'a pas déjà un cours à ce moment
            $existingSlot = Slot::where('week_id', $request->week_id)
                ->where(function ($query) use ($request) {
                    $query->where('teacher_id', $request->teacher_id)
                        ->orWhere('substitute_teacher_id', $request->teacher_id);
                })
                ->first();

            if ($existingSlot) {
                return response()->json([
                    'error' => 'L\'enseignant a déjà un cours prévu à ce moment'
                ], 422);
            }

            // Créer le slot
            $slot = Slot::create($request->all());

            // Charger les relations pour la réponse
            $slot->load(['teacher', 'substituteTeacher', 'teaching', 'Promotion']);

            return response()->json([
                'message' => 'Slot créé avec succès',
                'slot' => $slot
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée en masse plusieurs slots (bulk) à partir d'un tableau de placements.
     * Attendu : { year_id, week_number, placements: [ { teaching_id, duration, type, promotion_id?, group_id?, subgroup_id?, substitute_teacher_id?, is_neutralized? } ] }
     */
    public function storeSlotsBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year_id' => 'required|exists:years,id',
                'week_number' => 'required|integer',
                'placements' => 'required|array|min:1',
                'placements.*.teaching_id' => 'required|exists:teachings,id',
                'placements.*.duration' => 'required|numeric|min:0',
                'placements.*.type' => 'required|in:CM,TD,TP'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Données invalides',
                    'messages' => $validator->errors()
                ], 422);
            }

            // Trouver la semaine correspondant à l'année + numéro
            $week = Week::where('year_id', $request->year_id)->where('week_number', $request->week_number)->first();
            if (!$week) {
                return response()->json(['error' => 'Semaine introuvable pour cette année'], 404);
            }

            $created = [];
            $errors = [];

            foreach ($request->placements as $idx => $p) {
                $teaching = Teaching::find($p['teaching_id']);
                if (!$teaching) {
                    $errors[] = "Enseignement introuvable: {$p['teaching_id']} (index {$idx})";
                    continue;
                }

                // Récupère un enseignant assigné à l'enseignement (nécessaire car teacher_id n'est pas nullable)
                $teacher = $teaching->teachers->first();
                if (!$teacher) {
                    $errors[] = "Aucun enseignant assigné pour l'enseignement {$teaching->id} (index {$idx})";
                    continue;
                }

                $slotData = [
                    'duration' => $p['duration'],
                    'teacher_id' => $teacher->id,
                    'teaching_id' => $teaching->id,
                    'substitute_teacher_id' => $p['substitute_teacher_id'] ?? null,
                    'promotion_id' => $p['promotion_id'] ?? null,
                    'group_id' => $p['group_id'] ?? null,
                    'subgroup_id' => $p['subgroup_id'] ?? null,
                    'is_neutralized' => $p['is_neutralized'] ?? false,
                    'week_id' => $week->id,
                    'type' => $p['type']
                ];

                $slot = Slot::create($slotData);
                $slot->load(['teacher', 'substituteTeacher', 'teaching', 'Promotion']);
                $created[] = $slot;
            }

            $status = empty($errors) ? 201 : 207;
            return response()->json([
                'message' => empty($errors) ? 'Slots créés avec succès' : 'Création partielle: certains éléments ont échoué',
                'created' => $created,
                'errors' => $errors
            ], $status);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getCalendarData($year_id): JsonResponse
    {
        try {
            // Récupérer les semaines avec leurs créneaux
            $weeks = Week::where('year_id', $year_id)
                        ->with([
                            'slots.teacher',
                            'slots.teaching',
                            'slots.substituteTeacher',
                            'slots.Promotion.Groups.Subgroups',
                            'slots.Group',
                            'slots.Subgroup'
                        ])
                        ->orderBy('week_number')
                        ->get();
            if ($weeks->isEmpty()) {
                return response()->json([]);
            }
            // Récupérer la promotion du premier slot trouvé
            $firstSlot = $weeks->pluck('slots')->flatten()->first();
            if (!$firstSlot) {
                return response()->json([]);
            }

            $promotion = $firstSlot->Promotion;
            if (!$promotion) {
                return response()->json([]);
            }

            $calendarData = $weeks->map(function ($week) use ($promotion) {
                return [
                    'week' => $week->week_number,
                    'groups' => $this->formatPromotionGroups($week->slots, $promotion)
                ];
            });

            return response()->json($calendarData);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function formatPromotionGroups($slots, $promotion)
    {
        return collect([
            [
                'contents' => $this->formatSlotContents($slots->where('promotion_id', $promotion->id)->where('type', 'CM')),
                'groups' => $this->formatGroups($slots->where('promotion_id', $promotion->id), $promotion->Groups)
            ]
        ]);
    }

    private function formatGroups($promotionSlots, $groups)
    {
        return $groups->map(function ($group) use ($promotionSlots) {
            $groupSlots = $promotionSlots->where('group_id', $group->id);

            return [
                'contents' => $this->formatSlotContents($groupSlots->where('type', 'TD')),
                'groups' => $this->formatSubgroups($groupSlots, $group->Subgroups)
            ];
        })->values();
    }

    private function formatSubgroups($groupSlots, $subgroups)
    {
        return $subgroups->map(function ($subgroup) use ($groupSlots) {
            $subgroupSlots = $groupSlots->where('subgroup_id', $subgroup->id);

            return [
                'contents' => $this->formatSlotContents($subgroupSlots->where('type', 'TP'))
            ];
        })->values();
    }

    private function formatSlotContents($slots)
    {
        return $slots->map(function ($slot) {
            $data = [
                'hours' => $slot->duration,
                'type' => $slot->type,
                'teacherId' => $slot->teacher ? $slot->teacher->id : null,
                'teacherCode' => $slot->teacher ? $slot->teacher->acronym : null,
                'substituteId' => $slot->substituteTeacher ? $slot->substituteTeacher->id : null,
                'isNeutralized' => $slot->is_neutralized
            ];

            // Ajouter les IDs en fonction du type de slot
            switch($slot->type) {
                case 'CM':
                    $data['promotionId'] = $slot->promotion_id;
                    break;
                case 'TD':
                    $data['groupId'] = $slot->group_id;
                    break;
                case 'TP':
                    $data['subgroupId'] = $slot->subgroup_id;
                    break;
            }

            return $data;
        })->values()->all();
    }

    /**
     * Retourne les edt_slot pour une année + numéro de semaine (avec données de slot jointes)
     */
    public function getEdtSlots($year_id, $week_number): JsonResponse
    {
        try {
            $week = Week::where('year_id', $year_id)->where('week_number', $week_number)->first();
            if (!$week) {
                return response()->json(['error' => 'Semaine introuvable pour cette année'], 404);
            }

            $rows = DB::table('edt_slot')
                ->leftJoin('teachers', 'edt_slot.teacher_id', '=', 'teachers.id')
                ->where('edt_slot.week_id', $week->id)
                ->select(
                    'edt_slot.id as id',
                    'edt_slot.start_hour',
                    'edt_slot.day_of_week',
                    'edt_slot.room_id',
                    'edt_slot.slot_id',
                    'edt_slot.teaching_id',
                    'edt_slot.duration',
                    'edt_slot.promotion_id',
                    'edt_slot.group_id',
                    'edt_slot.subgroup_id',
                    'edt_slot.type',
                    'edt_slot.teacher_id',
                    'teachers.acronym as teacher_code'
                )
                ->get();

            return response()->json($rows);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crée en masse des slots + edt_slot (positionnement dans la semaine)
     * Attendu: { year_id, week_number, placements: [ { teaching_id, duration, type, promotion_id?, group_id?, subgroup_id?, substitute_teacher_id?, is_neutralized?, day_of_week, start_hour, room_id } ] }
     */
    public function storeEdtSlotsBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year_id' => 'required|exists:years,id',
                'week_number' => 'required|integer',
                'placements' => 'required|array|min:1',
                'placements.*.teaching_id' => 'required|exists:teachings,id',
                'placements.*.duration' => 'required|numeric|min:0',
                'placements.*.type' => 'required|in:CM,TD,TP',
                'placements.*.day_of_week' => 'required|string',
                'placements.*.start_hour' => ['required','regex:/^\d{2}:\d{2}$/'],
                'placements.*.room_id' => 'required|exists:rooms,id',
                'placements.*.teacher_id' => 'nullable|exists:teachers,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Données invalides', 'messages' => $validator->errors()], 422);
            }

            $week = Week::where('year_id', $request->year_id)->where('week_number', $request->week_number)->first();
            if (!$week) {
                return response()->json(['error' => 'Semaine introuvable pour cette année'], 404);
            }

            $created = [];
            $errors = [];

            foreach ($request->placements as $idx => $p) {
                $teaching = Teaching::find($p['teaching_id']);
                if (!$teaching) {
                    $errors[] = "Enseignement introuvable: {$p['teaching_id']} (index {$idx})";
                    continue;
                }

                // Prefer teacher_id provided in placement, otherwise fallback to teaching's assigned teacher
                $teacher = null;
                if (!empty($p['teacher_id'])) {
                    $teacher = Teacher::find($p['teacher_id']);
                    if (!$teacher) {
                        $errors[] = "Enseignant introuvable id={$p['teacher_id']} (index {$idx})";
                        continue;
                    }
                } else {
                    $teacher = $teaching->teachers->first();
                    if (!$teacher) {
                        $errors[] = "Aucun enseignant assigné pour l'enseignement {$teaching->id} (index {$idx})";
                        continue;
                    }
                }

                // Determine room_amount from room if available
                $roomAmount = 1;
                if (!empty($p['room_id'])) {
                    $roomRow = DB::table('rooms')->where('id', $p['room_id'])->first();
                    if ($roomRow) {
                        $roomAmount = $roomRow->seat_capacity ?? 1;
                    }
                }

                // Find slot_type id by acronym (CM/TD/TP) or fallback to first
                $typeAcr = strtoupper(trim($p['type'] ?? ''));
                $slotTypeRow = DB::table('slot_types')->whereRaw('UPPER(acronym) = ?', [$typeAcr])->first();
                if (!$slotTypeRow) {
                    $slotTypeRow = DB::table('slot_types')->first();
                }
                $typeId = $slotTypeRow->id ?? null;

                // Build slot data with required fields present in current schema
                $slotData = [
                    'duration' => $p['duration'],
                    'teaching_id' => $teaching->id,
                    'promotion_id' => $p['promotion_id'] ?? null,
                    'group_id' => $p['group_id'] ?? null,
                    'subgroup_id' => $p['subgroup_id'] ?? null,
                    'room_amount' => $roomAmount,
                    'is_neutralized' => $p['is_neutralized'] ?? false,
                    'week_id' => $week->id,
                    'type_id' => $typeId,
                    'is_exam' => false
                ];

                // Create Slot (required by current schema) and attach teacher via pivot
                $slot = Slot::create($slotData);
                // attach teacher in slots_teachers pivot
                DB::table('slots_teachers')->insert([
                    'slot_id' => $slot->id,
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // create edt_slot referencing the new slot
                $insert = [
                    'start_hour' => $p['start_hour'],
                    'slot_id' => $slot->id,
                    'room_id' => $p['room_id'],
                    'day_of_week' => $p['day_of_week'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Map day_of_week -> day if database uses 'day' column
                if (isset($insert['day_of_week']) && Schema::hasColumn('edt_slot', 'day') && !Schema::hasColumn('edt_slot', 'day_of_week')) {
                    $insert['day'] = $insert['day_of_week'];
                }

                // Also ensure start_hour -> start_time mapping if needed
                if (isset($insert['start_hour']) && Schema::hasColumn('edt_slot', 'start_time') && !Schema::hasColumn('edt_slot', 'start_hour')) {
                    $insert['start_time'] = $insert['start_hour'];
                }

                // Remove any keys that don't exist in the current edt_slot schema to avoid SQL errors
                foreach (array_keys($insert) as $col) {
                    if (!Schema::hasColumn('edt_slot', $col)) {
                        unset($insert[$col]);
                    }
                }

                $edtId = DB::table('edt_slot')->insertGetId($insert);
                $created[] = ['edt_id' => $edtId, 'slot_id' => $slot->id];
            }

            $status = empty($errors) ? 201 : 207;
            return response()->json(['message' => empty($errors) ? 'EDT slots créés' : 'Création partielle', 'created' => $created, 'errors' => $errors], $status);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }
}
