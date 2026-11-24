<?php

namespace Tests\Feature\Auth;

use App\Enums\WhatsappMessageType;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function adminWithPhone(array $attributes = []): User
    {
        return User::factory()->create(['email' => 'rudi@teslatech.my.id', 'phone' => '6281234567890', 'password' => 'password-lama', ...$attributes]);
    }

    /**
     * @return array{token: string, email: string}
     */
    private function linkFromMessage(): array
    {
        $message = WhatsappMessage::where('type', WhatsappMessageType::PasswordReset)->latest('id')->firstOrFail();
        preg_match('#/reset-password/([A-Za-z0-9]+)\?email=([^\s]+)#', $message->body, $matches);

        return ['token' => $matches[1], 'email' => urldecode($matches[2])];
    }

    public function test_forgot_password_pages_are_public_and_linked_from_login(): void
    {
        $this->get('/lupa-password')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_link_is_sent_to_the_users_whatsapp(): void
    {
        $this->adminWithPhone();

        $this->post('/lupa-password', ['email' => 'RUDI@teslatech.my.id'])->assertRedirect('/login');

        $message = WhatsappMessage::sole();
        $this->assertSame('6281234567890', $message->phone);
        $this->assertStringContainsString('/reset-password/', $message->body);
    }

    public function test_user_without_a_phone_gets_the_standard_email_notification(): void
    {
        Notification::fake();
        $user = $this->adminWithPhone(['phone' => null]);

        $this->post('/lupa-password', ['email' => $user->email])->assertRedirect('/login');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_response_does_not_reveal_whether_the_email_exists_or_is_active(): void
    {
        Notification::fake();
        $this->adminWithPhone(['email' => 'nonaktif@teslatech.my.id', 'is_active' => false]);

        $this->post('/lupa-password', ['email' => 'tidak-ada@teslatech.my.id'])->assertRedirect('/login')->assertSessionDoesntHaveErrors();
        $this->post('/lupa-password', ['email' => 'nonaktif@teslatech.my.id'])->assertRedirect('/login')->assertSessionDoesntHaveErrors();

        $this->assertSame(0, WhatsappMessage::count());
        Notification::assertNothingSent();
    }

    public function test_mail_failure_does_not_break_or_leak_through_the_response(): void
    {
        $user = $this->adminWithPhone(['phone' => null]);
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->post('/lupa-password', ['email' => $user->email])->assertRedirect('/login')->assertSessionDoesntHaveErrors();
    }

    public function test_user_can_reset_the_password_and_all_sessions_are_ended(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->adminWithPhone();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'sesi-rudi', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'sesi-lain', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $oldRemember = $user->remember_token;
        $this->post('/lupa-password', ['email' => $user->email]);
        $link = $this->linkFromMessage();

        $this->get("/reset-password/{$link['token']}?email=".urlencode($link['email']))->assertOk();
        $this->post('/reset-password', [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertRedirect('/login')->assertSessionDoesntHaveErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->post('/login', ['email' => $user->email, 'password' => 'password-lama'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password-baru-1'])->assertRedirect('/dashboard');
        $this->assertStringContainsString('semua sesi login', WhatsappMessage::latest('id')->first()->body);
        $this->assertSame('Password direset lewat tautan reset', Activity::where('log_name', 'Pengguna')->latest('id')->first()->description);
    }

    public function test_token_is_single_use_and_invalid_inputs_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->adminWithPhone();
        $this->adminWithPhone(['email' => 'lain@teslatech.my.id']);
        $this->post('/lupa-password', ['email' => $user->email]);
        $link = $this->linkFromMessage();
        $strong = ['password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'];

        $this->post('/reset-password', [...$link, 'token' => 'ngawur', ...$strong])->assertSessionHasErrors('email');
        $this->post('/reset-password', [...$link, 'email' => 'lain@teslatech.my.id', ...$strong])->assertSessionHasErrors('email');
        $this->post('/reset-password', [...$link, 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->post('/reset-password', [...$link, ...$strong])->assertSessionDoesntHaveErrors();
        $this->post('/reset-password', [...$link, 'password' => 'diretas-12345', 'password_confirmation' => 'diretas-12345'])->assertSessionHasErrors('email');
    }

    public function test_reset_attempts_are_rate_limited(): void
    {
        $payload = ['token' => 'ngawur', 'email' => 'rudi@teslatech.my.id', 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'];

        foreach (range(1, 5) as $attempt) {
            $this->post('/reset-password', $payload)->assertStatus(302);
        }

        $this->post('/reset-password', $payload)->assertStatus(429);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->adminWithPhone();
        $this->post('/lupa-password', ['email' => $user->email]);
        $link = $this->linkFromMessage();

        $this->travel(61)->minutes();

        $this->post('/reset-password', [...$link, 'password' => 'password-baru-1', 'password_confirmation' => 'password-baru-1'])->assertSessionHasErrors('email');
    }

    public function test_owner_can_send_a_reset_link_from_the_users_page_and_sees_throttle_and_missing_phone(): void
    {
        $owner = $this->owner();
        $target = $this->adminWithPhone();
        $noPhone = $this->adminWithPhone(['email' => 'tanpa@teslatech.my.id', 'phone' => null]);

        $this->actingAs($owner)->post("/users/{$target->id}/reset-link")->assertRedirect();
        $this->actingAs($owner)->post("/users/{$target->id}/reset-link")->assertRedirect();
        $this->actingAs($owner)->post("/users/{$noPhone->id}/reset-link")->assertRedirect();

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertSame('6281234567890', WhatsappMessage::sole()->phone);
    }

    public function test_only_users_who_can_manage_users_may_send_reset_links(): void
    {
        $target = $this->adminWithPhone();

        $this->actingAs($this->admin())->post("/users/{$target->id}/reset-link")->assertForbidden();
        $this->actingAs($this->userWithRole('Teknisi'))->post("/users/{$target->id}/reset-link")->assertForbidden();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_phone_numbers_are_normalised_and_validated_when_saving_users(): void
    {
        $owner = $this->owner();
        $payload = ['name' => 'Rudi', 'email' => 'rudi2@teslatech.my.id', 'role' => 'Teknisi', 'password' => 'rahasia123', 'is_active' => true];

        $this->actingAs($owner)->post('/users', [...$payload, 'phone' => '0812-3456-7890'])->assertSessionDoesntHaveErrors();
        $this->assertSame('6281234567890', User::where('email', 'rudi2@teslatech.my.id')->value('phone'));

        $this->actingAs($owner)->post('/users', [...$payload, 'email' => 'rudi3@teslatech.my.id', 'phone' => '12345'])->assertSessionHasErrors('phone');
        $this->actingAs($owner)->post('/users', [...$payload, 'email' => 'rudi4@teslatech.my.id', 'phone' => ''])->assertSessionDoesntHaveErrors();
        $this->assertNull(User::where('email', 'rudi4@teslatech.my.id')->value('phone'));
    }

    public function test_server_command_sets_a_new_password_and_ends_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->adminWithPhone(['phone' => null]);
        DB::table('sessions')->insert(['id' => 'sesi-rudi', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('admin:reset-password', ['email' => $user->email])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'password-darurat-1')
            ->expectsQuestion('Ulangi password baru', 'password-darurat-1')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('sessions')->count());
        $this->post('/login', ['email' => $user->email, 'password' => 'password-darurat-1'])->assertRedirect('/dashboard');
        $this->assertSame('Password direset lewat perintah server', Activity::where('log_name', 'Pengguna')->latest('id')->first()->description);
    }

    public function test_server_command_can_generate_a_password_and_rejects_bad_input(): void
    {
        $user = $this->adminWithPhone();

        $this->artisan('admin:reset-password', ['email' => 'tidak-ada@teslatech.my.id', '--generate' => true])->assertFailed();
        $this->artisan('admin:reset-password', ['email' => $user->email])->expectsQuestion('Password baru (minimal 8 karakter)', 'pendek')->assertFailed();
        $this->artisan('admin:reset-password', ['email' => $user->email])
            ->expectsQuestion('Password baru (minimal 8 karakter)', 'password-darurat-1')
            ->expectsQuestion('Ulangi password baru', 'beda-sekali-123')
            ->assertFailed();
        $this->assertTrue(password_verify('password-lama', $user->fresh()->password));

        $this->artisan('admin:reset-password', ['email' => $user->email, '--generate' => true])->expectsOutputToContain('Password baru: ')->assertSuccessful();
        $this->assertFalse(password_verify('password-lama', $user->fresh()->password));
    }
}
