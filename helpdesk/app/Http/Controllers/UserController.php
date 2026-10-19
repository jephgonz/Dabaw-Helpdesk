<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        try {
            $users = DB::table('helpdesk_users')
                ->select(['id', 'display_name', 'username', 'email', 'role', 'is_active', 'created_at'])
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $exception) {
            Log::error('Staff account directory could not load.', ['exception' => $exception]);
            $users = collect();
            session()->flash('error', 'The user directory is unavailable. Check the database connection.');
        }

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'activePage' => 'users',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'username' => ['nullable', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('helpdesk_users', 'username')],
            'password' => ['required', 'string', 'min:12', 'max:4096'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        if ($validated['role'] === 'staff' && empty($validated['username'])) {
            return back()->withErrors(['username' => 'Staff accounts need a unique username.'])->withInput();
        }
        if ($validated['role'] === 'admin' && !empty($validated['username'])) {
            return back()->withErrors(['username' => 'Administrator accounts do not use a staff username.'])->withInput();
        }

        try {
            DB::table('helpdesk_users')->insert([
                'display_name' => trim($validated['display_name']),
                'username' => $validated['role'] === 'staff' ? strtolower($validated['username']) : null,
                'email' => strtolower($validated['email']),
                'password_hash' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Account creation failed.', ['exception' => $exception]);
            return back()->withErrors(['form' => 'The account could not be created. Check the username and database configuration.'])->withInput();
        }

        return redirect('/admin/users')->with('success', 'Account created.');
    }

    public function setStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', Rule::in(['0', '1', 0, 1])],
        ]);
        $adminId = (int) $request->session()->get('user.id');
        $active = (int) $validated['is_active'];

        try {
            DB::transaction(function () use ($validated, $adminId, $active): void {
                $target = DB::table('helpdesk_users')->where('id', $validated['user_id'])->lockForUpdate()->first();
                if (!$target) {
                    throw new \RuntimeException('Account not found.');
                }
                if (!$active && (int) $target->id === $adminId) {
                    throw new \RuntimeException('You cannot deactivate your own account.');
                }
                if (!$active && $target->role === 'admin' && (int) $target->is_active === 1) {
                    $activeAdmins = DB::table('helpdesk_users')
                        ->where('role', 'admin')->where('is_active', 1)->lockForUpdate()->get(['id']);
                    if ($activeAdmins->count() <= 1) {
                        throw new \RuntimeException('The last active administrator cannot be deactivated.');
                    }
                }
                DB::table('helpdesk_users')->where('id', $target->id)->update([
                    'is_active' => $active,
                    'updated_at' => now(),
                ]);
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::error('Account status update failed.', ['exception' => $exception]);
            return back()->withErrors(['form' => 'The account status could not be updated.']);
        }

        return back()->with('success', $active ? 'Account activated.' : 'Account deactivated.');
    }
}
