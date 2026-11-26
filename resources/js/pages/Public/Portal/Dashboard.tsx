import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Copy, Cpu, Eye, EyeOff, KeyRound, MessageCircle, Receipt, RefreshCw, Wifi } from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { PortalShell } from '@/Components/PortalShell';
import { Badge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';
import { cn, formatDate, formatRupiah } from '@/lib/utils';

type Invoice = { invoice_number: string; period_start: string; period_end: string; due_date: string; total: number; outstanding: number; status: string; is_overdue: boolean; paid_at: string | null };
type Onu = { brand: string | null; model: string | null; serial_number: string; mac_address: string | null; installed_at: string | null };
type Live = {
    available: boolean;
    message: string | null;
    online: boolean | null;
    rx_power: number | null;
    rx_quality: 'good' | 'warning' | 'bad' | null;
    last_inform: string | null;
    ssid: string | null;
    wifi_password: string | null;
    devices: { hostname: string | null; ip_address: string | null; mac_address: string | null; interface: string | null }[];
};

type Props = {
    company: { name: string; logo: string | null };
    customer: { customer_code: string; name: string; package: string | null; address: string | null; status: string; installed_at: string | null; promise_to_pay_until: string | null };
    invoices: Invoice[];
    outstanding: number;
    payment: { available: boolean; channels: string[] };
    onu: Onu | null;
    hasMultipleAccounts: boolean;
    canChangeWifi: boolean;
    whatsappUrl: string | null;
};

const statusTone = { active: 'green', isolated: 'red', inactive: 'slate' } as const;
const statusLabel = { active: 'Aktif', isolated: 'Diisolir', inactive: 'Nonaktif' } as const;
const quality = {
    good: { label: 'Baik', className: 'text-emerald-600 dark:text-emerald-400' },
    warning: { label: 'Perlu dicek', className: 'text-amber-600 dark:text-amber-400' },
    bad: { label: 'Lemah', className: 'text-red-600 dark:text-red-400' },
} as const;

function Stat({ label, value, className }: { label: string; value: React.ReactNode; className?: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={cn('num mt-0.5 text-sm font-medium break-words', className)}>{value}</dd>
        </div>
    );
}

function WifiPassword({ password, loading }: { password: string | null | undefined; loading: boolean }) {
    const [visible, setVisible] = useState(false);
    const [copied, setCopied] = useState(false);

    if (loading && password === undefined) {
        return <span className="inline-block h-4 w-24 animate-pulse rounded bg-muted" />;
    }

    if (!password) {
        return <>-</>;
    }

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(password);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            setVisible(true);
        }
    };

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="select-all">{visible ? password : '•'.repeat(Math.min(password.length, 12))}</span>
            <button type="button" onClick={() => setVisible(!visible)} className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={visible ? 'Sembunyikan password WiFi' : 'Tampilkan password WiFi'}>
                {visible ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
            </button>
            <button type="button" onClick={() => void copy()} className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Salin password WiFi">
                {copied ? <Check className="size-3.5 text-emerald-600" /> : <Copy className="size-3.5" />}
            </button>
        </span>
    );
}

