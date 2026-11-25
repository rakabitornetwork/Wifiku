<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(string $title = 'Pasang Desa A', bool $published = true): void
    {
        $this->actingAs($this->owner())->post('/galeri-landing', [
            'photo' => UploadedFile::fake()->image('rumah.jpg', 1200, 900),
            'title' => $title,
            'caption' => 'Kabel rapi',
            'is_published' => $published,
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_owner_uploads_a_photo_that_is_re_encoded_to_webp_in_two_sizes(): void
    {
        $this->upload();

        $photo = GalleryPhoto::sole();
        Storage::disk('local')->assertExists([$photo->path, $photo->thumb_path]);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('local')->get($photo->path))[2]);
        $this->assertLessThanOrEqual(640, getimagesizefromstring(Storage::disk('local')->get($photo->thumb_path))[0]);
    }

    public function test_non_image_files_are_rejected(): void
    {
        $this->actingAs($this->owner())->post('/galeri-landing', [
            'photo' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;'),
            'title' => 'Palsu',
        ])->assertSessionHasErrors('photo');

        $this->assertSame(0, GalleryPhoto::count());
    }

    public function test_only_users_who_manage_settings_can_use_the_admin_page(): void
    {
        $this->get('/galeri-landing')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Teknisi'))->get('/galeri-landing')->assertForbidden();
        $this->actingAs($this->owner())->get('/galeri-landing')->assertOk();
    }

    public function test_landing_shows_only_published_photos_and_serves_them_publicly(): void
    {
        $this->upload('Tayang');
        $this->upload('Draft', false);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('gallery', 1)
            ->where('gallery.0.title', 'Tayang')
            ->missing('gallery.0.path'));

        $published = GalleryPhoto::where('title', 'Tayang')->first();
        $draft = GalleryPhoto::where('title', 'Draft')->first();
        auth()->logout();

        $this->get("/galeri/{$published->id}/thumb")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/galeri/{$published->id}/full")->assertOk();
        $this->get("/galeri/{$published->id}/ukuran-lain")->assertNotFound();
        $this->get("/galeri/{$draft->id}/full")->assertNotFound();
    }

    public function test_update_toggle_and_delete_remove_files(): void
    {
        $this->upload();
        $photo = GalleryPhoto::sole();
        $owner = $this->owner();

        $this->actingAs($owner)->put("/galeri-landing/{$photo->id}", ['title' => 'Baru', 'caption' => '', 'is_published' => false])->assertSessionDoesntHaveErrors();
        $this->assertFalse($photo->fresh()->is_published);
        $this->assertSame('Baru', $photo->fresh()->title);

        $this->actingAs($owner)->delete("/galeri-landing/{$photo->id}");
        $this->assertSame(0, GalleryPhoto::count());
        Storage::disk('local')->assertMissing($photo->path);
        Storage::disk('local')->assertMissing($photo->thumb_path);
    }
}
