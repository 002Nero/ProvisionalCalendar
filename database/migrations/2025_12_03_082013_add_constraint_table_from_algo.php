<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::create('edt_slot', function (Blueprint $table) {
            $table->id();
            $table->string('start_hour', 8);
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('room_id');
            $table->tinyInteger('day');
            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('slot_id')->references('id')->on('slots')->onDelete('cascade');
        });

        Schema::create('teacher_constraints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->string('constraint_type', 50);
            $table->string('day_of_week', 20)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->string('priority', 20)->default('hard');
            $table->unsignedBigInteger('week_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('week_id')->references('id')->on('weeks')->onDelete('set null');
        });

        Schema::create('room_constraints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->string('constraint_type', 50);
            $table->string('day_of_week', 20)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->string('priority', 20)->default('hard');
            $table->unsignedBigInteger('week_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('week_id')->references('id')->on('weeks')->onDelete('set null');
        });

        Schema::create('group_constraints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('constraint_type', 50);
            $table->string('day_of_week', 20)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->string('priority', 20)->default('hard');
            $table->unsignedBigInteger('week_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('week_id')->references('id')->on('weeks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edt_slot');
        Schema::dropIfExists('teacher_constraints');
        Schema::dropIfExists('room_constraints');
        Schema::dropIfExists('group_constraints');
    }
};

