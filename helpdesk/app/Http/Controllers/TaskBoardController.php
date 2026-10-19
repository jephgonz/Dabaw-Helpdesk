<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'board' => ['nullable', 'integer', 'min:1'],
        ]);
        $currentUser = $request->session()->get('user');
        $isAdmin = $currentUser['role'] === 'admin';
        $search = trim((string) ($validated['search'] ?? ''));

        try {
            $boardQuery = DB::table('helpdesk_users')
                ->where('is_active', 1)
                ->where('id', $validated['board'] ?? $currentUser['id']);
            if (!$isAdmin) {
                $boardQuery->where('role', 'staff');
            }
            $board = $boardQuery->first(['id', 'display_name', 'username', 'role']);
            abort_unless($board, 404);

            $usersQuery = DB::table('helpdesk_users')->where('is_active', 1);
            if (!$isAdmin) {
                $usersQuery->where('role', 'staff');
            }
            if ($search !== '') {
                $usersQuery->where(function ($query) use ($search): void {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }
            $users = $usersQuery->orderBy('display_name')
                ->get(['id', 'display_name', 'username', 'role']);

            if (!$users->contains('id', $board->id)) {
                $users->push($board);
            }

            $tasks = DB::table('helpdesk_tasks')
                ->join('helpdesk_users as creator', 'creator.id', '=', 'helpdesk_tasks.created_by')
                ->where('helpdesk_tasks.board_user_id', $board->id)
                ->orderByRaw("CASE helpdesk_tasks.status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
                ->orderByDesc('helpdesk_tasks.created_at')
                ->get([
                    'helpdesk_tasks.id',
                    'helpdesk_tasks.board_user_id',
                    'helpdesk_tasks.created_by',
                    'helpdesk_tasks.title',
                    'helpdesk_tasks.description',
                    'helpdesk_tasks.status',
                    'helpdesk_tasks.created_at',
                    'creator.display_name as creator_name',
                ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Task board could not load.', ['exception' => $exception]);
            $users = collect();
            $board = null;
            $tasks = collect();
            $request->session()->flash('error', 'The task board is unavailable. Check that the database migration has been applied.');
        }

        return Inertia::render('Tasks/Index', [
            'users' => $users,
            'board' => $board,
            'tasks' => $tasks,
            'search' => $search,
            'currentUserId' => (int) $currentUser['id'],
            'canManage' => $isAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'board_user_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $user = $request->session()->get('user');

        try {
            $board = DB::table('helpdesk_users')
                ->where('id', $validated['board_user_id'])
                ->where('is_active', 1)
                ->first(['id', 'role']);
            abort_unless($board, 404);
            abort_unless($user['role'] === 'admin' || $board->role === 'staff', 403);

            DB::table('helpdesk_tasks')->insert([
                'board_user_id' => $board->id,
                'created_by' => $user['id'],
                'title' => trim($validated['title']),
                'description' => isset($validated['description']) ? trim($validated['description']) : null,
                'status' => 'todo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Task creation failed.', ['user_id' => $user['id'], 'exception' => $exception]);

            return back()->withErrors(['form' => 'The task could not be added. Check the database connection and migration.']);
        }

        return back()->with('success', 'Task added to the board.');
    }

    public function update(Request $request, int $task): RedirectResponse
    {
        abort_unless($request->session()->get('user.role') === 'admin', 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $updated = DB::table('helpdesk_tasks')->where('id', $task)->update([
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'updated_at' => now(),
        ]);
        abort_unless($updated > 0 || DB::table('helpdesk_tasks')->where('id', $task)->exists(), 404);

        return back()->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, int $task): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'finished'])],
        ]);
        $user = $request->session()->get('user');
        $taskRecord = DB::table('helpdesk_tasks')->where('id', $task)->first(['id', 'board_user_id']);
        abort_unless($taskRecord, 404);
        abort_unless($user['role'] === 'admin' || (int) $taskRecord->board_user_id === (int) $user['id'], 403);

        DB::table('helpdesk_tasks')->where('id', $task)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Task status updated.');
    }

    public function destroy(Request $request, int $task): RedirectResponse
    {
        abort_unless($request->session()->get('user.role') === 'admin', 403);
        $deleted = DB::table('helpdesk_tasks')->where('id', $task)->delete();
        abort_unless($deleted > 0, 404);

        return back()->with('success', 'Task deleted.');
    }
}
