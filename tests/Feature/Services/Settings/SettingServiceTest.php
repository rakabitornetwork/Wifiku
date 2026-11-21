<?php

namespace Tests\Feature\Services\Settings;

use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypted_value_is_stored_encrypted_and_read_back_decrypted(): void
    {
        $service = new SettingService;

        $service->set('whatsapp', 'api_key', 'token-rahasia', encrypted: true);

        $this->assertDatabaseMissing('settings', ['key' => 'api_key', 'value' => 'token-rahasia']);
        $this->assertSame('token-rahasia', $service->get('whatsapp', 'api_key'));
    }

    public function test_returns_default_for_missing_setting(): void
    {
        $this->assertSame('fallback', (new SettingService)->get('company', 'unknown', 'fallback'));
    }
}
