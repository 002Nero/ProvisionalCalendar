<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class UserControllerApi extends Controller
{
    /**
     * Ajouter un nouvel utilisateur
     */
    public function store(Request $request)
    {
        Log::debug('UserControllerApi: store called', ['username' => $request->username, 'email' => $request->email]);

        try {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'role_id' => 'required|exists:roles,id',
            ]);

            $user = User::create([
                'username' => $validated['username'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'role_id' => $validated['role_id'],
                'password' => null
            ]);

            Log::info('UserControllerApi: user created successfully', ['user_id' => $user->id, 'username' => $user->username]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'user' => $user
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('UserControllerApi: validation failed on store', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserControllerApi: store failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier un utilisateur
     */
    public function update(Request $request, $id)
    {
        Log::debug('UserControllerApi: update called', ['user_id' => $id]);

        try {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username,' . $id,
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'role_id' => 'required|exists:roles,id',
            ]);

            $user = User::findOrFail($id);
            $user->update([
                'username' => $validated['username'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'role_id' => $validated['role_id'],
            ]);

            Log::info('UserControllerApi: user updated successfully', ['user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'user' => $user
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('UserControllerApi: validation failed on update', ['user_id' => $id, 'errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('UserControllerApi: update failed', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer tous les utilisateurs
     */
    public function index()
    {
        Log::debug('UserControllerApi: index called');

        try {
            $users = User::with('role')
                ->where('suspended', false)
                ->select('id', 'username', 'first_name', 'last_name', 'email', 'role_id', 'password')
                ->get()
                ->map(function ($user) {
                    $user->has_password = !is_null($user->password);
                    unset($user->password);
                    return $user;
                });

            Log::debug('UserControllerApi: users retrieved', ['count' => $users->count()]);

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('UserControllerApi: index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy($id)
    {
        Log::debug('UserControllerApi: destroy called', ['user_id' => $id]);

        try {
            $user = User::with('teacher')->findOrFail($id);

            // Marquer l'utilisateur comme suspendu (soft-delete logique)
            $user->suspended = true;
            $user->save();

            Log::info('UserControllerApi: user suspended successfully', ['user_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur suspendu avec succès',
                'user' => $user
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserControllerApi: destroy failed', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer et envoyer un nouveau mot de passe par email
     */
    public function createOrResetPassword(User $user)
    {
        Log::debug('UserControllerApi: createOrResetPassword called', ['user_id' => $user->id, 'email' => $user->email]);

        // Générer un mot de passe aléatoire
        $newPassword = Str::random(12);

        // Mettre à jour le mot de passe de l'utilisateur
        $user->password = Hash::make($newPassword);
        $user->save();

        Log::info('UserControllerApi: password reset for user', ['user_id' => $user->id]);

        try {
            // Envoyer le mot de passe par email
            Mail::send('emails.password-reset', [
                'password' => $newPassword,
                'username' => $user->username
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Vos identifiants de connexion');
            });

            Log::info('UserControllerApi: password reset email sent', ['user_id' => $user->id, 'email' => $user->email]);
        } catch (\Exception $e) {
            Log::error('UserControllerApi: failed to send password reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['message' => 'Nouveau mot de passe envoyé par email']);
    }
}
