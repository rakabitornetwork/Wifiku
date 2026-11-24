<?php

namespace Tests\Feature\Auth;

use App\Enums\WhatsappMessageType;
use App\Models\AdminPhoneVerification;
use App\Models\User;
use App\Models\VpnMember;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ProfilePhoneVerificationTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $user = $this->admin();
        $user->forceFill(['password' => 'password-lama', 'phone' => '6281234567890', ...$attributes])->save();

        return $user;
    }

    private function requestCode(User $user, string $phone = '0899-1234-5678', string $password = 'password-lama')
    {
        return $this->actingAs($user)->put('/profil/whatsapp', ['phone' => $phone, 'current_password' => $password]);
    }

    private function lastCode(): string
    {
        $message = WhatsappMessage::where('type', WhatsappMessageType::AccountSecurity)->latest('id')->firstOrFail();
        preg_match('/\*(\d{6})\*/', $message->body, $matches);

        return $matches[1];
    }

    public function test_requesting_a_new_number_sends_a_code_to_that_number_and_keeps_the_old_one(): void
    {
        $user = $this->user();

        $this->requestCode($user)->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('6281234567890', $user->fresh()->phone);
        $message = WhatsappMessage::sole();
        $this->assertSame('6289912345678', $message->phone);
        $this->assertSame(WhatsappMessageType::AccountSecurity, $message->type);
        $this->assertMatchesRegularExpression('/\*\d{6}\*/', $message->body);

        $pending = AdminPhoneVerification::sole();
        $this->assertSame('6289912345678', $pending->phone);
        $this->assertNotSame($this->lastCode(), $pending->code_hash);
        $this->assertTrue($pending->expires_at->isFuture());
    }

    public function test_requesting_needs_the_current_password_and_a_valid_number(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);

        $this->put('/profil/whatsapp', ['phone' => '0899-1234-5678', 'current_password' => 'salah'])->assertSessionHasErrors('current_password');
        $this->put('/profil/whatsapp', ['phone' => '0899-1234-5678'])->assertSessionHasErrors('current_password');
        $this->put('/profil/whatsapp', ['phone' => '12345', 'current_password' => 'password-lama'])->assertSessionHasErrors('phone');

        $this->assertSame(0, AdminPhoneVerification::count());
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_correct_code_saves_the_number_and_notifies_both_numbers(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        WhatsappMessage::query()->where('phone', '6289912345678')->update(['status' => 'sent']);

        $this->post('/profil/whatsapp/verifikasi', ['code' => $this->lastCode()])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('6289912345678', $user->fresh()->phone);
        $this->assertSame(0, AdminPhoneVerification::count());
        $toOld = WhatsappMessage::where('phone', '6281234567890')->sole();
        $this->assertStringContainsString('62•••••••5678', $toOld->body);
        $this->assertStringNotContainsString('6289912345678', $toOld->body);
        $this->assertStringContainsString($user->email, WhatsappMessage::where('phone', '6289912345678')->latest('id')->first()->body);

        $activity = Activity::where('log_name', 'Pengguna')->where('subject_id', $user->id)->latest('id')->first();
        $this->assertSame('6289912345678', $activity->attribute_changes['attributes']['phone']);
        $this->assertSame('6281234567890', $activity->attribute_changes['old']['phone']);
    }

    public function test_first_number_only_notifies_the_new_one(): void
    {
        $user = $this->user(['phone' => null]);
        $this->requestCode($user);
        $code = $this->lastCode();
        WhatsappMessage::query()->delete();

        $this->post('/profil/whatsapp/verifikasi', ['code' => $code])->assertSessionDoesntHaveErrors();

        $this->assertSame('6289912345678', $user->fresh()->phone);
        $this->assertSame(['6289912345678'], WhatsappMessage::pluck('phone')->all());
    }

    public function test_wrong_codes_are_counted_and_locked_after_five_tries(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        $correct = $this->lastCode();
        $wrong = $correct === '000000' ? '111111' : '000000';
        $this->withoutMiddleware(ThrottleRequests::class);

        $first = $this->post('/profil/whatsapp/verifikasi', ['code' => $wrong]);
        $first->assertSessionHasErrors('code');
        $this->assertStringContainsString('Sisa percobaan: 4', session('errors')->first('code'));

        foreach (range(1, 4) as $attempt) {
            $this->post('/profil/whatsapp/verifikasi', ['code' => $wrong]);
        }

        $this->assertSame(0, AdminPhoneVerification::count());
        $this->post('/profil/whatsapp/verifikasi', ['code' => $correct])->assertSessionHasErrors('code');
        $this->assertSame('6281234567890', $user->fresh()->phone);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->post('/profil/whatsapp/verifikasi', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame('6281234567890', $user->fresh()->phone);
    }

    public function test_code_format_and_missing_request_are_validated(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);

        $this->post('/profil/whatsapp/verifikasi', ['code' => '12ab'])->assertSessionHasErrors('code');
        $this->post('/profil/whatsapp/verifikasi', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertSame('6281234567890', $user->fresh()->phone);
    }

    public function test_resend_has_a_cooldown_then_replaces_the_code(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        $firstCode = $this->lastCode();

        $this->post('/profil/whatsapp/kirim-ulang')->assertRedirect();
        $this->assertSame(1, WhatsappMessage::count());

        $this->travel(61)->seconds();
        $this->post('/profil/whatsapp/kirim-ulang')->assertRedirect();
        $this->assertSame(2, WhatsappMessage::count());
        $this->assertSame('6289912345678', WhatsappMessage::latest('id')->first()->phone);

        $secondCode = $this->lastCode();
        if ($secondCode !== $firstCode) {
            $this->post('/profil/whatsapp/verifikasi', ['code' => $firstCode])->assertSessionHasErrors('code');
        }
        $this->post('/profil/whatsapp/verifikasi', ['code' => $secondCode])->assertSessionDoesntHaveErrors();
        $this->assertSame('6289912345678', $user->fresh()->phone);
    }

    public function test_cooldown_applies_to_any_new_number_so_the_form_cannot_flood_other_numbers(): void
    {
        $user = $this->user();
        $this->requestCode($user);

        $this->put('/profil/whatsapp', ['phone' => '0812-0000-1111', 'current_password' => 'password-lama'])->assertRedirect();

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame('6289912345678', AdminPhoneVerification::sole()->phone);
    }

    public function test_resend_without_a_pending_request_does_nothing(): void
    {
        $this->actingAs($this->user())->post('/profil/whatsapp/kirim-ulang')->assertRedirect();

        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_cancel_removes_the_pending_request(): void
    {
        $user = $this->user();
        $this->requestCode($user);

        $this->delete('/profil/whatsapp/verifikasi')->assertRedirect();

        $this->assertSame(0, AdminPhoneVerification::count());
    }

    public function test_one_users_code_cannot_verify_another_users_request(): void
    {
        $alice = $this->user();
        $this->requestCode($alice);
        $code = $this->lastCode();
        $bob = User::factory()->create(['phone' => '6281111111111']);

        $this->actingAs($bob)->post('/profil/whatsapp/verifikasi', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertSame('6281111111111', $bob->fresh()->phone);
        $this->assertSame(1, AdminPhoneVerification::count());
    }

    public function test_removing_the_number_needs_no_code_and_notifies_the_old_one(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        WhatsappMessage::query()->delete();

        $this->put('/profil/whatsapp', ['phone' => '', 'current_password' => 'password-lama'])->assertSessionDoesntHaveErrors();

        $this->assertNull($user->fresh()->phone);
        $this->assertSame(0, AdminPhoneVerification::count());
        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertStringContainsString('dihapus', $message->body);
    }

    public function test_submitting_the_current_number_changes_nothing(): void
    {
        $user = $this->user();

        $this->requestCode($user, '081234567890')->assertSessionDoesntHaveErrors();

        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame(0, AdminPhoneVerification::count());
    }

    public function test_profile_page_exposes_the_pending_request_but_never_the_code(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        $code = $this->lastCode();

        $response = $this->get('/profil');
        $response->assertInertia(fn (Assert $page) => $page->where('pendingPhone.phone', '6289912345678')->where('pendingPhone.attempts_left', 5)->where('pendingPhone.resend_in', fn ($seconds) => $seconds > 0 && $seconds <= 60));
        $this->assertStringNotContainsString('"'.$code.'"', $response->getContent());
    }

    public function test_next_reset_link_goes_to_the_verified_number(): void
    {
        $user = $this->user();
        $this->requestCode($user);
        $this->post('/profil/whatsapp/verifikasi', ['code' => $this->lastCode()]);
        $this->post('/logout');
        WhatsappMessage::query()->delete();

        $this->post('/lupa-password', ['email' => $user->email]);

        $this->assertSame('6289912345678', WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->sole()->phone);
    }

    public function test_requests_and_verification_attempts_are_rate_limited(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'tebakan'])->assertSessionHasErrors('current_password');
        }
        $this->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'password-lama'])->assertStatus(429);

        foreach (range(1, 10) as $attempt) {
            $this->post('/profil/whatsapp/verifikasi', ['code' => '123456']);
        }
        $this->post('/profil/whatsapp/verifikasi', ['code' => '123456'])->assertStatus(429);
    }

    public function test_a_vpn_member_session_cannot_use_any_of_the_endpoints(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member');

        $this->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'x'])->assertStatus(403);
        $this->post('/profil/whatsapp/verifikasi', ['code' => '123456'])->assertStatus(403);
        $this->post('/profil/whatsapp/kirim-ulang')->assertStatus(403);
        $this->delete('/profil/whatsapp/verifikasi')->assertStatus(403);
    }
}
