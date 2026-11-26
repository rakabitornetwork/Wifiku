<?php

namespace App\Http\Controllers;

use App\Models\GalleryPhoto;
use App\Models\LandingImage;
use App\Models\Package;
use App\Models\VpnServer;
use App\Services\Settings\SettingService;
use App\Support\LandingContent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page publik perusahaan di halaman utama, dan halaman Terms of Service.
 * Hanya data yang memang untuk publik yang dikirim ke browser (tidak pernah seluruh pengaturan).
 */
class LandingController extends Controller
{
    public function __invoke(SettingService $settings): Response
    {
        $company = $this->company($settings);
        $servers = VpnServer::query()->where('is_active', true)->get(['l2tp_enabled', 'sstp_enabled']);

        return Inertia::render('Public/Landing', [
            'company' => $company,
            'sections' => LandingContent::sections($company['name']),
            'plans' => $this->plans(),
            'gallery' => $this->gallery(),
            'images' => $this->images(),
            'vpn' => [
                'price' => (int) $settings->get('vpn', 'member_price'),
                'registrationOpen' => $settings->get('vpn', 'member_registration_open') === '1',
                'protocols' => array_values(array_filter([
                    $servers->contains('l2tp_enabled', true) ? 'L2TP' : null,
                    $servers->contains('sstp_enabled', true) ? 'SSTP' : null,
                ])),
            ],
            'staff' => request()->user('web') !== null,
            'seo' => $this->seo($company),
        ]);
    }

    public function terms(SettingService $settings): Response
    {
        $company = $this->company($settings);

        return Inertia::render('Public/Terms', [
            'company' => $company,
            'terms' => LandingContent::terms($company['name']),
        ]);
    }

    /**
     * Identitas dan kontak perusahaan dari Pengaturan; nilai kosong tetap null agar bagian terkait disembunyikan.
     *
     * @return array{name: string, logo: string|null, tagline: string|null, whatsapp: string|null, whatsapp_display: string|null, email: string|null, address: string|null, hours: string|null, instagram: string|null, facebook: string|null}
     */
    private function company(SettingService $settings): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $settings->get('company', 'cs_whatsapp')) ?: null;
        $logo = LandingImage::where('slot', LandingImage::LOGO_SLOT)->first();
        $value = fn (string $key): ?string => filled($settings->get('company', $key)) ? (string) $settings->get('company', $key) : null;

        return [
            'name' => (string) $settings->get('company', 'name', config('app.name')),
            'logo' => $logo ? route('landing-image.show', [LandingImage::LOGO_SLOT, 'v' => $logo->updated_at?->timestamp]) : null,
            'tagline' => $value('tagline'),
            'whatsapp' => $whatsapp,
            // 6287778888820 → 087778888820 (format telepon lokal)
            'whatsapp_display' => $whatsapp === null ? null : (str_starts_with($whatsapp, '62') ? '0'.substr($whatsapp, 2) : $whatsapp),
            'email' => $value('email'),
            'address' => $value('address'),
            'hours' => $value('operating_hours'),
            'instagram' => $value('instagram'),
            'facebook' => $value('facebook'),
        ];
    }

    /**
     * URL ilustrasi per slot; slot tanpa gambar bernilai null (landing memakai ilustrasi bawaan).
     *
     * @return array<string, array{url: string, is_ai: bool}|null>
     */
    private function images(): array
    {
        $images = LandingImage::all()->keyBy('slot');

        return collect(array_keys(LandingImage::SLOTS))->mapWithKeys(fn (string $slot) => [
            $slot => isset($images[$slot])
                ? ['url' => route('landing-image.show', [$slot, 'v' => $images[$slot]->updated_at?->timestamp]), 'is_ai' => $images[$slot]->is_ai]
                : null,
        ])->all();
    }

    /**
     * Foto instalasi yang berstatus tayang; hanya judul, keterangan, dan URL gambar yang dikirim.
     *
     * @return list<array{id: int, title: string, caption: string|null, thumb: string, full: string}>
     */
    private function gallery(): array
    {
        return GalleryPhoto::where('is_published', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (GalleryPhoto $photo) => [
                'id' => $photo->id,
                'title' => $photo->title,
                'caption' => $photo->caption,
                'thumb' => route('gallery.image', [$photo, 'thumb', 'v' => $photo->updated_at?->timestamp]),
                'full' => route('gallery.image', [$photo, 'full', 'v' => $photo->updated_at?->timestamp]),
            ])->all();
    }

    /**
     * Paket aktif yang ditandai tampil di landing, termurah dulu; nama yang sama di beberapa router digabung.
     *
     * @return list<array{name: string, price: int, speed: string|null, description: string|null, highlight: bool}>
     */
    private function plans(): array
    {
        return Package::query()
            ->where('is_active', true)
            ->where('show_on_landing', true)
            ->orderBy('price')
            ->orderBy('name')
            ->get(['name', 'price', 'rate_limit', 'landing_description', 'landing_highlight'])
            ->unique('name')
            ->map(fn (Package $package): array => [
                'name' => $package->name,
                'price' => $package->price,
                'speed' => self::speedLabel($package->rate_limit),
                'description' => $package->landing_description,
                'highlight' => $package->landing_highlight,
            ])
            ->values()
            ->all();
    }

    /**
     * "20M/20M" → "20 Mbps"; format yang tidak dikenali tidak ditampilkan.
     */
    public static function speedLabel(?string $rateLimit): ?string
    {
        if ($rateLimit === null || ! preg_match('/^(\d+(?:\.\d+)?)\s*([kKmMgG])/', trim($rateLimit), $matches)) {
            return null;
        }

        $unit = ['k' => 'Kbps', 'm' => 'Mbps', 'g' => 'Gbps'][strtolower($matches[2])];

        // Nol di belakang koma dibuang (1.50 → 1.5), bilangan bulat dibiarkan (20 tetap 20).
        $number = str_contains($matches[1], '.') ? rtrim(rtrim($matches[1], '0'), '.') : $matches[1];

        return $number.' '.$unit;
    }

    /**
     * Meta SEO dan JSON-LD LocalBusiness; hanya memuat kontak yang diisi di Pengaturan.
     *
     * @param  array<string, mixed>  $company
     * @return array{title: string, description: string, canonical: string, jsonLd: array<string, mixed>}
     */
    private function seo(array $company): array
    {
        $title = $company['name'].' — Pemasangan WiFi Rumahan Profesional';
        $description = 'Jasa pemasangan WiFi rumahan cepat, rapi, dan stabil. Survey lokasi, instalasi perangkat, hingga after-sales support dari teknisi berpengalaman.';

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => url('/'),
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $company['name'],
                'url' => url('/'),
                'description' => $description,
                'slogan' => $company['tagline'],
                'telephone' => $company['whatsapp'] ? '+'.$company['whatsapp'] : null,
                'email' => $company['email'],
                'address' => $company['address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $company['address'], 'addressCountry' => 'ID'] : null,
                'areaServed' => ['@type' => 'Country', 'name' => 'Indonesia'],
                'sameAs' => array_values(array_filter([$company['instagram'], $company['facebook']])) ?: null,
            ]),
        ];
    }
}
