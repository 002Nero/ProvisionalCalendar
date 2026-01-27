<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabelController extends Controller
{
    public function index(): JsonResponse
    {
        Log::debug('LabelController: index called');
        return response()->json(Label::all());
    }

    public function getLabel($label_id): JsonResponse
    {
        Log::debug('LabelController: getLabel called', ['label_id' => $label_id]);

        try {
            $label = Label::findOrFail($label_id);
            Log::debug('LabelController: label retrieved', ['label_id' => $label_id]);
            return response()->json($label);
        } catch (\Exception $e) {
            Log::error('LabelController: getLabel failed', [
                'label_id' => $label_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function updateLabel(Request $request, $label_id): JsonResponse
    {
        Log::debug('LabelController: updateLabel called', ['label_id' => $label_id, 'new_name' => $request->name]);

        try {
            // Validation de la requête
            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            // Recherche du label
            $label = Label::findOrFail($label_id);
            
            // Mise à jour du nom
            $label->update([
                'name' => $request->name
            ]);

            Log::info('LabelController: label updated successfully', [
                'label_id' => $label_id,
                'new_name' => $request->name
            ]);

            return response()->json([
                'message' => 'Label modifié avec succès',
                'label' => [
                    'id' => $label->id,
                    'original_name' => $label->original_name,
                    'name' => $label->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('LabelController: updateLabel failed', [
                'label_id' => $label_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
