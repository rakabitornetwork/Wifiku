import { PaymentChannelPicker, type PaymentChannel } from '@/Components/PaymentChannelPicker';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { MessageCircle } from 'lucide-react';

import { useBrand } from '@/Components/BrandLogo';
import { PortalShell, panel } from '@/Components/PortalShell';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Select } from '@/Components/ui/Field';
import { cn, formatDate, formatRupiah } from '@/lib/utils';

type Props = {
    company: string;
    title: string;
    message: string | null;
    customer: { customer_code: string; name: string; package: string; status: string };
    invoices: { invoice_number: string; period_start: string; period_end: string; due_date: string; total: number; outstanding: number; status: string; is_overdue: boolean }[];
    outstanding: number;
    payment: { available: boolean; channels: PaymentChannel[] };
    whatsappUrl: string | null;
};

export default function Isolir({ title, message, customer, invoices, outstanding, payment, whatsappUrl }: Props) {
    const brand = useBrand();
    const isolated = customer.status === 'isolated';
    const form = useForm({ invoice_number: invoices[0]?.invoice_number ?? '', channel: payment.channels[0]?.code ?? '' });

    const pay = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/isolir/${customer.customer_code}/pay`, { preserveScroll: true });
    };

    const hero = (
        <>
            <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-sm font-semibold">
                <span className={cn('size-2 rounded-full', isolated ? 'bg-rose-400' : 'bg-emerald-400')} /> {isolated ? 'Diisolir' : 'Aktif'}
            </span>
            <h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">{isolated ? title : 'Layanan Anda Aktif'}</h1>
            {isolated && message && <p className="mt-2 max-w-2xl text-sm leading-relaxed text-indigo-100/80">{message}</p>}
            <dl className="mt-7 grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 pt-6 lg:mt-10 lg:gap-5 lg:divide-x-0 lg:border-t-0 lg:pt-0">
                {[
                    ['ID Pelanggan', customer.customer_code],
                    ['Nama', customer.name],
                    ['Paket', customer.package],
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
        <PortalShell company={brand} hero={hero}>
            <Head title={isolated ? 'Layanan Diisolir' : 'Status Layanan'} />
            <div className="mx-auto max-w-6xl space-y-5 px-4 pb-4">
                <div className="grid items-start gap-5 lg:grid-cols-2 lg:gap-6">
                    <Card className={panel}>
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
                        <Card className={panel}>
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
                                        <PaymentChannelPicker channels={payment.channels} value={form.data.channel} onChange={(code) => form.setData('channel', code)} activeClass="border-indigo-600 bg-indigo-600 text-white shadow-sm" />
                                    </Field>
                                    {form.errors.invoice_number && invoices.length <= 1 && <p className="text-xs text-destructive">{form.errors.invoice_number}</p>}
                                    <Button type="submit" className="h-11 w-full bg-indigo-600 text-white hover:bg-indigo-700 hover:opacity-100" disabled={form.processing || !form.data.channel}>
                                        {form.processing ? 'Menyiapkan pembayaran...' : 'Bayar sekarang'}
                                    </Button>
                                    <p className="text-center text-xs text-muted-foreground">
                                        Anda akan diarahkan ke halaman pembayaran resmi. Layanan aktif kembali otomatis setelah pembayaran diterima.
                                    </p>
                                </form>
                            ) : (
                                <p className="p-5 text-center text-sm text-muted-foreground">Pembayaran online belum tersedia. Silakan hubungi CS untuk melakukan pembayaran.</p>
                            )}
                        </Card>
                    )}
                </div>

                {whatsappUrl && (
                    <div className="pt-2 text-center">
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#1fa855] px-6 text-sm font-semibold text-white shadow-sm shadow-emerald-600/30 transition hover:bg-[#188f47]"
                        >
                            <MessageCircle className="size-4" /> Hubungi CS via WhatsApp
                        </a>
                    </div>
                )}
            </div>
        </PortalShell>
    );
}
