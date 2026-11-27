import { CheckCircle2, Download, GitCommitHorizontal, Loader2, RefreshCw, SquareTerminal, XCircle } from 'lucide-react';
import { type ReactNode, useCallback, useEffect, useRef, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { UpdateTerminal } from '@/Components/UpdateTerminal';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { cn, formatDate } from '@/lib/utils';

type Info = {
    current: { hash: string; short: string; subject: string; date: string; branch: string; upstream: string };
    behind: number;
    ahead: number;
    commits: { hash: string; subject: string; date: string }[];
    file_count: number;
    needs_composer: boolean;
    migration_count: number;
    dirty: boolean;
    fetch_error: string | null;
    blocked: string | null;
    can_update: boolean;
};

type Step = { key: string; label: string; status: 'pending' | 'running' | 'done' | 'failed' | 'skipped'; output: string | null };
type State = { status: 'idle' | 'queued' | 'running' | 'succeeded' | 'failed'; by?: string | null; started_at?: string; finished_at?: string | null; from?: string | null; to?: string | null; error?: string | null; steps: Step[] };

const csrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

async function call<T>(url: string, method: 'GET' | 'POST' = 'GET'): Promise<{ ok: boolean; data: T & { message?: string } }> {
    const response = await fetch(url, { method, headers: { Accept: 'application/json', ...(method === 'POST' ? { 'X-XSRF-TOKEN': csrf() } : {}) } });

    return { ok: response.ok, data: await response.json().catch(() => ({ message: 'Respons server tidak valid.' })) };
}

type Tone = 'slate' | 'green' | 'amber' | 'blue' | 'red';

/** Lapisan gradasi per kondisi; semuanya ditumpuk dan hanya satu yang terlihat, sehingga perpindahan warna halus. */
const toneLayers: Record<Tone, string> = {
    slate: 'from-slate-500 to-slate-700',
    green: 'from-emerald-500 to-teal-700',
    amber: 'from-amber-500 to-orange-700',
    blue: 'from-sky-500 to-indigo-700',
    red: 'from-rose-500 to-red-700',
};

function versionTone(info: Info, state: State, checked: boolean, running: boolean): { tone: Tone; label: string; icon: ReactNode } {
    if (running) {
        return { tone: 'blue', label: 'Sedang diperbarui…', icon: <Loader2 className="size-4 animate-spin" /> };
    }

    if (state.status === 'failed') {
        return { tone: 'red', label: 'Pembaruan terakhir gagal', icon: <XCircle className="size-4" /> };
    }

    if (info.fetch_error) {
        return { tone: 'red', label: 'Gagal memeriksa remote', icon: <XCircle className="size-4" /> };
    }

    if (info.behind > 0) {
        return { tone: 'amber', label: `${info.behind} pembaruan tersedia`, icon: <Download className="size-4" /> };
    }

    if (state.status === 'succeeded') {
        return { tone: 'green', label: `Berhasil diperbarui${state.finished_at ? ` · ${formatDate(state.finished_at)}` : ''}`, icon: <CheckCircle2 className="size-4" /> };
    }

    return checked ? { tone: 'green', label: 'Sudah versi terbaru', icon: <CheckCircle2 className="size-4" /> } : { tone: 'slate', label: 'Memeriksa pembaruan…', icon: <Loader2 className="size-4 animate-spin" /> };
}

function VersionCard({ info, state, checked, running }: { info: Info; state: State; checked: boolean; running: boolean }) {
    const { tone, label, icon } = versionTone(info, state, checked, running);

    return (
        <div className="relative overflow-hidden rounded-2xl border border-transparent text-white shadow-lg shadow-black/10 lg:col-span-1">
            {(Object.keys(toneLayers) as Tone[]).map((key) => (
                <div key={key} aria-hidden className={cn('absolute inset-0 bg-gradient-to-br transition-opacity duration-700', toneLayers[key], key === tone ? 'opacity-100' : 'opacity-0')} />
            ))}
            <div className="relative space-y-4 p-6">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-[15px] font-semibold tracking-tight">Versi terpasang</h2>
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-medium whitespace-nowrap">
                        {icon} {label}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <GitCommitHorizontal className="size-5 opacity-80" />
                    <span className="num text-2xl font-semibold tracking-tight">{info.current.short}</span>
                    <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium">{info.current.branch}</span>
                </div>
                <p className="text-sm leading-snug">{info.current.subject}</p>
                <div className="space-y-0.5 text-xs text-white/75">
                    <p>{info.current.date ? formatDate(info.current.date) : '-'}</p>
                    <p>
                        Sumber: <span className="num">{info.current.upstream || 'belum diatur'}</span>
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function AppUpdate({ info: initialInfo, state: initialState }: { info: Info; state: State }) {
    const [info, setInfo] = useState(initialInfo);
    const [state, setState] = useState(initialState);
    const [checking, setChecking] = useState(false);
    const [checked, setChecked] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [terminalOpen, setTerminalOpen] = useState(initialState.status === 'queued' || initialState.status === 'running');
    const wasRunning = useRef(initialState.status === 'queued' || initialState.status === 'running');

    const running = state.status === 'queued' || state.status === 'running';

    const check = useCallback(async () => {
        setChecking(true);
        setError(null);
        const { ok, data } = await call<{ info: Info; state: State }>('/settings/update/check', 'POST');
        setChecking(false);

        if (ok) {
            setInfo(data.info);
            setState(data.state);
            setChecked(true);
        } else {
            setError(data.message ?? 'Pemeriksaan gagal.');
        }
    }, []);

    // Periksa remote otomatis saat halaman dibuka, kecuali pembaruan sedang berjalan.
    useEffect(() => {
        if (!running) {
            void check();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Pantau progres selama pembaruan berjalan; setelah sukses, muat ulang halaman agar memakai aset baru.
    useEffect(() => {
        if (!running) {
            if (wasRunning.current && state.status === 'succeeded') {
                const timer = window.setTimeout(() => window.location.reload(), 3000);

                return () => window.clearTimeout(timer);
            }

            return;
        }

        wasRunning.current = true;
        const timer = window.setInterval(async () => {
            const { ok, data } = await call<{ state: State; info: Info }>('/settings/update/status');

            if (ok) {
                setState(data.state);
                setInfo(data.info);
            }
            // Server mungkin sebentar tidak merespons saat cache dibersihkan; abaikan dan coba lagi.
        }, 2000);

        return () => window.clearInterval(timer);
    }, [running, state.status]);

    const start = async () => {
        setConfirming(false);
        setError(null);
        const { ok, data } = await call<{ state: State }>('/settings/update/start', 'POST');

        if (ok) {
            setState(data.state);
            setTerminalOpen(true);
        } else {
            setError(data.message ?? 'Pembaruan tidak dapat dimulai.');
        }
    };

    const upToDate = info.behind === 0 && !info.fetch_error;

    return (
        <>
            <PageHeader
                title="Pembaruan Aplikasi"
                description="Tarik perubahan terbaru dari git dan pasang dengan satu klik: kode, dependensi, migrasi database, cache, dan queue worker."
                actions={
                    <div className="flex flex-wrap gap-2">
                        {state.status !== 'idle' && (
                            <Button variant="outline" onClick={() => setTerminalOpen(true)}>
                                <SquareTerminal className="size-4" /> Lihat terminal
                            </Button>
                        )}
                        <Button variant="outline" onClick={check} disabled={checking || running}>
                            <RefreshCw className={cn('size-4', checking && 'animate-spin')} /> {checking ? 'Memeriksa...' : 'Periksa pembaruan'}
                        </Button>
                    </div>
                }
            />

            <div className="grid gap-5 lg:grid-cols-3">
                <VersionCard info={info} state={state} checked={checked} running={running} />

                <Card className="lg:col-span-2">
                    <CardHeader
                        title="Pembaruan tersedia"
                        action={info.fetch_error ? <Badge tone="red">Gagal memeriksa</Badge> : upToDate ? <Badge tone="green">Sudah terbaru</Badge> : <Badge tone="amber">{info.behind} commit baru</Badge>}
                    />
                    <div className="space-y-4 p-5">
                        {info.fetch_error && <pre className="overflow-x-auto rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs whitespace-pre-wrap">{info.fetch_error}</pre>}
                        {info.blocked && <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm">{info.blocked}</p>}
                        {error && <p className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">{error}</p>}

                        {info.commits.length > 0 && (
                            <>
                                <ul className="divide-y rounded-lg border">
                                    {info.commits.map((commit) => (
                                        <li key={commit.hash} className="flex items-start gap-3 px-3 py-2.5 text-sm">
                                            <span className="num rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">{commit.hash}</span>
                                            <span className="min-w-0 flex-1">{commit.subject}</span>
                                            <span className="shrink-0 text-xs text-muted-foreground">{formatDate(commit.date)}</span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="text-xs text-muted-foreground">
                                    {info.file_count} berkas berubah
                                    {info.migration_count > 0 && ` · ${info.migration_count} migrasi database akan dijalankan`}
                                    {info.needs_composer && ' · dependensi PHP akan dipasang ulang'}.
                                </p>
                            </>
                        )}

                        {upToDate && !running && <p className="text-sm text-muted-foreground">Aplikasi sudah memakai versi terbaru dari remote.</p>}

                        <div className="flex flex-wrap items-center gap-3">
                            {confirming ? (
                                <>
                                    <span className="text-sm">Pasang {info.behind} commit baru sekarang? Antrean pesan dimuat ulang sebentar.</span>
                                    <Button onClick={start}>Ya, perbarui</Button>
                                    <Button variant="outline" onClick={() => setConfirming(false)}>
                                        Batal
                                    </Button>
                                </>
                            ) : (
                                <Button onClick={() => setConfirming(true)} disabled={!info.can_update || running || checking}>
                                    <Download className="size-4" /> Perbarui sekarang
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>
            </div>

            <UpdateTerminal
                open={terminalOpen}
                status={state.status}
                steps={state.steps}
                error={state.error}
                host={`${window.location.hostname}`}
                from={state.from}
                to={state.to}
                onClose={() => setTerminalOpen(false)}
            />
        </>
    );
}
