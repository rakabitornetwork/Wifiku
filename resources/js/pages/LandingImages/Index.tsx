import { router } from '@inertiajs/react';
import { ImagePlus, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Textarea } from '@/Components/ui/Field';

type Slot = { slot: string; label: string; aspect: string; default_prompt: string; prompt: string; url: string | null; is_ai: boolean };

function LogoCard({ logo }: { logo: string | null }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = (input: HTMLInputElement) => {
        const file = input.files?.[0];
        input.value = '';

        if (!file) {
            return;
        }

        setError(null);
        router.post('/ilustrasi-landing/logo', { logo: file }, { preserveScroll: true, forceFormData: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: (errors) => setError(Object.values(errors)[0] ?? 'Unggahan gagal.') });
    };

    return (
        <Card className="mb-4 flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
            <div className="flex h-24 w-full items-center justify-center rounded-lg border bg-[repeating-conic-gradient(var(--muted)_0%_25%,transparent_0%_50%)] bg-[length:16px_16px] sm:w-56">
                {logo ? <img src={logo} alt="Logo perusahaan" className="max-h-20 max-w-[85%] object-contain" /> : <span className="px-3 text-center text-xs text-muted-foreground">Belum ada logo; memakai ikon bawaan.</span>}
            </div>
            <div className="min-w-0 flex-1 space-y-1">
                <div className="font-medium">Logo perusahaan</div>
                <p className="text-xs text-muted-foreground">Tampil di header dan footer landing page. Gunakan PNG atau WebP berlatar transparan, minimal 64 px, maksimal 4 MB.</p>
                {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
            </div>
            <div className="flex items-center gap-2">
                <label className={`inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-muted ${busy ? 'pointer-events-none opacity-50' : ''}`}>
                    <ImagePlus className="size-4" /> {busy ? 'Mengunggah...' : logo ? 'Ganti logo' : 'Unggah logo'}
                    <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => upload(event.currentTarget)} />
                </label>
                {logo && (
                    <Button variant="ghost" size="icon" aria-label="Hapus logo" onClick={() => router.delete('/ilustrasi-landing/logo', { preserveScroll: true })}>
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </div>
        </Card>
    );
}

function SlotCard({ item, configured }: { item: Slot; configured: boolean }) {
    const [prompt, setPrompt] = useState(item.prompt);
    const [busy, setBusy] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const generate = () =>
        router.post(`/ilustrasi-landing/${item.slot}/generate`, { prompt }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const upload = (input: HTMLInputElement) => {
        const file = input.files?.[0];
        input.value = '';

        if (!file) {
            return;
        }

        setUploadError(null);
        router.post(
            `/ilustrasi-landing/${item.slot}/upload`,
            { image: file },
            {
                preserveScroll: true,
                forceFormData: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (errors) => setUploadError(Object.values(errors)[0] ?? 'Unggahan gagal.'),
            },
        );
    };

    return (
        <Card className="space-y-4 p-4">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <div className="font-medium">{item.label}</div>
                    <div className="text-xs text-muted-foreground">Rasio {item.aspect}</div>
                </div>
                {item.url ? <Badge tone={item.is_ai ? 'blue' : 'green'}>{item.is_ai ? 'Dibuat AI' : 'Unggahan'}</Badge> : <Badge tone="slate">Bawaan</Badge>}
            </div>

            <div className="flex min-h-32 items-center justify-center overflow-hidden rounded-lg border bg-muted">
                {item.url ? <img src={item.url} alt={item.label} className="max-h-72 w-full object-contain" /> : <span className="p-6 text-xs text-muted-foreground">Memakai ilustrasi bawaan landing page.</span>}
            </div>

            <Field label="Prompt untuk AI" hint="Tulis dalam bahasa apa saja. Hindari meminta teks di dalam gambar; hasilnya sering salah eja.">
                <Textarea rows={4} value={prompt} onChange={(event) => setPrompt(event.target.value)} maxLength={1000} />
            </Field>

            {uploadError && <p className="text-sm text-red-600 dark:text-red-400">{uploadError}</p>}

            <div className="flex flex-wrap items-center gap-2">
                <Button onClick={generate} disabled={busy || !configured || prompt.trim().length < 10}>
                    <Sparkles className="size-4" /> {busy ? 'Membuat (bisa 1 menit)...' : 'Buat dengan AI'}
                </Button>
                <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-muted">
                    <ImagePlus className="size-4" /> Unggah sendiri
                    <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => upload(event.currentTarget)} />
                </label>
                <button type="button" className="text-xs text-muted-foreground underline" onClick={() => setPrompt(item.default_prompt)}>
                    Prompt bawaan
                </button>
                {item.url && (
                    <Button variant="ghost" size="icon" className="ml-auto" aria-label="Hapus gambar" onClick={() => router.delete(`/ilustrasi-landing/${item.slot}`, { preserveScroll: true })}>
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </div>
        </Card>
    );
}

export default function LandingImagesIndex({ slots, geminiConfigured, logo }: { slots: Slot[]; geminiConfigured: boolean; logo: string | null }) {
    return (
        <>
            <PageHeader title="Ilustrasi Landing" description="Logo perusahaan serta gambar pada hero, bagian Tentang, dan banner ajakan. Dibuat dengan Gemini atau diunggah sendiri; slot kosong memakai ilustrasi bawaan." />

            {!geminiConfigured && (
                <Card className="mb-4 border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    API key Gemini belum diisi. Isi di <a href="/settings/integrations" className="font-medium underline">Pengaturan → Gemini</a> untuk memakai tombol "Buat dengan AI". Unggah manual tetap bisa dipakai.
                </Card>
            )}

            <LogoCard logo={logo} />

            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                {slots.map((item) => (
                    <SlotCard key={item.slot} item={item} configured={geminiConfigured} />
                ))}
            </div>
            <p className="mt-4 text-xs text-muted-foreground">Gambar dari Gemini memuat watermark SynthID dan ditandai "Ilustrasi" di landing page. Jangan dipakai sebagai foto hasil instalasi; untuk itu gunakan Galeri Landing.</p>
        </>
    );
}
