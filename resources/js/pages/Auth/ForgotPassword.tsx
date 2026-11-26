import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { BrandLogo } from '@/Components/BrandLogo';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';
import { useTheme } from '@/Hooks/useTheme';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    useTheme();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/lupa-password');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent)] px-4">
            <Head title="Lupa Password" />
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <BrandLogo size="lg" />
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Lupa password</h1>
                        <p className="text-sm text-muted-foreground">Tautan untuk membuat password baru dikirim ke WhatsApp yang terdaftar pada akun Anda.</p>
                    </div>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input type="email" autoComplete="username" autoFocus value={data.email} onChange={(event) => setData('email', event.target.value)} required />
                        </Field>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Mengirim...' : 'Kirim tautan reset'}
                        </Button>
                    </form>
                </Card>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                    <Link href="/login" className="font-medium text-primary hover:underline">
                        Kembali ke halaman masuk
                    </Link>
                </p>
            </div>
        </div>
    );
}
