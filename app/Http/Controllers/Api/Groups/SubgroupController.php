<?php

namespace App\Http\Controllers\Api\Groups;

use App\Http\Controllers\Controller;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubgroupController extends Controller
{
    public function getSubgroupsByGroup($group_id): JsonResponse
    {
        try {
            $subgroups = Subgroup::where('group_id', $group_id)
                ->get()
                ->map(function ($subgroup) {
                    return [
                        'id' => $subgroup->id,
                        'name' => $subgroup->name,
                    ];
                });

            if (!$subgroups) {
                return response()->json([
                    'error' => 'Sous-groupe non trouvé'
                ], 404);
            }

            return response()->json($subgroups);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des sous-groupes',
            ], 500);
        }
    }

    public function getSubgroupById($subgroup_id): JsonResponse
    {
        try {
            $subgroup = Subgroup::with(['Group.Promotion'])
                ->find($subgroup_id);

            if (!$subgroup) {
                return response()->json([
                    'error' => 'Sous-groupe non trouvé'
                ], 404);
            }

            return response()->json([
                'id' => $subgroup->id,
                'name' => $subgroup->name
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeSubgroup(Request $request, $group): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $groupExists = Group::find($group);
            if (!$groupExists) {
                return response()->json([
                    'error' => 'Groupe non trouvé'
                ], 404);
            }

            $existingSubgroup = Subgroup::where('name', $request->name)
                ->where('group_id', $group)
                ->first();

            if ($existingSubgroup) {
                return response()->json([
                    'error' => 'Un sous-groupe avec ce nom existe déjà pour ce groupe'
                ], 422);
            }

            $subgroup = Subgroup::create([
                'name' => $request->name,
                'group_id' => $group
            ]);

            return response()->json([
                'id' => $subgroup->id,
                'name' => $subgroup->name
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSubgroup(Request $request, $subgroup): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Vérifie si le sous-groupe existe
            $subgroupToUpdate = Subgroup::find($subgroup);
            if (!$subgroupToUpdate) {
                return response()->json([
                    'error' => 'Sous-groupe non trouvé'
                ], 404);
            }

            // Vérifie si un autre sous-groupe avec le même nom existe déjà pour ce groupe
            $existingSubgroup = Subgroup::where('name', $request->name)
                ->where('group_id', $subgroupToUpdate->group_id)
                ->where('id', '!=', $subgroup)
                ->first();

            if ($existingSubgroup) {
                return response()->json([
                    'error' => 'Un sous-groupe avec ce nom existe déjà pour ce groupe'
                ], 422);
            }

            $subgroupToUpdate->update([
                'name' => $request->name
            ]);

            return response()->json([
                'message' => 'Sous-groupe modifié avec succès',
                'subgroup' => [
                    'id' => $subgroupToUpdate->id,
                    'name' => $subgroupToUpdate->name,
                    'group_id' => $subgroupToUpdate->group_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteSubgroup($subgroup): JsonResponse
    {
        try {
            $subgroupToDelete = Subgroup::with(['Group.Promotion'])
                ->find($subgroup);

            if (!$subgroupToDelete) {
                return response()->json([
                    'error' => 'Sous-groupe non trouvé'
                ], 404);
            }

            $subgroupToDelete->delete();

            return response()->json([]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
