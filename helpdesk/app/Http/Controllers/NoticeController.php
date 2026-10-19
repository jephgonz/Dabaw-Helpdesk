<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class NoticeController extends Controller
{
    public function index(): Response
    {
        try {
            $notices = DB::table('helpdesk_notices')
                ->leftJoin('helpdesk_users', 'helpdesk_users.id', '=', 'helpdesk_notices.posted_by')
                ->orderByDesc('helpdesk_notices.created_at')
                ->limit(50)
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
                    ? route('notices.attachment', ['notice' => $notice->id])
                    : null;
                unset($notice->attachment_path);
            });
        } catch (\Throwable $exception) {
            Log::error('Admin notices could not load.', ['exception' => $exception]);
            $notices = collect();
            session()->flash('error', 'Notices are unavailable. Check that the application database is up to date.');
        }

        return Inertia::render('Admin/Notices', ['notices' => $notices]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $user = $request->session()->get('user');
        $attachmentPath = null;

        try {
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('notice-attachments', 'local');
                if (!is_string($attachmentPath)) {
                    throw new RuntimeException('The memo image could not be stored.');
                }
            }
            DB::table('helpdesk_notices')->insert([
                'title' => trim($validated['title']),
                'body' => trim($validated['body']),
                'attachment_path' => $attachmentPath,
                'posted_by' => $user['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($attachmentPath !== null) {
                Storage::disk('local')->delete($attachmentPath);
            }
            Log::error('Admin notice could not be posted.', ['exception' => $exception]);

            return back()->withErrors(['form' => 'The notice could not be posted. Check the database connection and schema.']);
        }

        return back()->with('success', 'Notice published on the public helpdesk page.');
    }

    public function attachment(int $notice)
    {
        $noticeRecord = DB::table('helpdesk_notices')->where('id', $notice)->first(['attachment_path']);
        abort_if(!$noticeRecord || !$noticeRecord->attachment_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($noticeRecord->attachment_path), 404);
        $path = $disk->path($noticeRecord->attachment_path);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['notice_id' => ['required', 'integer', 'min:1']]);

        try {
            $notice = DB::table('helpdesk_notices')->where('id', $validated['notice_id'])->first(['attachment_path']);
            $deleted = DB::table('helpdesk_notices')->where('id', $validated['notice_id'])->delete();
            if ($deleted === 0) {
                return back()->withErrors(['form' => 'The notice was not found.']);
            }
            if ($notice?->attachment_path && !Storage::disk('local')->delete($notice->attachment_path)) {
                Log::warning('Deleted notice attachment could not be removed from storage.', ['notice_id' => $validated['notice_id']]);
            }
        } catch (\Throwable $exception) {
            Log::error('Admin notice could not be deleted.', ['exception' => $exception]);

            return back()->withErrors(['form' => 'The notice could not be deleted.']);
        }

        return back()->with('success', 'Notice removed from the public helpdesk page.');
    }
}
