<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Year;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YearController extends Controller
{
    public function index(): JsonResponse
    {
        Log::debug('YearController: index called');

        try {
            $years = Year::get()
                ->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->name,
                    ];
                });

            Log::debug('YearController: years retrieved', ['count' => $years->count()]);
            return response()->json($years);

        } catch (\Exception $e) {
            Log::error('YearController: index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::debug('YearController: store called', ['name' => $request->name]);

        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:years,name',
            ]);

            $year = Year::create([
                'name' => $request->name,
            ]);

            Log::info('YearController: year created successfully', ['year_id' => $year->id, 'name' => $year->name]);

            return response()->json([
                'message' => 'Année créée avec succès',
                'year' => [
                    'id' => $year->id,
                    'name' => $year->name,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('YearController: store failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        Log::debug('YearController: show called', ['year_id' => $id]);

        try {
            $year = Year::with(['teachings', 'teachers', 'academicPromotions'])
                ->find($id);

            if (!$year) {
                Log::warning('YearController: year not found', ['year_id' => $id]);
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            Log::debug('YearController: year retrieved successfully', ['year_id' => $id]);

            return response()->json([
                'id' => $year->id,
                'name' => $year->name,
                'teachings_count' => $year->teachings->count(),
                'teachers_count' => $year->teachers->count(),
                'promotions_count' => method_exists($year, 'academicPromotions') ? $year->academicPromotions->count() : 0,
            ]);

        } catch (\Exception $e) {
            Log::error('YearController: show failed', ['year_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
