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
        if ($user) {
            return redirect($user['role'] === 'admin' ? '/admin' : '/dashboard');
        }

        $type = $request->query('type', 'staff');

        return Inertia::render('Auth/Login', [
            'type' => in_array($type, ['admin', 'staff'], true) ? $type : 'staff',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $type = $request->input('login_action') === 'admin_login' ? 'admin' : 'staff';
        $validated = $request->validate([
            'login_action' => ['required', 'in:staff_login,admin_login'],
            'password' => ['required', 'string', 'max:4096'],
            'username' => ['required_if:login_action,staff_login', 'nullable', 'string', 'max:80'],
            'email' => ['required_if:login_action,admin_login', 'nullable', 'email', 'max:190'],
        ]);

        try {
            $query = DB::table('helpdesk_users')
                ->where('role', $type)
                ->where('is_active', 1);
            $identifier = $type === 'admin'
                ? strtolower(trim((string) $validated['email']))
                : strtolower(trim((string) $validated['username']));
            $candidates = $query
                ->where($type === 'admin' ? 'email' : 'username', $identifier)
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

                    return redirect($type === 'admin' ? '/admin' : '/dashboard');
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Helpdesk sign-in failed.', ['exception' => $exception]);
            return back()->withErrors([
                'login' => 'The sign-in service is unavailable. Check the local database configuration.',
            ])->onlyInput('email', 'username');
        }

        return back()->withErrors([
            'login' => $type === 'admin'
                ? 'The administrator email or password is incorrect, or this account is inactive.'
                : 'The username or password is incorrect, or this account is inactive.',
        ])->onlyInput('email', 'username');
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

        return redirect()->route('login');
    }
}
