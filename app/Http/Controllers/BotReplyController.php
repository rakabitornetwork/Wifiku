<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatBot;
use App\Services\Chat\ChatReply;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pratinjau balasan bot pelanggan (WhatsApp dan Telegram) untuk tiap pilihan menu, dengan data contoh.
 */
class BotReplyController extends Controller
{
    public function index(ChatBot $bot): Response
    {
        $botSteps = array_map(fn (array $step): array => [
            'trigger' => $step['trigger'],
            'title' => $step['title'],
            'replies' => array_map(fn (ChatReply $reply): array => ['text' => $reply->text, 'buttons' => $reply->buttons, 'request_contact' => $reply->requestContact], $step['replies']),
        ], $bot->preview());

        return Inertia::render('Settings/BotReplies', ['botSteps' => $botSteps]);
    }
}
