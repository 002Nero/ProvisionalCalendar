<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Slot;
use App\Models\Teacher;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * Service pour la gestion des créneaux EDT (Emploi Du Temps).
 * 
 * Gère la récupération, le filtrage et la mise à jour des créneaux
 * avec détection automatique de la configuration des colonnes.
 */
class EdtSlotService
{
    private const EXAM_TYPE_ACRONYM = 'EX';
    private const MINUTES_PER_HOUR = 60;

    protected array $columnConfig;
    protected ?int $examTypeId = null;

    /** @var array<int, string> */
    protected array $slotTypesColors = [];

    /** @var array<int, string> */
    protected array $slotTypesAcronyms = [];

    public function __construct()
    {
        $this->columnConfig = $this->detectColumnConfiguration();
        Log::debug('EdtSlotService: initialized with column configuration', [
            'config' => $this->columnConfig,
        ]);
    }

    /**
     * Récupère les créneaux EDT pour une année et semaine données avec filtres optionnels.
     *
     * @param int $yearId Identifiant de l'année
     * @param int $weekNumber Numéro de la semaine
     * @param array<string, mixed> $filters Filtres optionnels (promotion_id, group_id, subgroup)
     * @return array<int, array<string, mixed>> Liste des créneaux formatés
     */
    public function getEdtSlotsForWeek(int $yearId, int $weekNumber, array $filters = []): array
    {
        Log::debug('EdtSlotService: fetching slots for week', [
            'year_id' => $yearId,
            'week_number' => $weekNumber,
            'filters' => $filters,
        ]);

        $rows = $this->fetchEdtSlotRows($yearId, $weekNumber);

        if ($rows->isEmpty()) {
            Log::debug('EdtSlotService: no slots found for week', [
                'year_id' => $yearId,
                'week_number' => $weekNumber,
            ]);
            return [];
        }

        Log::debug('EdtSlotService: found raw edt_slot rows', ['count' => $rows->count()]);

        $slotIds = $this->extractSlotIds($rows);
        $slots = $this->loadSlotsWithFilters($slotIds, $filters);

        $this->loadSlotTypeData($slots);
        $slots = $this->attachTeachersToSlots($slots, $slotIds);

        $result = $this->buildResponse($rows, $slots, $filters !== []);

        Log::info('EdtSlotService: slots retrieved successfully', [
            'year_id' => $yearId,
            'week_number' => $weekNumber,
            'result_count' => count($result),
        ]);

        return $result;
    }

    /**
     * Détecte la configuration des colonnes de la table edt_slot.
     *
     * @return array<string, array<string, mixed>> Configuration des colonnes
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

    /**
     * Résout la configuration de la colonne de début.
     *
     * @return array<string, mixed>
     */
    protected function resolveStartColumnConfig(bool $hasStartHour, bool $hasStartTime): array
    {
        if ($hasStartHour && $hasStartTime) {
            return [
                'select' => DB::raw('COALESCE(edt_slot.start_hour, edt_slot.start_time) as start_hour'),
                'order' => 'COALESCE(edt_slot.start_hour, edt_slot.start_time)',
            ];
        }

        if ($hasStartHour) {
            return ['select' => 'edt_slot.start_hour', 'order' => 'edt_slot.start_hour'];
        }

        if ($hasStartTime) {
            return ['select' => 'edt_slot.start_time', 'order' => 'edt_slot.start_time'];
        }

        return ['select' => DB::raw('NULL as start_hour'), 'order' => null];
    }

    /**
     * Résout la configuration de la colonne du jour.
     *
     * @return array<string, mixed>
     */
    protected function resolveDayColumnConfig(bool $hasDayOfWeek, bool $hasDay): array
    {
        if ($hasDayOfWeek && $hasDay) {
            return [
                'select' => DB::raw('COALESCE(edt_slot.day_of_week, edt_slot.day) as day_of_week'),
                'order' => 'COALESCE(edt_slot.day_of_week, edt_slot.day)',
            ];
        }

        if ($hasDayOfWeek) {
            return ['select' => 'edt_slot.day_of_week', 'order' => 'edt_slot.day_of_week'];
        }

        if ($hasDay) {
            return ['select' => 'edt_slot.day', 'order' => 'edt_slot.day'];
        }

        return ['select' => DB::raw('NULL as day_of_week'), 'order' => null];
    }

