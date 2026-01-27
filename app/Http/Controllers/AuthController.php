<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login()
    {
        Log::debug('Login page accessed', ['already_authenticated' => Auth::check()]);

        if (Auth::check()) {
            Log::info('User already authenticated, redirecting to home', ['user_id' => Auth::id()]);
            return redirect('/');
        }
        return Inertia::render('LoginPage');
    }

    public function authenticate(Request $request)
    {
        Log::debug('Authentication attempt started', ['username' => $request->input('username')]);

        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (!$user) {
            Log::warning('Authentication failed: user not found', ['username' => $credentials['username']]);
            return redirect()->route('login')->withErrors([
                'username' => 'Identifiants incorrects.',
            ]);
        }

        // Refuser la connexion si l'utilisateur est suspendu
        if (isset($user->suspended) && $user->suspended) {
            Log::warning('Authentication failed: user account suspended', ['user_id' => $user->id, 'username' => $user->username]);
            return redirect()->route('login')->withErrors([
                'username' => 'Compte suspendu. Contactez un administrateur.',
            ]);
        }

        // Liste des noms d'utilisateur exclus de la vérification du mot de passe personnel
        $excludedUsernames = ['admin', 'reader', 'extended_reader'];

        // Si l'utilisateur a un mot de passe personnel et n'est pas dans la liste des exclus
        if ($user->personal_password && !in_array($user->username, $excludedUsernames)) {
            // Vérifie le mot de passe personnel
            if (!Hash::check($credentials['password'], $user->personal_password)) {
                Log::warning('Authentication failed: invalid personal password', ['user_id' => $user->id]);
                return redirect()->route('login')->withErrors([
                    'username' => 'Identifiants incorrects.',
                ]);
            }
        } else {
            // Vérifie le mot de passe normal
            if (!Hash::check($credentials['password'], $user->password)) {
                Log::warning('Authentication failed: invalid password', ['user_id' => $user->id]);
                return redirect()->route('login')->withErrors([
                    'username' => 'Identifiants incorrects.',
                ]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('User authenticated successfully', ['user_id' => $user->id, 'username' => $user->username, 'role_level' => $user->role->level ?? null]);

        // Vérifier si l'utilisateur n'a pas de mot de passe personnel et n'est pas dans la liste des exclus
        if (!$user->personal_password && !in_array($user->username, $excludedUsernames)) {
            Log::info('User redirected to create personal password', ['user_id' => $user->id]);
            return redirect()->route('create.personal.password');
        }

        $userRoleLevel = $user->role->level;

        switch ($userRoleLevel) {
            case 0:
                return redirect()->route('provisionnal_calendar.groups');
            case 1:
            case 2:
                return redirect()->route('provisionnal_calendar');
        }
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        Log::info('User logged out', ['user_id' => $userId]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showCreatePersonalPassword()
    {
        if (!Auth::check()) {
            Log::debug('Unauthenticated access to personal password page, redirecting to login');
            return redirect()->route('login');
        }

        Log::debug('Showing personal password creation page', ['user_id' => Auth::id()]);
        return Inertia::render('PersonnalPasswordPage');
    }

    public function createPersonalPassword(Request $request)
    {
        if (!Auth::check()) {
            Log::warning('Attempt to create personal password without authentication');
            return redirect()->route('login');
        }

        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        Log::info('User creating personal password', ['user_id' => $user->id]);

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'personal_password' => Hash::make($request->password)
            ]);

        Log::info('Personal password created successfully', ['user_id' => $user->id]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Veuillez vous reconnecter avec votre nouveau mot de passe personnel.');
    }
}
