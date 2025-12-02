<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ne conserver que les tables framework/système avant suppression complète
        $preserve = [
            'migrations',
            'jobs',
            'failed_jobs',
            'password_resets',
            'personal_access_tokens',
            'sessions',
        ];

        try {
            // Désactiver les checks FK pour pouvoir supprimer les tables dans n'importe quel ordre
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            $rows = DB::select('SHOW TABLES');
            $existing = array_map(function($r) { $a = (array) $r; return array_values($a)[0]; }, $rows);

            foreach ($existing as $table) {
                if (!in_array($table, $preserve)) {
                    Schema::dropIfExists($table);
                }
            }

            // On remettra FOREIGN_KEY_CHECKS à 1 plus bas après la recréation
        } catch (\Exception $e) {
            // Si SHOW TABLES n'est pas disponible (driver différent), on ignore
        }

        // Créer les tables dans l'ordre nécessaire
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique();
            $table->string('name', 50)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('slot_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('acronym', 10);
            $table->integer('slot_order');
            $table->string('color', 7);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('seat_capacity');
            $table->integer('computer_capacity');
            $table->integer('exam_capacity');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('subgroups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('student_amount')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('teachings', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->string('apogee_code', 10);
            $table->decimal('tp_hours_initial', 5, 2);
            $table->decimal('tp_hours_continued', 5, 2)->nullable();
            $table->decimal('td_hours_initial', 5, 2);
            $table->decimal('td_hours_continued', 5, 2)->nullable();
            $table->decimal('cm_hours', 5, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('year_id')->constrained('years');
            $table->integer('student_amount')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });


        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->integer('semester_number');
            $table->foreignId('year_id')->constrained('years');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('promotion_id')->constrained('promotions');
            $table->integer('student_amount')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('acronym', 3);
            $table->foreignId('role_id')->constrained('roles');
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['permanent', 'vacataire']);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('teachers_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('year_id')->constrained('years');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('teachers_teachings', function (Blueprint $table) {
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('teaching_id')->constrained('teachings');
            $table->primary(['teacher_id', 'teaching_id']);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('teachings_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_id')->constrained('teachings');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('type_id')->constrained('slot_types');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('weeks', function (Blueprint $table) {
            $table->id();
            $table->integer('week_number');
            $table->foreignId('year_id')->constrained('years');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->decimal('duration', 3, 1);
            $table->foreignId('teaching_id')->constrained('teachings');
            $table->foreignId('promotion_id')->constrained('promotions');
            $table->foreignId('group_id')->nullable()->constrained('groups');
            $table->foreignId('subgroup_id')->nullable()->constrained('subgroups');
            $table->integer('room_amount');
            $table->boolean('is_neutralized');
            $table->boolean('is_exam')->default(false);
            $table->foreignId('week_id')->constrained('weeks');
            $table->foreignId('type_id')->constrained('slot_types');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('slots_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('slots');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Réactiver les checks FK
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas vraiment prévu pour être rollbacké
    }
};
