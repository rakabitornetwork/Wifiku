<?php

namespace Tests\Feature;

use App\Models\LandingImage;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LandingImageTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function png(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(800, 600));

        return (string) ob_get_clean();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function configureKey(): void
    {
        app(SettingService::class)->set('gemini', 'api_key', 'kunci-rahasia-123', true);
    }

    public function test_generate_stores_a_sanitised_webp_and_sends_key_only_as_header(): void
    {
        $this->configureKey();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'image', 'data' => base64_encode($this->png())]]]]])]);

        $this->actingAs($this->owner())->post('/ilustrasi-landing/hero/generate', ['prompt' => 'Ilustrasi router WiFi di rumah'])->assertSessionDoesntHaveErrors();

        $image = LandingImage::sole();
        $this->assertTrue($image->is_ai);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('local')->get($image->path))[2]);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'kunci-rahasia-123')
            && ! str_contains($request->url(), 'kunci-rahasia')
            && $request['response_format']['aspect_ratio'] === '1:1');
    }

    public function test_api_errors_become_friendly_messages_without_leaking_the_key(): void
    {
        $this->configureKey();
        Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid: kunci-rahasia-123']], 403)]);

        $response = $this->actingAs($this->owner())->post('/ilustrasi-landing/hero/generate', ['prompt' => 'Ilustrasi router WiFi di rumah']);

        $response->assertRedirect();
        $this->assertSame(0, LandingImage::count());
        $this->assertStringNotContainsString('kunci-rahasia', json_encode(session()->all()));
    }

    public function test_generate_requires_a_configured_key_and_a_known_slot(): void
    {
        Http::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post('/ilustrasi-landing/hero/generate', ['prompt' => 'Ilustrasi router WiFi di rumah'])->assertRedirect();
        $this->actingAs($owner)->post('/ilustrasi-landing/footer/generate', ['prompt' => 'Ilustrasi router WiFi di rumah'])->assertNotFound();
        Http::assertNothingSent();
        $this->assertSame(0, LandingImage::count());
    }

    public function test_upload_replaces_the_previous_file_and_delete_restores_defaults(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/ilustrasi-landing/about/upload', ['image' => UploadedFile::fake()->image('a.jpg', 900, 600)])->assertSessionDoesntHaveErrors();
        $first = LandingImage::sole()->path;
        $this->actingAs($owner)->post('/ilustrasi-landing/about/upload', ['image' => UploadedFile::fake()->image('b.jpg', 900, 600)]);

        Storage::disk('local')->assertMissing($first);
        $this->assertSame(1, LandingImage::count());

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('images.about.is_ai', false)->where('images.hero', null));
        $this->get('/gambar-landing/about')->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($owner)->delete('/ilustrasi-landing/about');
        $this->assertSame(0, LandingImage::count());
        $this->get('/gambar-landing/about')->assertNotFound();
    }

    public function test_only_settings_managers_can_open_the_admin_page_and_gemini_key_is_never_sent(): void
    {
        $this->configureKey();

        $this->actingAs($this->userWithRole('Teknisi'))->get('/ilustrasi-landing')->assertForbidden();
        $this->actingAs($this->owner())->get('/ilustrasi-landing')->assertOk()->assertInertia(fn (Assert $page) => $page->where('geminiConfigured', true));
        $this->assertStringNotContainsString('kunci-rahasia', $this->get('/')->getContent());
    }
}
