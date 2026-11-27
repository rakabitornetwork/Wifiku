<?php

namespace Tests\Unit;

use App\Support\PaymentChannelCatalog;
use PHPUnit\Framework\TestCase;

class PaymentChannelCatalogTest extends TestCase
{
    public function test_known_codes_get_a_friendly_label_and_group(): void
    {
        $this->assertSame(['code' => 'BRIVA', 'label' => 'BRI', 'group' => 'Transfer Bank (Virtual Account)'], PaymentChannelCatalog::describe('BRIVA'));
        $this->assertSame('Indomaret', PaymentChannelCatalog::describe('INDOMARET')['label']);
        $this->assertSame('Dompet Digital', PaymentChannelCatalog::describe('ovo')['group']);
    }

    public function test_unknown_codes_fall_back_to_a_tidy_name_in_the_other_group(): void
    {
        $this->assertSame(['code' => 'new_bank_va', 'label' => 'New Bank Va', 'group' => 'Lainnya'], PaymentChannelCatalog::describe('new_bank_va'));
    }

    public function test_history_label_marks_bank_transfers(): void
    {
        $this->assertSame('BCA (Transfer Bank)', PaymentChannelCatalog::label('BCAVA'));
        $this->assertSame('QRIS (semua e-wallet & m-banking)', PaymentChannelCatalog::label('QRIS'));
    }

    public function test_options_handles_empty_input(): void
    {
        $this->assertSame([], PaymentChannelCatalog::options(null));
    }
}
