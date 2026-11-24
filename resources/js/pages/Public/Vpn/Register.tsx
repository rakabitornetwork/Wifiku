import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';

export default function Register({ company }: { company: string }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', whatsapp: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/daftar', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    };

    return (
        <VpnShell company={company} showAuthLinks={false}>
            <Head title="Daftar VPN Tunnel" />
            <div className="mx-auto w-full max-w-md px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Daftar pelanggan VPN Tunnel</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Setelah mendaftar Anda bisa langsung membuat akun VPN.</p>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Nama lengkap" error={errors.name}>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} autoComplete="name" autoFocus required />
                        </Field>
                        <Field label="Email" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="email" required />
                        </Field>
                        <Field label="No. WhatsApp" error={errors.whatsapp} hint="Tagihan dan bukti bayar dikirim ke nomor ini.">
                            <Input inputMode="tel" value={data.whatsapp} onChange={(event) => setData('whatsapp', event.target.value)} placeholder="08xxxxxxxxxx" autoComplete="tel" required />
                        </Field>
                        <Field label="Password" error={errors.password} hint="Minimal 8 karakter.">
                            <Input type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="new-password" required />
                        </Field>
                        <Field label="Ulangi password" error={errors.password_confirmation}>
                            <Input type="password" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} autoComplete="new-password" required />
                        </Field>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Mendaftar...' : 'Daftar'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    Sudah punya akun?{' '}
                    <Link href="/vpn-tunnel/masuk" className="font-medium text-primary hover:underline">
                        Masuk
                    </Link>
                </p>
            </div>
        </VpnShell>
    );
}
