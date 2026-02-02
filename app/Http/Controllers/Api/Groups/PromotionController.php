<?php

namespace App\Http\Controllers\Api\Groups;

use App\Http\Controllers\Controller;
use App\Models\Groups\Promotion;
use App\Models\Year;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function getPromotionsByYear($year_id): JsonResponse
    {
        try {
            $yearExists = Year::find($year_id);
            if (!$yearExists) {
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            $promotions = Promotion::where('year_id', $year_id)
                ->with('groups.subgroups')
                ->get()
                ->map(function ($promotion) {
                    $studentCount = $promotion->groups->sum(function ($group) {
                        return $group->subgroups->sum('student_amount');
                    });
                    return [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'student_amount' => $studentCount,
                    ];
                });

            return response()->json($promotions);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des promotions',
            ], 500);
        }
    }

    public function getPromotionById($promotion_id): JsonResponse
    {
        try {
            $promotion = Promotion::with(['Groups.Subgroups'])
                ->find($promotion_id);

            if (!$promotion) {
                return response()->json([
                    'error' => 'Promotion non trouvée'
                ], 404);
            }

            return response()->json([
                'id' => $promotion->id,
                'name' => $promotion->name,
                'student_amount' => $promotion->groups->sum(function ($group) {
                    return $group->subgroups->sum('student_amount');
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des groupes',
            ], 500);
        }
    }

    public function storePromotion(Request $request, $year): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Vérifie si l'année existe
            $yearExists = Year::find($year);
            if (!$yearExists) {
                return response()->json([
                    'error' => 'Année non trouvée'
                ], 404);
            }

            // Vérifie si une promotion avec le même nom existe déjà pour cette année
            $existingPromotion = Promotion::where('name', $request->name)
                ->where('year_id', $year)
                ->first();

            if ($existingPromotion) {
                return response()->json([
                    'error' => 'Une promotion avec ce nom existe déjà pour cette année'
                ], 422);
            }

            $promotion = Promotion::create([
                'name' => $request->name,
                'year_id' => $year
            ]);

            return response()->json([
                'id' => $promotion->id,
                'name' => $promotion->name
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePromotion(Request $request, $promotion): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Vérifie si la promotion existe
            $promotionToUpdate = Promotion::find($promotion);
            if (!$promotionToUpdate) {
                return response()->json([
                    'error' => 'Promotion non trouvée'
                ], 404);
            }

            // Vérifie si une autre promotion avec le même nom existe déjà pour cette année
            $existingPromotion = Promotion::where('name', $request->name)
                ->where('year_id', $promotionToUpdate->year_id)
                ->where('id', '!=', $promotion)
                ->first();

            if ($existingPromotion) {
                return response()->json([
                    'error' => 'Une promotion avec ce nom existe déjà pour cette année'
                ], 422);
            }

            $promotionToUpdate->update([
                'name' => $request->name
            ]);

            return response()->json([
                'id' => $promotionToUpdate->id,
                'name' => $promotionToUpdate->name
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deletePromotion($promotion): JsonResponse
    {
        try {
            $promotionToDelete = Promotion::with(['Groups.Subgroups'])
                ->find($promotion);

            if (!$promotionToDelete) {
                return response()->json([
                    'error' => 'Promotion non trouvée'
                ], 404);
            }

            $promotionToDelete->delete();

            return response()->json([]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
