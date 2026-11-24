<?php

namespace Tests\Feature\Auth;

use App\Enums\WhatsappMessageType;
use App\Models\User;
use App\Models\VpnMember;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $user = $this->admin();
        $user->forceFill(['password' => 'password-lama', 'phone' => '6281234567890', ...$attributes])->save();

        return $user;
    }

    public function test_page_requires_login_and_is_open_to_every_role(): void
    {
        $this->get('/profil')->assertRedirect('/login');
        $this->put('/profil/password', [])->assertRedirect('/login');

        foreach (['Owner', 'Admin', 'Teknisi'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/profil')->assertInertia(
                fn (Assert $page) => $page->component('Profile/Edit')->where('account.role', $role)->missing('account.password'),
            );
        }
    }

    public function test_changes_the_password_and_keeps_only_the_current_session(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->user();
        $oldRemember = $user->remember_token;
        DB::table('sessions')->insert([
            ['id' => 'sesi-hp', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'sesi-laptop', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->put('/profil/password', ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(0, DB::table('sessions')->whereIn('id', ['sesi-hp', 'sesi-laptop'])->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(password_verify('password-baru-1', $user->fresh()->password));
        $this->assertSame('Mengganti password sendiri', Activity::where('log_name', 'Pengguna')->latest('id')->first()->description);
    }

    public function test_new_password_works_for_login_and_old_one_does_not(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put('/profil/password', ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1']);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password-lama'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password-baru-1'])->assertRedirect('/dashboard');
    }

    public function test_rejects_wrong_current_password_weak_mismatched_and_unchanged_passwords(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);
        $good = ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'];

        $this->put('/profil/password', [...$good, 'current_password' => 'salah-sekali'])->assertSessionHasErrors('current_password');
        $this->put('/profil/password', [...$good, 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->put('/profil/password', [...$good, 'password_confirmation' => 'beda-sekali-1'])->assertSessionHasErrors('password');
        $this->put('/profil/password', [...$good, 'password' => 'password-lama', 'password_confirmation' => 'password-lama'])->assertSessionHasErrors('password');

        $this->assertTrue(password_verify('password-lama', $user->fresh()->password));
        $this->assertSame(0, Activity::where('description', 'Mengganti password sendiri')->count());
    }

    public function test_user_is_notified_on_whatsapp_when_a_phone_is_set(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profil/password', ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1']);

        $message = WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertStringContainsString('perangkat lain', $message->body);
    }

    public function test_no_notification_without_a_phone(): void
    {
        $user = $this->user(['phone' => null]);

        $this->actingAs($user)->put('/profil/password', ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionDoesntHaveErrors();

        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_attempts_are_rate_limited_against_guessing_the_current_password(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->put('/profil/password', ['current_password' => 'tebakan', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('current_password');
        }

        $this->put('/profil/password', ['current_password' => 'password-lama', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertStatus(429);
    }

    public function test_a_vpn_member_session_cannot_use_the_admin_profile(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member')->get('/profil')->assertStatus(403)->assertDontSee('Ganti password');
    }

    public function test_phone_can_be_changed_with_the_current_password_and_is_normalised(): void
    {
        $user = $this->user(['phone' => '6281234567890']);

        $this->actingAs($user)->put('/profil/whatsapp', ['phone' => '0899-1234-5678', 'current_password' => 'password-lama'])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('6289912345678', $user->fresh()->phone);
    }

    public function test_changing_the_phone_requires_the_right_password_and_a_valid_number(): void
    {
        $user = $this->user(['phone' => '6281234567890']);
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);

        $this->put('/profil/whatsapp', ['phone' => '0899-1234-5678', 'current_password' => 'salah'])->assertSessionHasErrors('current_password');
        $this->put('/profil/whatsapp', ['phone' => '12345', 'current_password' => 'password-lama'])->assertSessionHasErrors('phone');
        $this->put('/profil/whatsapp', ['phone' => '0899-1234-5678'])->assertSessionHasErrors('current_password');

        $this->assertSame('6281234567890', $user->fresh()->phone);
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_old_and_new_numbers_are_both_notified_without_revealing_the_full_new_number_to_the_old_one(): void
    {
        $user = $this->user(['phone' => '6281234567890']);

        $this->actingAs($user)->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'password-lama']);

        $toOld = WhatsappMessage::where('phone', '6281234567890')->sole();
        $toNew = WhatsappMessage::where('phone', '6289912345678')->sole();
        $this->assertSame(WhatsappMessageType::AccountSecurity, $toOld->type);
        $this->assertStringContainsString('62•••••••5678', $toOld->body);
        $this->assertStringNotContainsString('6289912345678', $toOld->body);
        $this->assertStringContainsString($user->email, $toNew->body);
    }

    public function test_setting_a_first_number_notifies_only_the_new_one_and_clearing_notifies_the_old_one(): void
    {
        $user = $this->user(['phone' => null]);
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);

        $this->put('/profil/whatsapp', ['phone' => '081234567890', 'current_password' => 'password-lama'])->assertSessionDoesntHaveErrors();
        $this->assertSame(['6281234567890'], WhatsappMessage::pluck('phone')->all());

        WhatsappMessage::query()->delete();
        $this->put('/profil/whatsapp', ['phone' => '', 'current_password' => 'password-lama'])->assertSessionDoesntHaveErrors();

        $this->assertNull($user->fresh()->phone);
        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertStringContainsString('dihapus', $message->body);
    }

    public function test_submitting_the_same_number_changes_nothing_and_sends_nothing(): void
    {
        $user = $this->user(['phone' => '6281234567890']);

        $this->actingAs($user)->put('/profil/whatsapp', ['phone' => '081234567890', 'current_password' => 'password-lama'])->assertSessionDoesntHaveErrors();

        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_phone_change_is_recorded_in_the_audit_log(): void
    {
        $user = $this->user(['phone' => '6281234567890']);

        $this->actingAs($user)->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'password-lama']);

        $activity = Activity::where('log_name', 'Pengguna')->where('subject_id', $user->id)->latest('id')->first();
        $this->assertSame('6289912345678', $activity->attribute_changes['attributes']['phone']);
        $this->assertSame('6281234567890', $activity->attribute_changes['old']['phone']);
    }

    public function test_the_new_number_receives_the_next_reset_link(): void
    {
        $user = $this->user(['phone' => '6281234567890']);
        $this->actingAs($user)->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'password-lama']);
        $this->post('/logout');
        WhatsappMessage::query()->delete();

        $this->post('/lupa-password', ['email' => $user->email]);

        $this->assertSame('6289912345678', WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->sole()->phone);
    }

    public function test_phone_changes_are_rate_limited_and_closed_to_member_sessions(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'tebakan'])->assertSessionHasErrors('current_password');
        }
        $this->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'password-lama'])->assertStatus(429);
    }

    public function test_a_vpn_member_session_cannot_change_an_admin_phone(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member')->put('/profil/whatsapp', ['phone' => '089912345678', 'current_password' => 'x'])->assertStatus(403);
    }
}
