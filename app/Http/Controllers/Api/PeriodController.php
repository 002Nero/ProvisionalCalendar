<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Year;

class PeriodController extends Controller
{
    public function getPeriodsByYear($year_id)
    {
        $year = Year::where('id', $year_id)->first();

        if (!$year) {
            return response()->json([
                'error' => 'Année non trouvée'
            ], 404);
        }

        return response()->json([
            'id' => $year->id,
            'name' => $year->name,
        ]);
    }
}
