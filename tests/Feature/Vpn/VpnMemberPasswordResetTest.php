<?php

namespace Tests\Feature\Vpn;

use App\Enums\WhatsappMessageType;
use App\Models\User;
use App\Models\VpnMember;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VpnMemberPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): VpnMember
    {
        return VpnMember::factory()->create(['email' => 'budi@example.com', 'password' => 'password-lama', ...$attributes]);
    }

    /**
     * Ambil tautan reset dari pesan WhatsApp terakhir.
     *
     * @return array{token: string, email: string}
     */
    private function linkFromMessage(): array
    {
        $message = WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->latest('id')->firstOrFail();
        preg_match('#/vpn-tunnel/reset-password/([A-Za-z0-9]+)\?email=([^\s]+)#', $message->body, $matches);

        return ['token' => $matches[1], 'email' => urldecode($matches[2])];
    }

    public function test_forgot_password_page_and_login_link_are_public(): void
    {
        $this->get('/vpn-tunnel/lupa-password')->assertOk();
        $this->get('/vpn-tunnel/masuk')->assertOk();
    }

    public function test_reset_link_is_sent_to_the_members_whatsapp(): void
    {
        $member = $this->member(['whatsapp' => '6281234567890']);

        $this->post('/vpn-tunnel/lupa-password', ['email' => 'BUDI@example.com'])->assertRedirect('/vpn-tunnel/masuk');

        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertSame(WhatsappMessageType::PasswordReset, $message->type);
        $this->assertStringContainsString('/vpn-tunnel/reset-password/', $message->body);
        $this->assertSame($member->email, $this->linkFromMessage()['email']);
    }

    public function test_response_is_identical_for_unknown_and_inactive_emails_and_nothing_is_sent(): void
    {
        $this->member(['email' => 'nonaktif@example.com', 'is_active' => false]);

        $known = $this->post('/vpn-tunnel/lupa-password', ['email' => 'tidak-ada@example.com']);
        $inactive = $this->post('/vpn-tunnel/lupa-password', ['email' => 'nonaktif@example.com']);

        $known->assertRedirect('/vpn-tunnel/masuk')->assertSessionDoesntHaveErrors();
        $inactive->assertRedirect('/vpn-tunnel/masuk')->assertSessionDoesntHaveErrors();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_repeated_requests_within_the_throttle_window_send_only_one_message(): void
    {
        $this->member();

        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);

        $this->assertSame(1, WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->count());
    }

    public function test_member_can_reset_the_password_and_log_in_with_the_new_one(): void
    {
        $this->member();
        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $link = $this->linkFromMessage();

        $this->get("/vpn-tunnel/reset-password/{$link['token']}?email=".urlencode($link['email']))->assertOk();
        $this->post('/vpn-tunnel/reset-password', [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])
            ->assertRedirect('/vpn-tunnel/masuk')
            ->assertSessionDoesntHaveErrors();

        $this->post('/vpn-tunnel/masuk', ['email' => 'budi@example.com', 'password' => 'password-lama'])->assertSessionHasErrors('email');
        $this->post('/vpn-tunnel/masuk', ['email' => 'budi@example.com', 'password' => 'password-baru-1'])->assertRedirect('/vpn-tunnel/akun');
        $this->assertStringContainsString('baru saja diubah', WhatsappMessage::latest('id')->first()->body);
    }

    public function test_token_cannot_be_reused(): void
    {
        $this->member();
        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $link = $this->linkFromMessage();
        $payload = [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'];

        $this->post('/vpn-tunnel/reset-password', $payload)->assertSessionDoesntHaveErrors();
        $this->post('/vpn-tunnel/reset-password', [...$payload, 'password' => 'diretas-12345', 'password_confirmation' => 'diretas-12345'])->assertSessionHasErrors('email');
    }

    public function test_invalid_token_wrong_email_and_weak_password_are_rejected(): void
    {
        $this->member();
        $this->member(['email' => 'lain@example.com']);
        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $link = $this->linkFromMessage();

        $this->post('/vpn-tunnel/reset-password', ['token' => 'ngawur', 'email' => 'budi@example.com', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('email');
        $this->post('/vpn-tunnel/reset-password', [...$link, 'email' => 'lain@example.com', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('email');
        $this->post('/vpn-tunnel/reset-password', [...$link, 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->post('/vpn-tunnel/reset-password', [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'beda-sekali'])->assertSessionHasErrors('password');
        $this->assertTrue(password_verify('password-lama', VpnMember::where('email', 'budi@example.com')->value('password')));
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->member();
        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $link = $this->linkFromMessage();

        $this->travel(61)->minutes();

        $this->post('/vpn-tunnel/reset-password', [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('email');
    }

    public function test_member_reset_does_not_touch_admin_users_with_the_same_email(): void
    {
        $admin = User::factory()->create(['email' => 'budi@example.com']);
        $this->member();

        $this->post('/vpn-tunnel/lupa-password', ['email' => 'budi@example.com']);
        $this->post('/vpn-tunnel/reset-password', [...$this->linkFromMessage(), 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1']);

        $this->assertSame($admin->password, $admin->fresh()->password);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }
}
