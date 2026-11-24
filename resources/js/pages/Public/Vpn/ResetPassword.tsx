import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';

export default function ResetPassword({ company, token, email }: { company: string; token: string; email: string }) {
    const { data, setData, post, processing, errors } = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/reset-password', { onFinish: () => setData((current) => ({ ...current, password: '', password_confirmation: '' })) });
    };

    return (
        <VpnShell company={company} showAuthLinks={false}>
            <Head title="Buat Password Baru" />
            <div className="mx-auto w-full max-w-sm px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Buat password baru</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Untuk akun {email || 'Anda'}.</p>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="username" required />
                        </Field>
                        <Field label="Password baru" error={errors.password} hint="Minimal 8 karakter.">
                            <Input type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="new-password" autoFocus required />
                        </Field>
                        <Field label="Ulangi password baru" error={errors.password_confirmation}>
                            <Input type="password" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} autoComplete="new-password" required />
                        </Field>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan password'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    Tautan kedaluwarsa?{' '}
                    <Link href="/vpn-tunnel/lupa-password" className="font-medium text-primary hover:underline">
                        Minta tautan baru
                    </Link>
                </p>
            </div>
        </VpnShell>
    );
}
