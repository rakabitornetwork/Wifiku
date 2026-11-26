<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikCommandException;
use App\Services\Mikrotik\MikrotikConnectionException;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Live trafik interface router untuk halaman Manajemen Router.
 *
 * Aliran (SSE) membuka satu koneksi API lalu mengirim cuplikan tiap beberapa detik lewat koneksi yang sama,
 * jauh lebih ringan bagi router daripada login ulang tiap cuplikan. Cuplikan tunggal (sample) tetap tersedia sebagai
 * cadangan bila proxy di depan aplikasi menahan aliran. Semua komunikasi ke router bertimeout singkat.
 */
class RouterTrafficController extends Controller
{
    private const STREAMS_KEY = 'router-traffic:open-streams';

    public function interfaces(Router $router): JsonResponse
    {
        try {
            return response()->json(['interfaces' => MikrotikService::forRouter($router)->interfaces()]);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return $this->failed($exception);
        }
    }

    public function sample(Request $request, Router $router): JsonResponse
    {
        $interface = $this->validatedInterface($request);

        try {
            $sample = MikrotikService::forRouter($router)->trafficSample($interface);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            return $this->failed($exception);
        }

        return response()->json($this->payload($sample, $interface));
    }

    /**
     * Aliran cuplikan selama stream_seconds, lalu ditutup agar browser menyambung ulang. Cuplikan pertama diambil sebelum
     * aliran dimulai sehingga kegagalan koneksi tetap menjadi jawaban 422 biasa, bukan aliran kosong.
     */
    public function stream(Request $request, Router $router): JsonResponse|StreamedResponse
    {
        $interface = $this->validatedInterface($request);

        if (! $this->acquireStream()) {
            return response()->json(['error' => 'Terlalu banyak pemantauan yang sedang berjalan. Tutup jendela live trafik lain lalu coba lagi.'], 429);
        }

        try {
            $service = MikrotikService::forRouter($router);
            $sample = $service->trafficSample($interface);
        } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
            $this->releaseStream();

            return $this->failed($exception);
        }

        $seconds = (int) config('traffic.stream_seconds');
        $interval = max(0, (int) config('traffic.interval_ms')) * 1000;
        $maxSamples = config('traffic.max_samples');

        return response()->stream(function () use ($service, $interface, $sample, $seconds, $interval, $maxSamples): void {
            // Selesaikan pembersihan walau browser memutus sambungan; pemutusan dikenali dari kegagalan menulis.
            ignore_user_abort(true);
            set_time_limit($seconds + 30);

            $deadline = microtime(true) + $seconds;
            $sent = 0;

            try {
                while (true) {
                    $this->emit('sample', $this->payload($sample, $interface));
                    $sent++;

                    if (($maxSamples !== null && $sent >= $maxSamples) || microtime(true) >= $deadline || connection_aborted()) {
                        break;
                    }

                    usleep($interval);

                    try {
                        $sample = $service->trafficSample($interface);
                    } catch (MikrotikConnectionException|MikrotikCommandException $exception) {
                        $this->emit('failure', ['error' => $exception->getMessage()]);

                        return;
                    }
                }

                $this->emit('end', []);
            } finally {
                $this->releaseStream();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function validatedInterface(Request $request): string
    {
        return $request->validate([
            // Nama interface dikirim apa adanya ke RouterOS; karakter kontrol atau pemisah atribut tidak diizinkan.
            'interface' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-@:\/ ]+$/'],
        ])['interface'];
    }

    /**
     * @param  array{rx_bps: int, tx_bps: int, rx_pps: int, tx_pps: int}  $sample
     * @return array<string, mixed>
     */
    private function payload(array $sample, string $interface): array
    {
        return [...$sample, 'interface' => $interface, 'at' => now()->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\ndata: ".json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * Batasi aliran serentak (tiap aliran menahan satu proses PHP). Hitungan berakhir sendiri bila proses mati mendadak.
     */
    private function acquireStream(): bool
    {
        Cache::add(self::STREAMS_KEY, 0, now()->addSeconds((int) config('traffic.stream_seconds') + 60));

        if (Cache::increment(self::STREAMS_KEY) > (int) config('traffic.max_streams')) {
            $this->releaseStream();

            return false;
        }

        return true;
    }

    private function releaseStream(): void
    {
        if ((int) Cache::get(self::STREAMS_KEY, 0) > 0) {
            Cache::decrement(self::STREAMS_KEY);
        }
    }

    private function failed(MikrotikConnectionException|MikrotikCommandException $exception): JsonResponse
    {
        return response()->json(['error' => $exception->getMessage()], 422);
    }
}
