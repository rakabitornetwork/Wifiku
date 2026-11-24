<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VpnMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function png(int $width = 600, int $height = 300, string $name = 'foto.png', string $extra = ''): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 60, 60));
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean().$extra);
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/profil/avatar', ['avatar' => $file]);
    }

    public function test_uploaded_photo_is_stored_as_a_256_square_webp_and_shown_in_the_app(): void
    {
        $user = $this->admin();

        $this->upload($user, $this->png(600, 300))->assertRedirect()->assertSessionDoesntHaveErrors();

        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith("avatars/{$user->id}-", $path);
        Storage::disk('local')->assertExists($path);
        [$width, $height, $type] = getimagesize(Storage::disk('local')->path($path));
        $this->assertSame([256, 256, IMAGETYPE_WEBP], [$width, $height, $type]);

        $this->get('/profil')->assertInertia(fn (Assert $page) => $page->where('account.avatar_url', $user->fresh()->avatarUrl())->where('auth.user.avatar_url', $user->fresh()->avatarUrl()));
        $this->assertSame('Mengubah foto profil', Activity::where('log_name', 'Pengguna')->latest('id')->first()->description);
    }

    public function test_the_original_file_is_never_kept_and_hidden_payloads_are_stripped(): void
    {
        $user = $this->admin();

        $this->upload($user, $this->png(300, 300, 'foto.png', "<?php system('id'); ?>"))->assertSessionDoesntHaveErrors();

        $stored = Storage::disk('local')->get($user->fresh()->avatar_path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('system(', $stored);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_replacing_and_removing_delete_the_old_files(): void
    {
        $user = $this->admin();
        $this->upload($user, $this->png());
        $first = $user->fresh()->avatar_path;

        $this->post('/profil/avatar', ['avatar' => $this->png(400, 400)])->assertSessionDoesntHaveErrors();
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->delete('/profil/avatar')->assertRedirect();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($second);
        $this->assertSame('Menghapus foto profil', Activity::where('log_name', 'Pengguna')->latest('id')->first()->description);
    }

    public function test_the_photo_url_changes_when_the_photo_changes(): void
    {
        $user = $this->admin();
        $this->upload($user, $this->png());
        $before = $user->fresh()->avatarUrl();

        $this->post('/profil/avatar', ['avatar' => $this->png(400, 400)]);

        $this->assertNotSame($before, $user->fresh()->avatarUrl());
    }

    public function test_only_jpg_png_and_webp_images_of_sane_size_are_accepted(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->withoutMiddleware(ThrottleRequests::class);
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fakePng = UploadedFile::fake()->createWithContent('foto.png', "<?php echo 'bukan gambar'; ?>");

        $this->post('/profil/avatar', ['avatar' => $svg])->assertSessionHasErrors('avatar');
        $this->post('/profil/avatar', ['avatar' => $fakePng])->assertSessionHasErrors('avatar');
        $this->post('/profil/avatar', ['avatar' => UploadedFile::fake()->image('animasi.gif', 200, 200)])->assertSessionHasErrors('avatar');
        $this->post('/profil/avatar', ['avatar' => UploadedFile::fake()->image('besar.png', 800, 800)->size(3000)])->assertSessionHasErrors('avatar');
        $this->post('/profil/avatar', ['avatar' => $this->png(32, 32)])->assertSessionHasErrors('avatar');
        $this->post('/profil/avatar', [])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_image_with_an_enormous_declared_size_is_rejected_before_decoding(): void
    {
        $header = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $bomb = "\x89PNG\r\n\x1a\n".$header('IHDR', pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0)).$header('IEND', '');
        $user = $this->admin();

        $this->upload($user, UploadedFile::fake()->createWithContent('bom.png', $bomb))->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_photos_are_served_only_to_logged_in_admins_with_safe_headers(): void
    {
        $owner = $this->admin();
        $this->upload($owner, $this->png());
        $url = $owner->fresh()->avatarUrl();
        $other = $this->userWithRole('Teknisi');

        $response = $this->actingAs($other)->get($url)->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_guests_and_member_sessions_cannot_fetch_photos(): void
    {
        $user = $this->admin();
        $this->upload($user, $this->png());
        $url = $user->fresh()->avatarUrl();
        $this->post('/logout');

        $this->get($url)->assertRedirect('/login');
        $this->actingAs(VpnMember::factory()->create(), 'member')->get($url)->assertForbidden();
    }

    public function test_missing_photo_is_a_404(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get(route('avatar.show', $user))->assertNotFound();
    }

    public function test_deleting_a_user_deletes_the_photo_file(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create();
        $this->actingAs($target);
        $this->post('/profil/avatar', ['avatar' => $this->png()]);
        $path = $target->fresh()->avatar_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($owner)->delete("/users/{$target->id}")->assertRedirect();

        $this->assertModelMissing($target);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_user_list_exposes_avatar_urls(): void
    {
        $owner = $this->owner();
        $this->upload($owner, $this->png());

        $this->get('/users')->assertInertia(fn (Assert $page) => $page->where('users.data.0.avatar_url', fn ($url) => is_string($url) || $url === null));
    }

    public function test_uploads_are_rate_limited(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        foreach (range(1, 10) as $attempt) {
            $this->post('/profil/avatar', ['avatar' => $this->png(100, 100)])->assertStatus(302);
        }

        $this->post('/profil/avatar', ['avatar' => $this->png(100, 100)])->assertStatus(429);
    }

    public function test_vpn_member_sessions_cannot_upload_or_remove_photos(): void
    {
        $this->actingAs(VpnMember::factory()->create(), 'member');

        $this->post('/profil/avatar', ['avatar' => $this->png()])->assertStatus(403);
        $this->delete('/profil/avatar')->assertStatus(403);
    }
}
