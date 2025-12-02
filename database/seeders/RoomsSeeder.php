<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'R46', 'seat_capacity' => 60, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => 'R50', 'seat_capacity' => 30, 'computer_capacity' => 15, 'exam_capacity' => 0],
            ['name' => 'R51', 'seat_capacity' => 16, 'computer_capacity' => 16, 'exam_capacity' => 0],
            ['name' => 'R52', 'seat_capacity' => 30, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => '103', 'seat_capacity' => 30, 'computer_capacity' => 15, 'exam_capacity' => 29],
            ['name' => '104', 'seat_capacity' => 32, 'computer_capacity' => 16, 'exam_capacity' => 0],
            ['name' => '105', 'seat_capacity' => 28, 'computer_capacity' => 14, 'exam_capacity' => 0],
            ['name' => '108', 'seat_capacity' => 16, 'computer_capacity' => 8, 'exam_capacity' => 0],
            ['name' => '109', 'seat_capacity' => 16, 'computer_capacity' => 8, 'exam_capacity' => 0],
            ['name' => '111', 'seat_capacity' => 28, 'computer_capacity' => 14, 'exam_capacity' => 0],
            ['name' => '112', 'seat_capacity' => 28, 'computer_capacity' => 14, 'exam_capacity' => 0],
            ['name' => '205', 'seat_capacity' => 36, 'computer_capacity' => 16, 'exam_capacity' => 0],
            ['name' => '206', 'seat_capacity' => 28, 'computer_capacity' => 14, 'exam_capacity' => 0],
            ['name' => '208', 'seat_capacity' => 30, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => '209', 'seat_capacity' => 30, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => 'AmphiC', 'seat_capacity' => 120, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => 'AmphiB', 'seat_capacity' => 156, 'computer_capacity' => 0, 'exam_capacity' => 0],
            ['name' => 'AmphiA', 'seat_capacity' => 163, 'computer_capacity' => 0, 'exam_capacity' => 0],
        ];

        foreach ($rooms as $r) {
            Room::updateOrCreate(['name' => $r['name']], $r);
        }
    }
}

