import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { AlertTriangle, CheckCircle2, MessageCircle } from 'lucide-react';

import { BrandLogo } from '@/Components/BrandLogo';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Select } from '@/Components/ui/Field';
import { Toaster } from '@/Components/Toaster';
import { useTheme } from '@/Hooks/useTheme';
import { cn, formatDate, formatRupiah } from '@/lib/utils';

type Props = {
    company: string;
    title: string;
    message: string | null;
    customer: { customer_code: string; name: string; package: string; status: string };
    invoices: { invoice_number: string; period_start: string; period_end: string; due_date: string; total: number; outstanding: number; status: string; is_overdue: boolean }[];
    outstanding: number;
    payment: { available: boolean; channels: string[] };
    whatsappUrl: string | null;
};

export default function Isolir({ company, title, message, customer, invoices, outstanding, payment, whatsappUrl }: Props) {
    useTheme();
    const isolated = customer.status === 'isolated';
    const form = useForm({ invoice_number: invoices[0]?.invoice_number ?? '', channel: payment.channels[0] ?? '' });

    const pay = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/isolir/${customer.customer_code}/pay`, { preserveScroll: true });
    };

    return (
        <div className="min-h-screen bg-[radial-gradient(60%_40%_at_50%_0%,color-mix(in_oklab,var(--primary)_12%,transparent),transparent)] px-4 py-8">
            <Head title={isolated ? 'Layanan Diisolir' : 'Status Layanan'} />
            <div className="mx-auto max-w-lg space-y-4">
                <div className="flex items-center justify-center gap-2.5 text-sm font-semibold">
                    <BrandLogo size="sm" />
                    {company}
                </div>

                <Card className={cn('p-5 text-center', isolated ? 'border-destructive/40' : 'border-emerald-500/40')}>
                    <div className={cn('mx-auto mb-3 flex size-12 items-center justify-center rounded-full', isolated ? 'bg-destructive/12 text-destructive' : 'bg-emerald-500/12 text-emerald-600')}>
                        {isolated ? <AlertTriangle className="size-6" /> : <CheckCircle2 className="size-6" />}
                    </div>
                    <h1 className="text-lg font-semibold tracking-tight">{isolated ? title : 'Layanan Anda Aktif'}</h1>
                    {isolated && message && <p className="mt-2 text-sm text-muted-foreground">{message}</p>}
                    <dl className="mt-4 grid grid-cols-3 gap-2 border-t pt-4 text-left text-xs">
                        <div>
                            <dt className="text-muted-foreground">ID Pelanggan</dt>
                            <dd className="num mt-0.5 font-medium">{customer.customer_code}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Nama</dt>
                            <dd className="mt-0.5 font-medium">{customer.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Paket</dt>
                            <dd className="mt-0.5 font-medium">{customer.package}</dd>
                        </div>
                    </dl>
                </Card>

                <Card>
                    <CardHeader title="Tagihan tertunggak" description={invoices.length ? `${invoices.length} invoice belum lunas` : undefined} />
                    {invoices.length === 0 ? (
                        <p className="px-5 py-8 text-center text-sm text-muted-foreground">Tidak ada tagihan tertunggak.</p>
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
                                <p className="text-center text-xs text-muted-foreground">Anda akan diarahkan ke halaman pembayaran resmi. Layanan aktif kembali otomatis setelah pembayaran diterima.</p>
                            </form>
                        ) : (
                            <p className="p-5 text-center text-sm text-muted-foreground">Pembayaran online belum tersedia. Silakan hubungi CS untuk melakukan pembayaran.</p>
                        )}
                    </Card>
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
            <Toaster />
        </div>
    );
}
