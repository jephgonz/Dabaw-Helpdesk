<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->session()->get('user');
        $userId = (int) $user['id'];
        $role = (string) $user['role'];
        abort_unless($role === 'admin', 403);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
        ]);

        $displayName = trim($validated['display_name']);
        $email = strtolower(trim($validated['email']));

        try {
            $updated = DB::table('helpdesk_users')
                ->where('id', $userId)
                ->where('role', $role)
                ->where('is_active', 1)
                ->update([
                    'display_name' => $displayName,
                    'email' => $email,
                    'updated_at' => now(),
                ]);
            if ($updated === 0 && !DB::table('helpdesk_users')->where('id', $userId)->where('is_active', 1)->exists()) {
                return redirect()->route('login')->withErrors(['login' => 'Your account is no longer active. Please sign in again.']);
            }
        } catch (\Throwable $exception) {
            Log::error('Profile update failed.', ['user_id' => $userId, 'exception' => $exception]);

            return back()->withErrors(['profile' => 'Your profile could not be updated. Please try again.'])->withInput();
        }

        $request->session()->put('user', [
            ...$user,
            'name' => $displayName,
            'email' => $email,
        ]);

        return back()->with('success', 'Profile updated.');
    }
}
