import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { AuthSplitLayout } from '@/Components/AuthSplitLayout';
import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Field';

export default function ResetPassword({ company, token, email }: { company: string; token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/reset-password', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    };

    return (
        <AuthSplitLayout headline="Akun VPN Anda, siap dipakai di MikroTik." benefits={['Kelola akun VPN Tunnel Anda', 'Ambil script RouterOS siap tempel', 'Tagihan dan perpanjangan akun']}>
            <Head title="Buat Password Baru" />
            <div className="mb-8">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Buat password baru</h1>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">Untuk akun {email || 'Anda'}.</p>
            </div>
            <form onSubmit={submit} className="space-y-5">
                <Field label="Email" error={errors.email}>
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        autoComplete="username"
                        required
                    />
                </Field>
                <Field label="Password baru" error={errors.password} hint="Minimal 8 karakter.">
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />
                </Field>
                <Field label="Ulangi password baru" error={errors.password_confirmation}>
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </Field>
                <Button
                    variant="save"
                    type="submit"
                    className="h-12 w-full text-base"
                    disabled={processing}
                >
                    {processing ? 'Menyimpan...' : 'Simpan password'}
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Tautan kedaluwarsa?{' '}
                <Link href="/vpn-tunnel/lupa-password" className="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">
                    Minta tautan baru
                </Link>
            </p>
        </AuthSplitLayout>
    );
}
