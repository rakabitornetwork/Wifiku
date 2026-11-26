import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Receipt, Wallet } from 'lucide-react';
import { useState } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { Badge, StatusBadge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card, CardHeader, StatCard } from '@/Components/ui/Card';
import { Field } from '@/Components/ui/Field';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { cn, formatDate, formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';

type InvoiceRow = {
    invoice_number: string;
    account: string | null;
    period_start: string;
    period_end: string;
    due_date: string;
    total: number;
    outstanding: number;
    status: string;
    is_overdue: boolean;
    checkout_url: string | null;
    paid_at: string | null;
    paid_via: string | null;
};

type Props = {
    company: string;
    member: { name: string; email: string };
    open: InvoiceRow[];
    outstanding: number;
    history: Paginated<InvoiceRow>;
    payment: { available: boolean; channels: string[] };
};

export default function Billing({ company, member, open, outstanding, history, payment }: Props) {
    const [channel, setChannel] = useState(payment.channels[0] ?? '');
    const [paying, setPaying] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const pay = (invoiceNumber: string) =>
        router.post(
            '/vpn-tunnel/tagihan/bayar',
            { invoice_number: invoiceNumber, channel },
            {
                preserveScroll: true,
                onStart: () => {
                    setPaying(invoiceNumber);
                    setError(null);
                },
                onError: (errors) => setError(Object.values(errors)[0] ?? 'Pembayaran tidak dapat diproses.'),
                onFinish: () => setPaying(null),
            },
        );

    return (
        <VpnShell
            company={company}
            member
            hero={
                <div>
                    <p className="text-sm text-indigo-200/80">{member.name}</p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl">Tagihan &amp; pembayaran</h1>
                    <p className="mt-2 text-sm text-indigo-100/80">{member.email}</p>
                </div>
            }
        >
            <Head title="Tagihan VPN" />
            <div className="mx-auto max-w-5xl space-y-5 px-4 pb-4">

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Belum dibayar" value={formatRupiah(outstanding)} hint={open.length > 0 ? `${open.length} tagihan` : 'Tidak ada tagihan'} icon={<Wallet className="size-4" />} tone={open.length > 0 ? 'amber' : 'green'} />
                    <StatCard tone="blue" label="Riwayat tagihan" value={String(history.total)} hint="lunas atau dibatalkan" icon={<CheckCircle2 className="size-4" />} />
                </div>

                <Card>
                    <CardHeader title="Tagihan belum dibayar" description="Bayar lewat QRIS atau Virtual Account. Akun aktif atau diperpanjang otomatis setelah pembayaran diterima." />
                    {open.length === 0 ? (
                        <p className="px-5 py-10 text-center text-sm text-muted-foreground">Tidak ada tagihan yang menunggu. Tagihan perpanjangan terbit otomatis menjelang masa berlaku habis.</p>
                    ) : (
                        <>
                            {payment.available ? (
                                <div className="border-b px-5 py-3">
                                    <Field label="Metode pembayaran">
                                        <div className="flex flex-wrap gap-2">
                                            {payment.channels.map((item) => (
                                                <button
                                                    key={item}
                                                    type="button"
                                                    onClick={() => setChannel(item)}
                                                    className={cn('num rounded-lg border px-3 py-1.5 text-xs font-medium transition', channel === item ? 'border-primary bg-accent text-accent-foreground' : 'hover:bg-muted')}
                                                >
                                                    {item}
                                                </button>
                                            ))}
                                        </div>
                                    </Field>
                                </div>
                            ) : (
                                <p className="border-b bg-amber-500/10 px-5 py-3 text-sm">Pembayaran online belum tersedia. Silakan hubungi CS untuk membayar.</p>
                            )}
                            {error && <p className="border-b bg-destructive/10 px-5 py-2 text-sm text-destructive">{error}</p>}
                            <ul className="divide-y">
                                {open.map((invoice) => (
                                    <li key={invoice.invoice_number} className="row-in flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="num text-sm font-medium">{invoice.invoice_number}</span>
                                                {invoice.is_overdue && <Badge tone="red">Lewat tempo</Badge>}
                                                {invoice.status === 'partial' && <StatusBadge status="partial" />}
                                            </div>
                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                {invoice.account} · {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                            </div>
                                            <div className="text-xs text-muted-foreground">Jatuh tempo {formatDate(invoice.due_date)}</div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="num text-base font-semibold">{formatRupiah(invoice.outstanding)}</span>
                                            {invoice.checkout_url && (
                                                <a href={invoice.checkout_url} className={buttonClass('outline', 'sm')} title="Transaksi sebelumnya masih berlaku">
                                                    <ExternalLink className="size-3.5" /> Lanjutkan
                                                </a>
                                            )}
                                            <Button size="sm" onClick={() => pay(invoice.invoice_number)} disabled={!payment.available || !channel || paying !== null}>
                                                {paying === invoice.invoice_number ? 'Menyiapkan...' : 'Bayar'}
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <div className="flex items-center justify-between border-t bg-muted/40 px-5 py-3.5">
                                <span className="text-sm font-medium">Total belum dibayar</span>
                                <span className="num text-lg font-semibold">{formatRupiah(outstanding)}</span>
                            </div>
                        </>
                    )}
                </Card>

                <Card>
                    <CardHeader title="Riwayat tagihan" action={<Receipt className="size-4 text-muted-foreground" />} />
                    {history.data.length === 0 ? (
                        <EmptyState title="Belum ada riwayat" description="Tagihan yang sudah dibayar akan tercatat di sini." />
                    ) : (
                        <TableWrap>
                            <Table>
                                <thead>
                                    <tr>
                                        <Th>No. invoice</Th>
                                        <Th>Akun</Th>
                                        <Th>Periode</Th>
                                        <Th>Total</Th>
                                        <Th>Dibayar</Th>
                                        <Th>Status</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.data.map((invoice) => (
                                        <tr key={invoice.invoice_number} className="hover:bg-muted/40">
                                            <Td className="num font-medium whitespace-nowrap">{invoice.invoice_number}</Td>
                                            <Td>{invoice.account}</Td>
                                            <Td className="whitespace-nowrap text-muted-foreground">
                                                {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                            </Td>
                                            <Td className="num text-right whitespace-nowrap">{formatRupiah(invoice.total)}</Td>
                                            <Td className="whitespace-nowrap text-muted-foreground">
                                                {invoice.paid_at ? formatDate(invoice.paid_at) : '-'}
                                                {invoice.paid_via && <span className="ml-1.5 text-xs">({invoice.paid_via})</span>}
                                            </Td>
                                            <Td>
                                                <StatusBadge status={invoice.status} />
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </TableWrap>
                    )}
                    <Pagination meta={history} />
                </Card>

                <p className="text-center text-xs text-muted-foreground">
                    Kembali ke{' '}
                    <Link href="/vpn-tunnel/akun" className="font-medium text-primary hover:underline">
                        akun VPN saya
                    </Link>
                </p>
            </div>
        </VpnShell>
    );
}
