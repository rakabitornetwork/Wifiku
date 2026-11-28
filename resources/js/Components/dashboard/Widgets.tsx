import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowDownRight, ArrowUpRight, CalendarClock, CheckCircle2, ChevronRight, Megaphone, MessageSquareWarning, PhoneOff, Radio, Ticket, UserPlus, WifiOff, type LucideIcon } from 'lucide-react';

import { Card, CardHeader } from '@/Components/ui/Card';
import { cn, formatRupiah } from '@/lib/utils';

export type Widgets = {
    finance?: { trend: { month: string; revenue: number; expenses: number; net_profit: number }[]; current: { revenue: number; expenses: number; net_profit: number }; revenue_change: number | null };
    collection?: { billed: number; collected: number; invoices: number; paid_invoices: number; percent: number };
    overdue?: { count: number; total: number; items: { invoice_number: string; customer: string; customer_code: string; outstanding: number; days_late: number }[] };
    upcoming?: { count: number; total: number; items: { invoice_number: string; customer: string; outstanding: number; due_in_days: number }[] };
    routers_offline?: { total: number; offline: number; items: { name: string; host: string; offline_since: string | null; error: string | null }[] };
    voucher_today?: { count: number; revenue: number; yesterday_revenue: number; revenue_change: number | null; top_profiles: { name: string; count: number }[] };
    weak_signal?: { count: number; critical: number; items: { customer: string; serial_number: string; rx_power: number; level: 'warning' | 'critical' }[] };
    attention?: { key: string; label: string; value: number; tone: 'red' | 'amber' | 'blue' | 'green'; href: string; hint: string | null }[];
    activity?: { description: string; causer: string; log: string | null; at: string }[];
};

/** "Rp 1,2 jt" / "Rp 150 rb": ringkas agar muat di sumbu grafik dan kartu kecil. */
export function compactRupiah(value: number): string {
    const abs = Math.abs(value);
    const sign = value < 0 ? '-' : '';
    const fmt = (n: number) => n.toLocaleString('id-ID', { maximumFractionDigits: 1 });

    if (abs >= 1_000_000_000) {
        return `${sign}Rp ${fmt(abs / 1_000_000_000)} M`;
    }

    if (abs >= 1_000_000) {
        return `${sign}Rp ${fmt(abs / 1_000_000)} jt`;
    }

    if (abs >= 1_000) {
        return `${sign}Rp ${fmt(abs / 1_000)} rb`;
    }

    return `${sign}Rp ${fmt(abs)}`;
}

function timeAgo(iso: string): string {
    const seconds = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));

    if (seconds < 60) {
        return 'baru saja';
    }

    const units: [number, string][] = [[60, 'menit'], [3600, 'jam'], [86400, 'hari']];
    let label = 'menit';
    let divisor = 60;

    for (const [size, name] of units) {
        if (seconds >= size) {
            divisor = size;
            label = name;
        }
    }

    return `${Math.floor(seconds / divisor)} ${label} lalu`;
}

function EmptyNote({ icon: Icon = CheckCircle2, children }: { icon?: LucideIcon; children: React.ReactNode }) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-10 text-center text-sm text-muted-foreground">
            <Icon className="size-7 text-emerald-500" />
            {children}
        </div>
    );
}

function ViewAll({ href, children }: { href: string; children: React.ReactNode }) {
    return (
        <Link href={href} className="flex items-center justify-center gap-1 border-t px-6 py-3 text-sm font-semibold text-primary transition hover:bg-accent/50">
            {children} <ChevronRight className="size-4" />
        </Link>
    );
}

const monthLabel = (month: string) => new Date(`${month}-01T00:00:00`).toLocaleDateString('id-ID', { month: 'short' });

