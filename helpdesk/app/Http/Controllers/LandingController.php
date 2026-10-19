<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
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
                    ? route('notices.attachment', ['notice' => $notice->id])
                    : null;
                unset($notice->attachment_path);
            });
        } catch (\Throwable $exception) {
            Log::error('Public notices could not load.', ['exception' => $exception]);
            $notices = collect();
            session()->flash('error', 'Notices are temporarily unavailable.');
        }

        return Inertia::render('Landing/Index', ['notices' => $notices]);
    }
}
