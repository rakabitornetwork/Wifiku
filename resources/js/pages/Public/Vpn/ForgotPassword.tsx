import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';

export default function ForgotPassword({ company }: { company: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/lupa-password');
    };

    return (
        <VpnShell company={company} showAuthLinks={false}>
            <Head title="Lupa Password" />
            <div className="mx-auto w-full max-w-sm px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Lupa password</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Masukkan email akun Anda. Tautan untuk membuat password baru dikirim ke nomor WhatsApp yang Anda daftarkan.</p>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="username" autoFocus required />
                        </Field>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Mengirim...' : 'Kirim tautan reset'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    <Link href="/portal?layanan=vpn" className="font-medium text-primary hover:underline">
                        Kembali ke halaman masuk
                    </Link>
                </p>
            </div>
        </VpnShell>
    );
}
