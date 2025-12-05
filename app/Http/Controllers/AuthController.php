<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return Inertia::render('LoginPage');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'username' => 'Identifiants incorrects.',
            ]);
        }

        // Refuser la connexion si l'utilisateur est suspendu
        if (isset($user->suspended) && $user->suspended) {
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
                return redirect()->route('login')->withErrors([
                    'username' => 'Identifiants incorrects.',
                ]);
            }
        } else {
            // Vérifie le mot de passe normal
            if (!Hash::check($credentials['password'], $user->password)) {
                return redirect()->route('login')->withErrors([
                    'username' => 'Identifiants incorrects.',
                ]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Vérifier si l'utilisateur n'a pas de mot de passe personnel et n'est pas dans la liste des exclus
        if (!$user->personal_password && !in_array($user->username, $excludedUsernames)) {
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showCreatePersonalPassword()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return Inertia::render('PersonnalPasswordPage');
    }

    public function createPersonalPassword(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'personal_password' => Hash::make($request->password)
            ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Veuillez vous reconnecter avec votre nouveau mot de passe personnel.');
    }
}
