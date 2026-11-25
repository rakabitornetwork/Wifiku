import { router } from '@inertiajs/react';
import { ImagePlus, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Textarea } from '@/Components/ui/Field';

type Slot = { slot: string; label: string; aspect: string; default_prompt: string; prompt: string; url: string | null; is_ai: boolean };

function SlotCard({ item, configured }: { item: Slot; configured: boolean }) {
    const [prompt, setPrompt] = useState(item.prompt);
    const [busy, setBusy] = useState(false);

    const generate = () =>
        router.post(`/ilustrasi-landing/${item.slot}/generate`, { prompt }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const upload = (file: File | undefined) => {
        if (file) {
            router.post(`/ilustrasi-landing/${item.slot}/upload`, { image: file }, { preserveScroll: true, forceFormData: true });
        }
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

            <div className="flex flex-wrap items-center gap-2">
                <Button onClick={generate} disabled={busy || !configured || prompt.trim().length < 10}>
                    <Sparkles className="size-4" /> {busy ? 'Membuat (bisa 1 menit)...' : 'Buat dengan AI'}
                </Button>
                <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-muted">
                    <ImagePlus className="size-4" /> Unggah sendiri
                    <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => upload(event.target.files?.[0])} />
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

export default function LandingImagesIndex({ slots, geminiConfigured }: { slots: Slot[]; geminiConfigured: boolean }) {
    return (
        <>
            <PageHeader title="Ilustrasi Landing" description="Gambar pada hero, bagian Tentang, dan banner ajakan. Dibuat dengan Gemini atau diunggah sendiri; slot kosong memakai ilustrasi bawaan." />

            {!geminiConfigured && (
                <Card className="mb-4 border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    API key Gemini belum diisi. Isi di <a href="/settings/integrations" className="font-medium underline">Pengaturan → Gemini</a> untuk memakai tombol "Buat dengan AI". Unggah manual tetap bisa dipakai.
                </Card>
            )}

            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                {slots.map((item) => (
                    <SlotCard key={item.slot} item={item} configured={geminiConfigured} />
                ))}
            </div>
            <p className="mt-4 text-xs text-muted-foreground">Gambar dari Gemini memuat watermark SynthID dan ditandai "Ilustrasi" di landing page. Jangan dipakai sebagai foto hasil instalasi; untuk itu gunakan Galeri Landing.</p>
        </>
    );
}
