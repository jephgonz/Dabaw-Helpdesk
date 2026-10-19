<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->session()->get('user');
        if (($user['role'] ?? null) === 'admin') {
            return redirect('/admin');
        }
        if ($user) {
            $request->session()->forget('user');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_action' => ['required', 'in:admin_login'],
            'password' => ['required', 'string', 'max:4096'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        try {
            $candidates = DB::table('helpdesk_users')
                ->where('role', 'admin')
                ->where('is_active', 1);
            $candidates = $candidates
                ->where('email', strtolower(trim((string) $validated['email'])))
                ->orderBy('id')
                ->get(['id', 'display_name', 'username', 'email', 'password_hash', 'role']);

            foreach ($candidates as $candidate) {
                if (password_verify($validated['password'], $candidate->password_hash)) {
                    $request->session()->regenerate();
                    $request->session()->put('user', [
                        'id' => (int) $candidate->id,
                        'name' => (string) $candidate->display_name,
                        'email' => (string) $candidate->email,
                        'username' => $candidate->username,
                        'role' => (string) $candidate->role,
                    ]);

                    return redirect('/admin');
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Helpdesk sign-in failed.', ['exception' => $exception]);
            return back()->withErrors([
                'login' => 'The sign-in service is unavailable. Check the local database configuration.',
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'login' => 'The administrator email or password is incorrect, or this account is inactive.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $nativeSessionName = session_name();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('dabaw_helpdesk_tool');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $request->isSecure(),
            'samesite' => 'Lax',
            'path' => '/helpdesk',
        ]);
        session_start();
        $_SESSION = [];
        session_destroy();
        setcookie('dabaw_helpdesk_tool', '', [
            'expires' => time() - 3600,
            'path' => '/helpdesk',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($nativeSessionName);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
