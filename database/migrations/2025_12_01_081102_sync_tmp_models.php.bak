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
        $preserve = [
            'migrations',
            'jobs',
            'failed_jobs',
            'password_resets',
            'personal_access_tokens',
            'sessions',
        ];

        $driver = DB::connection()->getDriverName();

        try {
            // Logique de suppression adaptée au driver
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
                $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $existing = array_column($rows, 'name');
            } else { // MySQL et autres
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                $rows = DB::select('SHOW TABLES');
                $existing = array_map(fn($r) => array_values((array)$r)[0], $rows);
            }

            foreach ($existing as $table) {
                if (!in_array($table, $preserve)) {
                    Schema::dropIfExists($table);
                }
            }
        } catch (\Exception $e) {
            // Continue si erreur de nettoyage
        }

        // Création sécurisée des tables
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->integer('level')->unique();
                $table->string('name', 50)->unique();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('years')) {
            Schema::create('years', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('slot_types')) {
            Schema::create('slot_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('acronym', 10);
                $table->integer('slot_order');
                $table->string('color', 7);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('labels')) {
            Schema::create('labels', function (Blueprint $table) {
                $table->id();
                $table->string('original_name');
                $table->string('name');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('seat_capacity');
                $table->integer('computer_capacity');
                $table->integer('exam_capacity');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('subgroups')) {
            Schema::create('subgroups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('group_id');
                $table->integer('student_amount')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('teachings')) {
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
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('year_id')->constrained('years');
                $table->integer('student_amount')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }


        if (!Schema::hasTable('semesters')) {
            Schema::create('semesters', function (Blueprint $table) {
                $table->id();
                $table->integer('semester_number');
                $table->foreignId('year_id')->constrained('years');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('promotion_id')->constrained('promotions');
                $table->integer('student_amount')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('users')) {
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
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->enum('type', ['permanent', 'vacataire']);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('teachers_years')) {
            Schema::create('teachers_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('teachers');
                $table->foreignId('year_id')->constrained('years');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('teachers_teachings')) {
            Schema::create('teachers_teachings', function (Blueprint $table) {
                $table->foreignId('teacher_id')->constrained('teachers');
                $table->foreignId('teaching_id')->constrained('teachings');
                $table->primary(['teacher_id', 'teaching_id']);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('teachings_rooms')) {
            Schema::create('teachings_rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teaching_id')->constrained('teachings');
                $table->foreignId('room_id')->constrained('rooms');
                $table->foreignId('type_id')->constrained('slot_types');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('weeks')) {
            Schema::create('weeks', function (Blueprint $table) {
                $table->id();
                $table->integer('week_number');
                $table->foreignId('year_id')->constrained('years');
                $table->date('start_date');
                $table->date('end_date');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('slots')) {
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
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('slots_teachers')) {
            Schema::create('slots_teachers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('slot_id')->constrained('slots');
                $table->foreignId('teacher_id')->constrained('teachers');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        // Réactivation des clés étrangères
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ...existing code...
    }
};
