<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrowserNotificationController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'enabled' => false,
        'task_due' => true,
    ];

    public function settings(Request $request): JsonResponse
    {
        try {
            return response()->json($this->settingsFor((int) $request->session()->get('user.id')));
        } catch (\Throwable $exception) {
            Log::error('Browser notification settings could not load.', ['exception' => $exception]);

            return response()->json(['error' => 'Notification settings are unavailable.'], 503);
        }
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'task_due' => ['required', 'boolean'],
        ]);
        $adminId = (int) $request->session()->get('user.id');

        try {
            DB::table('helpdesk_notification_settings')->updateOrInsert(
                ['admin_id' => $adminId],
                [
                    'enabled' => (bool) $validated['enabled'],
                    'task_due' => (bool) $validated['task_due'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $exception) {
            Log::error('Browser notification settings could not save.', ['exception' => $exception, 'admin_id' => $adminId]);

            return response()->json(['error' => 'Notification settings could not be saved.'], 503);
        }

        return response()->json($this->settingsFor($adminId));
    }

    public function poll(Request $request): JsonResponse
    {
        $adminId = (int) $request->session()->get('user.id');

        try {
            $settings = $this->settingsFor($adminId);
            $events = [];
            if (! $settings['enabled']) {
                return response()->json([
                    'settings' => $settings,
                    'events' => $events,
                ]);
            }

            if ($settings['task_due']) {
                $tasks = DB::table('helpdesk_accomplishments')
                    ->where('created_by', $adminId)
                    ->whereIn('status', ['todo', 'in_progress'])
                    ->whereDate('due_date', now()->toDateString())
                    ->orderBy('due_date')
                    ->limit(20)
                    ->get(['id', 'title']);
                foreach ($tasks as $task) {
                    $events[] = [
                        'id' => 'task-due-'.$task->id.'-'.now()->toDateString(),
                        'title' => 'Task due today',
                        'body' => $task->title,
                        'url' => '/admin/accomplishments',
                    ];
                }
            }

        } catch (\Throwable $exception) {
            Log::error('Browser notifications could not be checked.', ['exception' => $exception, 'admin_id' => $adminId]);

            return response()->json(['error' => 'Notifications could not be checked.'], 503);
        }

        return response()->json([
            'settings' => $settings,
            'events' => $events,
        ]);
    }

    private function settingsFor(int $adminId): array
    {
        $settings = DB::table('helpdesk_notification_settings')->where('admin_id', $adminId)->first();
        if (! $settings) {
            return self::DEFAULT_SETTINGS;
        }

        return [
            'enabled' => (bool) $settings->enabled,
            'task_due' => (bool) $settings->task_due,
        ];
    }
}
