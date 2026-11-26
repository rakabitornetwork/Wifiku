import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { MessageCircle } from 'lucide-react';

import { VpnShell } from '@/Components/VpnShell';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Select } from '@/Components/ui/Field';
import { cn, formatDate, formatRupiah } from '@/lib/utils';

type Props = {
    company: string;
    account: { token: string; name: string; username: string; protocol: string; status: string; expires_at: string | null; monthly_price: number };
    invoices: { invoice_number: string; period_start: string; period_end: string; due_date: string; total: number; outstanding: number; status: string; is_overdue: boolean }[];
    outstanding: number;
    memberUrl: string | null;
    payment: { available: boolean; channels: string[] };
    whatsappUrl: string | null;
};

export default function VpnPortal({ company, account, invoices, outstanding, memberUrl, payment, whatsappUrl }: Props) {
    const active = account.status === 'active';
    const form = useForm({ invoice_number: invoices[0]?.invoice_number ?? '', channel: payment.channels[0] ?? '' });

    const pay = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/layanan-vpn/${account.token}/pay`, { preserveScroll: true });
    };

    const hero = (
        <>
            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-sm font-semibold">
                <span className={cn('size-2 rounded-full', active ? 'bg-emerald-400' : 'bg-rose-400')} /> {active ? 'Aktif' : 'Tidak aktif'}
            </span>
            <h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">{active ? 'VPN Tunnel Aktif' : 'VPN Tunnel Tidak Aktif'}</h1>
            <p className="mt-2 max-w-xl text-sm text-indigo-100/80">{active ? account.name : 'Masa berlaku akun telah habis. Tunnel tersambung kembali otomatis setelah pembayaran diterima.'}</p>
            <dl className="mt-7 grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 pt-6 lg:mt-10 lg:gap-5 lg:divide-x-0 lg:border-t-0 lg:pt-0">
                {[
                    ['Akun', account.username],
                    ['Protokol', account.protocol],
                    ['Berlaku s/d', account.expires_at ? formatDate(account.expires_at) : '-'],
                ].map(([label, value]) => (
                    <div key={label} className="min-w-0 px-4 first:pl-0 last:pr-0 lg:rounded-2xl lg:border lg:border-white/10 lg:bg-white/[0.06] lg:px-6 lg:py-5 lg:first:pl-6 lg:last:pr-6">
                        <dt className="text-xs whitespace-nowrap text-indigo-200/75 lg:text-sm">{label}</dt>
                        <dd className="num mt-1.5 truncate text-base font-semibold whitespace-nowrap sm:text-xl lg:text-2xl">{value}</dd>
                    </div>
                ))}
            </dl>
        </>
    );

    return (
        <VpnShell company={company} hero={hero} showAuthLinks={false}>
            <Head title="VPN Tunnel" />
            <div className="mx-auto max-w-5xl space-y-5 px-4 pb-4">
                <div className="grid items-start gap-5 lg:grid-cols-2">
                    <Card>
                        <CardHeader title="Tagihan" description={invoices.length ? `${invoices.length} invoice belum lunas` : undefined} />
                        {invoices.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-muted-foreground">Tidak ada tagihan. Tagihan perpanjangan akan terbit otomatis menjelang masa berlaku habis.</p>
                        ) : (
                            <>
                                <ul className="divide-y">
                                    {invoices.map((invoice) => (
                                        <li key={invoice.invoice_number} className="row-in flex items-center justify-between gap-3 px-5 py-3.5">
                                            <div className="min-w-0">
                                                <div className="num text-sm font-medium">{invoice.invoice_number}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                                </div>
                                                <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    Jatuh tempo {formatDate(invoice.due_date)}
                                                    {invoice.is_overdue && <Badge tone="red">Lewat tempo</Badge>}
                                                </div>
                                            </div>
                                            <div className="num shrink-0 text-right text-sm font-semibold">{formatRupiah(invoice.outstanding)}</div>
                                        </li>
                                    ))}
                                </ul>
                                <div className="flex items-center justify-between border-t bg-muted/40 px-5 py-3.5">
                                    <span className="text-sm font-medium">Total yang harus dibayar</span>
                                    <span className="num text-lg font-semibold">{formatRupiah(outstanding)}</span>
                                </div>
                            </>
                        )}
                    </Card>

                    {invoices.length > 0 && (
                        <Card>
                            <CardHeader title="Bayar sekarang" description="QRIS atau Virtual Account" />
                            {payment.available ? (
                                <form onSubmit={pay} className="space-y-3 p-5">
                                    {invoices.length > 1 && (
                                        <Field label="Invoice yang dibayar" error={form.errors.invoice_number}>
                                            <Select value={form.data.invoice_number} onChange={(event) => form.setData('invoice_number', event.target.value)}>
                                                {invoices.map((invoice) => (
                                                    <option key={invoice.invoice_number} value={invoice.invoice_number}>
                                                        {invoice.invoice_number} — {formatRupiah(invoice.outstanding)}
                                                    </option>
                                                ))}
                                            </Select>
                                        </Field>
                                    )}
                                    <Field label="Metode pembayaran" error={form.errors.channel}>
                                        <div className="flex flex-wrap gap-2">
                                            {payment.channels.map((channel) => (
                                                <button
                                                    key={channel}
                                                    type="button"
                                                    onClick={() => form.setData('channel', channel)}
                                                    className={cn(
                                                        'num rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                                                        form.data.channel === channel ? 'border-primary bg-accent text-accent-foreground' : 'hover:bg-muted',
                                                    )}
                                                >
                                                    {channel}
                                                </button>
                                            ))}
                                        </div>
                                    </Field>
                                    {form.errors.invoice_number && invoices.length <= 1 && <p className="text-xs text-destructive">{form.errors.invoice_number}</p>}
                                    <Button type="submit" className="w-full" disabled={form.processing || !form.data.channel}>
                                        {form.processing ? 'Menyiapkan pembayaran...' : 'Bayar sekarang'}
                                    </Button>
                                    <p className="text-center text-xs text-muted-foreground">
                                        Anda akan diarahkan ke halaman pembayaran resmi. Masa berlaku diperpanjang otomatis setelah pembayaran diterima.
                                    </p>
                                </form>
                            ) : (
                                <p className="p-5 text-center text-sm text-muted-foreground">Pembayaran online belum tersedia. Silakan hubungi CS untuk melakukan pembayaran.</p>
                            )}
                        </Card>
                    )}
                </div>

                {memberUrl && (
                    <a href={memberUrl} className="flex h-10 items-center justify-center rounded-xl border bg-card text-sm font-medium transition hover:bg-muted">
                        ← Kembali ke akun VPN saya
                    </a>
                )}

                {whatsappUrl && (
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <MessageCircle className="size-4" /> Hubungi CS via WhatsApp
                    </a>
                )}
            </div>
        </VpnShell>
    );
}
