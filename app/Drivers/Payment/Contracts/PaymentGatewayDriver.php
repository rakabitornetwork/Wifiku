<?php

namespace App\Drivers\Payment\Contracts;

use App\Drivers\Payment\Data\CallbackResult;
use App\Drivers\Payment\Data\TransactionRequest;
use App\Drivers\Payment\Data\TransactionResult;
use Illuminate\Http\Request;

interface PaymentGatewayDriver
{
    /**
     * Kunci kredensial yang wajib diisi di Panel Admin untuk driver ini.
     *
     * @return list<string>
     */
    public static function requiredCredentials(): array;

    /**
     * Membuat transaksi QRIS / Virtual Account di gateway.
     */
    public function createTransaction(TransactionRequest $request): TransactionResult;

    /**
     * Memverifikasi signature callback yang masuk dari gateway.
     */
    public function verifyCallback(Request $request): bool;

    /**
     * Menerjemahkan payload callback menjadi hasil yang seragam antar-driver.
     */
    public function parseCallback(Request $request): CallbackResult;

    /**
     * Daftar channel pembayaran yang tersedia di gateway.
     *
     * @return list<array{code: string, name: string}>
     */
    public function availableChannels(): array;
}
