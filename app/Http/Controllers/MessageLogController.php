<?php

namespace App\Http\Controllers;

use App\Enums\WhatsappMessageType;
use App\Models\Customer;
use App\Models\TelegramMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Riwayat pesan WhatsApp dan Telegram ke pelanggan dalam satu daftar. Pesan yang memuat kode OTP, tautan reset, atau
 * password tidak pernah ditampilkan, dan tidak ikut dicari lewat isi pesan.
 */
class MessageLogController extends Controller
{
    /** Jenis yang isinya rahasia walau penandanya belum terisi (data lama). */
    private const SENSITIVE_TYPES = ['account_security', 'password_reset'];

    public function index(Request $request): Response
    {
        $channel = in_array($request->query('kanal'), ['whatsapp', 'telegram'], true) ? (string) $request->query('kanal') : '';
        $status = in_array($request->query('status'), ['queued', 'sent', 'failed'], true) ? (string) $request->query('status') : '';
        $type = in_array($request->query('jenis'), array_column(WhatsappMessageType::cases(), 'value'), true) ? (string) $request->query('jenis') : '';
        $search = trim((string) $request->query('search'));

        $whatsapp = DB::table('whatsapp_messages')->selectRaw("'whatsapp' as channel, id, customer_id, phone as destination, type, status, body, error, is_sensitive, attempts, created_at, sent_at");
        $telegram = DB::table('telegram_messages')->selectRaw("'telegram' as channel, id, customer_id, chat_id as destination, type, status, body, error, is_sensitive, attempts, created_at, sent_at");

        foreach ([$whatsapp, $telegram] as $query) {
            $this->filter($query, $status, $type, $search);
        }

        $source = match ($channel) {
            'whatsapp' => $whatsapp,
            'telegram' => $telegram,
            default => $whatsapp->unionAll($telegram),
        };

        $messages = DB::query()->fromSub($source, 'm')->orderByDesc('created_at')->orderByDesc('id')->paginate($this->perPage(15))->withQueryString();
        $customers = Customer::withTrashed()->whereIn('id', collect($messages->items())->pluck('customer_id')->filter()->unique())->get(['id', 'name', 'customer_code'])->keyBy('id');

        $messages->setCollection($messages->getCollection()->map(fn (object $row): array => [
            'key' => "{$row->channel}-{$row->id}",
            'channel' => $row->channel,
            'destination' => $row->destination,
            'type' => $row->type,
            'status' => $row->status,
            'body' => $row->is_sensitive || in_array($row->type, self::SENSITIVE_TYPES, true) ? TelegramMessage::REDACTED : $row->body,
            'hidden' => (bool) $row->is_sensitive || in_array($row->type, self::SENSITIVE_TYPES, true),
            'error' => $row->error,
            'attempts' => (int) $row->attempts,
            'created_at' => $row->created_at,
            'sent_at' => $row->sent_at,
            'customer' => ($customer = $customers->get($row->customer_id)) ? ['id' => $customer->id, 'name' => $customer->name, 'code' => $customer->customer_code] : null,
        ]));

        $since = now()->subDay();
        $count = fn (string $table, string $state, bool $recent) => DB::table($table)->where('status', $state)->when($recent, fn (Builder $query) => $query->where('created_at', '>=', $since))->count();

        return Inertia::render('Messages/Index', [
            'messages' => $messages,
            'filters' => ['kanal' => $channel, 'status' => $status, 'jenis' => $type, 'search' => $search],
            'types' => array_column(WhatsappMessageType::cases(), 'value'),
            'summary' => [
                'sent_24h' => $count('whatsapp_messages', 'sent', true) + $count('telegram_messages', 'sent', true),
                'failed_24h' => $count('whatsapp_messages', 'failed', true) + $count('telegram_messages', 'failed', true),
                'queued' => $count('whatsapp_messages', 'queued', false) + $count('telegram_messages', 'queued', false),
            ],
        ]);
    }

    private function filter(Builder $query, string $status, string $type, string $search): void
    {
        $query
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($type !== '', fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = "%{$search}%";
                    $query->whereIn('customer_id', Customer::withTrashed()->where('name', 'like', $like)->orWhere('customer_code', 'like', $like)->select('id'))
                        ->orWhere(fn (Builder $query) => $query->where('is_sensitive', false)->whereNotIn('type', self::SENSITIVE_TYPES)->where('body', 'like', $like));

                    // Nomor tujuan dicari sesuai kolomnya di tiap tabel.
                    $query->orWhere($query->from === 'whatsapp_messages' ? 'phone' : 'chat_id', 'like', $like);
                });
            });
    }
}
