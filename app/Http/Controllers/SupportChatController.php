<?php

namespace App\Http\Controllers;

use App\Services\SupportChat;
use App\Support\HelpArticles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints behind the chat bubble. */
class SupportChatController extends Controller
{
    /** Help search: works for visitors too. */
    public function help(Request $request): JsonResponse
    {
        $q = is_string($request->query('q')) ? mb_substr($request->query('q'), 0, 200) : '';

        return response()->json(['articles' => HelpArticles::search($q)]);
    }

    /** Current chat (messages after ?after=id) plus whether support is online. */
    public function state(Request $request, SupportChat $chat): JsonResponse
    {
        $user = $request->user();
        $online = $chat->staffOnline();
        if (! $user) {
            return response()->json(['signedIn' => false, 'online' => $online]);
        }
        abort_if($user->role === 'admin', 403);
        $chat->markLearnerActive($user);
        $open = $chat->openChat($user);
        $after = max(0, (int) $request->query('after', 0));
        if ($open) {
            $open->forceFill(['student_read_at' => now()])->saveQuietly();
        }

        return response()->json([
            'signedIn' => true,
            'online' => $online,
            'chat' => $open ? ['id' => $open->id] : null,
            'messages' => $open ? $chat->messages($open, $after)->map(fn ($m) => ['mine' => ! $m['staff']] + $m)->values() : [],
        ]);
    }

    public function send(Request $request, SupportChat $chat): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role === 'admin', 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $conversation = $chat->learnerSends($user, trim($data['body']));

        return response()->json(['chat' => ['id' => $conversation->id], 'online' => $chat->staffOnline()], 201);
    }
}