function BillingCard({ invoices, outstanding, payment }: Pick<Props, 'invoices' | 'outstanding' | 'payment'>) {
    const unpaid = invoices.filter((invoice) => invoice.status !== 'paid');
    const form = useForm({ invoice_number: unpaid[0]?.invoice_number ?? '', channel: payment.channels[0] ?? '' });

    const pay = (event: FormEvent) => {
        event.preventDefault();
        form.post('/pelanggan/bayar');
    };

    return (
        <Card className="p-5">
            <div className="flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 font-semibold">
                    <Receipt className="size-4 text-primary" /> Tagihan
                </h2>
                {outstanding > 0 ? <span className="num text-lg font-semibold text-amber-600 dark:text-amber-400">{formatRupiah(outstanding)}</span> : <Badge tone="green">Tidak ada tunggakan</Badge>}
            </div>

            {invoices.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">Belum ada tagihan.</p>
            ) : (
                <ul className="mt-4 divide-y">
                    {invoices.map((invoice) => (
                        <li key={invoice.invoice_number} className="flex items-center justify-between gap-3 py-3 text-sm">
                            <div className="min-w-0">
                                <div className="num font-medium">{invoice.invoice_number}</div>
                                <div className="text-xs text-muted-foreground">
                                    {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)} · {invoice.status === 'paid' ? `Lunas ${formatDate(invoice.paid_at)}` : `Jatuh tempo ${formatDate(invoice.due_date)}`}
                                </div>
                            </div>
                            <div className="shrink-0 text-right">
                                <div className="num font-semibold">{formatRupiah(invoice.status === 'paid' ? invoice.total : invoice.outstanding)}</div>
                                <Badge tone={invoice.status === 'paid' ? 'green' : invoice.is_overdue ? 'red' : 'amber'}>{invoice.status === 'paid' ? 'Lunas' : invoice.is_overdue ? 'Terlambat' : invoice.status === 'partial' ? 'Sebagian' : 'Belum bayar'}</Badge>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {unpaid.length > 0 && payment.available && (
                <form onSubmit={pay} className="mt-4 space-y-3 border-t pt-4">
                    {unpaid.length > 1 && (
                        <Field label="Bayar tagihan" error={form.errors.invoice_number}>
                            <select value={form.data.invoice_number} onChange={(event) => form.setData('invoice_number', event.target.value)} className="h-10 w-full rounded-lg border bg-background px-3 text-sm">
                                {unpaid.map((invoice) => (
                                    <option key={invoice.invoice_number} value={invoice.invoice_number}>
                                        {invoice.invoice_number} — {formatRupiah(invoice.outstanding)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Metode pembayaran" error={form.errors.channel}>
                        <div className="flex flex-wrap gap-2">
                            {payment.channels.map((channel) => (
                                <button key={channel} type="button" onClick={() => form.setData('channel', channel)} className={cn('rounded-lg border px-3 py-1.5 text-sm font-medium transition', form.data.channel === channel ? 'border-primary bg-accent text-accent-foreground' : 'hover:bg-muted')}>
                                    {channel}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <Button type="submit" className="w-full" disabled={form.processing || !form.data.channel}>
                        {form.processing ? 'Menyiapkan pembayaran...' : 'Bayar sekarang'}
                    </Button>
                </form>
            )}
        </Card>
    );
}

function OnuCard({ onu, live, loading, refresh }: { onu: Onu | null; live: Live | null; loading: boolean; refresh: () => void }) {
    return (
        <Card className="p-5">
            <div className="flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 font-semibold">
                    <Cpu className="size-4 text-primary" /> Status ONU
                </h2>
                {onu && (
                    <Button variant="ghost" size="sm" onClick={refresh} disabled={loading}>
                        <RefreshCw className={cn('size-3.5', loading && 'animate-spin')} /> Perbarui
                    </Button>
                )}
            </div>

            {!onu ? (
                <p className="mt-4 text-sm text-muted-foreground">Belum ada ONU yang tercatat untuk akun Anda. Hubungi CS bila ini keliru.</p>
            ) : (
                <>
                    <dl className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <Stat label="Perangkat" value={[onu.brand, onu.model].filter(Boolean).join(' ') || '-'} />
                        <Stat label="Serial number" value={onu.serial_number} />
                        <Stat label="MAC" value={onu.mac_address ?? '-'} />
                        <Stat label="Koneksi" value={loading && !live ? <span className="inline-block h-4 w-16 animate-pulse rounded bg-muted" /> : live?.online === null || live?.online === undefined ? '-' : <Badge tone={live.online ? 'green' : 'red'}>{live.online ? 'Online' : 'Offline'}</Badge>} />
                        <Stat
                            label="Redaman (RX)"
                            value={loading && !live ? <span className="inline-block h-4 w-16 animate-pulse rounded bg-muted" /> : live?.rx_power != null ? `${live.rx_power.toFixed(2)} dBm` : '-'}
                        />
                        <Stat label="Kualitas sinyal" value={live?.rx_quality ? quality[live.rx_quality].label : '-'} className={live?.rx_quality ? quality[live.rx_quality].className : undefined} />
                        <Stat label="Terakhir terlihat" value={live?.last_inform ? new Date(live.last_inform).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-'} />
                        <Stat label="Nama WiFi (SSID)" value={live?.ssid ?? '-'} />
                        <Stat label="Password WiFi" value={<WifiPassword password={live ? live.wifi_password : undefined} loading={loading} />} />
                        <Stat label="Terpasang sejak" value={formatDate(onu.installed_at)} />
                    </dl>

                    {live?.message && <p className="mt-4 rounded-lg bg-muted p-3 text-sm text-muted-foreground">{live.message}</p>}

                    {live && live.devices.length > 0 && (
                        <div className="mt-5">
                            <h3 className="text-sm font-medium">Perangkat terhubung ({live.devices.length})</h3>
                            <ul className="mt-2 divide-y rounded-lg border text-sm">
                                {live.devices.map((device, index) => (
                                    <li key={`${device.mac_address}-${index}`} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-0.5 px-3 py-2">
                                        <span className="font-medium">{device.hostname ?? 'Perangkat tanpa nama'}</span>
                                        <span className="num text-xs text-muted-foreground">{[device.ip_address, device.mac_address].filter(Boolean).join(' · ')}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </>
            )}
        </Card>
    );
}

function WifiCard({ currentSsid }: { currentSsid: string | null }) {
    const form = useForm({ ssid: '', password: '', password_confirmation: '' });

    useEffect(() => {
        if (currentSsid && form.data.ssid === '') {
            form.setData('ssid', currentSsid);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentSsid]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/pelanggan/wifi', { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <Card className="p-5">
            <h2 className="flex items-center gap-2 font-semibold">
                <KeyRound className="size-4 text-primary" /> Ganti nama dan password WiFi
            </h2>
            <p className="mt-2 rounded-lg bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">Setelah disimpan, semua perangkat yang tersambung akan terputus dan harus menyambung lagi dengan nama dan password baru. Catat password baru Anda; password yang sedang aktif bisa dilihat di kartu Status ONU.</p>
            <form onSubmit={submit} className="mt-4 grid gap-4 sm:grid-cols-2">
                <Field label="Nama WiFi (SSID)" error={form.errors.ssid} hint="Maksimal 32 karakter." className="sm:col-span-2">
                    <Input value={form.data.ssid} onChange={(event) => form.setData('ssid', event.target.value)} maxLength={32} required />
                </Field>
                <Field label="Password baru" error={form.errors.password} hint="8–63 karakter.">
                    <Input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} minLength={8} maxLength={63} autoComplete="new-password" required />
                </Field>
                <Field label="Ulangi password baru" error={form.errors.password_confirmation}>
                    <Input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} minLength={8} maxLength={63} autoComplete="new-password" required />
                </Field>
                <Button type="submit" className="sm:col-span-2" disabled={form.processing}>
                    {form.processing ? 'Mengirim ke ONU...' : 'Simpan pengaturan WiFi'}
                </Button>
            </form>
        </Card>
    );
}

export default function Dashboard({ company, customer, invoices, outstanding, payment, onu, hasMultipleAccounts, canChangeWifi, whatsappUrl }: Props) {
    const [live, setLive] = useState<Live | null>(null);
    const [loading, setLoading] = useState(false);

    const load = useCallback(async () => {
        if (!onu) {
            return;
        }

        setLoading(true);

        try {
            const response = await fetch('/pelanggan/onu', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (response.status === 401) {
                router.visit('/pelanggan/masuk');

                return;
            }

            setLive(response.ok ? await response.json() : { available: false, message: 'Status ONU belum dapat dimuat. Coba lagi.', online: null, rx_power: null, rx_quality: null, last_inform: null, ssid: null, wifi_password: null, devices: [] });
        } catch {
            setLive({ available: false, message: 'Koneksi terputus saat memuat status ONU.', online: null, rx_power: null, rx_quality: null, last_inform: null, ssid: null, wifi_password: null, devices: [] });
        } finally {
            setLoading(false);
        }
    }, [onu]);

    useEffect(() => {
        void load();
    }, [load]);

    return (
        <PortalShell company={company} loggedIn>
            <Head title="Portal Pelanggan" />
            <div className="mx-auto w-full max-w-5xl space-y-5 px-4 py-8">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Halo, {customer.name}</h1>
                        <p className="num mt-1 text-sm text-muted-foreground">
                            {customer.customer_code}
                            {customer.package && ` · Paket ${customer.package}`}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasMultipleAccounts && (
                            <Link href="/pelanggan/pilih" className={buttonClass('outline', 'sm')}>
                                Ganti sambungan
                            </Link>
                        )}
                        <Badge tone={statusTone[customer.status as keyof typeof statusTone] ?? 'slate'}>{statusLabel[customer.status as keyof typeof statusLabel] ?? customer.status}</Badge>
                    </div>
                </div>

                {customer.status === 'isolated' && (
                    <Card className="border-red-500/40 bg-red-500/10 p-4 text-sm">
                        Layanan Anda sedang diisolir karena tagihan belum dibayar. Selesaikan pembayaran di bawah; layanan aktif kembali otomatis setelah pembayaran diterima.
                        {customer.promise_to_pay_until && <span className="block text-xs text-muted-foreground">Janji bayar sampai {formatDate(customer.promise_to_pay_until)}.</span>}
                    </Card>
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    <BillingCard invoices={invoices} outstanding={outstanding} payment={payment} />
                    <OnuCard onu={onu} live={live} loading={loading} refresh={() => void load()} />
                </div>

                {canChangeWifi ? <WifiCard currentSsid={live?.ssid ?? null} /> : onu && customer.status !== 'active' ? (
                    <Card className="p-5 text-sm text-muted-foreground">
                        <Wifi className="mr-2 inline size-4" /> Pengaturan WiFi hanya tersedia saat layanan aktif.
                    </Card>
                ) : null}

                {whatsappUrl && (
                    <div className="text-center">
                        <a href={whatsappUrl} target="_blank" rel="noreferrer" className={buttonClass('outline', 'md')}>
                            <MessageCircle className="size-4" /> Hubungi CS via WhatsApp
                        </a>
                    </div>
                )}
            </div>
        </PortalShell>
    );
}
