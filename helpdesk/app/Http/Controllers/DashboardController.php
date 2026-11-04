<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(Request $request): Response
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

        try {
            $quarterStartsAt = now()->startOfQuarter();
            $nextQuarterStartsAt = $quarterStartsAt->copy()->addQuarter();
            $taskSummary = DB::table('helpdesk_accomplishments')
                ->where('created_by', $request->session()->get('user.id'))
                ->selectRaw(
                    "SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN status = 'completed' AND completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END) as completed_this_quarter",
                    [$quarterStartsAt, $nextQuarterStartsAt]
                )
                ->first();
            $pendingTaskCount = (int) ($taskSummary->pending_count ?? 0);
            $completedThisQuarterCount = (int) ($taskSummary->completed_this_quarter ?? 0);
            $openTasks = DB::table('helpdesk_accomplishments')
                ->where('created_by', $request->session()->get('user.id'))
                ->where('status', 'todo');
            $openTaskCount = $pendingTaskCount;
            $pendingTasks = $openTasks
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderByDesc('created_at')
                ->limit(4)
                ->get(['id', 'title', 'due_date', 'status']);
        } catch (\Throwable $exception) {
            Log::error('Admin overview task summary could not load.', ['exception' => $exception]);
            $pendingTaskCount = 0;
            $completedThisQuarterCount = 0;
            $openTaskCount = 0;
            $pendingTasks = collect();
            session()->flash('error', 'Your task summary is unavailable. Check that the application database is up to date.');
        }

        return Inertia::render('Admin/Dashboard', [
            'users' => $users,
            'ticketCounts' => $ticketCounts,
            'newTicketCount' => $newTicketCount,
            'openTaskCount' => $openTaskCount,
            'pendingTaskCount' => $pendingTaskCount,
            'completedThisQuarterCount' => $completedThisQuarterCount,
            'pendingTasks' => $pendingTasks,
            'activePage' => 'overview',
        ]);
    }
}
