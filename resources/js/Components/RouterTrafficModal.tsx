import { Activity, ArrowDown, ArrowUp, Pause, Play } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Field, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { cn } from '@/lib/utils';

type Iface = { name: string; type: string; running: boolean; comment: string | null };
type Point = { rx: number; tx: number };

const POINTS = 60;
const INTERVAL_MS = 2000;
const RETRY_MS = 5000;
/** Bila aliran tidak mengirim cuplikan pertama dalam waktu ini, proxy dianggap menahannya dan dipakai pengambilan per cuplikan. */
const SILENCE_MS = 8000;
const STREAM_BROKEN_KEY = 'router-traffic-stream-broken';

/** 24_500_000 menjadi "24,5 Mbps". */
export function formatBps(value: number): string {
    const units = [
        [1_000_000_000, 'Gbps'],
        [1_000_000, 'Mbps'],
        [1_000, 'Kbps'],
    ] as const;

    for (const [size, label] of units) {
        if (value >= size) {
            return `${(value / size).toLocaleString('id-ID', { maximumFractionDigits: value / size >= 100 ? 0 : 1 })} ${label}`;
        }
    }

    return `${Math.round(value)} bps`;
}

/** Batas atas sumbu yang bulat (1, 2, 5 × 10^n) di atas nilai tertinggi, minimal 100 Kbps. */
function niceMax(value: number): number {
    const target = Math.max(value * 1.15, 100_000);
    const magnitude = 10 ** Math.floor(Math.log10(target));

    return ([1, 2, 5, 10].map((step) => step * magnitude).find((candidate) => candidate >= target) ?? target) as number;
}

function defaultInterface(list: Iface[], routerId: number): string {
    try {
        const saved = localStorage.getItem(`router-traffic-${routerId}`);

        if (saved && list.some((item) => item.name === saved)) {
            return saved;
        }
    } catch {
        // localStorage tidak tersedia: pakai tebakan di bawah.
    }

    const running = list.filter((item) => item.running);

    return (running.find((item) => /wan|uplink|isp|internet|ether1/i.test(`${item.name} ${item.comment ?? ''}`)) ?? running[0] ?? list[0])?.name ?? '';
}

function Chart({ points }: { points: Point[] }) {
    const W = 640;
    const H = 220;
    const left = 56;
    const top = 14;
    const bottom = 24;
    const plotH = H - top - bottom;
    const plotW = W - left - 8;
    const max = niceMax(Math.max(0, ...points.flatMap((point) => [point.rx, point.tx])));
    const step = plotW / (POINTS - 1);
    const offset = POINTS - points.length;
    const x = (index: number) => left + (index + offset) * step;
    const y = (value: number) => top + plotH - (value / max) * plotH;
    const line = (key: 'rx' | 'tx') => points.map((point, index) => `${index === 0 ? 'M' : 'L'}${x(index).toFixed(1)} ${y(point[key]).toFixed(1)}`).join(' ');
    const area = (key: 'rx' | 'tx') => (points.length > 1 ? `${line(key)} L${x(points.length - 1).toFixed(1)} ${top + plotH} L${x(0).toFixed(1)} ${top + plotH} Z` : '');

    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Grafik trafik masuk dan keluar dua menit terakhir" className="h-auto w-full">
            <defs>
                <linearGradient id="traffic-rx" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#6366f1" stopOpacity="0.35" />
                    <stop offset="1" stopColor="#6366f1" stopOpacity="0.02" />
                </linearGradient>
                <linearGradient id="traffic-tx" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#10b981" stopOpacity="0.3" />
                    <stop offset="1" stopColor="#10b981" stopOpacity="0.02" />
                </linearGradient>
            </defs>
            {[0, 0.25, 0.5, 0.75, 1].map((fraction) => (
                <g key={fraction}>
                    <line x1={left} x2={W - 8} y1={y(max * fraction)} y2={y(max * fraction)} className="stroke-border" strokeDasharray={fraction === 0 ? undefined : '3 4'} />
                    <text x={left - 8} y={y(max * fraction) + 4} textAnchor="end" className="fill-muted-foreground text-[11px]">
                        {fraction === 0 ? '0' : formatBps(max * fraction).replace(' ', '')}
                    </text>
                </g>
            ))}
            <text x={left} y={H - 6} className="fill-muted-foreground text-[11px]">
                2 menit lalu
            </text>
            <text x={W - 8} y={H - 6} textAnchor="end" className="fill-muted-foreground text-[11px]">
                sekarang
            </text>
            <path d={area('rx')} fill="url(#traffic-rx)" />
            <path d={area('tx')} fill="url(#traffic-tx)" />
            <path d={line('rx')} fill="none" stroke="#6366f1" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
            <path d={line('tx')} fill="none" stroke="#10b981" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
        </svg>
    );
}

