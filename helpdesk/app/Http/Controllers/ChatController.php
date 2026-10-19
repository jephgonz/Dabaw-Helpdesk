<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Chat/Index');
    }

    public function state(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user.id');
        $users = DB::table('helpdesk_users')
            ->where('is_active', 1)
            ->where('id', '<>', $userId)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'role'])
            ->map(static fn (object $user): array => [
                'id' => (int) $user->id,
                'name' => $user->display_name,
                'role' => $user->role,
            ])
            ->values();

        $conversations = DB::table('chat_conversations')
            ->join('helpdesk_users as user_one', 'user_one.id', '=', 'chat_conversations.user_one_id')
            ->join('helpdesk_users as user_two', 'user_two.id', '=', 'chat_conversations.user_two_id')
            ->where(static fn ($query) => $query->where('chat_conversations.user_one_id', $userId)
                ->orWhere('chat_conversations.user_two_id', $userId))
            ->orderByDesc('chat_conversations.updated_at')
            ->get([
                'chat_conversations.id',
                'chat_conversations.user_one_id',
                'chat_conversations.user_two_id',
                'chat_conversations.updated_at',
                'user_one.display_name as user_one_name',
                'user_one.role as user_one_role',
                'user_two.display_name as user_two_name',
                'user_two.role as user_two_role',
            ])
            ->map(function (object $conversation) use ($userId): array {
                $other = (int) $conversation->user_one_id === $userId
                    ? ['id' => (int) $conversation->user_two_id, 'name' => $conversation->user_two_name, 'role' => $conversation->user_two_role]
                    : ['id' => (int) $conversation->user_one_id, 'name' => $conversation->user_one_name, 'role' => $conversation->user_one_role];
                $latestMessage = DB::table('chat_messages')
                    ->where('conversation_id', $conversation->id)
                    ->orderByDesc('id')
                    ->first(['body', 'created_at', 'sender_id']);

                return [
                    'id' => (int) $conversation->id,
                    'user' => $other,
                    'updatedAt' => $conversation->updated_at,
                    'lastMessage' => $latestMessage ? [
                        'body' => $latestMessage->body,
                        'createdAt' => $latestMessage->created_at,
                        'senderId' => (int) $latestMessage->sender_id,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'users' => $users,
            'conversations' => $conversations,
            'csrfToken' => csrf_token(),
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'min:1', 'exists:helpdesk_users,id']]);
        $userId = (int) $request->session()->get('user.id');
        $otherUserId = (int) $validated['user_id'];
        abort_if($otherUserId === $userId, 422, 'Choose another account to start a conversation.');
        abort_unless(DB::table('helpdesk_users')->where('id', $otherUserId)->where('is_active', 1)->exists(), 422, 'This account is inactive.');

        $userOneId = min($userId, $otherUserId);
        $userTwoId = max($userId, $otherUserId);
        DB::table('chat_conversations')->insertOrIgnore([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conversationId = (int) DB::table('chat_conversations')
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->value('id');

        return response()->json(['conversation_id' => $conversationId], 201);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $userId = (int) $request->session()->get('user.id');
        $this->authorizeConversation($conversation, $userId);

        $messages = DB::table('chat_messages')
            ->join('helpdesk_users', 'helpdesk_users.id', '=', 'chat_messages.sender_id')
            ->where('chat_messages.conversation_id', $conversation)
            ->orderByDesc('chat_messages.id')
            ->limit(200)
            ->get([
                'chat_messages.id',
                'chat_messages.sender_id',
                'helpdesk_users.display_name as sender_name',
                'chat_messages.body',
                'chat_messages.created_at',
            ])
            ->reverse()
            ->values()
            ->map(static fn (object $message): array => [
                'id' => (int) $message->id,
                'senderId' => (int) $message->sender_id,
                'senderName' => $message->sender_name,
                'body' => $message->body,
                'createdAt' => $message->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:8000']]);
        $userId = (int) $request->session()->get('user.id');
        $this->authorizeConversation($conversation, $userId);

        $now = now();
        $messageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversation,
            'sender_id' => $userId,
            'body' => trim($validated['body']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('chat_conversations')->where('id', $conversation)->update(['updated_at' => $now]);

        return response()->json(['message_id' => (int) $messageId, 'created_at' => $now->toIso8601String()], 201);
    }

    private function authorizeConversation(int $conversation, int $userId): void
    {
        abort_unless(DB::table('chat_conversations')
            ->where('id', $conversation)
            ->where(static fn ($query) => $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId))
            ->exists(), 404);
    }
}
