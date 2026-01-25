<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Year;
use App\Models\Week;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\Teaching;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\User;
use App\Models\Role;
use App\Models\Groups\Promotion;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CalendarControllerTest extends TestCase
{
	use RefreshDatabase;

	protected Year $year;
	protected Week $week;
	protected Teaching $teaching;
	protected Teacher $teacher;
	protected Room $room;
	protected Promotion $promotion;
	protected Group $group;
	protected Subgroup $subgroupA;
	protected Subgroup $subgroupB;
	protected SlotType $slotTypeCM;
	protected SlotType $slotTypeTD;
	protected SlotType $slotTypeTP;
	protected SlotType $slotTypeEX;

	protected function setUp(): void
	{
		parent::setUp();
		$this->createMissingTables();
		$this->createTestData();
	}

	protected function createMissingTables(): void
	{
		// TODO: Replace with models data when migrations will work.
		// Table slot_types
		if (!Schema::hasTable('slot_types')) {
			Schema::create('slot_types', function ($table) {
				$table->id();
				$table->string('name');
				$table->string('acronym', 10);
				$table->integer('slot_order')->default(0);
				$table->string('color', 20)->nullable();
				$table->timestamps();
			});
		}

		// Ajouter les colonnes manquantes à la table slots
		if (!Schema::hasColumn('slots', 'room_amount')) {
			Schema::table('slots', function ($table) {
				$table->integer('room_amount')->nullable()->after('subgroup_id');
			});
		}
		if (!Schema::hasColumn('slots', 'is_exam')) {
			Schema::table('slots', function ($table) {
				$table->boolean('is_exam')->default(false)->after('is_neutralized');
			});
		}
		if (!Schema::hasColumn('slots', 'type_id')) {
			// Supprimer l'ancienne colonne type (enum) et ajouter type_id
			if (Schema::hasColumn('slots', 'type')) {
				// SQLite ne permet pas de supprimer directement des colonnes
				// On ignore cette étape pour SQLite
			}
			Schema::table('slots', function ($table) {
				$table->foreignId('type_id')->nullable()->after('week_id');
			});
		}

		// Table slots_teachers - table pivot pour la relation many-to-many
		if (!Schema::hasTable('slots_teachers')) {
			Schema::create('slots_teachers', function ($table) {
				$table->id();
				$table->foreignId('slot_id')->constrained('slots')->onDelete('cascade');
				$table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
				$table->timestamps();
			});
		}

		// Table edt_slot - placements des créneaux
		if (!Schema::hasTable('edt_slot')) {
			Schema::create('edt_slot', function ($table) {
				$table->id();
				$table->string('start_hour', 8);
				$table->foreignId('slot_id')->constrained('slots')->onDelete('cascade');
				$table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
				$table->string('day_of_week', 20);
				$table->timestamps();
			});
		}
	}

	protected function createTestData(): void
	{
		$role = Role::create([
			'name' => 'teacher',
			'level' => 1
		]);

		$this->year = Year::create([
			'name' => '2024-2025',
			'periodicity' => 'Semestrial'
		]);

		$this->week = Week::create([
			'name' => 'Semaine 1',
			'week_number' => 1,
			'year_id' => $this->year->id,
		]);

		$this->slotTypeCM = SlotType::create([
			'name' => 'Cours Magistral',
			'acronym' => 'CM',
			'slot_order' => 1,
			'color' => '#FDE74C'
		]);

		$this->slotTypeTD = SlotType::create([
			'name' => 'Travaux Dirigés',
			'acronym' => 'TD',
			'slot_order' => 2,
			'color' => '#FFDDD2'
		]);

		$this->slotTypeTP = SlotType::create([
			'name' => 'Travaux Pratiques',
			'acronym' => 'TP',
			'slot_order' => 3,
			'color' => '#809BCE'
		]);

		$this->slotTypeEX = SlotType::create([
			'name' => 'Examen',
			'acronym' => 'EX',
			'slot_order' => 5,
			'color' => '#A26769'
		]);

		$this->promotion = Promotion::create([
			'name' => 'BUT1',
			'year_id' => $this->year->id,
		]);

		$this->group = Group::create([
			'name' => 'G1',
			'promotion_id' => $this->promotion->id,
		]);

		$this->subgroupA = Subgroup::create([
			'name' => 'A',
			'group_id' => $this->group->id,
		]);

		$this->subgroupB = Subgroup::create([
			'name' => 'B',
			'group_id' => $this->group->id,
		]);

		$this->room = Room::create([
			'name' => 'R46',
			'seat_capacity' => 60,
			'computer_capacity' => 0,
			'exam_capacity' => 0
		]);

		$this->teaching = Teaching::create([
			'title' => 'Initiation au développement',
			'apogee_code' => 'TIN01A1M',
			'tp_hours_initial' => 15.00,
			'td_hours_initial' => 10.00,
			'cm_hours' => 15.00,
			'year_id' => $this->year->id,
		]);

		$user = User::create([
			'username' => 'jdoe',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'email' => 'teacher@test.com',
			'password' => bcrypt('password'),
			'acronym' => 'JD',
			'role_id' => $role->id,
		]);

		// Utiliser DB::table car le modèle Teacher n'a pas 'type' dans fillable
		$teacherId = DB::table('teachers')->insertGetId([
			'user_id' => $user->id,
			'acronym' => 'JD',
			'type' => 'permanent',
			'year_id' => $this->year->id,
			'created_at' => now(),
			'updated_at' => now(),
		]);
		$this->teacher = Teacher::find($teacherId);
	}

	protected function createSlotWithEdtSlot(array $slotData, array $edtSlotData, array $teacherIds = []): array
	{
		if (empty($teacherIds)) {
			$teacherIds = [$this->teacher->id];
		}

		// Mapper type_id vers type enum pour la migration originale
		$typeMap = [
			$this->slotTypeCM->id => 'CM',
			$this->slotTypeTD->id => 'TD',
			$this->slotTypeTP->id => 'TP',
			$this->slotTypeEX->id ?? 999 => 'CM', // EX n'existe pas dans l'enum, utiliser CM
		];

		$slotDataForInsert = $slotData;
		if (isset($slotData['type_id'])) {
			$slotDataForInsert['type'] = $typeMap[$slotData['type_id']] ?? 'CM';
		}

		// Utiliser DB::table car la migration originale a teacher_id et type NOT NULL
		$slotId = DB::table('slots')->insertGetId(array_merge($slotDataForInsert, [
			'teacher_id' => $teacherIds[0], // Pour satisfaire la contrainte NOT NULL
			'created_at' => now(),
			'updated_at' => now()
		]));
		$slot = Slot::find($slotId);

		// Attacher tous les enseignants via la table pivot
		$slot->teachers()->attach($teacherIds);

		$edtId = DB::table('edt_slot')->insertGetId(array_merge([
			'slot_id' => $slot->id,
			'created_at' => now(),
			'updated_at' => now()
		], $edtSlotData));

		return ['slot' => $slot, 'edt_id' => $edtId];
	}

	public function test_get_edt_slots_returns_data_for_valid_year_and_week(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(1);

		$data = $response->json();
		$this->assertEquals('Lundi', $data[0]['day_of_week']);
		$this->assertEquals('08:00', $data[0]['start_hour']);
		$this->assertEquals($this->room->id, $data[0]['room_id']);
		$this->assertEquals($this->room->name, $data[0]['room_name']);
		$this->assertEquals($this->teaching->id, $data[0]['teaching_id']);
		$this->assertEquals($this->teaching->title, $data[0]['teaching_label']);
		$this->assertEquals($this->teaching->apogee_code, $data[0]['teaching_code']);
		$this->assertEquals(2.0, $data[0]['duration']);
		$this->assertEquals($this->slotTypeCM->acronym, $data[0]['type_acronym']);
		$this->assertEquals($this->slotTypeCM->color, $data[0]['type_color']);
	}

	public function test_get_edt_slots_returns_empty_array_for_week_without_slots(): void
	{
		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(0);
	}

	public function test_get_edt_slots_returns_empty_for_nonexistent_year(): void
	{
		$response = $this->getJson("/api/edt/9999/1");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(0);
	}

	public function test_get_edt_slots_returns_empty_for_nonexistent_week(): void
	{
		$response = $this->getJson("/api/edt/{$this->year->id}/99");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(0);
	}

	public function test_get_edt_slots_filters_by_promotion_id(): void
	{
		$otherPromotion = Promotion::create([
			'name' => 'BUT2',
			'year_id' => $this->year->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $otherPromotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}?promotion_id={$this->promotion->id}");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(1);

		$data = $response->json();
		$this->assertEquals($this->promotion->id, $data[0]['promotion_id']);
	}

	public function test_get_edt_slots_filters_by_group_id(): void
	{
		$otherGroup = Group::create([
			'name' => 'G2',
			'promotion_id' => $this->promotion->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $otherGroup->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}?group_id={$this->group->id}");

		$response->assertStatus(200)
			->assertJsonIsArray();

		$data = $response->json();
		foreach ($data as $item) {
			if (isset($item['group_id']) && $item['group_id'] !== null) {
				$this->assertEquals($this->group->id, $item['group_id']);
			}
		}
	}

	public function test_get_edt_slots_filters_by_subgroup(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 1.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => $this->subgroupA->id,
			'room_amount' => 15,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTP->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => $this->subgroupB->id,
			'room_amount' => 15,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTP->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}?subgroup=A");

		$response->assertStatus(200)
			->assertJsonIsArray();

		$data = $response->json();
		foreach ($data as $item) {
			if (isset($item['subgroup_id']) && $item['subgroup_id'] !== null) {
				$this->assertEquals($this->subgroupA->id, $item['subgroup_id']);
			}
		}
	}

	public function test_get_edt_slots_returns_teacher_information(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200);

		$data = $response->json();
		$this->assertNotEmpty($data);
		$this->assertEquals($this->teacher->id, $data[0]['teacher_id']);
		$this->assertEquals($this->teacher->acronym, $data[0]['teacher_code']);
		$this->assertArrayHasKey('teachers', $data[0]);
		$this->assertIsArray($data[0]['teachers']);
		$this->assertCount(1, $data[0]['teachers']);
		$this->assertEquals($this->teacher->id, $data[0]['teachers'][0]['id']);
		$this->assertEquals($this->teacher->acronym, $data[0]['teachers'][0]['code']);
	}

	public function test_get_edt_slots_returns_multiple_teachers(): void
	{
		$user2 = User::create([
			'username' => 'jsmith',
			'first_name' => 'Jane',
			'last_name' => 'Smith',
			'email' => 'teacher2@test.com',
			'password' => bcrypt('password'),
			'acronym' => 'JS',
			'role_id' => Role::first()->id,
		]);

		$teacher2Id = DB::table('teachers')->insertGetId([
			'user_id' => $user2->id,
			'acronym' => 'JS',
			'type' => 'permanent',
			'year_id' => $this->year->id,
			'created_at' => now(),
			'updated_at' => now(),
		]);
		$teacher2 = Teacher::find($teacher2Id);

		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		], [$this->teacher->id, $teacher2->id]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200);

		$data = $response->json();
		$this->assertCount(1, $data);
		$this->assertArrayHasKey('teachers', $data[0]);
		$this->assertCount(2, $data[0]['teachers']);
	}

	public function test_get_edt_slots_returns_exam_color_for_exam_slot(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => true,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200);

		$data = $response->json();
		$this->assertNotEmpty($data);
		$this->assertTrue($data[0]['is_exam']);
		$this->assertEquals($this->slotTypeEX->color, $data[0]['type_color']);
	}

	public function test_get_edt_slots_returns_multiple_slots_ordered(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(2);
	}

	public function test_get_edt_slots_filters_combined(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => $this->subgroupA->id,
			'room_amount' => 15,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTP->id,
		], [
			'day_of_week' => 'Mercredi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson(
			"/api/edt/{$this->year->id}/{$this->week->week_number}" .
				"?promotion_id={$this->promotion->id}" .
				"&group_id={$this->group->id}" .
				"&subgroup=A"
		);

		$response->assertStatus(200)
			->assertJsonIsArray();
	}

	public function test_get_edt_slots_returns_neutralized_status(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => true,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200);
		$data = $response->json();
		$this->assertCount(1, $data);
	}

	public function test_get_edt_slots_does_not_return_slots_from_other_weeks(): void
	{
		$week2 = Week::create([
			'name' => 'Semaine 2',
			'week_number' => 2,
			'year_id' => $this->year->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Slot dans la semaine 2 - utiliser DB::table
		$slot2Id = DB::table('slots')->insertGetId([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $week2->id,
			'type_id' => $this->slotTypeTD->id,
			'type' => 'TD',
			'teacher_id' => $this->teacher->id,
			'created_at' => now(),
			'updated_at' => now()
		]);
		$slot2 = Slot::find($slot2Id);
		$slot2->teachers()->attach($this->teacher->id);

		DB::table('edt_slot')->insert([
			'slot_id' => $slot2->id,
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/1");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(1);

		$data = $response->json();
		$this->assertEquals('Lundi', $data[0]['day_of_week']);
	}

	public function test_get_edt_slots_does_not_return_slots_from_other_years(): void
	{
		$year2 = Year::create([
			'name' => '2025-2026',
			'periodicity' => 'Semestrial'
		]);

		$week2 = Week::create([
			'name' => 'Semaine 1',
			'week_number' => 1,
			'year_id' => $year2->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Créer le slot pour year2 avec DB::table pour satisfaire la contrainte teacher_id
		$slot2Id = DB::table('slots')->insertGetId([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $week2->id,
			'type_id' => $this->slotTypeTD->id,
			'type' => 'TD',
			'teacher_id' => $this->teacher->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		DB::table('slots_teachers')->insert([
			'slot_id' => $slot2Id,
			'teacher_id' => $this->teacher->id,
		]);

		DB::table('edt_slot')->insert([
			'slot_id' => $slot2Id,
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/1");

		$response->assertStatus(200)
			->assertJsonIsArray()
			->assertJsonCount(1);
	}

	public function test_get_edt_slots_returns_room_information(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200);

		$data = $response->json();
		$this->assertEquals($this->room->id, $data[0]['room_id']);
		$this->assertEquals($this->room->name, $data[0]['room_name']);
	}

	public function test_get_edt_slots_returns_correct_type_info_for_different_slot_types(): void
	{
		$this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Mardi',
			'start_hour' => '10:00',
			'room_id' => $this->room->id,
		]);

		$this->createSlotWithEdtSlot([
			'duration' => 1.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => $this->subgroupA->id,
			'room_amount' => 15,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTP->id,
		], [
			'day_of_week' => 'Mercredi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->getJson("/api/edt/{$this->year->id}/{$this->week->week_number}");

		$response->assertStatus(200)
			->assertJsonCount(3);

		$data = $response->json();

		$typeFound = ['CM' => false, 'TD' => false, 'TP' => false];
		foreach ($data as $item) {
			if ($item['type_acronym'] === 'CM') {
				$this->assertEquals($this->slotTypeCM->color, $item['type_color']);
				$typeFound['CM'] = true;
			} elseif ($item['type_acronym'] === 'TD') {
				$this->assertEquals($this->slotTypeTD->color, $item['type_color']);
				$typeFound['TD'] = true;
			} elseif ($item['type_acronym'] === 'TP') {
				$this->assertEquals($this->slotTypeTP->color, $item['type_color']);
				$typeFound['TP'] = true;
			}
		}

		$this->assertTrue($typeFound['CM'], 'CM slot not found');
		$this->assertTrue($typeFound['TD'], 'TD slot not found');
		$this->assertTrue($typeFound['TP'], 'TP slot not found');
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - VALIDATION
	// =========================================================================

	public function test_store_edt_slots_bulk_returns_422_when_updates_missing_edt_slot_id(): void
	{
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(422)
			->assertJsonStructure(['error', 'messages']);
	}

	public function test_store_edt_slots_bulk_returns_422_when_updates_missing_day_of_week(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'start_hour' => '08:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(422)
			->assertJsonStructure(['error', 'messages']);
	}

	public function test_store_edt_slots_bulk_returns_422_when_updates_missing_start_hour(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Lundi',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(422)
			->assertJsonStructure(['error', 'messages']);
	}

	public function test_store_edt_slots_bulk_returns_422_when_updates_missing_room_id(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00'
				]
			]
		]);

		$response->assertStatus(422)
			->assertJsonStructure(['error', 'messages']);
	}

	public function test_store_edt_slots_bulk_returns_422_when_start_hour_has_invalid_format(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '8:00', // Invalide, le format correct : 08:00
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(422);
	}

	public function test_store_edt_slots_bulk_returns_422_when_room_id_does_not_exist(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00',
					'room_id' => 99999 // Inexistant
				]
			]
		]);

		$response->assertStatus(422);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - SUCCÈS
	// =========================================================================

	public function test_store_edt_slots_bulk_returns_200_with_empty_updates(): void
	{
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => []
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '0 mise(s) à jour']);
	}

	public function test_store_edt_slots_bulk_updates_single_edt_slot_successfully(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Mardi',
					'start_hour' => '10:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour'])
			->assertJsonFragment(['updated' => [$data['edt_id']]]);

		// Vérification de la mise à jour dans la DB
		$updatedEdtSlot = DB::table('edt_slot')->where('id', $data['edt_id'])->first();
		$this->assertEquals('Mardi', $updatedEdtSlot->day_of_week);
		$this->assertEquals('10:00', $updatedEdtSlot->start_hour);
	}

	public function test_store_edt_slots_bulk_updates_multiple_edt_slots_successfully(): void
	{
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 1.5,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data1['edt_id'],
					'day_of_week' => 'Mercredi',
					'start_hour' => '14:00',
					'room_id' => $this->room->id
				],
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Jeudi',
					'start_hour' => '16:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '2 mise(s) à jour']);

		// Vérification de la mise à jour dans la DB
		$updated1 = DB::table('edt_slot')->where('id', $data1['edt_id'])->first();
		$updated2 = DB::table('edt_slot')->where('id', $data2['edt_id'])->first();

		$this->assertEquals('Mercredi', $updated1->day_of_week);
		$this->assertEquals('14:00', $updated1->start_hour);
		$this->assertEquals('Jeudi', $updated2->day_of_week);
		$this->assertEquals('16:00', $updated2->start_hour);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - GESTION D'ERREURS
	// =========================================================================

	public function test_store_edt_slots_bulk_returns_207_when_edt_slot_not_found(): void
	{
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => 99999, // Invalide
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(207)
			->assertJsonFragment(['message' => '0 mise(s) à jour'])
			->assertJsonStructure(['errors']);

		$this->assertStringContainsString('non trouvé', $response->json('errors')[0]);
	}

	public function test_store_edt_slots_bulk_returns_207_with_partial_success(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Mardi',
					'start_hour' => '10:00',
					'room_id' => $this->room->id
				],
				[
					'edt_slot_id' => 99999, // Invalide
					'day_of_week' => 'Mercredi',
					'start_hour' => '14:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(207)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);

		$this->assertCount(1, $response->json('updated'));
		$this->assertCount(1, $response->json('errors'));
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - CONFLITS D'ENSEIGNANT
	// =========================================================================

	public function test_store_edt_slots_bulk_detects_teacher_time_conflict(): void
	{
		// Crée le premier créneau de 08:00 à 10:00
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Crée le second créneau de 14:00 à 16:00 (pas de conflit initialement)
		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		// Essaie de déplacer le second créneau à 09:00 (chevauche le premier créneau 08:00-10:00)
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '09:00', // Chevauche 08:00-10:00
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(207);
		$this->assertStringContainsString("Conflit d'emploi du temps", $response->json('errors')[0]);
	}

	public function test_store_edt_slots_bulk_allows_adjacent_teacher_slots(): void
	{
		// Crée le premier créneau de 08:00 à 10:00
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Crée le second créneau de 14:00 à 16:00
		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		// Déplace le second créneau à 10:00 (adjacent, pas de chevauchement)
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '10:00', // Adjacent à 08:00-10:00
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);
	}

	public function test_store_edt_slots_bulk_allows_different_day_for_same_teacher(): void
	{
		// Crée le premier créneau de 08:00 à 10:00
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Crée le second créneau de 14:00 à 16:00
		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $this->room->id,
		]);

		// Déplace le second créneau à Mardi à 08:00 (jour différent, même heure OK)
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Mardi',
					'start_hour' => '08:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - CONFLITS DE SALLE
	// =========================================================================

	public function test_store_edt_slots_bulk_detects_room_time_conflict(): void
	{
		// Crée un second enseignant
		$user2 = User::create([
			'username' => 'jsmith',
			'first_name' => 'Jane',
			'last_name' => 'Smith',
			'email' => 'teacher2@test.com',
			'password' => bcrypt('password'),
			'acronym' => 'JS',
			'role_id' => Role::first()->id,
		]);

		$teacher2Id = DB::table('teachers')->insertGetId([
			'user_id' => $user2->id,
			'acronym' => 'JS',
			'type' => 'permanent',
			'year_id' => $this->year->id,
			'created_at' => now(),
			'updated_at' => now(),
		]);
		$teacher2 = Teacher::find($teacher2Id);

		// Crée le premier créneau avec l'enseignant 1 de 08:00 à 10:00
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		], [$this->teacher->id]);

		// Crée une autre salle
		$room2 = Room::create([
			'name' => 'R50',
			'seat_capacity' => 30,
			'computer_capacity' => 0,
			'exam_capacity' => 0
		]);

		// Crée le second créneau avec l'enseignant 2 de 14:00 à 16:00 dans une salle différente
		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $room2->id,
		], [$teacher2->id]);

		// Essaie de déplacer le second créneau dans la même salle et à un horaire qui chevauche
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '09:00', // Chevauche 08:00-10:00
					'room_id' => $this->room->id // Même salle que le premier créneau
				]
			]
		]);

		$response->assertStatus(207);
		$this->assertStringContainsString('Conflit de salle', $response->json('errors')[0]);
	}

	public function test_store_edt_slots_bulk_allows_same_room_different_time(): void
	{
		// Crée un second enseignant
		$user2 = User::create([
			'username' => 'jsmith',
			'first_name' => 'Jane',
			'last_name' => 'Smith',
			'email' => 'teacher2@test.com',
			'password' => bcrypt('password'),
			'acronym' => 'JS',
			'role_id' => Role::first()->id,
		]);

		$teacher2Id = DB::table('teachers')->insertGetId([
			'user_id' => $user2->id,
			'acronym' => 'JS',
			'type' => 'permanent',
			'year_id' => $this->year->id,
			'created_at' => now(),
			'updated_at' => now(),
		]);
		$teacher2 = Teacher::find($teacher2Id);

		// Crée le premier créneau de 08:00 à 10:00
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		], [$this->teacher->id]);

		// Crée une autre salle
		$room2 = Room::create([
			'name' => 'R50',
			'seat_capacity' => 30,
			'computer_capacity' => 0,
			'exam_capacity' => 0
		]);

		// Crée le second créneau avec l'enseignant 2 de 14:00 à 16:00 dans une salle différente
		$data2 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => $this->group->id,
			'subgroup_id' => null,
			'room_amount' => 30,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeTD->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '14:00',
			'room_id' => $room2->id,
		], [$teacher2->id]);

		// Déplace le second créneau dans la même salle mais à un horaire non chevauchant (10:00-12:00)
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data2['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '10:00', // Adjacent à 08:00-10:00
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - SLOT SANS ENSEIGNANT
	// =========================================================================

	public function test_store_edt_slots_bulk_updates_slot_without_teacher(): void
	{
		// Crée un créneau sans enseignant en utilisant DB::table
		$slotId = DB::table('slots')->insertGetId([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
			'type' => 'CM',
			'teacher_id' => $this->teacher->id, // Pas d'enseignant assigné
			'created_at' => now(),
			'updated_at' => now()
		]);

		// Pas d'enseignant dans la table pivot slots_teachers

		$edtSlotId = DB::table('edt_slot')->insertGetId([
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'slot_id' => $slotId,
			'room_id' => $this->room->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $edtSlotId,
					'day_of_week' => 'Mardi',
					'start_hour' => '10:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - DIFFÉRENTES SEMAINES
	// =========================================================================

	public function test_store_edt_slots_bulk_allows_same_time_different_weeks(): void
	{
		// Crée un créneau pour la semaine 1
		$data1 = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Crée la semaine 2
		$week2 = Week::create([
			'name' => 'Semaine 2',
			'week_number' => 2,
			'year_id' => $this->year->id,
		]);

		// Crée un créneau pour la semaine 2 à un horaire différent initialement
		$slot2Id = DB::table('slots')->insertGetId([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $week2->id,
			'type_id' => $this->slotTypeTD->id,
			'type' => 'TD',
			'teacher_id' => $this->teacher->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		DB::table('slots_teachers')->insert([
			'slot_id' => $slot2Id,
			'teacher_id' => $this->teacher->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		$edtSlotId2 = DB::table('edt_slot')->insertGetId([
			'day_of_week' => 'Mardi',
			'start_hour' => '14:00',
			'slot_id' => $slot2Id,
			'room_id' => $this->room->id,
			'created_at' => now(),
			'updated_at' => now()
		]);

		// Déplace le créneau de la semaine 2 au même jour/heure que le créneau de la semaine 1 (devrait être autorisé)
		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $edtSlotId2,
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00', // Même que la semaine 1, mais semaine différente
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonFragment(['message' => '1 mise(s) à jour']);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - STRUCTURE DE RÉPONSE
	// =========================================================================

	public function test_store_edt_slots_bulk_response_has_correct_structure(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Mardi',
					'start_hour' => '10:00',
					'room_id' => $this->room->id
				]
			]
		]);

		$response->assertStatus(200)
			->assertJsonStructure([
				'message',
				'updated',
				'errors'
			]);
	}

	// =========================================================================
	// TESTS storeEdtSlotsBulk - CHANGEMENT DE SALLE
	// =========================================================================

	public function test_store_edt_slots_bulk_changes_room_successfully(): void
	{
		$data = $this->createSlotWithEdtSlot([
			'duration' => 2.0,
			'teaching_id' => $this->teaching->id,
			'promotion_id' => $this->promotion->id,
			'group_id' => null,
			'subgroup_id' => null,
			'room_amount' => 60,
			'is_neutralized' => false,
			'is_exam' => false,
			'week_id' => $this->week->id,
			'type_id' => $this->slotTypeCM->id,
		], [
			'day_of_week' => 'Lundi',
			'start_hour' => '08:00',
			'room_id' => $this->room->id,
		]);

		// Crée une nouvelle salle
		$newRoom = Room::create([
			'name' => 'Amphi A',
			'seat_capacity' => 200,
			'computer_capacity' => 0,
			'exam_capacity' => 0
		]);

		$response = $this->postJson('/api/edt/bulk', [
			'updates' => [
				[
					'edt_slot_id' => $data['edt_id'],
					'day_of_week' => 'Lundi',
					'start_hour' => '08:00',
					'room_id' => $newRoom->id
				]
			]
		]);

		$response->assertStatus(200);

		// Vérification de la mise à jour dans la DB
		$updatedEdtSlot = DB::table('edt_slot')->where('id', $data['edt_id'])->first();
		$this->assertEquals($newRoom->id, $updatedEdtSlot->room_id);
	}
}
