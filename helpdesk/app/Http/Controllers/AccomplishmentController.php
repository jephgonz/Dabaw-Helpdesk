<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccomplishmentController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $accomplishments = DB::table('helpdesk_accomplishments')
                ->where('created_by', $request->session()->get('user.id'))
                ->orderByRaw("CASE status WHEN 'todo' THEN 0 ELSE 1 END")
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'title',
                    'description',
                    'due_date',
                    'status',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ]);
        } catch (\Throwable $exception) {
            Log::error('Accomplishment board could not load.', ['exception' => $exception]);
            $accomplishments = collect();
            session()->flash('error', 'The accomplishment board is unavailable. Check that the application database is up to date.');
        }

        return Inertia::render('Admin/Accomplishments', [
            'accomplishments' => $accomplishments,
            'activePage' => 'accomplishments',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        try {
            DB::table('helpdesk_accomplishments')->insert([
                'created_by' => $request->session()->get('user.id'),
                'title' => trim($validated['title']),
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
                'due_date' => $validated['due_date'] ?? null,
                'status' => $validated['status'],
                'completed_at' => $validated['status'] === 'completed' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Accomplishment could not be created.', ['exception' => $exception]);

            return back()->withErrors(['form' => 'The accomplishment could not be saved. Check the database connection and schema.']);
        }

        return back()->with('success', 'Accomplishment added to your board.');
    }

    public function update(Request $request, int $accomplishment): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $userId = $request->session()->get('user.id');

        try {
            $updated = DB::transaction(function () use ($validated, $userId, $accomplishment): bool {
                $task = DB::table('helpdesk_accomplishments')
                    ->where('id', $accomplishment)
                    ->where('created_by', $userId)
                    ->lockForUpdate()
                    ->first(['status', 'completed_at']);
                if (! $task) {
                    return false;
                }

                $completedAt = $validated['status'] === 'completed'
                    ? ($task->status === 'completed' && $task->completed_at !== null ? $task->completed_at : now())
                    : null;
                DB::table('helpdesk_accomplishments')
                    ->where('id', $accomplishment)
                    ->where('created_by', $userId)
                    ->update([
                        'title' => trim($validated['title']),
                        'description' => isset($validated['description']) ? trim($validated['description']) : null,
                        'due_date' => $validated['due_date'] ?? null,
                        'status' => $validated['status'],
                        'completed_at' => $completedAt,
                        'updated_at' => now(),
                    ]);

                return true;
            });
            if (! $updated) {
                return back()->withErrors(['form' => 'The accomplishment was not found on your board.']);
            }
        } catch (\Throwable $exception) {
            Log::error('Accomplishment could not be updated.', ['exception' => $exception, 'accomplishment_id' => $accomplishment]);

            return back()->withErrors(['form' => 'The accomplishment could not be updated.']);
        }

        return back()->with('success', 'Accomplishment updated.');
    }

    public function complete(Request $request, int $accomplishment): RedirectResponse
    {
        $userId = $request->session()->get('user.id');

        try {
            $updated = DB::transaction(function () use ($userId, $accomplishment): bool {
                $task = DB::table('helpdesk_accomplishments')
                    ->where('id', $accomplishment)
                    ->where('created_by', $userId)
                    ->lockForUpdate()
                    ->first(['status', 'completed_at']);
                if (! $task) {
                    return false;
                }

                if ($task->status !== 'completed') {
                    DB::table('helpdesk_accomplishments')
                        ->where('id', $accomplishment)
                        ->where('created_by', $userId)
                        ->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                return true;
            });
            if (! $updated) {
                return back()->withErrors(['form' => 'The accomplishment was not found on your board.']);
            }
        } catch (\Throwable $exception) {
            Log::error('Accomplishment could not be completed.', ['exception' => $exception, 'accomplishment_id' => $accomplishment]);

            return back()->withErrors(['form' => 'The accomplishment could not be marked complete.']);
        }

        return back()->with('success', 'Accomplishment marked complete.');
    }

    public function destroy(Request $request, int $accomplishment): RedirectResponse
    {
        try {
            $deleted = DB::table('helpdesk_accomplishments')
                ->where('id', $accomplishment)
                ->where('created_by', $request->session()->get('user.id'))
                ->delete();
            if ($deleted === 0) {
                return back()->withErrors(['form' => 'The accomplishment was not found on your board.']);
            }
        } catch (\Throwable $exception) {
            Log::error('Accomplishment could not be deleted.', ['exception' => $exception, 'accomplishment_id' => $accomplishment]);

            return back()->withErrors(['form' => 'The accomplishment could not be deleted.']);
        }

        return back()->with('success', 'Accomplishment removed from your board.');
    }

    public function report(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['required', Rule::in(['1', '2', '3', '4'])],
        ]);
        $year = (int) $validated['year'];
        $quarter = (int) $validated['quarter'];
        $periodStart = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();
        $periodEnd = $periodStart->addMonths(3);

        try {
            $accomplishments = DB::table('helpdesk_accomplishments')
                ->where('created_by', $request->session()->get('user.id'))
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $periodStart)
                ->where('completed_at', '<', $periodEnd)
                ->orderBy('completed_at')
                ->get(['title', 'description', 'due_date', 'completed_at']);
        } catch (\Throwable $exception) {
            Log::error('Accomplishment report could not load.', ['exception' => $exception, 'year' => $year, 'quarter' => $quarter]);
            abort(503, 'The accomplishment report is unavailable. Check that the application database is up to date.');
        }

        $output = fopen('php://temp', 'w+');
        if ($output === false) {
            throw new \RuntimeException('Could not create the accomplishment report.');
        }
        fputcsv($output, ['Title', 'Description', 'Due date', 'Completed on'], ',', '"', '');
        foreach ($accomplishments as $accomplishment) {
            fputcsv($output, [
                $this->spreadsheetSafe($accomplishment->title),
                $this->spreadsheetSafe($accomplishment->description ?? ''),
                $accomplishment->due_date ?? '',
                CarbonImmutable::parse($accomplishment->completed_at)->format('Y-m-d'),
            ], ',', '"', '');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        if ($csv === false) {
            throw new \RuntimeException('Could not read the accomplishment report.');
        }

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            "accomplishments-Q{$quarter}-{$year}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['todo', 'completed'])],
        ];
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
