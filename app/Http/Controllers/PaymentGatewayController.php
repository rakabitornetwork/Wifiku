<?php

namespace App\Http\Controllers;

use App\Drivers\Payment\Exceptions\PaymentGatewayException;
use App\Drivers\Payment\PaymentGatewayManager;
use App\Enums\FeeBearer;
use App\Http\Requests\PaymentGatewayRequest;
use App\Models\PaymentGateway;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/PaymentGateways', [
            'gateways' => PaymentGateway::query()->orderBy('sort_order')->get()->map(fn (PaymentGateway $gateway): array => [
                'id' => $gateway->id,
                'driver' => $gateway->driver->value,
                'name' => $gateway->name,
                'is_active' => $gateway->is_active,
                'is_sandbox' => $gateway->is_sandbox,
                'enabled_channels' => $gateway->enabled_channels ?? [],
                'fee_bearer' => $gateway->fee_bearer->value,
                'sort_order' => $gateway->sort_order,
                // Nilai kredensial tidak pernah dikirim ke browser; hanya status terisi atau belum.
                'credentials' => collect(PaymentGatewayManager::driverClass($gateway->driver)::requiredCredentials())
                    ->map(fn (string $key): array => [
                        'key' => $key,
                        'label' => Str::headline($key),
                        'is_set' => filled($gateway->credentials[$key] ?? null),
                    ])->all(),
            ])->all(),
            'mask' => PaymentGatewayRequest::MASK,
            'callbackUrls' => collect(PaymentGateway::query()->pluck('driver'))
                ->mapWithKeys(fn ($driver): array => [$driver->value => route('webhooks.payment', $driver->value)])
                ->all(),
            'feeBearers' => array_map(fn (FeeBearer $bearer): string => $bearer->value, FeeBearer::cases()),
        ]);
    }

    public function update(PaymentGatewayRequest $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $previous = $paymentGateway->credentials ?? [];
        $merged = $request->mergedCredentials();

        $paymentGateway->update([
            ...$request->safe()->only(['name', 'is_active', 'is_sandbox', 'fee_bearer', 'sort_order']),
            'enabled_channels' => array_values(array_unique($request->input('enabled_channels', []))),
            'credentials' => $merged ?: null,
        ]);

        // Hanya nama kunci yang dicatat; nilai kredensial tidak pernah masuk Audit Log.
        $changedKeys = array_keys(array_filter($merged, fn (string $value, string $key): bool => ($previous[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changedKeys !== []) {
            activity('Payment Gateway')
                ->performedOn($paymentGateway)
                ->causedBy($request->user())
                ->withProperties(['keys' => $changedKeys])
                ->log("Kredensial {$paymentGateway->name} diubah (".implode(', ', $changedKeys).')');
        }

        Toast::success("Pengaturan {$paymentGateway->name} disimpan.");

        return back();
    }

    /**
     * Daftar channel langsung dari API gateway, untuk membantu Owner mengisi channel aktif.
     */
    public function channels(PaymentGateway $paymentGateway, PaymentGatewayManager $gateways): JsonResponse
    {
        try {
            return response()->json(['channels' => $gateways->driver($paymentGateway)->availableChannels()]);
        } catch (PaymentGatewayException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