    /**
     * Récupère les lignes edt_slot brutes depuis la base.
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

    /**
     * Applique l'ordre de tri à la requête.
     */
    protected function applyOrdering(Builder $query): void
    {
        $dayOrder = $this->columnConfig['day']['order'] ?? null;
        $startOrder = $this->columnConfig['start']['order'] ?? null;

        if ($dayOrder !== null && $dayOrder !== '') {
            $query->orderByRaw($dayOrder . ' asc');
        }

        if ($startOrder !== null && $startOrder !== '') {
            $query->orderByRaw($startOrder . ' asc');
        }
    }

    /**
     * Extrait les identifiants de slots uniques.
     *
     * @return array<int>
     */
    protected function extractSlotIds(Collection $rows): array
    {
        return $rows->pluck('slot_id')->filter()->unique()->values()->all();
    }

    /**
     * Charge les slots avec application des filtres.
     *
     * @param array<int> $slotIds Identifiants des slots
     * @param array<string, mixed> $filters Filtres à appliquer
     */
    protected function loadSlotsWithFilters(array $slotIds, array $filters): Collection
    {
        if ($slotIds === []) {
            return collect();
        }

        $query = Slot::whereIn('id', $slotIds)
            ->with(['teaching', 'Promotion', 'Group', 'Subgroup']);

        $this->applyFilters($query, $filters);

        return $query->get()->keyBy('id');
    }

    /**
     * Applique les filtres à la requête de slots.
     * Extensible : pour ajouter de nouveaux filtres, créer une sous-classe et surcharger cette méthode.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Slot> $query
     * @param array<string, mixed> $filters
     */
    protected function applyFilters($query, array $filters): void
    {
        if ($filters === []) {
            return;
        }

        $query->where(function ($subQuery) use ($filters): void {
            $this->applyPromotionFilter($subQuery, $filters);
            $this->applyGroupFilter($subQuery, $filters);
            $this->applySubgroupFilter($subQuery, $filters);
        });
    }

    /**
     * Applique le filtre de promotion.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Slot> $query
     * @param array<string, mixed> $filters
     */
    protected function applyPromotionFilter($query, array $filters): void
    {
        $promotionId = $filters['promotion_id'] ?? null;
        if ($promotionId !== null && $promotionId !== '') {
            $query->where('promotion_id', $promotionId);
        }
    }

    /**
     * Applique le filtre de groupe.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Slot> $query
     * @param array<string, mixed> $filters
     */
    protected function applyGroupFilter($query, array $filters): void
    {
        $groupId = $filters['group_id'] ?? null;
        if ($groupId !== null && $groupId !== '') {
            $query->where(function ($subQuery) use ($groupId): void {
                $subQuery->where('group_id', $groupId)
                    ->orWhereNull('group_id');
            });
        }
    }

    /**
     * Applique le filtre de sous-groupe.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Slot> $query
     * @param array<string, mixed> $filters
     */
    protected function applySubgroupFilter($query, array $filters): void
    {
        $subgroup = $filters['subgroup'] ?? null;
        if ($subgroup === null || $subgroup === '') {
            return;
        }

        $subgroupId = $this->resolveSubgroupId((string) $subgroup);

        if ($subgroupId !== null) {
            $query->where(function ($subQuery) use ($subgroupId): void {
                $subQuery->where('subgroup_id', $subgroupId)
                    ->orWhereNull('subgroup_id');
            });
        }
    }

    /**
     * Résout l'identifiant du sous-groupe à partir de son nom.
     * Extensible : surcharger pour supporter d'autres mappings.
     */
    protected function resolveSubgroupId(string $subgroupName): ?int
    {
        $subgroupMap = $this->getSubgroupMapping();
        return $subgroupMap[$subgroupName] ?? null;
    }

    /**
     * Retourne le mapping nom → id des sous-groupes.
     * Extensible : surcharger pour personnaliser le mapping.
     *
     * @return array<string, int>
     */
    protected function getSubgroupMapping(): array
    {
        return ['A' => 1, 'B' => 2];
    }