/** Live trafik satu interface router: cuplikan tiap 2 detik dari RouterOS, grafik 2 menit terakhir. */
export function RouterTrafficModal({ router, onClose }: { router: { id: number; name: string } | null; onClose: () => void }) {
    const routerId = router?.id ?? null;
    const [interfaces, setInterfaces] = useState<Iface[]>([]);
    const [selected, setSelected] = useState('');
    const [points, setPoints] = useState<Point[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [loadingList, setLoadingList] = useState(false);
    const [paused, setPaused] = useState(false);
    const [peak, setPeak] = useState({ rx: 0, tx: 0 });

    useEffect(() => {
        setInterfaces([]);
        setSelected('');
        setPoints([]);
        setPeak({ rx: 0, tx: 0 });
        setError(null);
        setPaused(false);

        if (routerId === null) {
            return;
        }

        const controller = new AbortController();
        setLoadingList(true);

        fetch(`/routers/${routerId}/traffic/interfaces`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? 'Gagal membaca daftar interface.');
                }

                setInterfaces(data.interfaces);
                setSelected(defaultInterface(data.interfaces, routerId));
            })
            .catch((failure: Error) => {
                if (failure.name !== 'AbortError') {
                    setError(failure.message);
                }
            })
            .finally(() => setLoadingList(false));

        return () => controller.abort();
    }, [routerId]);

    useEffect(() => {
        if (routerId === null || selected === '' || paused) {
            return;
        }

        try {
            localStorage.setItem(`router-traffic-${routerId}`, selected);
        } catch {
            // Tidak fatal: pilihan hanya tidak diingat.
        }

        const stop = new AbortController();
        let current: AbortController | null = null;
        let timer: number | undefined;
        const sleep = (ms: number) => new Promise<void>((resolve) => (timer = window.setTimeout(resolve, ms)));
        const url = (path: string) => `/routers/${routerId}/traffic${path}?interface=${encodeURIComponent(selected)}`;
        const accept = { Accept: 'application/json' };

        const push = (data: { rx_bps: number; tx_bps: number }) => {
            setError(null);
            setPoints((existing) => [...existing, { rx: data.rx_bps, tx: data.tx_bps }].slice(-POINTS));
            setPeak((existing) => ({ rx: Math.max(existing.rx, data.rx_bps), tx: Math.max(existing.tx, data.tx_bps) }));
        };

        const fail = async (response: Response): Promise<never> => {
            const data = await response.json().catch(() => ({}));

            throw new Error(data.error ?? data.message ?? 'Gagal membaca trafik.');
        };

        /** Satu aliran SSE: satu koneksi ke router untuk banyak cuplikan. 'silent' bila proxy menahan aliran. */
        const streamOnce = async (): Promise<'ended' | 'silent' | 'hidden'> => {
            current = new AbortController();
            const local = current;
            let received = false;
            let silenced = false;
            const watchdog = window.setTimeout(() => {
                if (!received) {
                    silenced = true;
                    local.abort();
                }
            }, SILENCE_MS);
            const onAbort = () => local.abort();
            stop.signal.addEventListener('abort', onAbort);

            try {
                const response = await fetch(url('/stream'), { headers: { Accept: 'text/event-stream' }, signal: local.signal });

                if (!response.ok || !response.body) {
                    return await fail(response);
                }

                const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
                let buffer = '';

                for (;;) {
                    const { done, value } = await reader.read();

                    if (done) {
                        return 'ended';
                    }

                    buffer += value;
                    let boundary = buffer.indexOf('\n\n');

                    while (boundary !== -1) {
                        const block = buffer.slice(0, boundary);
                        buffer = buffer.slice(boundary + 2);
                        boundary = buffer.indexOf('\n\n');

                        const event = /^event: (.+)$/m.exec(block)?.[1];
                        const data = /^data: (.*)$/m.exec(block)?.[1];

                        if (event === 'sample' && data) {
                            received = true;
                            window.clearTimeout(watchdog);
                            push(JSON.parse(data));
                        } else if (event === 'failure') {
                            throw new Error((data ? JSON.parse(data).error : null) ?? 'Koneksi ke router terputus.');
                        } else if (event === 'end') {
                            return 'ended';
                        }
                    }
                }
            } catch (failure) {
                if (stop.signal.aborted) {
                    throw failure;
                }

                if (silenced) {
                    return 'silent';
                }

                if (local.signal.aborted) {
                    return 'hidden';
                }

                throw failure;
            } finally {
                window.clearTimeout(watchdog);
                stop.signal.removeEventListener('abort', onAbort);
                local.abort();
                current = null;
            }
        };

        /** Cadangan: satu permintaan per cuplikan, dipakai bila aliran tidak sampai ke browser. */
        const pollOnce = async () => {
            const response = await fetch(url(''), { headers: accept, signal: stop.signal });

            if (!response.ok) {
                await fail(response);
            }

            push(await response.json());
        };

        const onVisibility = () => document.hidden && current?.abort();
        document.addEventListener('visibilitychange', onVisibility);

        void (async () => {
            let polling = false;

            try {
                polling = sessionStorage.getItem(STREAM_BROKEN_KEY) === '1';
            } catch {
                // Tanpa sessionStorage: coba aliran lagi.
            }

            while (!stop.signal.aborted) {
                let delay = 0;

                try {
                    if (document.hidden) {
                        delay = 500;
                    } else if (polling) {
                        await pollOnce();
                        delay = INTERVAL_MS;
                    } else {
                        const result = await streamOnce();

                        // Jeda singkat agar aliran yang berakhir seketika tidak menyambung ulang tanpa henti.
                        delay = result === 'ended' ? 300 : 0;

                        if (result === 'silent') {
                            polling = true;

                            try {
                                sessionStorage.setItem(STREAM_BROKEN_KEY, '1');
                            } catch {
                                // Hanya tidak diingat untuk jendela berikutnya.
                            }
                        }
                    }
                } catch (failure) {
                    if (stop.signal.aborted) {
                        return;
                    }

                    setError((failure as Error).message);
                    delay = RETRY_MS;
                }

                if (delay > 0) {
                    await sleep(delay);
                }
            }
        })();

        return () => {
            stop.abort();
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [routerId, selected, paused]);

    const latest = points[points.length - 1];
    const live = !paused && error === null && points.length > 0;
    const hasSelection = useMemo(() => interfaces.some((item) => item.name === selected), [interfaces, selected]);

    return (
        <Modal
            open={router !== null}
            onClose={onClose}
            size="lg"
            placement="center"
            title={`Live trafik · ${router?.name ?? ''}`}
            description="Cuplikan langsung dari router tiap 2 detik lewat satu koneksi. Pemantauan berhenti saat jendela ditutup atau tab disembunyikan."
            footer={
                <Button variant="outline" onClick={onClose}>
                    Tutup
                </Button>
            }
        >
            <div className="flex flex-wrap items-end gap-3">
                <Field label="Interface" className="min-w-0 flex-1">
                    <Select
                        value={selected}
                        onChange={(event) => {
                            setSelected(event.target.value);
                            setPoints([]);
                            setPeak({ rx: 0, tx: 0 });
                        }}
                        disabled={interfaces.length === 0}
                    >
                        {loadingList && <option>Memuat interface...</option>}
                        {interfaces.map((item) => (
                            <option key={item.name} value={item.name}>
                                {item.name}
                                {item.comment ? ` · ${item.comment}` : ''}
                                {item.running ? '' : ' (tidak aktif)'}
                            </option>
                        ))}
                    </Select>
                </Field>
                <span className={cn('inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold', live ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : paused ? 'text-muted-foreground' : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300')} role="status">
                    <span className="relative flex size-2">
                        {live && <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70" />}
                        <span className={cn('relative inline-flex size-2 rounded-full', live ? 'bg-emerald-500' : paused ? 'bg-slate-400' : 'bg-rose-500')} />
                    </span>
                    {paused ? 'Dijeda' : live ? 'Live' : error ? 'Terputus' : 'Menghubungkan'}
                </span>
                <Button variant="outline" onClick={() => setPaused((value) => !value)} disabled={!hasSelection} aria-label={paused ? 'Lanjutkan' : 'Jeda'}>
                    {paused ? <Play className="size-4" /> : <Pause className="size-4" />} {paused ? 'Lanjut' : 'Jeda'}
                </Button>
            </div>

            {error && <p className="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">{error}{!paused && ' Mencoba lagi otomatis...'}</p>}

            <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-xl border border-indigo-500/25 bg-indigo-500/10 p-4">
                    <div className="flex items-center gap-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                        <ArrowDown className="size-3.5" /> Masuk (RX)
                    </div>
                    <div className="num mt-1.5 text-2xl font-semibold tracking-tight whitespace-nowrap">{latest ? formatBps(latest.rx) : '–'}</div>
                    <div className="num mt-0.5 text-xs whitespace-nowrap text-muted-foreground">puncak {peak.rx > 0 ? formatBps(peak.rx) : '–'}</div>
                </div>
                <div className="rounded-xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                    <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        <ArrowUp className="size-3.5" /> Keluar (TX)
                    </div>
                    <div className="num mt-1.5 text-2xl font-semibold tracking-tight whitespace-nowrap">{latest ? formatBps(latest.tx) : '–'}</div>
                    <div className="num mt-0.5 text-xs whitespace-nowrap text-muted-foreground">puncak {peak.tx > 0 ? formatBps(peak.tx) : '–'}</div>
                </div>
            </div>

            <div className="mt-5 rounded-xl border bg-card p-3">
                {points.length === 0 ? (
                    <div className="flex h-48 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Activity className="size-6" />
                        {loadingList ? 'Membaca interface...' : error ? 'Belum ada data.' : 'Menunggu cuplikan pertama...'}
                    </div>
                ) : (
                    <Chart points={points} />
                )}
                <div className="mt-2 flex justify-center gap-5 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-indigo-500" /> Masuk (RX)
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-emerald-500" /> Keluar (TX)
                    </span>
                </div>
            </div>
        </Modal>
    );
}
