import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input } from '@/Components/ui/Field';

export default function Login({ company }: { company: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/masuk', { onFinish: () => setData('password', '') });
    };

    return (
        <VpnShell company={company} showAuthLinks={false}>
            <Head title="Masuk VPN Tunnel" />
            <div className="mx-auto w-full max-w-sm px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Masuk ke akun VPN Tunnel</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Kelola akun VPN dan ambil script RouterOS Anda.</p>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="username" autoFocus required />
                        </Field>
                        <Field label="Password" error={errors.password}>
                            <Input type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="current-password" required />
                        </Field>
                        <Checkbox label="Ingat saya" checked={data.remember} onChange={(checked) => setData('remember', checked)} />
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Memproses...' : 'Masuk'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    Belum punya akun?{' '}
                    <Link href="/vpn-tunnel/daftar" className="font-medium text-primary hover:underline">
                        Daftar
                    </Link>
                </p>
            </div>
        </VpnShell>
    );
}
