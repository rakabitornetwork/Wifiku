import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { AuthSplitLayout } from '@/Components/AuthSplitLayout';
import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Field';

export default function ForgotPassword({ company }: { company: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/vpn-tunnel/lupa-password');
    };

    return (
        <AuthSplitLayout headline="Akun VPN Anda, siap dipakai di MikroTik." benefits={['Kelola akun VPN Tunnel Anda', 'Ambil script RouterOS siap tempel', 'Tagihan dan perpanjangan akun']}>
            <Head title="Lupa Password" />
            <div className="mb-8">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Lupa password</h1>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">Masukkan email akun Anda. Tautan untuk membuat password baru dikirim ke nomor WhatsApp yang Anda daftarkan.</p>
            </div>
            <form onSubmit={submit} className="space-y-5">
                <Field label="Email" error={errors.email}>
                    <Input
                        className="h-12 bg-slate-50 text-base dark:bg-background"
                        type="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />
                </Field>
                <Button
                    type="submit"
                    className="h-12 w-full bg-indigo-600 text-base font-semibold text-white shadow-md shadow-indigo-600/25 hover:bg-indigo-700 hover:opacity-100"
                    disabled={processing}
                >
                    {processing ? 'Mengirim...' : 'Kirim tautan reset'}
                </Button>
            </form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                <Link href="/portal?layanan=vpn" className="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">
                    Kembali ke halaman masuk
                </Link>
            </p>
        </AuthSplitLayout>
    );
}
