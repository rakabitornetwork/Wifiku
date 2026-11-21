<?php

namespace App\Services\Whatsapp;

use App\Services\Settings\SettingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP untuk Evolution API (sidecar Baileys). Semua panggilan memakai timeout singkat.
 */
class EvolutionApiClient
{
    public function __construct(private readonly SettingService $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->get('whatsapp', 'enabled') !== '0'
            && filled($this->settings->get('whatsapp', 'base_url'))
            && filled($this->settings->get('whatsapp', 'instance'))
            && filled($this->settings->get('whatsapp', 'api_key'));
    }

    /**
     * @throws WhatsappDeliveryException
     */
    public function sendText(string $phone, string $text): void
    {
        $this->call(fn (PendingRequest $request) => $request->post("/message/sendText/{$this->instance()}", ['number' => $phone, 'text' => $text]));
    }

    /**
     * @return array{state: string, connected: bool}
     *
     * @throws WhatsappDeliveryException
     */
    public function connectionState(): array
    {
        $state = (string) ($this->call(fn (PendingRequest $request) => $request->get("/instance/connectionState/{$this->instance()}"))->json('instance.state') ?? 'unknown');

        return ['state' => $state, 'connected' => $state === 'open'];
    }

    /**
     * Minta QR (atau pairing code) baru. Bila sudah terhubung, tidak ada QR yang dikembalikan.
     *
     * @return array{state: string, connected: bool, qr: string|null, pairing_code: string|null}
     *
     * @throws WhatsappDeliveryException
     */
    public function connect(): array
    {
        $state = $this->connectionState();

        if ($state['connected']) {
            return [...$state, 'qr' => null, 'pairing_code' => null];
        }

        $response = $this->call(fn (PendingRequest $request) => $request->get("/instance/connect/{$this->instance()}"));
        $qr = $response->json('base64');

        return [...$state, 'qr' => is_string($qr) && $qr !== '' ? $qr : null, 'pairing_code' => $response->json('pairingCode')];
    }

    private function instance(): string
    {
        return rawurlencode((string) $this->settings->get('whatsapp', 'instance'));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws WhatsappDeliveryException
     */
    private function call(callable $request): Response
    {
        if (! $this->isConfigured()) {
            throw new WhatsappDeliveryException('WA Gateway belum dikonfigurasi.');
        }

        $pending = Http::baseUrl((string) $this->settings->get('whatsapp', 'base_url'))
            ->acceptJson()
            ->withHeaders(['apikey' => (string) $this->settings->get('whatsapp', 'api_key')])
            ->connectTimeout(5)
            ->timeout(10);

        try {
            return $request($pending)->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new WhatsappDeliveryException("Evolution API: {$exception->getMessage()}", previous: $exception);
        }
    }
}
