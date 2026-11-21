<?php

namespace App\Http\Requests;

use App\Drivers\Payment\PaymentGatewayManager;
use App\Enums\FeeBearer;
use App\Models\PaymentGateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentGatewayRequest extends FormRequest
{
    /**
     * Teks yang ditampilkan di form sebagai pengganti kredensial tersimpan.
     */
    public const MASK = '••••••••';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_sandbox' => ['boolean'],
            'fee_bearer' => ['required', Rule::enum(FeeBearer::class)],
            'sort_order' => ['required', 'integer', 'between:0,255'],
            'enabled_channels' => ['nullable', 'array', 'max:50'],
            'enabled_channels.*' => ['string', 'max:50'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Gateway yang diaktifkan wajib memiliki seluruh kredensial drivernya.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('is_active') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $missing = array_filter(
                    $this->requiredKeys(),
                    fn (string $key): bool => blank($this->mergedCredentials()[$key] ?? null),
                );

                foreach ($missing as $key) {
                    $validator->errors()->add("credentials.{$key}", 'Wajib diisi sebelum gateway diaktifkan.');
                }
            },
        ];
    }

    /**
     * Kunci kredensial yang dibutuhkan driver gateway ini.
     *
     * @return list<string>
     */
    public function requiredKeys(): array
    {
        return PaymentGatewayManager::driverClass($this->gateway()->driver)::requiredCredentials();
    }

    /**
     * Kredensial baru digabung dengan yang tersimpan; isian kosong atau topeng berarti tidak diubah.
     *
     * @return array<string, string>
     */
    public function mergedCredentials(): array
    {
        $stored = $this->gateway()->credentials ?? [];
        $merged = [];

        foreach ($this->requiredKeys() as $key) {
            $incoming = $this->input("credentials.{$key}");

            $merged[$key] = filled($incoming) && $incoming !== self::MASK ? $incoming : ($stored[$key] ?? null);
        }

        return array_filter($merged, fn (?string $value): bool => filled($value));
    }

    private function gateway(): PaymentGateway
    {
        return $this->route('paymentGateway');
    }
}
