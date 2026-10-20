<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyAllocationController extends Controller
{
    public function index(): Response
    {
        $monthlyTarget = DB::table('helpdesk_settings')
            ->where('setting_key', 'monthly_target')
            ->value('setting_value');
        $weekdayAllocation = DB::table('helpdesk_settings')
            ->where('setting_key', 'weekday_allocation')
            ->value('setting_value');

        return Inertia::render('Admin/MonthlyAllocationPlanner', [
            'activePage' => 'monthly-allocation',
            'monthlyTarget' => (int) ($monthlyTarget ?? config('monthly-allocation.monthly_target')),
            'weekdayAllocation' => (int) ($weekdayAllocation ?? config('monthly-allocation.weekday_allocation')),
            'adPaths' => $this->availableAdPaths(),
        ]);
    }

    public function download(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'target' => ['required', 'integer', 'min:1', 'max:'.config('monthly-allocation.maximum_target')],
            'base_amount' => ['required', 'integer', 'min:1', 'max:'.config('monthly-allocation.maximum_target')],
        ]);

        $ads = $this->availableAdPaths();
        if ($ads === []) {
            return response()->json([
                'message' => 'The configured ad directory is missing or has no PNG assets.',
            ], 503);
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $validated['month']);
        $dates = [];
        for ($date = $month->startOfMonth(); $date->lte($month->endOfMonth()); $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $dates[] = $date;
            }
        }
        if (count($dates) < 2) {
            return response()->json(['message' => 'The selected month needs at least two weekdays.'], 422);
        }

        $validatedRows = Validator::make($request->all(), [
            'references' => ['required', 'array', 'list', 'size:'.count($dates)],
            'references.*' => ['required', 'regex:/^1\d{8}$/'],
            'ads' => ['required', 'array', 'list', 'size:'.count($dates)],
            'ads.*' => ['required', 'string', Rule::in($ads)],
        ])->validate();

        $target = (int) $validated['target'];
        $requestedBaseAllocation = (int) $validated['base_amount'];
        $standardAllocationFits = $target >= $requestedBaseAllocation * (count($dates) - 2) + 2;
        $baseAllocation = $standardAllocationFits
            ? $requestedBaseAllocation
            : min($requestedBaseAllocation, intdiv($target, count($dates)));
        $finalTwoWeekdayTotal = $target - $baseAllocation * (count($dates) - 2);
        $lastDayIfUnsplit = $finalTwoWeekdayTotal - $baseAllocation;
        if ($lastDayIfUnsplit < 500) {
            $firstFinalAllocation = $baseAllocation;
            $lastFinalAllocation = $lastDayIfUnsplit;
        } else {
            $firstFinalAllocation = intdiv($finalTwoWeekdayTotal, 2);
            $lastFinalAllocation = $finalTwoWeekdayTotal - $firstFinalAllocation;
        }
        DB::table('helpdesk_settings')->updateOrInsert(
            ['setting_key' => 'monthly_target'],
            ['setting_value' => (string) $target],
        );
        DB::table('helpdesk_settings')->updateOrInsert(
            ['setting_key' => 'weekday_allocation'],
            ['setting_value' => (string) $requestedBaseAllocation],
        );

        $output = fopen('php://temp', 'w+');
        if ($output === false) {
            throw new \RuntimeException('Could not create the monthly CSV output.');
        }

        fputcsv($output, ['number', 'datetime', 'value', 'refnumber', 'ads'], ',', '"', '');
        $number = (string) config('monthly-allocation.number');
        foreach ($dates as $index => $date) {
            $minuteOfDay = random_int(8 * 60, 11 * 60 + 59);
            $time = CarbonImmutable::createFromTime(
                intdiv($minuteOfDay, 60),
                $minuteOfDay % 60,
            )->format('g:i A');
            $amount = match ($index) {
                count($dates) - 2 => $firstFinalAllocation,
                count($dates) - 1 => $lastFinalAllocation,
                default => $baseAllocation,
            };
            fputcsv($output, [
                $number,
                $date->format('M d, Y').' '.$time,
                $amount,
                $validatedRows['references'][$index],
                $validatedRows['ads'][$index],
            ], ',', '"', '');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        if ($csv === false) {
            throw new \RuntimeException('Could not read the generated monthly CSV.');
        }

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            'monthly-plan-'.$month->format('F-Y').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function availableAdPaths(): array
    {
        $adDirectory = (string) config('monthly-allocation.ad_directory');
        if (!is_dir($adDirectory)) {
            return [];
        }

        return collect(File::files($adDirectory))
            ->filter(static fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'png')
            ->map(static fn (\SplFileInfo $file): string|false => $file->getRealPath())
            ->filter(static fn (string|false $path): bool => is_string($path))
            ->values()
            ->all();
    }
}
