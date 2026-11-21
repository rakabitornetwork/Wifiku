<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

/**
 * Implementasi RouterOsClient di atas evilfreelancer/routeros-api-php dengan timeout singkat.
 */
class RouterOsApiClient implements RouterOsClient
{
    private const CONNECT_TIMEOUT = 5;

    private const READ_TIMEOUT = 10;

    private ?Client $client = null;

    public function __construct(private readonly Router $router) {}

    public function command(string $path, array $attributes = [], array $where = []): array
    {
        $query = new Query($path);

        foreach ($where as $key => $value) {
            $query->where($key, (string) $value);
        }

        foreach ($attributes as $key => $value) {
            $query->equal($key, (string) $value);
        }

        try {
            $client = $this->client();
            $raw = $client->query($query)->read(false);
        } catch (MikrotikConnectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MikrotikConnectionException("Gagal mengirim perintah {$path} ke {$this->router->name}: {$exception->getMessage()}", previous: $exception);
        }

        $parsed = $client->parseResponse(is_array($raw) ? $raw : []);

        if (in_array('!trap', (array) $raw, true) || in_array('!fatal', (array) $raw, true)) {
            $message = $parsed['after']['message'] ?? 'perintah ditolak router';

            throw new MikrotikCommandException("{$this->router->name} menolak {$path}: {$message}");
        }

        unset($parsed['after']);

        return array_values($parsed);
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        try {
            return $this->client = new Client([
                'host' => $this->router->host,
                'user' => $this->router->username,
                'pass' => $this->router->password,
                'port' => $this->router->api_port,
                'ssl' => $this->router->use_ssl,
                'timeout' => self::CONNECT_TIMEOUT,
                'socket_timeout' => self::READ_TIMEOUT,
                'attempts' => 1,
                'delay' => 0,
            ]);
        } catch (Throwable $exception) {
            throw new MikrotikConnectionException(
                "Tidak dapat terhubung ke {$this->router->name} ({$this->router->host}:{$this->router->api_port}): {$exception->getMessage()}",
                previous: $exception,
            );
        }
    }
}
