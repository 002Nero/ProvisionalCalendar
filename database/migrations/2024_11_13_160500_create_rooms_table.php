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
		Schema::create('rooms', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->integer('seat_capacity');
			$table->integer('computer_capacity');
			$table->integer('exam_capacity');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('rooms');
	}
};
