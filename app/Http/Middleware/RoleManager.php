<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoleManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            Log::debug('RoleManager: unauthenticated user, redirecting to login', ['required_role' => $role]);
            return redirect()->route('login');
        }

        $userRole = Auth::user()->role;
        Log::debug('RoleManager: checking access', [
            'user_id' => Auth::id(),
            'user_role_level' => $userRole->level,
            'required_role' => $role
        ]);

        switch ($role) {
            case 'admin':
                if ($userRole->level === 0) {
                    Log::debug('RoleManager: admin access granted', ['user_id' => Auth::id()]);
                    return $next($request);
                }
                break;
            case 'extended_reader':
            case 'reader':
                if ($userRole->level === 1 || $userRole->level === 2) {
                    Log::debug('RoleManager: reader access granted', ['user_id' => Auth::id(), 'role' => $role]);
                    return $next($request);
                }
                break;
        }

        Log::warning('RoleManager: access denied, redirecting user', [
            'user_id' => Auth::id(),
            'user_role_level' => $userRole->level,
            'required_role' => $role
        ]);

        switch ($userRole->level) {
            case 0:
                return redirect()->route('provisionnal_calendar.groups');
            case 1:
            case 2:
                return redirect()->route('provisionnal_calendar');
        }

        return redirect()->route('logout');
    }
}
