<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->session()->get('user');
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'newTicketCount' => fn (): ?int => $this->newTicketCount($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'generatedCard' => fn () => $request->session()->get('generatedCard'),
            ],
        ];
    }

    private function newTicketCount(Request $request): ?int
    {
        $user = $request->session()->get('user');
        if (($user['role'] ?? null) !== 'admin') {
            return 0;
        }

        try {
            return (int) DB::table('helpdesk_tickets')->where('status', 'new')->count();
        } catch (\Throwable $exception) {
            Log::error('Sidebar support request count could not load.', ['exception' => $exception]);

            return null;
        }
    }
}
