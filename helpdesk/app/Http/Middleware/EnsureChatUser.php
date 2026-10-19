<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('user');
        $role = $user['role'] ?? null;
        if ($role !== 'admin') {
            return redirect()->route('admin.login');
        }

        try {
            $active = DB::table('helpdesk_users')
                ->where('id', $user['id'] ?? 0)
                ->where('role', 'admin')
                ->where('is_active', 1)
                ->exists();
        } catch (\Throwable $exception) {
            Log::error('Chat access could not be verified.', ['exception' => $exception]);
            abort(503, 'Chat access could not be verified.');
        }

        if (!$active) {
            $request->session()->forget('user');

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
