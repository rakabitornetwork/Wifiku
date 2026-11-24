import { useForm } from '@inertiajs/react';
import { KeyRound, MessageCircle } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';
import { formatDate } from '@/lib/utils';

type Props = { account: { name: string; email: string; phone: string | null; role: string | null; last_login_at: string | null } };

export default function ProfileEdit({ account }: Props) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const phoneForm = useForm({ phone: account.phone ?? '', current_password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/profil/password', { preserveScroll: true, onSuccess: () => form.reset(), onError: () => form.reset('password', 'password_confirmation') });
    };

    // Setelah disimpan, isian mengikuti nomor yang sudah dinormalkan server (08xx menjadi 62xx).
    useEffect(() => {
        phoneForm.setData('phone', account.phone ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [account.phone]);

    const submitPhone = (event: FormEvent) => {
        event.preventDefault();
        phoneForm.put('/profil/whatsapp', { preserveScroll: true, onFinish: () => phoneForm.setData('current_password', '') });
    };

    return (
        <>
            <PageHeader title="Akun Saya" description="Data akun Anda dan penggantian password." />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <Card>
                    <CardHeader title="Informasi akun" />
                    <dl className="space-y-3 p-5 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Nama</dt>
                            <dd className="font-medium">{account.name}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Email</dt>
                            <dd className="font-medium break-all">{account.email}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Role</dt>
                            <dd className="font-medium">{account.role ?? '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">WhatsApp</dt>
                            <dd className="num font-medium">{account.phone ?? <span className="font-normal text-muted-foreground">Belum diisi</span>}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Login terakhir</dt>
                            <dd className="font-medium">{account.last_login_at ? formatDate(account.last_login_at) : '-'}</dd>
                        </div>
                        {!account.phone && <p className="rounded-lg bg-amber-500/10 p-3 text-xs">Tanpa nomor WhatsApp, tautan reset password tidak bisa dikirim ke Anda. Isi nomor Anda pada formulir Nomor WhatsApp.</p>}
                    </dl>
                </Card>

 <div className="space-y-4">
                <Card>
                    <CardHeader title="Nomor WhatsApp" description="Tujuan tautan reset password dan notifikasi keamanan akun Anda." />
                    <form onSubmit={submitPhone} className="space-y-4 p-5">
                        <Field label="No. WhatsApp" error={phoneForm.errors.phone} hint="Format 08xx. Kosongkan untuk menghapus nomor (tautan reset tidak bisa dikirim lagi).">
                            <Input inputMode="tel" value={phoneForm.data.phone} onChange={(event) => phoneForm.setData('phone', event.target.value)} placeholder="081234567890" />
                        </Field>
                        <Field label="Password saat ini" error={phoneForm.errors.current_password} hint="Diminta karena nomor ini menentukan ke mana tautan reset dikirim.">
                            <Input type="password" autoComplete="current-password" value={phoneForm.data.current_password} onChange={(event) => phoneForm.setData('current_password', event.target.value)} required />
                        </Field>
                        <Button type="submit" disabled={phoneForm.processing || phoneForm.data.phone === (account.phone ?? '')}>
                            <MessageCircle className="size-4" /> {phoneForm.processing ? 'Menyimpan...' : 'Simpan nomor'}
                        </Button>
                    </form>
                </Card>

                <Card>
                    <CardHeader title="Ganti password" description="Setelah diganti, sesi login Anda di perangkat lain diakhiri." />
                    <form onSubmit={submit} className="space-y-4 p-5">
                        <Field label="Password saat ini" error={form.errors.current_password}>
                            <Input type="password" autoComplete="current-password" value={form.data.current_password} onChange={(event) => form.setData('current_password', event.target.value)} required />
                        </Field>
                        <Field label="Password baru" error={form.errors.password} hint="Minimal 8 karakter dan berbeda dari password saat ini.">
                            <Input type="password" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required />
                        </Field>
                        <Field label="Ulangi password baru" error={form.errors.password_confirmation}>
                            <Input type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            <KeyRound className="size-4" /> {form.processing ? 'Menyimpan...' : 'Ganti password'}
                        </Button>
                    </form>
                </Card>
                </div>
            </div>
        </>
    );
}