    /**
     * Charge les données des types de slots (couleurs, acronymes).
     */
    protected function loadSlotTypeData(Collection $slots): void
    {
        $typeIds = $slots->pluck('type_id')->filter()->unique()->values()->all();

        if ($typeIds !== []) {
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

    /**
     * Charge les données du type d'examen.
     */
    protected function loadExamTypeData(): void
    {
        $examTypeRow = DB::table('slot_types')
            ->where('acronym', self::EXAM_TYPE_ACRONYM)
            ->first();

        if ($examTypeRow !== null) {
            $this->examTypeId = $examTypeRow->id;
            $this->slotTypesColors[$this->examTypeId] = $examTypeRow->color;
        }
    }

    /**
     * Attache les enseignants aux slots.
     *
     * @param array<int> $slotIds
     */
    protected function attachTeachersToSlots(Collection $slots, array $slotIds): Collection
    {
        if ($slotIds === []) {
            return $slots;
        }

        $teachersBySlot = $this->loadTeachersBySlot($slotIds);

        foreach ($slots as $id => $slot) {
            $slot->teacher_ids = $teachersBySlot[$id] ?? [];
        }

        return $slots;
    }

    /**
     * Charge les enseignants groupés par slot.
     *
     * @param array<int> $slotIds
     * @return array<int, array<int>>
     */
    protected function loadTeachersBySlot(array $slotIds): array
    {
        $teachersRows = DB::table('slots_teachers')
            ->whereIn('slot_id', $slotIds)
            ->get();

        $teachersBySlot = [];
        foreach ($teachersRows as $teacherRow) {
            $teachersBySlot[$teacherRow->slot_id][] = $teacherRow->teacher_id;
        }

        return $teachersBySlot;
    }

    /**
     * Construit la réponse finale.
     *
     * @return array<int, array<string, mixed>>
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

    /**
     * Détermine si une ligne doit être ignorée.
     */
    protected function shouldSkipRow(stdClass $row, Collection $slots, bool $hasFilters): bool
    {
        $slotId = $row->slot_id ?? null;
        return $hasFilters && $slotId !== null && !isset($slots[$slotId]);
    }

    /**
     * Construit les données d'une ligne.
     *
     * @return array<string, mixed>
     */
    protected function buildRowData(stdClass $row, Collection $slots): array
    {
        $baseData = $this->extractBaseRowData($row);
        $slotInfo = $this->buildSlotInfo($row, $slots);

        return array_merge($baseData, $slotInfo);
    }

    /**
     * Extrait les données de base d'une ligne.
     *
     * @return array<string, mixed>
     */
    protected function extractBaseRowData(stdClass $row): array
    {
        $start = $this->extractPropertyValue($row, ['start_hour', 'start_time']);
        $day = $this->extractPropertyValue($row, ['day_of_week', 'day']);

        return [
            'id' => $row->id,
            'start_hour' => $start,
            'day_of_week' => $day,
            'room_id' => $row->room_id,
            'room_name' => $row->room_name ?? null,
        ];
    }

    /**
     * Extrait la première valeur non nulle parmi les propriétés données.
     *
     * @param array<string> $properties
     */
    private function extractPropertyValue(stdClass $row, array $properties): mixed
    {
        foreach ($properties as $property) {
            if (property_exists($row, $property) && $row->{$property} !== null) {
                return $row->{$property};
            }
        }

        return null;
    }

    /**
     * Construit les informations du slot.
     *
     * @return array<string, mixed>
     */
    protected function buildSlotInfo(stdClass $row, Collection $slots): array
    {
        $slotId = $row->slot_id ?? null;

        if ($slotId === null || !isset($slots[$slotId])) {
            return [];
        }

        $slot = $slots[$slotId];
        $teachers = $this->buildTeachersArray($slot->teacher_ids ?? []);
        $firstTeacher = $teachers[0] ?? null;

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

    /**
     * Récupère l'acronyme du type de slot.
     */
    protected function getTypeAcronym(?int $typeId): ?string
    {
        if ($typeId === null || !isset($this->slotTypesAcronyms[$typeId])) {
            return null;
        }

        return $this->slotTypesAcronyms[$typeId];
    }

    /**
     * Détermine la couleur du slot.
     */
    protected function determineColor(Slot $slot): ?string
    {
        if ($this->isExamSlot($slot)) {
            return $this->slotTypesColors[$this->examTypeId] ?? null;
        }

        if ($slot->type_id !== null && isset($this->slotTypesColors[$slot->type_id])) {
            return $this->slotTypesColors[$slot->type_id];
        }

        return null;
    }

    /**
     * Vérifie si le slot est un examen.
     */
    private function isExamSlot(Slot $slot): bool
    {
        return $slot->is_exam
            && $this->examTypeId !== null
            && isset($this->slotTypesColors[$this->examTypeId]);
    }

    /**
     * Construit le tableau des enseignants pour un slot.
     *
     * @param array<int> $teacherIds
     * @return array<int, array<string, mixed>>
     */
    protected function buildTeachersArray(array $teacherIds): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $teachers = [];
        $teacherModels = Teacher::with('user')->whereIn('id', $teacherIds)->get();

        foreach ($teacherModels as $teacher) {
            $teachers[] = $this->formatTeacherData($teacher);
        }

        return $teachers;
    }

    /**
     * Formate les données d'un enseignant.
     *
     * @return array<string, mixed>
     */
    protected function formatTeacherData(Teacher $teacher): array
    {
        $firstName = $teacher->first_name ?? $teacher->user?->first_name ?? '';
        $lastName = $teacher->last_name ?? $teacher->user?->last_name ?? '';

        $teacherName = trim($firstName . ' ' . $lastName);

        if ($teacherName === '') {
            $teacherName = $teacher->acronym;
        }

        return [
            'id' => $teacher->id,
            'code' => $teacher->acronym,
            'name' => $teacherName,
        ];
    }

    /**
     * Met à jour plusieurs créneaux EDT en lot.
     *
     * @param array<int, array<string, mixed>> $updates
     * @return array<string, array<int, mixed>>
     */
    public function updateEdtSlotsBulk(array $updates): array
    {
        Log::debug('EdtSlotService: starting bulk update', ['update_count' => count($updates)]);

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

        Log::info('EdtSlotService: bulk update completed', [
            'updated_count' => count($updated),
            'error_count' => count($errors),
        ]);

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Traite la mise à jour d'un créneau EDT.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function processEdtSlotUpdate(array $data): array
    {
        $edtSlotId = $data['edt_slot_id'] ?? null;

        if ($edtSlotId === null) {
            Log::warning('EdtSlotService: update failed - missing edt_slot_id');
            return $this->createUpdateError(null, 'edt_slot_id manquant');
        }

        $edtSlot = $this->findEdtSlot((int) $edtSlotId);
        if ($edtSlot === null) {
            return $this->createUpdateError($edtSlotId, "edt_slot {$edtSlotId} non trouvé");
        }

        $slot = $this->findSlot((int) $edtSlot->slot_id);
        if ($slot === null) {
            return $this->performUpdate((int) $edtSlotId, $data);
        }

        $week = $this->findWeek((int) $slot->week_id);
        if ($week === null) {
            return $this->performUpdate((int) $edtSlotId, $data);
        }

        $conflictError = $this->checkConflicts((int) $edtSlotId, $slot, $week, $data);
        if ($conflictError !== null) {
            return $this->createUpdateError($edtSlotId, $conflictError);
        }

        return $this->performUpdate((int) $edtSlotId, $data);
    }

    /**
     * Vérifie les conflits pour une mise à jour.
     *
     * @param array<string, mixed> $data
     */
    protected function checkConflicts(int $edtSlotId, stdClass $slot, stdClass $week, array $data): ?string
    {
        $timeInfo = $this->parseTimeInfo(
            (string) $data['start_hour'],
            (float) $slot->duration,
            (string) $data['day_of_week']
        );

        if ($this->hasTeacherConflict($edtSlotId, (int) $slot->id, (int) $week->id, $timeInfo)) {
            return "Conflit d'emploi du temps : l'enseignant a déjà un cours à ce créneau pour edt_slot {$edtSlotId}";
        }

        if ($this->hasRoomConflict($edtSlotId, (int) $week->id, (int) $data['room_id'], $timeInfo)) {
            return "Conflit de salle : cette salle est déjà occupée à ce créneau horaire pour edt_slot {$edtSlotId}";
        }

        return null;
    }

    /**
     * Parse les informations temporelles.
     *
     * @return array<string, mixed>
     */
    protected function parseTimeInfo(string $startHour, float $duration, string $dayOfWeek): array
    {
        $timeParts = explode(':', $startHour);
        $startMinutes = (int) $timeParts[0] * self::MINUTES_PER_HOUR + (int) ($timeParts[1] ?? 0);
        $endMinutes = $startMinutes + (int) ($duration * self::MINUTES_PER_HOUR);

        return [
            'start_minutes' => $startMinutes,
            'end_minutes' => $endMinutes,
            'day_of_week' => trim($dayOfWeek),
        ];
    }

    /**
     * Vérifie s'il y a un conflit d'enseignant.
     *
     * @param array<string, mixed> $timeInfo
     */
    protected function hasTeacherConflict(int $edtSlotId, int $slotId, int $weekId, array $timeInfo): bool
    {
        $teacherRow = DB::table('slots_teachers')->where('slot_id', $slotId)->first();

        if ($teacherRow === null) {
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

    /**
     * Vérifie s'il y a un conflit de salle.
     *
     * @param array<string, mixed> $timeInfo
     */
    protected function hasRoomConflict(int $edtSlotId, int $weekId, int $roomId, array $timeInfo): bool
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

    /**
     * Vérifie s'il y a un chevauchement temporel.
     *
     * @param array<string, mixed> $timeInfo
     */
    protected function hasTimeOverlap(Collection $existingSlots, array $timeInfo): bool
    {
        foreach ($existingSlots as $existing) {
            if ($this->slotsOverlap($existing, $timeInfo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si deux créneaux se chevauchent.
     *
     * @param array<string, mixed> $timeInfo
     */
    private function slotsOverlap(stdClass $existing, array $timeInfo): bool
    {
        $existingTimeParts = explode(':', $existing->start_hour);
        $existingStartMinutes = (int) $existingTimeParts[0] * self::MINUTES_PER_HOUR
            + (int) ($existingTimeParts[1] ?? 0);
        $existingEndMinutes = $existingStartMinutes + (int) ($existing->duration * self::MINUTES_PER_HOUR);

        $noOverlap = $timeInfo['end_minutes'] <= $existingStartMinutes
            || $timeInfo['start_minutes'] >= $existingEndMinutes;

        return !$noOverlap;
    }

    /**
     * Effectue la mise à jour du créneau.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function performUpdate(int $edtSlotId, array $data): array
    {
        $updateData = [
            'day_of_week' => $data['day_of_week'],
            'start_hour' => $data['start_hour'],
            'room_id' => $data['room_id'],
            'updated_at' => now(),
        ];

        $result = DB::table('edt_slot')->where('id', $edtSlotId)->update($updateData);

        if ($result > 0) {
            return ['success' => true, 'edt_slot_id' => $edtSlotId];
        }

        return $this->createUpdateError($edtSlotId, "Impossible de mettre à jour edt_slot {$edtSlotId}");
    }

    /**
     * Crée une réponse d'erreur de mise à jour.
     *
     * @return array<string, mixed>
     */
    protected function createUpdateError(?int $edtSlotId, string $message): array
    {
        return [
            'success' => false,
            'edt_slot_id' => $edtSlotId,
            'error' => $message,
        ];
    }

    /**
     * Recherche un créneau EDT par son identifiant.
     */
    protected function findEdtSlot(int $id): ?stdClass
    {
        return DB::table('edt_slot')->where('id', $id)->first();
    }

    /**
     * Recherche un slot par son identifiant.
     */
    protected function findSlot(int $id): ?stdClass
    {
        return DB::table('slots')->where('id', $id)->first();
    }

    /**
     * Recherche une semaine par son identifiant.
     */
    protected function findWeek(int $id): ?stdClass
    {
        return DB::table('weeks')->where('id', $id)->first();
    }
}
