import { useEffect, useRef } from 'react';

export type TerminalStep = { key: string; label: string; status: 'pending' | 'running' | 'done' | 'failed' | 'skipped'; output: string | null };

type Props = {
    open: boolean;
    status: 'idle' | 'queued' | 'running' | 'succeeded' | 'failed';
    steps: TerminalStep[];
    error?: string | null;
    host: string;
    from?: string | null;
    to?: string | null;
    onClose: () => void;
};

/** Perintah yang dijalankan AppUpdater untuk tiap langkah (tampilan saja; eksekusi tetap di server). */
const commands: Record<string, string> = {
    preflight: 'git status --porcelain',
    pull: 'git pull --ff-only',
    composer: 'composer install --no-dev --optimize-autoloader',
    migrate: 'php artisan migrate --force',
    cache: 'php artisan optimize:clear',
    queue: 'php artisan queue:restart',
};

/**
 * Popup bergaya terminal Linux yang menampilkan progres pembaruan: tiap langkah ditulis sebagai perintah shell
 * beserta keluarannya, lengkap dengan kursor berkedip dan gulir otomatis ke baris terbaru.
 */
export function UpdateTerminal({ open, status, steps, error, host, from, to, onClose }: Props) {
    const body = useRef<HTMLDivElement>(null);
    const running = status === 'queued' || status === 'running';
    const prompt = (
        <>
            <span className="font-semibold text-emerald-400">{host}</span>
            <span className="text-slate-400">:</span>
            <span className="font-semibold text-sky-400">~/app</span>
            <span className="text-slate-400">$ </span>
        </>
    );

    useEffect(() => {
        if (open) {
            body.current?.scrollTo({ top: body.current.scrollHeight });
        }
    }, [open, steps, status]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" aria-label="Terminal pembaruan aplikasi" onClick={onClose}>
            <div className="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-white/10 bg-[#1e1e2e] shadow-2xl shadow-black/60" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-center gap-2 border-b border-black/40 bg-[#2d2d3f] px-4 py-2.5">
                    <div className="flex gap-1.5">
                        <button type="button" onClick={onClose} aria-label="Tutup terminal" className="size-3 rounded-full bg-[#ff5f56] transition hover:brightness-125" />
                        <span className="size-3 rounded-full bg-[#ffbd2e]" />
                        <span className="size-3 rounded-full bg-[#27c93f]" />
                    </div>
                    <p className="flex-1 truncate text-center font-mono text-xs text-slate-300">{host}: ~/app — app:update</p>
                    <span className="w-10 text-right font-mono text-[10px] text-slate-500">bash</span>
                </div>

                <div ref={body} className="min-h-64 flex-1 overflow-auto p-4 font-mono text-[13px] leading-relaxed text-slate-200">
                    <p className="text-slate-500"># Pembaruan aplikasi{from ? ` dari ${from}` : ''}{to ? ` ke ${to}` : ''}</p>

                    {steps.map((step) => (
                        <div key={step.key} className="mt-3">
                            {step.status !== 'pending' && (
                                <p className="break-all">
                                    {prompt}
                                    <span className="text-slate-100">{commands[step.key] ?? step.key}</span>
                                </p>
                            )}
                            {step.status === 'running' && (
                                <p className="text-amber-300">
                                    <span className="inline-block animate-pulse">▍</span> {step.label}…
                                </p>
                            )}
                            {step.output && <pre className="mt-1 break-all whitespace-pre-wrap text-slate-400">{step.output}</pre>}
                            {step.status === 'done' && <p className="text-emerald-400">[ OK ] {step.label}</p>}
                            {step.status === 'skipped' && <p className="text-slate-500">[ SKIP ] {step.label}</p>}
                            {step.status === 'failed' && <p className="text-rose-400">[ GAGAL ] {step.label}</p>}
                        </div>
                    ))}

                    {error && <p className="mt-4 break-words text-rose-400">error: {error}</p>}
                    {status === 'succeeded' && <p className="mt-4 text-emerald-400">✔ Pembaruan selesai. Halaman dimuat ulang otomatis…</p>}

                    <p className="mt-3">
                        {prompt}
                        {running ? <span className="inline-block animate-pulse text-slate-100">▍</span> : <span className="inline-block animate-pulse text-slate-100">█</span>}
                    </p>
                </div>

                <div className="flex items-center justify-between border-t border-black/40 bg-[#2d2d3f] px-4 py-2 font-mono text-[11px] text-slate-400">
                    <span>{running ? '● berjalan' : status === 'succeeded' ? '● selesai' : status === 'failed' ? '● gagal' : '○ siap'}</span>
                    <button type="button" onClick={onClose} className="rounded px-2 py-0.5 text-slate-300 transition hover:bg-white/10">
                        {running ? 'Sembunyikan (proses tetap berjalan)' : 'Tutup (Esc)'}
                    </button>
                </div>
            </div>
        </div>
    );
}
