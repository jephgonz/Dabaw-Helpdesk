<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        try {
            $tickets = DB::table('helpdesk_tickets')
                ->select(['id', 'submitted_by', 'requester_name', 'requester_email', 'subject', 'description', 'status', 'created_at'])
                ->orderByRaw("FIELD(status, 'new', 'in_progress', 'resolved')")
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $exception) {
            Log::error('Support requests could not load.', ['exception' => $exception]);
            $tickets = collect();
            session()->flash('error', 'The support request inbox is unavailable. Check the database connection and schema.');
        }

        return Inertia::render('Admin/Tickets', [
            'tickets' => $tickets,
            'newTicketCount' => $tickets->where('status', 'new')->count(),
            'activePage' => 'tickets',
        ]);
    }

    public function submitPublic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:120'],
            'requester_email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'website' => ['prohibited'],
        ]);

        try {
            DB::table('helpdesk_tickets')->insert([
                'submitted_by' => null,
                'requester_name' => trim($validated['requester_name']),
                'requester_email' => strtolower(trim($validated['requester_email'])),
                'subject' => trim($validated['subject']),
                'description' => trim($validated['description']),
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Public support report submission failed.', ['exception' => $exception]);
            return back()->withErrors(['form' => 'Your report could not be sent. Please try again later.']);
        }

        return back()->with('success', 'Your report was sent to the helpdesk. Thank you.');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])],
        ]);

        try {
            $updated = DB::transaction(function () use ($validated): bool {
                $ticket = DB::table('helpdesk_tickets')->where('id', $validated['ticket_id'])->lockForUpdate()->first();
                if (!$ticket) {
                    return false;
                }
                $resolvedAt = $validated['status'] === 'resolved'
                    ? ($ticket->status === 'resolved' && $ticket->resolved_at !== null ? $ticket->resolved_at : now())
                    : null;
                DB::table('helpdesk_tickets')->where('id', $ticket->id)->update([
                    'status' => $validated['status'],
                    'resolved_at' => $resolvedAt,
                    'updated_at' => now(),
                ]);

                return true;
            });
            if (!$updated) {
                return back()->withErrors(['form' => 'The support request was not found.']);
            }
        } catch (\Throwable $exception) {
            Log::error('Support request status update failed.', ['exception' => $exception]);
            return back()->withErrors(['form' => 'The support request status could not be updated.']);
        }

        return back()->with('success', 'Support request status updated.');
    }

    public function metrics(Request $request)
    {
        $period = $request->query('period', 'month');
        if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
            return response()->json(['error' => 'Choose day, week, month, or year.'], 400);
        }

        $settings = match ($period) {
            'day' => ['slots' => 24, 'format' => '%Y-%m-%d %H:00:00', 'start' => now()->startOfHour()->subHours(23), 'step' => 'hour', 'label' => 'H:i', 'title' => 'Resolved in the past 24 hours'],
            'week' => ['slots' => 7, 'format' => '%Y-%m-%d', 'start' => now()->startOfDay()->subDays(6), 'step' => 'day', 'label' => 'D', 'title' => 'Resolved in the past 7 days'],
            'month' => ['slots' => 30, 'format' => '%Y-%m-%d', 'start' => now()->startOfDay()->subDays(29), 'step' => 'day', 'label' => 'M j', 'title' => 'Resolved in the past 30 days'],
            default => ['slots' => 12, 'format' => '%Y-%m', 'start' => now()->startOfMonth()->subMonths(11), 'step' => 'month', 'label' => 'M Y', 'title' => 'Resolved in the past 12 months'],
        };
        try {
            $counts = DB::table('helpdesk_tickets')
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', $settings['start'])
                ->selectRaw('DATE_FORMAT(resolved_at, ?) AS bucket, COUNT(*) AS total', [$settings['format']])
                ->groupBy('bucket')->pluck('total', 'bucket');
        } catch (\Throwable $exception) {
            Log::error('Ticket resolution metrics could not load.', ['exception' => $exception]);
            return response()->json(['error' => 'Ticket resolution metrics are unavailable.'], 503);
        }
        $labels = $keys = $values = [];
        $cursor = $settings['start']->copy();
        for ($index = 0; $index < $settings['slots']; $index++) {
            $key = $period === 'day' ? $cursor->format('Y-m-d H:00:00') : ($period === 'year' ? $cursor->format('Y-m') : $cursor->format('Y-m-d'));
            $keys[] = $key;
            $labels[] = $cursor->format($settings['label']);
            $values[] = (int) ($counts[$key] ?? 0);
            $cursor = $settings['step'] === 'hour' ? $cursor->addHour() : ($settings['step'] === 'month' ? $cursor->addMonth() : $cursor->addDay());
        }

        return response()->json(['period' => $period, 'title' => $settings['title'], 'labels' => $labels, 'keys' => $keys, 'values' => $values, 'total' => array_sum($values)]);
    }
}
