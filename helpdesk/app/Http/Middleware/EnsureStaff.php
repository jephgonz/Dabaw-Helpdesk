<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('user.role') !== 'staff') {
            return redirect()->route('login', ['type' => 'staff']);
        }
        try {
            $active = DB::table('helpdesk_users')
                ->where('id', $request->session()->get('user.id'))
                ->where('role', 'staff')
                ->where('is_active', 1)
                ->exists();
        } catch (\Throwable $exception) {
            Log::error('Staff access could not be verified.', ['exception' => $exception]);
            abort(503, 'Staff access could not be verified.');
        }
        if (!$active) {
            $request->session()->forget('user');
            return redirect()->route('login', ['type' => 'staff']);
        }

        return $next($request);
    }
}
