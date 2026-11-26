import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { AuthSplitLayout } from '@/Components/AuthSplitLayout';
import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Field';

export default function Register({ company }: { company: string }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', whatsapp: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/daftar', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    };

    return (
        <AuthSplitLayout headline="Akun VPN Anda, siap dipakai di MikroTik." benefits={['Kelola akun VPN Tunnel Anda', 'Ambil script RouterOS siap tempel', 'Tagihan dan perpanjangan akun']}>
            <Head title="Daftar VPN Tunnel" />
            <div className="mb-8">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Daftar pelanggan VPN Tunnel</h1>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">Setelah mendaftar Anda bisa langsung membuat akun VPN.</p>
            </div>
            <form onSubmit={submit} className="space-y-5">
                <Field label="Nama lengkap" error={errors.name}>
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        autoComplete="name"
                        autoFocus
                        required
                    />
                </Field>
                <Field label="Email" error={errors.email}>
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        autoComplete="email"
                        required
                    />
                </Field>
                <Field label="No. WhatsApp" error={errors.whatsapp} hint="Tagihan dan bukti bayar dikirim ke nomor ini.">
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        inputMode="tel"
                        value={data.whatsapp}
                        onChange={(event) => setData('whatsapp', event.target.value)}
                        placeholder="08xxxxxxxxxx"
                        autoComplete="tel"
                        required
                    />
                </Field>
                <Field label="Password" error={errors.password} hint="Minimal 8 karakter.">
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </Field>
                <Field label="Ulangi password" error={errors.password_confirmation}>
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
                    type="submit"
                    className="h-12 w-full bg-indigo-600 text-base font-semibold text-white shadow-md shadow-indigo-600/25 hover:bg-indigo-700 hover:opacity-100"
                    disabled={processing}
                >
                    {processing ? 'Mendaftar...' : 'Daftar'}
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Sudah punya akun?{' '}
                <Link href="/portal?layanan=vpn" className="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">
                    Masuk
                </Link>
            </p>
        </AuthSplitLayout>
    );
}
