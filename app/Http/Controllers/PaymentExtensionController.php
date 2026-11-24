<?php

namespace App\Http\Controllers;

use App\Actions\Customer\GrantPaymentExtension;
use App\Http\Requests\PaymentExtensionRequest;
use App\Models\Customer;
use App\Models\PaymentExtension;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PaymentExtensionController extends Controller
{
    /**
     * Data untuk dialog perpanjangan: tunggakan, batas hari milik pengguna, dan riwayat.
     */
    public function index(Customer $customer, GrantPaymentExtension $grant): JsonResponse
    {
        $maxDays = $grant->maxDaysFor(request()->user());

        return response()->json([
            'current_until' => $customer->promise_to_pay_until?->toDateString(),
            'status' => $customer->status->value,
            'max_days' => $maxDays,
            'max_date' => CarbonImmutable::today()->addDays($maxDays)->toDateString(),
            'outstanding' => $grant->outstanding($customer),
            'history' => $customer->paymentExtensions()
                ->with('grantor:id,name')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (PaymentExtension $extension): array => [
                    'id' => $extension->id,
                    'granted_until' => $extension->granted_until->toDateString(),
                    'previous_until' => $extension->previous_until?->toDateString(),
                    'days' => $extension->days,
                    'reason' => $extension->reason,
                    'granted_by' => $extension->grantor?->name,
                    'created_at' => $extension->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function store(PaymentExtensionRequest $request, Customer $customer, GrantPaymentExtension $grant): RedirectResponse
    {
        $grant->handle($customer, CarbonImmutable::parse($request->validated('until')), $request->validated('reason'), $request->user());

        Toast::success("Perpanjangan dicatat; {$customer->name} tidak akan diisolir sampai tanggal tersebut.");

        return back();
    }
}
