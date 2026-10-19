<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'taskStatusCounts' => $this->taskStatusCounts(),
            'newTicketCount' => $newTicketCount,
            'activePage' => 'overview',
        ]);
    }

    public function staff(Request $request): Response
    {
        try {
            $notices = DB::table('helpdesk_notices')
                ->leftJoin('helpdesk_users', 'helpdesk_users.id', '=', 'helpdesk_notices.posted_by')
                ->orderByDesc('helpdesk_notices.created_at')
                ->limit(10)
                ->get([
                    'helpdesk_notices.id',
                    'helpdesk_notices.title',
                    'helpdesk_notices.body',
                    'helpdesk_notices.attachment_path',
                    'helpdesk_notices.created_at',
                    'helpdesk_users.display_name as author',
                ]);
            $notices->each(function (object $notice): void {
                $notice->attachment_url = $notice->attachment_path
                    ? route('staff.notices.attachment', ['notice' => $notice->id])
                    : null;
                unset($notice->attachment_path);
            });
        } catch (\Throwable $exception) {
            Log::error('Staff notices could not load.', ['exception' => $exception]);
            $notices = collect();
            session()->flash('error', 'The latest notices are unavailable. Check that the application database is up to date.');
        }

        $user = $request->session()->get('user');

        return Inertia::render('Staff/Dashboard', [
            'notices' => $notices,
            'taskStatusCounts' => $this->taskStatusCounts((int) $user['id']),
        ]);
    }

    private function taskStatusCounts(?int $boardUserId = null): array
    {
        try {
            $query = DB::table('helpdesk_tasks')
                ->select('status', DB::raw('COUNT(*) as total'));
            if ($boardUserId !== null) {
                $query->where('board_user_id', $boardUserId);
            }

            $counts = $query->groupBy('status')->pluck('total', 'status');

            return [
                'todo' => (int) ($counts['todo'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'finished' => (int) ($counts['finished'] ?? 0),
            ];
        } catch (\Throwable $exception) {
            Log::error('Task board summary could not load.', ['board_user_id' => $boardUserId, 'exception' => $exception]);
            session()->flash('error', 'The task board summary is unavailable. Check that the task board migration has been applied.');

            return ['todo' => 0, 'in_progress' => 0, 'finished' => 0];
        }
    }

    public function staffRequests(Request $request): Response
    {
        $user = $request->session()->get('user');
        try {
            $tickets = DB::table('helpdesk_tickets')
                ->where('submitted_by', $user['id'])
                ->orderByDesc('created_at')
                ->get(['id', 'subject', 'description', 'status', 'created_at']);
        } catch (\Throwable $exception) {
            Log::error('Staff support requests could not load.', ['exception' => $exception]);
            $tickets = collect();
            session()->flash('error', 'Your support requests are unavailable. Check the database connection.');
        }

        return Inertia::render('Staff/Requests', ['tickets' => $tickets]);
    }
}
