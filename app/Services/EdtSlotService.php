<?php

namespace App\Services;

use App\Models\Slot;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EdtSlotService
{
    protected array $columnConfig;
    protected ?int $examTypeId = null;
    protected array $slotTypesColors = [];
    protected array $slotTypesAcronyms = [];

    public function __construct()
    {
        $this->columnConfig = $this->detectColumnConfiguration();
    }

    /**
     * Récupère les edt_slots pour une année et semaine donnée avec filtres optionnels
     */
    public function getEdtSlotsForWeek(int $yearId, int $weekNumber, array $filters = []): array
    {
        $rows = $this->fetchEdtSlotRows($yearId, $weekNumber);
        
        if ($rows->isEmpty()) {
            return [];
        }

        $slotIds = $this->extractSlotIds($rows);
        $slots = $this->loadSlotsWithFilters($slotIds, $filters);
        
        $this->loadSlotTypeData($slots);
        $slots = $this->attachTeachersToSlots($slots, $slotIds);

        return $this->buildResponse($rows, $slots, !empty($filters));
    }

    /**
     * Détecte la configuration des colonnes de la table edt_slot
     */
    protected function detectColumnConfiguration(): array
    {
        $hasStartHour = Schema::hasColumn('edt_slot', 'start_hour');
        $hasStartTime = Schema::hasColumn('edt_slot', 'start_time');
        $hasDayOfWeek = Schema::hasColumn('edt_slot', 'day_of_week');
        $hasDay = Schema::hasColumn('edt_slot', 'day');

        return [
            'start' => $this->resolveStartColumnConfig($hasStartHour, $hasStartTime),
            'day' => $this->resolveDayColumnConfig($hasDayOfWeek, $hasDay),
        ];
    }

    protected function resolveStartColumnConfig(bool $hasStartHour, bool $hasStartTime): array
    {
        if ($hasStartHour && $hasStartTime) {
            return [
                'select' => DB::raw("COALESCE(edt_slot.start_hour, edt_slot.start_time) as start_hour"),
                'order' => "COALESCE(edt_slot.start_hour, edt_slot.start_time)",
            ];
        }
        
        if ($hasStartHour) {
            return ['select' => 'edt_slot.start_hour', 'order' => 'edt_slot.start_hour'];
        }
        
        if ($hasStartTime) {
            return ['select' => 'edt_slot.start_time', 'order' => 'edt_slot.start_time'];
        }

        return ['select' => DB::raw("NULL as start_hour"), 'order' => null];
    }

    protected function resolveDayColumnConfig(bool $hasDayOfWeek, bool $hasDay): array
    {
        if ($hasDayOfWeek && $hasDay) {
            return [
                'select' => DB::raw("COALESCE(edt_slot.day_of_week, edt_slot.day) as day_of_week"),
                'order' => "COALESCE(edt_slot.day_of_week, edt_slot.day)",
            ];
        }
        
        if ($hasDayOfWeek) {
            return ['select' => 'edt_slot.day_of_week', 'order' => 'edt_slot.day_of_week'];
        }
        
        if ($hasDay) {
            return ['select' => 'edt_slot.day', 'order' => 'edt_slot.day'];
        }

        return ['select' => DB::raw("NULL as day_of_week"), 'order' => null];
    }

    /**
     * Récupère les lignes edt_slot brutes depuis la base
     */
    protected function fetchEdtSlotRows(int $yearId, int $weekNumber): Collection
    {
        $query = DB::table('edt_slot')
            ->leftJoin('slots', 'edt_slot.slot_id', '=', 'slots.id')
            ->leftJoin('weeks', 'slots.week_id', '=', 'weeks.id')
            ->leftJoin('rooms', 'edt_slot.room_id', '=', 'rooms.id')
            ->where('weeks.year_id', $yearId)
            ->where('weeks.week_number', $weekNumber)
            ->select('edt_slot.*', 'rooms.name as room_name');

        $this->applyOrdering($query);

        return $query->get();
    }

    protected function applyOrdering($query): void
    {
        if (!empty($this->columnConfig['day']['order'])) {
            $query->orderByRaw($this->columnConfig['day']['order'] . ' asc');
        }
        
        if (!empty($this->columnConfig['start']['order'])) {
            $query->orderByRaw($this->columnConfig['start']['order'] . ' asc');
        }
    }

    protected function extractSlotIds(Collection $rows): array
    {
        return $rows->pluck('slot_id')->filter()->unique()->values()->all();
    }

    /**
     * Charge les slots avec application des filtres
     */
    protected function loadSlotsWithFilters(array $slotIds, array $filters): Collection
    {
        if (empty($slotIds)) {
            return collect();
        }

        $query = Slot::whereIn('id', $slotIds)
            ->with(['teaching', 'Promotion', 'Group', 'Subgroup']);

        $this->applyFilters($query, $filters);

        return $query->get()->keyBy('id');
    }

    /**
     * Applique les filtres à la requête de slots
     * Extensible : pour ajouter de nouveaux filtres, créer une sous-classe et surcharger cette méthode
     */
    protected function applyFilters($query, array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        $query->where(function ($q) use ($filters) {
            $this->applyPromotionFilter($q, $filters);
            $this->applyGroupFilter($q, $filters);
            $this->applySubgroupFilter($q, $filters);
        });
    }

    protected function applyPromotionFilter($query, array $filters): void
    {
        if (!empty($filters['promotion_id'])) {
            $query->where('promotion_id', $filters['promotion_id']);
        }
    }

    protected function applyGroupFilter($query, array $filters): void
    {
        if (!empty($filters['group_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('group_id', $filters['group_id'])
                    ->orWhereNull('group_id');
            });
        }
    }

    protected function applySubgroupFilter($query, array $filters): void
    {
        if (!empty($filters['subgroup'])) {
            $subgroupId = $this->resolveSubgroupId($filters['subgroup']);
            
            if ($subgroupId) {
                $query->where(function ($q) use ($subgroupId) {
                    $q->where('subgroup_id', $subgroupId)
                        ->orWhereNull('subgroup_id');
                });
            }
        }
    }

    /**
     * Résout l'identifiant du sous-groupe à partir de son nom
     * Extensible : surcharger pour supporter d'autres mappings
     */
    protected function resolveSubgroupId(string $subgroupName): ?int
    {
        $subgroupMap = $this->getSubgroupMapping();
        return $subgroupMap[$subgroupName] ?? null;
    }

    /**
     * Retourne le mapping nom → id des sous-groupes
     * Extensible : surcharger pour personnaliser le mapping
     */
    protected function getSubgroupMapping(): array
    {
        return ['A' => 1, 'B' => 2];
    }

    /**
     * Charge les données des types de slots (couleurs, acronymes)
     */
    protected function loadSlotTypeData(Collection $slots): void
    {
        $typeIds = $slots->pluck('type_id')->filter()->unique()->values()->all();

        if (!empty($typeIds)) {
            $this->slotTypesColors = DB::table('slot_types')
                ->whereIn('id', $typeIds)
                ->pluck('color', 'id')
                ->toArray();
                
            $this->slotTypesAcronyms = DB::table('slot_types')
                ->whereIn('id', $typeIds)
                ->pluck('acronym', 'id')
                ->toArray();
        }

        $this->loadExamTypeData();
    }

    protected function loadExamTypeData(): void
    {
        $examTypeRow = DB::table('slot_types')->where('acronym', 'EX')->first();
        
        if ($examTypeRow) {
            $this->examTypeId = $examTypeRow->id;
            $this->slotTypesColors[$this->examTypeId] = $examTypeRow->color;
        }
    }

    /**
     * Attache les enseignants aux slots
     */
    protected function attachTeachersToSlots(Collection $slots, array $slotIds): Collection
    {
        if (empty($slotIds)) {
            return $slots;
        }

        $teachersBySlot = $this->loadTeachersBySlot($slotIds);

        foreach ($slots as $id => $slot) {
            $slot->teacher_ids = $teachersBySlot[$id] ?? [];
        }

        return $slots;
    }

    protected function loadTeachersBySlot(array $slotIds): array
    {
        $teachersRows = DB::table('slots_teachers')
            ->whereIn('slot_id', $slotIds)
            ->get();

        $teachersBySlot = [];
        foreach ($teachersRows as $tr) {
            $teachersBySlot[$tr->slot_id][] = $tr->teacher_id;
        }

        return $teachersBySlot;
    }

    /**
     * Construit la réponse finale
     */
    protected function buildResponse(Collection $rows, Collection $slots, bool $hasFilters): array
    {
        $result = [];

        foreach ($rows as $row) {
            if ($this->shouldSkipRow($row, $slots, $hasFilters)) {
                continue;
            }

            $result[] = $this->buildRowData($row, $slots);
        }

        return $result;
    }

    protected function shouldSkipRow($row, Collection $slots, bool $hasFilters): bool
    {
        return $hasFilters && !empty($row->slot_id) && !isset($slots[$row->slot_id]);
    }

    protected function buildRowData($row, Collection $slots): array
    {
        $baseData = $this->extractBaseRowData($row);
        $slotInfo = $this->buildSlotInfo($row, $slots);

        return array_merge($baseData, $slotInfo);
    }

    protected function extractBaseRowData($row): array
    {
        $start = property_exists($row, 'start_hour') 
            ? $row->start_hour 
            : (property_exists($row, 'start_time') ? $row->start_time : null);
            
        $day = property_exists($row, 'day_of_week') 
            ? $row->day_of_week 
            : (property_exists($row, 'day') ? $row->day : null);

        return [
            'id' => $row->id,
            'start_hour' => $start,
            'day_of_week' => $day,
            'room_id' => $row->room_id,
            'room_name' => $row->room_name ?? null,
        ];
    }

    protected function buildSlotInfo($row, Collection $slots): array
    {
        if (empty($row->slot_id) || !isset($slots[$row->slot_id])) {
            return [];
        }

        $slot = $slots[$row->slot_id];
        $teachers = $this->buildTeachersArray($slot->teacher_ids ?? []);
        $firstTeacher = !empty($teachers) ? $teachers[0] : null;

        return [
            'slot_id' => $slot->id,
            'duration' => $slot->duration,
            'teaching_id' => $slot->teaching_id,
            'teaching_label' => $slot->teaching?->title,
            'teaching_code' => $slot->teaching?->apogee_code,
            'promotion_id' => $slot->promotion_id,
            'group_id' => $slot->group_id,
            'subgroup_id' => $slot->subgroup_id,
            'type_id' => $slot->type_id,
            'type_acronym' => $this->getTypeAcronym($slot->type_id),
            'type_color' => $this->determineColor($slot),
            'is_exam' => (bool) ($slot->is_exam ?? false),
            'teachers' => $teachers,
            'teacher_id' => $firstTeacher['id'] ?? null,
            'teacher_code' => $firstTeacher['code'] ?? null,
            'teacher_name' => $firstTeacher['name'] ?? null,
        ];
    }

    protected function getTypeAcronym(?int $typeId): ?string
    {
        return $typeId && isset($this->slotTypesAcronyms[$typeId]) 
            ? $this->slotTypesAcronyms[$typeId] 
            : null;
    }

    protected function determineColor($slot): ?string
    {
        if ($slot->is_exam && $this->examTypeId && isset($this->slotTypesColors[$this->examTypeId])) {
            return $this->slotTypesColors[$this->examTypeId];
        }

        if ($slot->type_id && isset($this->slotTypesColors[$slot->type_id])) {
            return $this->slotTypesColors[$slot->type_id];
        }

        return null;
    }

    /**
     * Construit le tableau des enseignants pour un slot
     */
    protected function buildTeachersArray(array $teacherIds): array
    {
        if (empty($teacherIds)) {
            return [];
        }

        $teachers = [];
        $teacherModels = Teacher::with('user')->whereIn('id', $teacherIds)->get();

        foreach ($teacherModels as $teacher) {
            $teachers[] = $this->formatTeacherData($teacher);
        }

        return $teachers;
    }

    protected function formatTeacherData(Teacher $teacher): array
    {
        $firstName = $teacher->first_name ?? $teacher->user?->first_name;
        $lastName = $teacher->last_name ?? $teacher->user?->last_name;
        
        $teacherName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        
        if (empty($teacherName)) {
            $teacherName = $teacher->acronym;
        }

        return [
            'id' => $teacher->id,
            'code' => $teacher->acronym,
            'name' => $teacherName,
        ];
    }

    public function updateEdtSlotsBulk(array $updates): array
    {
        $updated = [];
        $errors = [];

        foreach ($updates as $updateData) {
            $result = $this->processEdtSlotUpdate($updateData);

            if ($result['success']) {
                $updated[] = $result['edt_slot_id'];
            } else {
                $errors[] = $result['error'];
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    protected function processEdtSlotUpdate(array $data): array
    {
        $edtSlotId = $data['edt_slot_id'] ?? null;

        if (empty($edtSlotId)) {
            return $this->updateError($edtSlotId, 'edt_slot_id manquant');
        }

        $edtSlot = $this->findEdtSlot($edtSlotId);
        if (!$edtSlot) {
            return $this->updateError($edtSlotId, "edt_slot {$edtSlotId} non trouvé");
        }

        $slot = $this->findSlot($edtSlot->slot_id);
        if (!$slot) {
            return $this->performUpdate($edtSlotId, $data);
        }

        $week = $this->findWeek($slot->week_id);
        if (!$week) {
            return $this->performUpdate($edtSlotId, $data);
        }

        $conflictError = $this->checkConflicts($edtSlotId, $slot, $week, $data);
        if ($conflictError) {
            return $this->updateError($edtSlotId, $conflictError);
        }

        return $this->performUpdate($edtSlotId, $data);
    }

    protected function checkConflicts(int $edtSlotId, object $slot, object $week, array $data): ?string
    {
        $timeInfo = $this->parseTimeInfo($data['start_hour'], $slot->duration, $data['day_of_week']);

        $teacherConflict = $this->checkTeacherConflict($edtSlotId, $slot->id, $week->id, $timeInfo);
        if ($teacherConflict) {
            return "Conflit d'emploi du temps : l'enseignant a déjà un cours à ce créneau pour edt_slot {$edtSlotId}";
        }

        $roomConflict = $this->checkRoomConflict($edtSlotId, $week->id, $data['room_id'], $timeInfo);
        if ($roomConflict) {
            return "Conflit de salle : cette salle est déjà occupée à ce créneau horaire pour edt_slot {$edtSlotId}";
        }

        return null;
    }

    protected function parseTimeInfo(string $startHour, float $duration, string $dayOfWeek): array
    {
        $timeParts = explode(':', $startHour);
        $startMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
        $endMinutes = $startMinutes + ($duration * 60);

        return [
            'start_minutes' => $startMinutes,
            'end_minutes' => $endMinutes,
            'day_of_week' => trim($dayOfWeek),
        ];
    }

    protected function checkTeacherConflict(int $edtSlotId, int $slotId, int $weekId, array $timeInfo): bool
    {
        $teacherRow = DB::table('slots_teachers')->where('slot_id', $slotId)->first();

        if (!$teacherRow) {
            return false;
        }

        $conflicts = DB::table('edt_slot as es')
            ->join('slots as s', 'es.slot_id', '=', 's.id')
            ->join('slots_teachers as st', 's.id', '=', 'st.slot_id')
            ->where('st.teacher_id', $teacherRow->teacher_id)
            ->where('es.day_of_week', $timeInfo['day_of_week'])
            ->where('s.week_id', $weekId)
            ->where('es.id', '!=', $edtSlotId)
            ->select('es.start_hour', 's.duration')
            ->get();

        return $this->hasTimeOverlap($conflicts, $timeInfo);
    }

    protected function checkRoomConflict(int $edtSlotId, int $weekId, int $roomId, array $timeInfo): bool
    {
        $conflicts = DB::table('edt_slot as es')
            ->join('slots as s', 'es.slot_id', '=', 's.id')
            ->where('es.room_id', $roomId)
            ->where('es.day_of_week', $timeInfo['day_of_week'])
            ->where('s.week_id', $weekId)
            ->where('es.id', '!=', $edtSlotId)
            ->select('es.start_hour', 's.duration')
            ->get();

        return $this->hasTimeOverlap($conflicts, $timeInfo);
    }

    protected function hasTimeOverlap(Collection $existingSlots, array $timeInfo): bool
    {
        foreach ($existingSlots as $existing) {
            $existingTimeParts = explode(':', $existing->start_hour);
            $existingStartMinutes = intval($existingTimeParts[0]) * 60 + intval($existingTimeParts[1]);
            $existingEndMinutes = $existingStartMinutes + ($existing->duration * 60);

            $hasOverlap = !(
                $timeInfo['end_minutes'] <= $existingStartMinutes ||
                $timeInfo['start_minutes'] >= $existingEndMinutes
            );

            if ($hasOverlap) {
                return true;
            }
        }

        return false;
    }

    protected function performUpdate(int $edtSlotId, array $data): array
    {
        $updateData = [
            'day_of_week' => $data['day_of_week'],
            'start_hour' => $data['start_hour'],
            'room_id' => $data['room_id'],
            'updated_at' => now(),
        ];

        $result = DB::table('edt_slot')->where('id', $edtSlotId)->update($updateData);

        if ($result) {
            return ['success' => true, 'edt_slot_id' => $edtSlotId];
        }

        return $this->updateError($edtSlotId, "Impossible de mettre à jour edt_slot {$edtSlotId}");
    }

    protected function updateError(?int $edtSlotId, string $message): array
    {
        return [
            'success' => false,
            'edt_slot_id' => $edtSlotId,
            'error' => $message,
        ];
    }

    protected function findEdtSlot(int $id): ?object
    {
        return DB::table('edt_slot')->where('id', $id)->first();
    }

    protected function findSlot(int $id): ?object
    {
        return DB::table('slots')->where('id', $id)->first();
    }

    protected function findWeek(int $id): ?object
    {
        return DB::table('weeks')->where('id', $id)->first();
    }
}
