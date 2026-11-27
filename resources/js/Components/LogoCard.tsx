import { router } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';

export function LogoCard({ logo }: { logo: string | null }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = (input: HTMLInputElement) => {
        const file = input.files?.[0];
        input.value = '';

        if (!file) {
            return;
        }

        setError(null);
        router.post('/settings/logo', { logo: file }, { preserveScroll: true, forceFormData: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: (errors) => setError(Object.values(errors)[0] ?? 'Unggahan gagal.') });
    };

    return (
        <Card className="mb-4 flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
            <div className="flex h-24 w-full items-center justify-center rounded-lg border bg-[repeating-conic-gradient(var(--muted)_0%_25%,transparent_0%_50%)] bg-[length:16px_16px] sm:w-56">
                {logo ? <img src={logo} alt="Logo perusahaan" className="max-h-20 max-w-[85%] object-contain" /> : <span className="px-3 text-center text-xs text-muted-foreground">Belum ada logo; memakai ikon bawaan.</span>}
            </div>
            <div className="min-w-0 flex-1 space-y-1">
                <div className="font-medium">Logo perusahaan</div>
                <p className="text-xs text-muted-foreground">Dipakai di seluruh aplikasi: panel admin, halaman login, portal pelanggan, dan landing page. Gunakan PNG atau WebP berlatar transparan, minimal 64 px, maksimal 4 MB.</p>
                {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
            </div>
            <div className="flex items-center gap-2">
                <label className={`inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-muted ${busy ? 'pointer-events-none opacity-50' : ''}`}>
                    <ImagePlus className="size-4" /> {busy ? 'Mengunggah...' : logo ? 'Ganti logo' : 'Unggah logo'}
                    <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => upload(event.currentTarget)} />
                </label>
                {logo && (
                    <Button variant="ghost" size="icon" aria-label="Hapus logo" onClick={() => router.delete('/settings/logo', { preserveScroll: true })}>
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </div>
        </Card>
    );
}
