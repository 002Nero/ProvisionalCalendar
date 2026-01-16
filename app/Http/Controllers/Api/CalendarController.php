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
     * Accepte des paramètres optionnels: promotion_id, group_id, subgroup pour filtrer les résultats.
     */
    public function getEdtSlots(Request $request, $year_id, $week_number): JsonResponse
    {
        try {
            // Read optional filters from query params
            $promotionId = $request->query('promotion_id');
            $groupId = $request->query('group_id');
            $subgroup = $request->query('subgroup');
            
            // Prefer a direct join by weeks to find edt_slot rows for the given year and week_number.
            // Build select list dynamically to avoid referencing columns that may not exist in all environments.
            $hasStartHour = Schema::hasColumn('edt_slot', 'start_hour');
            $hasStartTime = Schema::hasColumn('edt_slot', 'start_time');
            $hasDayOfWeek = Schema::hasColumn('edt_slot', 'day_of_week');
            $hasDay = Schema::hasColumn('edt_slot', 'day');

            if ($hasStartHour && $hasStartTime) {
                $startSelect = DB::raw("COALESCE(edt_slot.start_hour, edt_slot.start_time) as start_hour");
                $startOrderExpr = "COALESCE(edt_slot.start_hour, edt_slot.start_time)";
            } elseif ($hasStartHour) {
                $startSelect = 'edt_slot.start_hour';
                $startOrderExpr = 'edt_slot.start_hour';
            } elseif ($hasStartTime) {
                $startSelect = 'edt_slot.start_time';
                $startOrderExpr = 'edt_slot.start_time';
            } else {
                $startSelect = DB::raw("NULL as start_hour");
                $startOrderExpr = null;
            }

            if ($hasDayOfWeek && $hasDay) {
                $daySelect = DB::raw("COALESCE(edt_slot.day_of_week, edt_slot.day) as day_of_week");
                $dayOrderExpr = "COALESCE(edt_slot.day_of_week, edt_slot.day)";
            } elseif ($hasDayOfWeek) {
                $daySelect = 'edt_slot.day_of_week';
                $dayOrderExpr = 'edt_slot.day_of_week';
            } elseif ($hasDay) {
                $daySelect = 'edt_slot.day';
                $dayOrderExpr = 'edt_slot.day';
            } else {
                $daySelect = DB::raw("NULL as day_of_week");
                $dayOrderExpr = null;
            }

            // Select basic edt_slot columns (use * to avoid referencing non-existent fields)
            // Join slots first, then join weeks via slots.week_id (edt_slot does not have week_id column)
            $query = DB::table('edt_slot')
                ->leftJoin('slots', 'edt_slot.slot_id', '=', 'slots.id')
                ->leftJoin('weeks', 'slots.week_id', '=', 'weeks.id')
                ->leftJoin('rooms', 'edt_slot.room_id', '=', 'rooms.id')
                ->where('weeks.year_id', $year_id)
                ->where('weeks.week_number', $week_number)
                ->select('edt_slot.*', 'rooms.name as room_name');

            // apply ordering only when we have valid expressions
            if (!empty($dayOrderExpr)) {
                $query->orderByRaw($dayOrderExpr . ' asc');
            }
            if (!empty($startOrderExpr)) {
                $query->orderByRaw($startOrderExpr . ' asc');
            }

            $rows = $query->get();

            // Collect slot_ids referenced and load the authoritative slot data
            $slotIds = collect($rows)->pluck('slot_id')->filter()->unique()->values()->all();
            $slots = [];
            $slotTypesColors = [];
            if (!empty($slotIds)) {
                $query = Slot::whereIn('id', $slotIds)->with(['teaching', 'Promotion', 'Group', 'Subgroup']);
                
                // Apply filters if provided - use OR logic to include parent/child relationships
                // A slot can be for: promotion only (CM), group (TD), or subgroup (TP)
                if ($promotionId || $groupId || $subgroup) {
                    $query->where(function($q) use ($promotionId, $groupId, $subgroup) {
                        if ($promotionId) {
                            // Include slots for this promotion (CM level) or any group/subgroup in this promotion
                            $q->Where('promotion_id', $promotionId);
                        }
                        if ($groupId) {
                            // Include slots for this specific group (TD level)
                                $q->Where(function($q2) use ($groupId) {
                                $q2->where('group_id', $groupId)
                                    ->orWhereNull('group_id');
                            });
                        }
                        if ($subgroup) {
                            // Convertir A/B → id
                            $subgroupMap = ["A" => 1, "B" => 2];
                            $subgroupId = $subgroupMap[$subgroup] ?? null;

                            if ($subgroupId) {
                                $q->where(function ($q2) use ($subgroupId) {
                                    $q2->where('subgroup_id', $subgroupId)
                                    ->orWhereNull('subgroup_id');
                                });
                            }
                        }
                    });
                }
                
                $slotModels = $query->get()->keyBy('id');

                // load colors for slot types
                $typeIds = $slotModels->pluck('type_id')->filter()->unique()->values()->all();
                $examTypeId = null;
                if (!empty($typeIds)) {
                    $slotTypesColors = DB::table('slot_types')->whereIn('id', $typeIds)->pluck('color', 'id')->toArray();
                    $slotTypesAcronyms = DB::table('slot_types')->whereIn('id', $typeIds)->pluck('acronym', 'id')->toArray();
                } else {
                    $slotTypesAcronyms = [];
                }
                // load exam slot type color
                $examTypeRow = DB::table('slot_types')->where('acronym', 'EX')->first();
                if ($examTypeRow) {
                    $examTypeId = $examTypeRow->id;
                    $slotTypesColors[$examTypeId] = $examTypeRow->color;
                }
                // load teachers from pivot table for these slots
                $teachersRows = DB::table('slots_teachers')->whereIn('slot_id', $slotIds)->get();
                $teachersBySlot = [];
                foreach ($teachersRows as $tr) {
                    if (!isset($teachersBySlot[$tr->slot_id])) $teachersBySlot[$tr->slot_id] = [];
                    $teachersBySlot[$tr->slot_id][] = $tr->teacher_id;
                }

                if ($slotModels && count($slotModels) > 0) {
                    foreach ($slotModels as $id => $s) {
                        $slots[$id] = $s;
                        $slots[$id]->teacher_ids = $teachersBySlot[$id] ?? [];
                    }
                }
            }

            // Build response mapping edt_slot rows to enriched objects using slot data
            $result = [];
            foreach ($rows as $r) {
                $slotInfo = null;
                if (!empty($r->slot_id) && isset($slots[$r->slot_id])) {
                    $slot = $slots[$r->slot_id];
                    $teacherId = !empty($slot->teacher_ids) ? $slot->teacher_ids[0] : null;
                    $teacher = $teacherId ? Teacher::with('user')->find($teacherId) : null;
                    
                    // Build teacher name: try first_name/last_name from teacher, then from user, then acronym
                    $teacherName = null;
                    if ($teacher) {
                        $firstName = $teacher->first_name ?? null;
                        $lastName = $teacher->last_name ?? null;
                        
                        // If teacher doesn't have first/last name, get from user
                        if ((!$firstName || !$lastName) && $teacher->user) {
                            $firstName = $firstName ?: ($teacher->user->first_name ?? null);
                            $lastName = $lastName ?: ($teacher->user->last_name ?? null);
                        }
                        
                        $teacherName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
                        if (empty($teacherName)) {
                            $teacherName = $teacher->acronym ?? null;
                        }
                    }
                    
                    // Determine final color: use exam color if is_exam=1, else use type color
                    $finalColor = null;
                    if ($slot->is_exam && $examTypeId && isset($slotTypesColors[$examTypeId])) {
                        $finalColor = $slotTypesColors[$examTypeId];
                    } elseif ($slot->type_id && isset($slotTypesColors[$slot->type_id])) {
                        $finalColor = $slotTypesColors[$slot->type_id];
                    }

                    $slotInfo = [
                        'slot_id' => $slot->id,
                        'duration' => $slot->duration,
                        'teaching_id' => $slot->teaching_id,
                        'teaching_label' => ($slot->teaching) ? $slot->teaching->label : null,
                        'teaching_code' => ($slot->teaching && isset($slot->teaching->apogee_code)) ? $slot->teaching->apogee_code : null,
                        'promotion_id' => $slot->promotion_id ?? null,
                        'group_id' => $slot->group_id ?? null,
                        'subgroup_id' => $slot->subgroup_id ?? null,
                        'type_id' => $slot->type_id ?? null,
                        'type_acronym' => $slot->type_id && isset($slotTypesAcronyms[$slot->type_id]) ? $slotTypesAcronyms[$slot->type_id] : null,
                        'type_color' => $finalColor,
                        'is_exam' => $slot->is_exam ?? false,
                        'teacher_id' => $teacher ? $teacher->id : null,
                        'teacher_code' => ($teacher && isset($teacher->acronym)) ? $teacher->acronym : null,
                        'teacher_name' => $teacherName
                    ];
                }

                // derive start and day values from edt_slot row
                $start = property_exists($r, 'start_hour') ? $r->start_hour : (property_exists($r, 'start_time') ? $r->start_time : null);
                $day = property_exists($r, 'day_of_week') ? $r->day_of_week : (property_exists($r, 'day') ? $r->day : null);
                      
                $result[] = array_merge([
                    'id' => $r->id,
                    'start_hour' => $start,
                    'day_of_week' => $day,
                    'room_id' => $r->room_id,
                    'room_name' => $r->room_name ?? null,
                ], is_array($slotInfo) ? $slotInfo : []);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde des placements edt_slot (updates uniquement)
     */
    public function storeEdtSlotsBulk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'updates' => 'nullable|array',
                'updates.*.edt_slot_id' => 'required|integer',
                'updates.*.day_of_week' => 'required|string',
                'updates.*.start_hour' => ['required','regex:/^\d{2}:\d{2}$/'],
                'updates.*.room_id' => 'required|exists:rooms,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Données invalides', 'messages' => $validator->errors()], 422);
            }

            $updated = [];
            $errors = [];

            // Handle updates
            $updates = $request->input('updates', []);
            foreach ($updates as $p) {
                $edtSlotId = $p['edt_slot_id'] ?? null;
                
                if (!empty($edtSlotId)) {
                    // Load the current edt_slot to get the associated slot and week info
                    $edtSlot = DB::table('edt_slot')->where('id', $edtSlotId)->first();
                    if (!$edtSlot) {
                        $errors[] = "edt_slot {$edtSlotId} non trouvé";
                        continue;
                    }
                    
                    // Get slot info to find teacher info
                    $slot = DB::table('slots')->where('id', $edtSlot->slot_id)->first();
                    if ($slot) {
                        // Get teacher for this slot from pivot table
                        $teacherRow = DB::table('slots_teachers')->where('slot_id', $slot->id)->first();
                        $teacherId = $teacherRow ? $teacherRow->teacher_id : null;
                        
                        // If teacher exists, check for conflicts
                        if ($teacherId) {
                            $week = DB::table('weeks')->where('id', $slot->week_id)->first();
                            
                            // Parse times
                            $timeParts = explode(':', $p['start_hour']);
                            $newStartMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
                            $newEndMinutes = $newStartMinutes + ($slot->duration * 60);
                            $newDayOfWeek = trim($p['day_of_week']);
                            
                            // Check for conflicts with other placements for this teacher
                            $conflicts = DB::table('edt_slot as es')
                                ->join('slots as s', 'es.slot_id', '=', 's.id')
                                ->join('slots_teachers as st', 's.id', '=', 'st.slot_id')
                                ->where('st.teacher_id', $teacherId)
                                ->where('es.day_of_week', $newDayOfWeek)
                                ->where('s.week_id', $week->id) // Check by slot.week_id
                                ->where('es.id', '!=', $edtSlotId) // Exclude current slot
                                ->select('es.start_hour', 's.duration')
                                ->get();
                            
                            $hasConflict = false;
                            foreach ($conflicts as $existing) {
                                $existingTimeParts = explode(':', $existing->start_hour);
                                $existingStartMinutes = intval($existingTimeParts[0]) * 60 + intval($existingTimeParts[1]);
                                $existingEndMinutes = $existingStartMinutes + ($existing->duration * 60);
                                
                                // Check for overlap
                                if (!($newEndMinutes <= $existingStartMinutes || $newStartMinutes >= $existingEndMinutes)) {
                                    $hasConflict = true;
                                    break;
                                }
                            }
                            
                            if ($hasConflict) {
                                $errors[] = "Conflit d'emploi du temps : l'enseignant a déjà un cours à ce créneau pour edt_slot {$edtSlotId}";
                                continue;
                            }
                        }
                        
                        // Check for room conflict (room cannot be used by multiple slots at same time)
                        $newRoomId = $p['room_id'];
                        $roomConflicts = DB::table('edt_slot as es')
                            ->join('slots as s', 'es.slot_id', '=', 's.id')
                            ->where('es.room_id', $newRoomId)
                            ->where('es.day_of_week', $newDayOfWeek)
                            ->where('s.week_id', $week->id)
                            ->where('es.id', '!=', $edtSlotId) // Exclude current slot
                            ->select('es.start_hour', 's.duration')
                            ->get();
                        
                        $hasRoomConflict = false;
                        foreach ($roomConflicts as $existing) {
                            $existingTimeParts = explode(':', $existing->start_hour);
                            $existingStartMinutes = intval($existingTimeParts[0]) * 60 + intval($existingTimeParts[1]);
                            $existingEndMinutes = $existingStartMinutes + ($existing->duration * 60);
                            
                            // Check for overlap
                            if (!($newEndMinutes <= $existingStartMinutes || $newStartMinutes >= $existingEndMinutes)) {
                                $hasRoomConflict = true;
                                break;
                            }
                        }
                        
                        if ($hasRoomConflict) {
                            $errors[] = "Conflit de salle : cette salle est déjà occupée à ce créneau horaire pour edt_slot {$edtSlotId}";
                            continue;
                        }
                    }
                    
                    $updateData = [
                        'day_of_week' => $p['day_of_week'],
                        'start_hour' => $p['start_hour'],
                        'room_id' => $p['room_id'],
                        'updated_at' => now()
                    ];
                    
                    $result = DB::table('edt_slot')->where('id', $edtSlotId)->update($updateData);
                    if ($result) {
                        $updated[] = $edtSlotId;
                    } else {
                        $errors[] = "Impossible de mettre à jour edt_slot {$edtSlotId}";
                    }
                }
            }

            $status = empty($errors) ? 200 : 207;
            $message = count($updated) . ' mise(s) à jour';
            return response()->json(['message' => $message, 'updated' => $updated, 'errors' => $errors], $status);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crée un nouveau placement edt_slot
     */
    public function createEdtSlot(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year_id' => 'required|exists:years,id',
                'week_number' => 'required|integer',
                'teaching_id' => 'required|exists:teachings,id',
                'duration' => 'required|numeric|min:0',
                'type' => 'required|in:CM,TD,TP',
                'day_of_week' => 'required|string',
                'start_hour' => ['required','regex:/^\d{2}:\d{2}$/'],
                'room_id' => 'required|exists:rooms,id',
                'teacher_id' => 'nullable|exists:teachers,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Données invalides', 'messages' => $validator->errors()], 422);
            }

            $week = Week::where('year_id', $request->year_id)->where('week_number', $request->week_number)->first();
            if (!$week) {
                return response()->json(['error' => 'Semaine introuvable'], 404);
            }

            $teaching = Teaching::find($request->teaching_id);
            if (!$teaching) {
                return response()->json(['error' => 'Enseignement introuvable'], 404);
            }

            // Get teacher (optional). If none is supplied, we allow creating the slot without a teacher.
            $teacher = null;
            $teacherId = null;
            if (!empty($request->teacher_id)) {
                $teacher = Teacher::find($request->teacher_id);
                $teacherId = $request->teacher_id;
            }

            // Vérifier les conflits d'enseignant : l'enseignant ne peut pas avoir deux cours le même jour au même créneau
            if ($teacherId) {
                // Parse start_hour to get time in minutes
                $timeParts = explode(':', $request->start_hour);
                $startMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
                $endMinutes = $startMinutes + ($request->duration * 60);
                
                // Get day_of_week as string
                $dayOfWeek = trim($request->day_of_week);
                
                // Find all existing edt_slot placements for this teacher on the same day
                $conflict = DB::table('edt_slot as es')
                    ->join('slots as s', 'es.slot_id', '=', 's.id')
                    ->join('slots_teachers as st', 's.id', '=', 'st.slot_id')
                    ->where('st.teacher_id', $teacherId)
                    ->where('es.day_of_week', $dayOfWeek)
                    ->where('s.week_id', $week->id) // Check by slot.week_id (edt_slot doesn't have week_id)
                    ->select('es.start_hour', 's.duration')
                    ->get();
                
                // Check for time overlap
                foreach ($conflict as $existing) {
                    $existingTimeParts = explode(':', $existing->start_hour);
                    $existingStartMinutes = intval($existingTimeParts[0]) * 60 + intval($existingTimeParts[1]);
                    $existingEndMinutes = $existingStartMinutes + ($existing->duration * 60);
                    
                    // Check if there's an overlap
                    if (!($endMinutes <= $existingStartMinutes || $startMinutes >= $existingEndMinutes)) {
                        return response()->json([
                            'error' => 'Conflit d\'emploi du temps : cet enseignant a déjà un cours à ce créneau'
                        ], 422);
                    }
                }
            }

            // Vérifier les conflits de salle : une salle ne peut pas être utilisée par deux groupes au même moment
            $timeParts = explode(':', $request->start_hour);
            $startMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
            $endMinutes = $startMinutes + ($request->duration * 60);
            $dayOfWeek = trim($request->day_of_week);
            $roomId = $request->room_id;
            
            $roomConflict = DB::table('edt_slot as es')
                ->join('slots as s', 'es.slot_id', '=', 's.id')
                ->where('es.room_id', $roomId)
                ->where('es.day_of_week', $dayOfWeek)
                ->where('s.week_id', $week->id)
                ->select('es.start_hour', 's.duration')
                ->get();
            
            // Check for time overlap for room
            foreach ($roomConflict as $existing) {
                $existingTimeParts = explode(':', $existing->start_hour);
                $existingStartMinutes = intval($existingTimeParts[0]) * 60 + intval($existingTimeParts[1]);
                $existingEndMinutes = $existingStartMinutes + ($existing->duration * 60);
                
                // Check if there's an overlap
                if (!($endMinutes <= $existingStartMinutes || $startMinutes >= $existingEndMinutes)) {
                    return response()->json([
                        'error' => 'Conflit de salle : cette salle est déjà occupée à ce créneau horaire'
                    ], 422);
                }
            }

            // Find slot type
            $typeAcr = strtoupper(trim($request->type ?? ''));
            $slotTypeRow = DB::table('slot_types')->whereRaw('UPPER(acronym) = ?', [$typeAcr])->first();
            if (!$slotTypeRow) {
                $slotTypeRow = DB::table('slot_types')->first();
            }
            $typeId = $slotTypeRow->id ?? null;

            // Get room amount
            $roomAmount = 1;
            $roomRow = DB::table('rooms')->where('id', $request->room_id)->first();
            if ($roomRow) {
                $roomAmount = $roomRow->seat_capacity ?? 1;
            }

            // Create slot
            $slot = Slot::create([
                'duration' => $request->duration,
                'teaching_id' => $teaching->id,
                'promotion_id' => $request->promotion_id ?? null,
                'group_id' => $request->group_id ?? null,
                'subgroup_id' => $request->subgroup_id ?? null,
                'room_amount' => $roomAmount,
                'is_neutralized' => $request->is_neutralized ?? false,
                'week_id' => $week->id,
                'type_id' => $typeId,
                'is_exam' => false
            ]);

            // Create pivot only if a teacher is provided
            if ($teacher) {
                DB::table('slots_teachers')->insert([
                    'slot_id' => $slot->id,
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create edt_slot
            $edtId = DB::table('edt_slot')->insertGetId([
                'day_of_week' => $request->day_of_week,
                'start_hour' => $request->start_hour,
                'slot_id' => $slot->id,
                'room_id' => $request->room_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'Placement créé', 'edt_id' => $edtId], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprime un placement edt_slot
     */
    public function deleteEdtSlot(Request $request, $id): JsonResponse
    {
        try {
            $result = DB::table('edt_slot')->where('id', $id)->delete();
            
            if ($result) {
                return response()->json(['message' => 'Placement supprimé'], 200);
            } else {
                return response()->json(['error' => 'Placement introuvable'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Une erreur est survenue', 'message' => $e->getMessage()], 500);
        }
    }
}
