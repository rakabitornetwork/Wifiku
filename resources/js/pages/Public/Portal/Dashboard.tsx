import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, Copy, Eye, EyeOff, MessageCircle, RefreshCw, Smartphone } from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { PortalShell, panel } from '@/Components/PortalShell';
import { Button } from '@/Components/ui/Button';
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

const statusLabel = { active: 'Aktif', isolated: 'Diisolir', inactive: 'Nonaktif' } as const;
const statusDot = { active: 'bg-emerald-400', isolated: 'bg-rose-400', inactive: 'bg-slate-400' } as const;

const quality = {
    good: { label: 'Baik', text: 'text-emerald-600 dark:text-emerald-400', onDark: 'text-emerald-300' },
    warning: { label: 'Perlu dicek', text: 'text-amber-600 dark:text-amber-400', onDark: 'text-amber-300' },
    bad: { label: 'Lemah', text: 'text-rose-600 dark:text-rose-400', onDark: 'text-rose-300' },
} as const;

const SKELETON = <span className="inline-block h-6 w-20 animate-pulse rounded bg-white/15" />;

/** Rentang yang digambar pada meter redaman (dBm): lemah sekali di kiri, terlalu kuat di kanan. */
const METER_MIN = -35;
const METER_MAX = -5;

function SignalMeter({ rx, quality: grade }: { rx: number | null; quality: 'good' | 'warning' | 'bad' | null }) {
    const position = rx === null ? null : Math.min(100, Math.max(0, ((rx - METER_MIN) / (METER_MAX - METER_MIN)) * 100));

    return (
        <div>
            <div className="relative h-2.5 overflow-visible rounded-full">
                <div className="absolute inset-0 flex overflow-hidden rounded-full">
                    <div className="bg-rose-500" style={{ width: '23.3%' }} />
                    <div className="bg-amber-400" style={{ width: '10%' }} />
                    <div className="bg-emerald-500" style={{ width: '56.7%' }} />
                    <div className="bg-amber-400" style={{ width: '10%' }} />
                </div>
                {position !== null && (
                    <span
                        className={cn('absolute top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white bg-slate-900 shadow-md dark:border-slate-900 dark:bg-white', grade === 'bad' && 'bg-rose-600 dark:bg-rose-400')}
                        style={{ left: `${position}%` }}
                    />
                )}
            </div>
            <div className="num mt-2 flex justify-between text-[11px] text-muted-foreground">
                <span>{METER_MIN}</span>
                <span>-28</span>
                <span>-25</span>
                <span>-8</span>
                <span>{METER_MAX} dBm</span>
            </div>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="num mt-1 overflow-x-auto text-sm font-semibold whitespace-nowrap">{value}</dd>
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
        <Card className={cn('p-6', panel)}>
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-base font-semibold">Tagihan</h2>
                {outstanding > 0 ? <span className="num text-xl font-semibold text-amber-600 dark:text-amber-400">{formatRupiah(outstanding)}</span> : <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Tidak ada tunggakan</span>}
            </div>

            {invoices.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">Belum ada tagihan.</p>
            ) : (
                <ul className="mt-4 divide-y divide-slate-100 dark:divide-white/10">
                    {invoices.map((invoice) => {
                        const paid = invoice.status === 'paid';
                        const tone = paid ? 'text-emerald-600 dark:text-emerald-400' : invoice.is_overdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400';

                        return (
                            <li key={invoice.invoice_number} className="row-in flex items-center justify-between gap-4 py-3.5 text-sm">
                                <div className="min-w-0">
                                    <div className="num truncate font-semibold">{invoice.invoice_number}</div>
                                    <div className="mt-0.5 text-xs whitespace-nowrap text-muted-foreground">
                                        {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <div className="num font-semibold">{formatRupiah(paid ? invoice.total : invoice.outstanding)}</div>
                                    <div className={cn('mt-0.5 text-xs font-medium whitespace-nowrap', tone)}>
                                        {paid ? `Lunas ${formatDate(invoice.paid_at)}` : invoice.is_overdue ? `Terlambat sejak ${formatDate(invoice.due_date)}` : `Jatuh tempo ${formatDate(invoice.due_date)}`}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {unpaid.length > 0 && payment.available && (
                <form onSubmit={pay} className="mt-3 space-y-4 border-t border-slate-100 pt-5 dark:border-white/10">
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
                                <button
                                    key={channel}
                                    type="button"
                                    onClick={() => form.setData('channel', channel)}
                                    className={cn('rounded-lg border px-3.5 py-2 text-sm font-semibold transition', form.data.channel === channel ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm' : 'border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 dark:border-white/15 dark:hover:bg-white/5')}
                                >
                                    {channel}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <Button type="submit" className="h-11 w-full bg-indigo-600 text-white hover:bg-indigo-700 hover:opacity-100" disabled={form.processing || !form.data.channel}>
                        {form.processing ? 'Menyiapkan pembayaran...' : `Bayar ${formatRupiah(outstanding)}`}
                    </Button>
                </form>
            )}
        </Card>
    );
}

function OnuCard({ onu, live, loading, refresh }: { onu: Onu | null; live: Live | null; loading: boolean; refresh: () => void }) {
    const grade = live?.rx_quality ?? null;

    return (
        <Card className={cn('p-6', panel)}>
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-base font-semibold">Status ONU</h2>
                {onu && (
                    <Button variant="ghost" size="sm" onClick={refresh} disabled={loading} className="-mr-2 text-muted-foreground">
                        <RefreshCw className={cn('size-3.5', loading && 'animate-spin')} /> Perbarui
                    </Button>
                )}
            </div>

            {!onu ? (
                <p className="mt-4 text-sm text-muted-foreground">Belum ada ONU yang tercatat untuk akun Anda. Hubungi CS bila ini keliru.</p>
            ) : (
                <>
                    <div className="mt-5">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <div className="text-xs text-muted-foreground">Redaman optik (RX)</div>
                                <div className={cn('num mt-1 text-3xl font-semibold tracking-tight whitespace-nowrap', grade ? quality[grade].text : 'text-foreground')}>
                                    {loading && !live ? <span className="inline-block h-8 w-28 animate-pulse rounded bg-muted" /> : live?.rx_power != null ? `${live.rx_power.toFixed(2)} dBm` : '-'}
                                </div>
                            </div>
                            {grade && <span className={cn('rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap', grade === 'good' ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300' : grade === 'warning' ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-rose-500/12 text-rose-700 dark:text-rose-300')}>{quality[grade].label}</span>}
                        </div>
                        <div className="mt-4">
                            <SignalMeter rx={live?.rx_power ?? null} quality={grade} />
                        </div>
                    </div>

                    <dl className="mt-6 grid gap-x-6 gap-y-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10">
                        <Detail label="Perangkat" value={[onu.brand, onu.model].filter(Boolean).join(' ') || '-'} />
                        <Detail label="Terakhir terlihat" value={live?.last_inform ? new Date(live.last_inform).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'} />
                        <Detail label="Serial number" value={onu.serial_number} />
                        <Detail label="MAC" value={onu.mac_address ?? '-'} />
                        <Detail label="Nama WiFi (SSID)" value={live?.ssid ?? '-'} />
                        <Detail label="Password WiFi" value={<WifiPassword password={live ? live.wifi_password : undefined} loading={loading} />} />
                        <Detail label="Terpasang sejak" value={formatDate(onu.installed_at)} />
                    </dl>

                    {live?.message && <p className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">{live.message}</p>}

                    {live && live.devices.length > 0 && (
                        <div className="mt-6 border-t border-slate-100 pt-5 dark:border-white/10">
                            <h3 className="text-sm font-semibold">Perangkat terhubung ({live.devices.length})</h3>
                            <ul className="mt-3 divide-y divide-slate-100 text-sm dark:divide-white/10">
                                {live.devices.map((device, index) => (
                                    <li key={`${device.mac_address}-${index}`} className="row-in flex flex-wrap items-center justify-between gap-x-4 gap-y-0.5 py-2.5">
                                        <span className="flex items-center gap-2 font-medium">
                                            <Smartphone className="size-4 text-indigo-500" /> {device.hostname ?? 'Perangkat tanpa nama'}
                                        </span>
                                        <span className="num text-xs whitespace-nowrap text-muted-foreground">{[device.ip_address, device.mac_address].filter(Boolean).join(' · ')}</span>
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
        <Card className={cn('p-6', panel)}>
            <h2 className="text-base font-semibold">Ganti nama dan password WiFi</h2>
            <p className="mt-3 flex items-start gap-2.5 rounded-lg border border-amber-300/70 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                <span>Setelah disimpan, semua perangkat yang tersambung akan terputus dan harus menyambung lagi dengan nama dan password baru. Catat password baru Anda; password yang sedang aktif bisa dilihat di kartu Status ONU.</span>
            </p>
            <form onSubmit={submit} className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                <Field label="Nama WiFi (SSID)" error={form.errors.ssid} hint="Maksimal 32 karakter." className="sm:col-span-2 lg:col-span-1">
                    <Input value={form.data.ssid} onChange={(event) => form.setData('ssid', event.target.value)} maxLength={32} required />
                </Field>
                <Field label="Password baru" error={form.errors.password} hint="8–63 karakter.">
                    <Input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} minLength={8} maxLength={63} autoComplete="new-password" required />
                </Field>
                <Field label="Ulangi password baru" error={form.errors.password_confirmation}>
                    <Input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} minLength={8} maxLength={63} autoComplete="new-password" required />
                </Field>
                <Button type="submit" className="h-11 bg-indigo-600 text-white hover:bg-indigo-700 hover:opacity-100 sm:col-span-2 lg:col-span-1" disabled={form.processing}>
                    {form.processing ? 'Mengirim ke ONU...' : 'Simpan pengaturan WiFi'}
                </Button>
            </form>
        </Card>
    );
}

function HeroStat({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="min-w-0 px-4 first:pl-0 last:pr-0 lg:rounded-2xl lg:border lg:border-white/10 lg:bg-white/[0.06] lg:px-6 lg:py-5 lg:first:pl-6 lg:last:pr-6">
            <div className="text-xs whitespace-nowrap text-indigo-200/75 lg:text-sm">{label}</div>
            <div className="num mt-1.5 truncate text-lg font-semibold whitespace-nowrap sm:text-2xl lg:mt-2 lg:text-3xl">{children}</div>
        </div>
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
                router.visit('/portal');

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

    const grade = live?.rx_quality ?? null;
    const status = customer.status as keyof typeof statusLabel;

    const hero = (
        <>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm text-indigo-200/80">Selamat datang,</p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">{customer.name}</h1>
                    <p className="num mt-2 text-sm text-indigo-100/80 lg:mt-3 lg:text-base">
                        {customer.customer_code}
                        {customer.package && ` · Paket ${customer.package}`}
                    </p>
                </div>
                <div className="flex items-center gap-2.5">
                    {hasMultipleAccounts && (
                        <Link href="/pelanggan/pilih" className="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-white transition hover:bg-white/10">
                            Ganti sambungan
                        </Link>
                    )}
                    <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-sm font-semibold">
                        <span className={cn('size-2 rounded-full', statusDot[status] ?? 'bg-slate-400')} /> {statusLabel[status] ?? customer.status}
                    </span>
                </div>
            </div>

            <div className="mt-7 grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 pt-6 lg:mt-10 lg:gap-5 lg:divide-x-0 lg:border-t-0 lg:pt-0">
                <HeroStat label="Tagihan">{outstanding > 0 ? <span className="text-amber-300">{formatRupiah(outstanding)}</span> : <span className="text-emerald-300">Lunas</span>}</HeroStat>
                <HeroStat label="Koneksi">
                    {!onu ? '-' : loading && !live ? SKELETON : live?.online == null ? '-' : <span className={live.online ? 'text-emerald-300' : 'text-rose-300'}>{live.online ? 'Online' : 'Offline'}</span>}
                </HeroStat>
                <HeroStat label="Redaman">
                    {!onu ? '-' : loading && !live ? SKELETON : live?.rx_power != null ? <span className={grade ? quality[grade].onDark : undefined}>{live.rx_power.toFixed(1)} dBm</span> : '-'}
                </HeroStat>
            </div>
        </>
    );

    return (
        <PortalShell company={company} loggedIn hero={hero}>
            <Head title="Portal Pelanggan" />
            <div className="mx-auto w-full max-w-6xl space-y-5 px-4 pb-4 lg:space-y-6">
                {customer.status === 'isolated' && (
                    <div className="flex items-start gap-3 rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900 shadow-sm dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-100">
                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-300" />
                        <div>
                            <p className="font-semibold">Layanan Anda sedang diisolir</p>
                            <p className="mt-0.5">Selesaikan pembayaran di bawah; layanan aktif kembali otomatis setelah pembayaran diterima.</p>
                            {customer.promise_to_pay_until && <p className="mt-1 text-xs opacity-80">Janji bayar sampai {formatDate(customer.promise_to_pay_until)}.</p>}
                        </div>
                    </div>
                )}

                {/* Seluler: Tagihan, Status ONU, lalu WiFi berurutan. Desktop: Tagihan + WiFi menumpuk di kiri, Status ONU memanjang di kanan. */}
                <div className="grid items-start gap-5 lg:grid-cols-[5fr_7fr] lg:gap-6">
                    <div className="lg:col-start-1 lg:row-start-1">
                        <BillingCard invoices={invoices} outstanding={outstanding} payment={payment} />
                    </div>
                    <div className="lg:col-start-2 lg:row-span-2 lg:row-start-1">
                        <OnuCard onu={onu} live={live} loading={loading} refresh={() => void load()} />
                    </div>
                    {canChangeWifi ? (
                        <div className="lg:col-start-1 lg:row-start-2">
                            <WifiCard currentSsid={live?.ssid ?? null} />
                        </div>
                    ) : onu && customer.status !== 'active' ? (
                        <Card className={cn('p-5 text-sm text-muted-foreground lg:col-start-1 lg:row-start-2', panel)}>Pengaturan WiFi hanya tersedia saat layanan aktif.</Card>
                    ) : null}
                </div>

                {whatsappUrl && (
                    <div className="pt-2 text-center">
                        <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#1fa855] px-6 text-sm font-semibold text-white shadow-sm shadow-emerald-600/30 transition hover:bg-[#188f47]">
                            <MessageCircle className="size-4" /> Hubungi CS via WhatsApp
                        </a>
                    </div>
                )}
            </div>
        </PortalShell>
    );
}
