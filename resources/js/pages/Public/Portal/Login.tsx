import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';

import { PortalShell } from '@/Components/PortalShell';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';

type Props = { company: { name: string; logo: string | null }; pendingPhone: string | null; resendSeconds: number };

export default function Login({ company, pendingPhone, resendSeconds }: Props) {
    const phoneForm = useForm({ whatsapp: '' });
    const resendForm = useForm({});
    const otpForm = useForm({ otp: '' });
    const [wait, setWait] = useState(resendSeconds);

    useEffect(() => {
        if (!pendingPhone || wait <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setWait((value) => value - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [pendingPhone, wait]);

    const requestCode = (event: FormEvent) => {
        event.preventDefault();
        phoneForm.post('/pelanggan/masuk', { onSuccess: () => setWait(resendSeconds) });
    };

    const resend = () => resendForm.post('/pelanggan/kirim-ulang', { preserveScroll: true, onSuccess: () => setWait(resendSeconds) });

    const verify = (event: FormEvent) => {
        event.preventDefault();
        otpForm.post('/pelanggan/verifikasi', { onError: () => otpForm.reset('otp') });
    };

    return (
        <PortalShell company={company}>
            <Head title="Masuk Portal Pelanggan" />
            <div className="mx-auto w-full max-w-sm px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Masuk ke portal pelanggan</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Lihat tagihan, cek status ONU, dan ganti nama serta password WiFi Anda.</p>
                </div>
                <Card className="glass p-6">
                    {pendingPhone ? (
                        <form onSubmit={verify} className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Jika nomor <span className="num font-medium text-foreground">{pendingPhone}</span> terdaftar, kode masuk 6 digit dikirim ke WhatsApp-nya. Berlaku 10 menit.
                            </p>
                            <Field label="Kode dari WhatsApp" error={otpForm.errors.otp}>
                                <Input
                                    value={otpForm.data.otp}
                                    onChange={(event) => otpForm.setData('otp', event.target.value.replace(/\D/g, '').slice(0, 6))}
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    placeholder="000000"
                                    className="num text-center text-lg tracking-[0.4em]"
                                    autoFocus
                                    required
                                />
                            </Field>
                            <Button type="submit" className="w-full" disabled={otpForm.processing || otpForm.data.otp.length !== 6}>
                                {otpForm.processing ? 'Memeriksa...' : 'Masuk'}
                            </Button>
                            <div className="flex items-center justify-between text-sm">
                                <button type="button" className="font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline" onClick={resend} disabled={wait > 0 || resendForm.processing}>
                                    {wait > 0 ? `Kirim ulang (${wait} dtk)` : 'Kirim ulang kode'}
                                </button>
                                <Link href="/pelanggan/masuk?ganti=1" className="text-muted-foreground hover:text-foreground hover:underline">
                                    Ganti nomor
                                </Link>
                            </div>
                        </form>
                    ) : (
                        <form onSubmit={requestCode} className="space-y-4">
                            <Field label="Nomor WhatsApp" error={phoneForm.errors.whatsapp} hint="Nomor yang terdaftar di data pelanggan kami, misalnya 08123456789.">
                                <Input type="tel" value={phoneForm.data.whatsapp} onChange={(event) => phoneForm.setData('whatsapp', event.target.value)} inputMode="tel" placeholder="08123456789" className="num" autoComplete="tel" autoFocus required />
                            </Field>
                            <Button type="submit" className="w-full" disabled={phoneForm.processing || phoneForm.data.whatsapp.trim() === ''}>
                                {phoneForm.processing ? 'Mengirim...' : 'Kirim kode ke WhatsApp'}
                            </Button>
                        </form>
                    )}
                </Card>
            </div>
        </PortalShell>
    );
}
