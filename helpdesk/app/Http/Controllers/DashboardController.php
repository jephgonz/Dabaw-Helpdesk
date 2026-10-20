<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(): Response
    {
        try {
            $users = DB::table('helpdesk_users')
                ->select(['id', 'display_name', 'username', 'email', 'role', 'is_active', 'created_at'])
                ->orderByDesc('created_at')
                ->get();
            $weekStartsAt = now()->startOfWeek();
            $nextWeekStartsAt = $weekStartsAt->copy()->addWeek();
            $ticketCounts = DB::table('helpdesk_tickets')
                ->selectRaw(
                    "SUM(status = 'new') as new_count, SUM(status = 'in_progress') as in_progress_count, SUM(status = 'resolved' AND resolved_at >= ? AND resolved_at < ?) as resolved_this_week",
                    [$weekStartsAt, $nextWeekStartsAt]
                )
                ->first();
            $newTicketCount = (int) DB::table('helpdesk_tickets')->where('status', 'new')->count();
        } catch (\Throwable $exception) {
            Log::error('Admin overview could not load.', ['exception' => $exception]);
            $users = collect();
            $ticketCounts = (object) ['new_count' => 0, 'in_progress_count' => 0, 'resolved_this_week' => 0];
            $newTicketCount = 0;
            session()->flash('error', 'The overview could not be loaded. Check the database connection and schema.');
        }

        return Inertia::render('Admin/Dashboard', [
            'users' => $users,
            'ticketCounts' => $ticketCounts,
            'newTicketCount' => $newTicketCount,
            'activePage' => 'overview',
        ]);
    }
}