export function FinanceWidget({ data }: { data: NonNullable<Widgets['finance']> }) {
    const max = Math.max(1, ...data.trend.flatMap((row) => [row.revenue, row.expenses]));
    const empty = data.trend.every((row) => row.revenue === 0 && row.expenses === 0);
    const W = 600;
    const H = 230;
    const top = 16;
    const bottom = 30;
    const left = 58;
    const plot = H - top - bottom;
    const group = (W - left) / data.trend.length;
    const barWidth = Math.min(26, group / 3);
    const y = (value: number) => top + plot - (value / max) * plot;
    const change = data.revenue_change;
    const profitable = data.current.net_profit >= 0;

    return (
        <Card className="flex min-w-0 flex-col lg:col-span-2">
            <CardHeader
                title="Pendapatan dan pengeluaran"
                description="Enam bulan terakhir, uang yang benar-benar diterima."
                action={
                    <Link href="/reports/profit-loss" className="text-sm font-semibold whitespace-nowrap text-primary hover:underline">
                        Laporan lengkap
                    </Link>
                }
            />
            <div className="grid gap-x-8 gap-y-4 px-4 pt-5 sm:px-6 md:grid-cols-3 [&>div]:min-w-0">
                <div>
                    <div className="text-xs text-muted-foreground">Pendapatan bulan ini</div>
                    <div className="num mt-1 flex flex-wrap items-baseline gap-x-2 text-xl font-semibold tracking-tight sm:text-2xl">
                        {formatRupiah(data.current.revenue)}
                        {change !== null && (
                            <span className={cn('inline-flex items-center text-xs font-semibold', change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')}>
                                {change >= 0 ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
                                {Math.abs(change).toLocaleString('id-ID')}%
                            </span>
                        )}
                    </div>
                    {change !== null && <div className="mt-0.5 text-xs text-muted-foreground">dibanding bulan lalu</div>}
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">Pengeluaran bulan ini</div>
                    <div className="num mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{formatRupiah(data.current.expenses)}</div>
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">{profitable ? 'Laba bersih' : 'Rugi bersih'}</div>
                    <div className={cn('num mt-1 text-xl font-semibold tracking-tight sm:text-2xl', profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')}>{formatRupiah(Math.abs(data.current.net_profit))}</div>
                </div>
            </div>

            <div className="px-4 pt-4 pb-5">
                <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Grafik pendapatan dan pengeluaran enam bulan terakhir" className="h-auto w-full">
                    {[0, 0.25, 0.5, 0.75, 1].map((step) => (
                        <g key={step}>
                            <line x1={left} x2={W} y1={y(max * step)} y2={y(max * step)} className="stroke-border" strokeDasharray={step === 0 ? undefined : '3 4'} />
                            <text x={left - 8} y={y(max * step) + 4} textAnchor="end" className="fill-muted-foreground text-[11px]">
                                {step === 0 ? '0' : compactRupiah(max * step).replace('Rp ', '')}
                            </text>
                        </g>
                    ))}
                    {data.trend.map((row, index) => {
                        const x = left + group * index + group / 2;

                        return (
                            <g key={row.month}>
                                <rect x={x - barWidth - 2} y={y(row.revenue)} width={barWidth} height={Math.max(0, top + plot - y(row.revenue))} rx={5} className="fill-indigo-500">
                                    <title>{`${monthLabel(row.month)}: pendapatan ${formatRupiah(row.revenue)}`}</title>
                                </rect>
                                <rect x={x + 2} y={y(row.expenses)} width={barWidth} height={Math.max(0, top + plot - y(row.expenses))} rx={5} className="fill-rose-400">
                                    <title>{`${monthLabel(row.month)}: pengeluaran ${formatRupiah(row.expenses)}`}</title>
                                </rect>
                                <text x={x} y={H - 8} textAnchor="middle" className={cn('text-[12px]', index === data.trend.length - 1 ? 'fill-foreground font-semibold' : 'fill-muted-foreground')}>
                                    {monthLabel(row.month)}
                                </text>
                            </g>
                        );
                    })}
                    {empty && (
                        <text x={(W + left) / 2} y={top + plot / 2} textAnchor="middle" className="fill-muted-foreground text-[13px]">
                            Belum ada pemasukan atau pengeluaran tercatat
                        </text>
                    )}
                </svg>
                <div className="mt-2 flex justify-center gap-5 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-indigo-500" /> Pendapatan
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-rose-400" /> Pengeluaran
                    </span>
                </div>
            </div>
        </Card>
    );
}

export function CollectionWidget({ data }: { data: NonNullable<Widgets['collection']> }) {
    const radius = 54;
    const circumference = 2 * Math.PI * radius;
    const tone = data.invoices === 0 ? 'stroke-muted-foreground/40' : data.percent >= 80 ? 'stroke-emerald-500' : data.percent >= 50 ? 'stroke-amber-500' : 'stroke-rose-500';
    const text = data.invoices === 0 ? 'text-muted-foreground' : data.percent >= 80 ? 'text-emerald-600 dark:text-emerald-400' : data.percent >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400';

    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Penagihan bulan ini" description="Tagihan yang terbit bulan ini dan sudah tertagih." />
            <div className="flex flex-1 flex-col items-center justify-center px-6 py-6">
                <div className="relative size-40">
                    <svg viewBox="0 0 140 140" className="size-full -rotate-90" aria-hidden="true">
                        <circle cx="70" cy="70" r={radius} fill="none" strokeWidth="13" className="stroke-muted" />
                        <circle cx="70" cy="70" r={radius} fill="none" strokeWidth="13" strokeLinecap="round" className={cn('transition-[stroke-dashoffset] duration-700', tone)} strokeDasharray={circumference} strokeDashoffset={circumference * (1 - Math.min(100, data.percent) / 100)} />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span className={cn('num text-4xl font-semibold tracking-tight', text)}>{data.invoices === 0 ? '–' : `${data.percent}%`}</span>
                        <span className="text-xs text-muted-foreground">tertagih</span>
                    </div>
                </div>
                {data.invoices === 0 ? (
                    <p className="mt-4 text-center text-sm text-muted-foreground">Belum ada tagihan terbit bulan ini.</p>
                ) : (
                    <dl className="mt-5 grid w-full grid-cols-2 gap-3 text-center">
                        <div className="rounded-xl bg-muted p-3">
                            <dt className="text-xs text-muted-foreground">Tertagih</dt>
                            <dd className="num mt-0.5 text-sm font-semibold">{compactRupiah(data.collected)}</dd>
                        </div>
                        <div className="rounded-xl bg-muted p-3">
                            <dt className="text-xs text-muted-foreground">Total tagihan</dt>
                            <dd className="num mt-0.5 text-sm font-semibold">{compactRupiah(data.billed)}</dd>
                        </div>
                        <p className="col-span-2 text-xs text-muted-foreground">
                            {data.paid_invoices} dari {data.invoices} invoice sudah lunas
                        </p>
                    </dl>
                )}
            </div>
        </Card>
    );
}

export function OverdueWidget({ data }: { data: NonNullable<Widgets['overdue']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Tagihan terlambat" description={data.count > 0 ? `${data.count} invoice · ${formatRupiah(data.total)}` : 'Lewat jatuh tempo dan belum lunas.'} />
            {data.items.length === 0 ? (
                <EmptyNote>Tidak ada tagihan yang terlambat.</EmptyNote>
            ) : (
                <>
                    <ul className="flex-1 divide-y divide-border/70">
                        {data.items.map((item) => (
                            <li key={item.invoice_number} className="row-in flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-semibold">{item.customer}</div>
                                    <div className="num truncate text-xs text-muted-foreground">{item.invoice_number}</div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <div className="num font-semibold">{compactRupiah(item.outstanding)}</div>
                                    <div className={cn('text-xs font-semibold', item.days_late > 14 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400')}>{item.days_late} hari</div>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <ViewAll href="/invoices?status=unpaid">Lihat semua tagihan</ViewAll>
                </>
            )}
        </Card>
    );
}

export function WeakSignalWidget({ data }: { data: NonNullable<Widgets['weak_signal']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Sinyal ONT bermasalah" description={data.count > 0 ? `${data.count} ONT di bawah -25 dBm${data.critical > 0 ? ` · ${data.critical} kritis` : ''}` : 'ONT dengan redaman terburuk.'} />
            {data.items.length === 0 ? (
                <EmptyNote>Semua sinyal ONT yang terbaca dalam batas baik.</EmptyNote>
            ) : (
                <>
                    <ul className="flex-1 divide-y divide-border/70">
                        {data.items.map((item) => (
                            <li key={item.serial_number} className="row-in flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-semibold">{item.customer}</div>
                                    <div className="num truncate text-xs text-muted-foreground">{item.serial_number}</div>
                                </div>
                                <span className={cn('num shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', item.level === 'critical' ? 'bg-rose-500/12 text-rose-700 ring-rose-500/25 dark:text-rose-300' : 'bg-amber-500/14 text-amber-700 ring-amber-500/30 dark:text-amber-300')}>
                                    {item.rx_power.toFixed(2)} dBm
                                </span>
                            </li>
                        ))}
                    </ul>
                    <ViewAll href="/monitoring">Buka monitoring</ViewAll>
                </>
            )}
        </Card>
    );
}

const attentionIcons: Record<string, LucideIcon> = { outages: Megaphone, promises: CalendarClock, new_customers: UserPlus, whatsapp: MessageSquareWarning, no_whatsapp: PhoneOff };
const attentionTone = {
    red: 'bg-rose-500/12 text-rose-600 dark:text-rose-300',
    amber: 'bg-amber-500/14 text-amber-600 dark:text-amber-300',
    blue: 'bg-sky-500/12 text-sky-600 dark:text-sky-300',
    green: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-300',
} as const;

export function AttentionWidget({ items }: { items: NonNullable<Widgets['attention']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Perlu perhatian" description="Yang menunggu tindakan Anda hari ini." />
            <ul className="divide-y divide-border/70">
                {items.map((item) => {
                    const Icon = attentionIcons[item.key] ?? AlertTriangle;
                    const quiet = item.value === 0;

                    return (
                        <li key={item.key} className="row-in">
                            <Link href={item.href} className="flex items-center gap-3 px-6 py-3.5 transition hover:bg-accent/50">
                                <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl', quiet ? 'bg-muted text-muted-foreground' : attentionTone[item.tone])}>
                                    <Icon className="size-[18px]" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-semibold">{item.label}</span>
                                    {item.hint && !quiet && <span className="block truncate text-xs text-muted-foreground">{item.hint}</span>}
                                </span>
                                <span className={cn('num text-2xl font-semibold tracking-tight', quiet && 'text-muted-foreground/60')}>{item.value}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}

export function ActivityWidget({ items }: { items: NonNullable<Widgets['activity']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader
                title="Aktivitas terbaru"
                description="Perubahan terakhir yang dilakukan tim."
                action={
                    <Link href="/audit-log" className="text-sm font-semibold whitespace-nowrap text-primary hover:underline">
                        Audit log
                    </Link>
                }
            />
            {items.length === 0 ? (
                <EmptyNote icon={Radio}>Belum ada aktivitas tercatat.</EmptyNote>
            ) : (
                <ul className="divide-y divide-border/70">
                    {items.map((item, index) => (
                        <li key={`${item.at}-${index}`} className="row-in flex items-center gap-3 px-6 py-3 text-sm">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground">{item.causer.slice(0, 1).toUpperCase()}</span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-medium">{item.description}</span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {item.causer}
                                    {item.log && ` · ${item.log}`}
                                </span>
                            </span>
                            <span className="shrink-0 text-xs whitespace-nowrap text-muted-foreground">{timeAgo(item.at)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

const dueLabel = (days: number) => (days <= 0 ? 'Hari ini' : days === 1 ? 'Besok' : `${days} hari lagi`);

export function UpcomingWidget({ data }: { data: NonNullable<Widgets['upcoming']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Jatuh tempo 3 hari ke depan" description={data.count > 0 ? `${data.count} invoice · ${formatRupiah(data.total)}` : 'Tagihan yang segera jatuh tempo.'} />
            {data.items.length === 0 ? (
                <EmptyNote icon={CalendarClock}>Tidak ada tagihan yang jatuh tempo dalam 3 hari ke depan.</EmptyNote>
            ) : (
                <>
                    <ul className="flex-1 divide-y divide-border/70">
                        {data.items.map((item) => (
                            <li key={item.invoice_number} className="row-in flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-semibold">{item.customer}</div>
                                    <div className="num truncate text-xs text-muted-foreground">{item.invoice_number}</div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <div className="num font-semibold">{compactRupiah(item.outstanding)}</div>
                                    <div className={cn('text-xs font-semibold', item.due_in_days <= 0 ? 'text-rose-600 dark:text-rose-400' : item.due_in_days === 1 ? 'text-amber-600 dark:text-amber-400' : 'text-sky-600 dark:text-sky-400')}>{dueLabel(item.due_in_days)}</div>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <ViewAll href="/invoices?status=unpaid">Lihat semua tagihan</ViewAll>
                </>
            )}
        </Card>
    );
}

/** 135 menit menjadi "2 jam 15 mnt"; 3000 menit menjadi "2 hari 2 jam". Hanya dua satuan terbesar agar singkat. */
function humanDuration(minutes: number): string {
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const mins = minutes % 60;

    if (days > 0) {
        return hours > 0 ? `${days} hari ${hours} jam` : `${days} hari`;
    }

    if (hours > 0) {
        return mins > 0 ? `${hours} jam ${mins} mnt` : `${hours} jam`;
    }

    return `${mins} mnt`;
}

function offlineFor(since: string | null): string {
    if (!since) {
        return 'Lama belum diketahui';
    }

    const minutes = Math.max(1, Math.round((Date.now() - new Date(since).getTime()) / 60000));

    return `Putus ${humanDuration(minutes)}`;
}

export function RoutersOfflineWidget({ data }: { data: NonNullable<Widgets['routers_offline']> }) {
    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Router offline" description={data.offline > 0 ? `${data.offline} dari ${data.total} router tidak terjangkau` : `${data.total} router dipantau`} />
            {data.items.length === 0 ? (
                <EmptyNote>Semua router terjangkau.</EmptyNote>
            ) : (
                <>
                    <ul className="flex-1 divide-y divide-border/70">
                        {data.items.map((item) => (
                            <li key={item.host} className="row-in flex items-center gap-3 px-6 py-3 text-sm">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/12 text-rose-600 dark:text-rose-300">
                                    <WifiOff className="size-[18px]" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-semibold">{item.name}</div>
                                    <div className="num truncate text-xs text-muted-foreground">{item.error ?? item.host}</div>
                                </div>
                                <span className="shrink-0 text-right text-xs font-semibold whitespace-nowrap text-rose-600 dark:text-rose-400">{offlineFor(item.offline_since)}</span>
                            </li>
                        ))}
                    </ul>
                    <ViewAll href="/routers">Kelola router</ViewAll>
                </>
            )}
        </Card>
    );
}

export function VoucherTodayWidget({ data }: { data: NonNullable<Widgets['voucher_today']> }) {
    const change = data.revenue_change;
    const topMax = Math.max(1, ...data.top_profiles.map((row) => row.count));

    return (
        <Card className="flex min-w-0 flex-col">
            <CardHeader title="Penjualan voucher hari ini" description="Voucher yang dipakai login pertama hari ini." />
            <div className="flex-1 px-6 py-5">
                <div className="flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
                    <div>
                        <div className="num text-4xl font-semibold tracking-tight">{data.count}</div>
                        <div className="text-xs text-muted-foreground">voucher terjual</div>
                    </div>
                    <div className="text-right">
                        <div className="num text-xl font-semibold tracking-tight">{formatRupiah(data.revenue)}</div>
                        {change !== null ? (
                            <div className={cn('inline-flex items-center text-xs font-semibold', change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')}>
                                {change >= 0 ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
                                {Math.abs(change).toLocaleString('id-ID')}% dari kemarin
                            </div>
                        ) : (
                            <div className="text-xs text-muted-foreground">{data.yesterday_revenue === 0 ? 'Kemarin belum ada penjualan' : ''}</div>
                        )}
                    </div>
                </div>

                {data.top_profiles.length === 0 ? (
                    <div className="mt-5 flex items-center gap-2 rounded-xl bg-muted px-4 py-3 text-sm text-muted-foreground">
                        <Ticket className="size-4" /> Belum ada voucher terjual hari ini.
                    </div>
                ) : (
                    <ul className="mt-5 space-y-3">
                        {data.top_profiles.map((row) => (
                            <li key={row.name} className="row-in">
                                <div className="flex justify-between gap-3 text-sm">
                                    <span className="truncate font-medium">{row.name}</span>
                                    <span className="num shrink-0 font-semibold">{row.count}</span>
                                </div>
                                <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-muted">
                                    <div className="h-full rounded-full bg-violet-500" style={{ width: `${Math.max(6, (row.count / topMax) * 100)}%` }} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
}
