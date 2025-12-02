<?php

namespace Database\Seeders;

use App\Models\Week;
use App\Models\Year;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class WeekSeeder extends Seeder
{
    public function run(): void
    {
        $weeksPerYear = 44;

        $years = Year::all();

        foreach ($years as $year) {
            $startYear = null;
            if (preg_match('/^(\d{4})/', $year->name, $matches)) {
                $startYear = (int) $matches[1];
            }

            if (!$startYear) {
                $startDate = Carbon::now()->startOfWeek();
            } else {
                $base = Carbon::create($startYear, 8, 25);
                $startDate = $base->copy()->next(Carbon::MONDAY);
            }

            for ($i = 1; $i <= $weeksPerYear; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i - 1)->startOfDay();
                $weekEnd = $weekStart->copy()->addDays(6)->startOfDay();

                Week::updateOrCreate(
                    [
                        'year_id' => $year->id,
                        'week_number' => $i,
                    ],
                    [
                        'week_number' => $i,
                        'year_id' => $year->id,
                        'start_date' => $weekStart->toDateString(),
                        'end_date' => $weekEnd->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
