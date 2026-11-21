<?php

namespace App\Drivers\Payment;

use App\Drivers\Payment\Contracts\PaymentGatewayDriver;
use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Models\PaymentGateway;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractPaymentDriver implements PaymentGatewayDriver
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Base URL API sesuai mode sandbox / production.
     */
    abstract protected function baseUrl(): string;

    /**
     * Ambil satu kredensial terdekripsi; gagal jika belum diisi di Panel Admin.
     */
    protected function credential(string $key): string
    {
        $value = $this->gateway->credentials[$key] ?? null;

        if (blank($value)) {
            throw new PaymentGatewayException("Kredensial [{$key}] untuk gateway {$this->gateway->driver->value} belum diatur.");
        }

        return $value;
    }

    /**
     * HTTP client dengan timeout singkat agar UI dan worker tidak menggantung.
     */
    protected function http(?string $baseUrl = null): PendingRequest
    {
        return Http::baseUrl($baseUrl ?? $this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(10);
    }

    /**
     * Jalankan request ke gateway; galat jaringan dan respons non-2xx menjadi PaymentGatewayException.
     *
     * @param  Closure(): Response  $request
     */
    protected function send(Closure $request, ?Closure $errorMessage = null): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException("Tidak dapat menghubungi {$this->gateway->name}: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            $detail = $errorMessage ? $errorMessage($response) : null;

            throw new PaymentGatewayException("{$this->gateway->name} menolak permintaan (HTTP {$response->status()})".($detail ? ": {$detail}" : '.'));
        }

        return $response;
    }
}
