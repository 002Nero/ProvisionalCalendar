<?php

namespace App\Http\Controllers\Api\Groups;

use App\Http\Controllers\Controller;
use App\Models\Groups\Promotion;
use App\Models\Groups\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{

    public function getGroupsByPromotion($promotion_id): JsonResponse
    {
        try {
            $groups = Group::where('promotion_id', $promotion_id)
                ->with('subgroups')
                ->get()
                ->map(function ($group) {
                    $studentCount = $group->subgroups->sum('student_amount');
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'student_amount' => $studentCount,
                    ];
                });

            return response()->json($groups);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des groupes',
            ], 500);
        }
    }

    public function getGroupById($group_id): JsonResponse
    {
        try {
            $group = Group::with(['Subgroups', 'Promotion'])
                ->find($group_id);

            if (!$group) {
                return response()->json([
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            return response()->json([
                'id' => $group->id,
                'name' => $group->name,
                'student_amount' => $group->subgroups->sum('student_amount'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeGroup(Request $request, $promotion): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Vérifie si la promotion existe
            $promotionExists = Promotion::find($promotion);
            if (!$promotionExists) {
                return response()->json([
                    'error' => 'Promotion non trouvée'
                ], 404);
            }

            // Vérifie si un groupe avec le même nom existe déjà pour cette promotion
            $existingGroup = Group::where('name', $request->name)
                ->where('promotion_id', $promotion)
                ->first();

            if ($existingGroup) {
                return response()->json([
                    'error' => 'Un groupe avec ce nom existe déjà pour cette promotion'
                ], 422);
            }

            $group = Group::create([
                'name' => $request->name,
                'promotion_id' => $promotion
            ]);

            return response()->json([
                'id' => $group->id,
                'name' => $group->name
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateGroup(Request $request, $group): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Vérifie si le groupe existe
            $groupToUpdate = Group::find($group);
            if (!$groupToUpdate) {
                return response()->json([
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            // Vérifie si un autre groupe avec le même nom existe déjà pour cette promotion
            $existingGroup = Group::where('name', $request->name)
                ->where('promotion_id', $groupToUpdate->promotion_id)
                ->where('id', '!=', $group)
                ->first();

            if ($existingGroup) {
                return response()->json([
                    'error' => 'Un groupe avec ce nom existe déjà pour cette promotion'
                ], 422);
            }

            $groupToUpdate->update([
                'name' => $request->name
            ]);

            return response()->json([
                'id' => $groupToUpdate->id,
                'name' => $groupToUpdate->name
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteGroup($group): JsonResponse
    {
        try {
            $groupToDelete = Group::with(['Subgroups', 'Promotion', 'slots'])
                ->find($group);

            if (!$groupToDelete) {
                return response()->json([
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            $groupToDelete->delete();

            return response()->json([]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors de la suppression',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
