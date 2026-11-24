import { Head, Link, useForm } from '@inertiajs/react';
import { Wifi } from 'lucide-react';
import { type FormEvent } from 'react';

import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';
import { useTheme } from '@/Hooks/useTheme';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({ token, email, password: '', password_confirmation: '' });
    useTheme();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/reset-password', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent)] px-4">
            <Head title="Buat Password Baru" />
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <div className="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg">
                        <Wifi className="size-6" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Buat password baru</h1>
                        <p className="text-sm text-muted-foreground">Untuk akun {email || 'Anda'}. Setelah disimpan, semua sesi login lama diakhiri.</p>
                    </div>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input type="email" autoComplete="username" value={data.email} onChange={(event) => setData('email', event.target.value)} required />
                        </Field>
                        <Field label="Password baru" error={errors.password} hint="Minimal 8 karakter.">
                            <Input type="password" autoComplete="new-password" autoFocus value={data.password} onChange={(event) => setData('password', event.target.value)} required />
                        </Field>
                        <Field label="Ulangi password baru" error={errors.password_confirmation}>
                            <Input type="password" autoComplete="new-password" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} required />
                        </Field>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan password'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    Tautan kedaluwarsa?{' '}
                    <Link href="/lupa-password" className="font-medium text-primary hover:underline">
                        Minta tautan baru
                    </Link>
                </p>
            </div>
        </div>
    );
}
