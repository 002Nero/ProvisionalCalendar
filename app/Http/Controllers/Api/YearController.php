<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Year;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YearController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $years = Year::get()
                ->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->name,
                    ];
                });

            return response()->json($years);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:years,name',
            ]);

            $year = Year::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'message' => 'Année créée avec succès',
                'year' => [
                    'id' => $year->id,
                    'name' => $year->name,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $year = Year::with(['teachings', 'teachers', 'academicPromotions'])
                ->find($id);

            if (!$year) {
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            return response()->json([
                'id' => $year->id,
                'name' => $year->name,
                'teachings_count' => $year->teachings->count(),
                'teachers_count' => $year->teachers->count(),
                'promotions_count' => method_exists($year, 'academicPromotions') ? $year->academicPromotions->count() : 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
