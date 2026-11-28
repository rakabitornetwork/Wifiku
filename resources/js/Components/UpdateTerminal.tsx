import { Minus, Plus, Square, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

export type TerminalStep = { key: string; label: string; status: 'pending' | 'running' | 'done' | 'failed' | 'skipped'; output: string | null };

type Props = {
    open: boolean;
    status: 'idle' | 'queued' | 'running' | 'succeeded' | 'failed';
    steps: TerminalStep[];
    error?: string | null;
    user: string;
    host: string;
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

/** Palet Tango bawaan GNOME Terminal di Ubuntu: latar aubergine, prompt hijau/biru tebal seperti PS1 bawaan ~/.bashrc. */
const palette = { background: '#300a24', foreground: '#eeeeec', green: '#8ae234', blue: '#729fcf', red: '#ef2929', yellow: '#fce94f', gray: '#babdb6' };

const SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Jeda acak antara min dan max (ms) agar ritme ketikan tidak seragam seperti mesin. */
const between = (min: number, max: number) => min + Math.random() * (max - min);

/**
 * Jeda sebelum karakter berikutnya, meniru orang mengetik: lebih lama setelah spasi dan tanda baca,
 * sesekali tersendat, dan sangat cepat untuk huruf yang berurutan.
 */
function keystrokeDelay(previous: string | undefined): number {
    if (previous === ' ') {
        return between(90, 200);
    }

    if (previous === '-' || previous === ':' || previous === '/') {
        return between(70, 150);
    }

    return Math.random() < 0.08 ? between(180, 320) : between(35, 95);
}

function Cursor({ blink }: { blink: boolean }) {
    return <span aria-hidden className={cn('inline-block h-[1.15em] w-[0.6em] translate-y-[0.2em]', blink && 'animate-terminal-blink')} style={{ backgroundColor: palette.foreground }} />;
}

function Prompt({ user, host }: { user: string; host: string }) {
    return (
        <>
            <span className="font-bold" style={{ color: palette.green }}>
                {user}@{host}
            </span>
            :<span className="font-bold" style={{ color: palette.blue }}>~/app</span>${' '}
        </>
    );
}

type BlockProps = { step: TerminalStep; canStart: boolean; user: string; host: string; tick: number; onEntered: (key: string) => void };

/**
 * Satu perintah di terminal: prompt muncul, perintah diketik seperti manusia (kursor blok diam selama mengetik),
 * jeda singkat seolah Enter ditekan, lalu keluaran tampil. Langkah yang sudah selesai saat terminal dibuka
 * ditampilkan langsung tanpa animasi.
 */
function CommandBlock({ step, canStart, user, host, tick, onEntered }: BlockProps) {
    const text = commands[step.key] ?? step.key;
    const [animated] = useState(() => step.status === 'running' && !prefersReducedMotion());
    const [shown, setShown] = useState(animated ? 0 : text.length);
    const [entered, setEntered] = useState(!animated);

    useEffect(() => {
        if (!animated && canStart) {
            onEntered(step.key);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canStart]);

    useEffect(() => {
        if (!animated || !canStart || entered) {
            return;
        }

        // Jeda "berpikir" sebelum huruf pertama, ketikan per huruf, lalu jeda sebelum Enter.
        const delay = shown === 0 ? between(350, 650) : shown < text.length ? keystrokeDelay(text[shown - 1]) : between(220, 400);
        const timer = window.setTimeout(() => {
            if (shown < text.length) {
                setShown(shown + 1);
            } else {
                setEntered(true);
                onEntered(step.key);
            }
        }, delay);

        return () => window.clearTimeout(timer);
    }, [animated, canStart, entered, shown, text, step.key, onEntered]);

    if (!canStart) {
        return null;
    }

    return (
        <div>
            <p className="break-all">
                <Prompt user={user} host={host} />
                {text.slice(0, shown)}
                {!entered && <Cursor blink={false} />}
            </p>
            {entered && (
                <>
                    {step.status === 'running' && (
                        <p style={{ color: palette.yellow }}>
                            {SPINNER[tick % SPINNER.length]} {step.label}
                        </p>
                    )}
                    {step.output && <pre className="break-all whitespace-pre-wrap" style={{ color: palette.gray }}>{step.output}</pre>}
                    {step.status === 'done' && <p style={{ color: palette.green }}>✓ {step.label}</p>}
                    {step.status === 'skipped' && <p style={{ color: palette.gray }}>– {step.label} (dilewati)</p>}
                    {step.status === 'failed' && <p style={{ color: palette.red }}>✗ {step.label} gagal</p>}
                </>
            )}
        </div>
    );
}

/**
 * Popup yang meniru GNOME Terminal di Ubuntu (headerbar Yaru, latar aubergine, prompt bawaan bash) untuk
 * menampilkan progres pembaruan aplikasi.
 */
export function UpdateTerminal({ open, status, steps, error, user, host, onClose }: Props) {
    const body = useRef<HTMLDivElement>(null);
    const running = status === 'queued' || status === 'running';
    const [entered, setEntered] = useState<Record<string, boolean>>({});
    const [tick, setTick] = useState(0);
    const visible = steps.filter((step) => step.status !== 'pending');
    const allEntered = visible.every((step) => entered[step.key]);

    const onEntered = useCallback((key: string) => setEntered((current) => (current[key] ? current : { ...current, [key]: true })), []);

    // Spinner hanya berputar selama ada langkah yang berjalan.
    useEffect(() => {
        if (!open || !running) {
            return;
        }

        const timer = window.setInterval(() => setTick((value) => value + 1), 80);

        return () => window.clearInterval(timer);
    }, [open, running]);

    // Mulai dari awal setiap kali terminal ditutup, agar dibuka ulang tanpa sisa animasi lama.
    useEffect(() => {
        if (!open) {
            setEntered({});
        }
    }, [open]);

    // Seperti terminal sungguhan: setiap keluaran baru menggulir ke baris paling bawah.
    useEffect(() => {
        body.current?.scrollTo({ top: body.current.scrollHeight });
    });

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

    const title = `${user}@${host}: ~/app`;
    const windowButton = 'flex size-6 items-center justify-center rounded-full bg-white/10 text-white/90 transition hover:bg-white/20';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Terminal pembaruan aplikasi" onClick={onClose}>
            <div className="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-xl rounded-b-md shadow-2xl ring-1 shadow-black/70 ring-black/50" onClick={(event) => event.stopPropagation()}>
                <div className="relative flex h-11 shrink-0 items-center bg-[#303030] px-2 text-white select-none">
                    <span className="flex size-8 items-center justify-center rounded-md text-white/80" aria-hidden>
                        <Plus className="size-4" />
                    </span>
                    <p className="pointer-events-none absolute inset-x-24 truncate text-center text-[13px] font-bold">{title}</p>
                    <div className="ml-auto flex items-center gap-3 pr-1">
                        <span className={windowButton} aria-hidden>
                            <Minus className="size-3.5" />
                        </span>
                        <span className={windowButton} aria-hidden>
                            <Square className="size-3" />
                        </span>
                        <button type="button" onClick={onClose} className={cn(windowButton, 'border-transparent bg-gradient-to-br from-rose-500 to-red-700 text-white hover:brightness-110')} aria-label={running ? 'Sembunyikan terminal (proses tetap berjalan)' : 'Tutup terminal'} title={running ? 'Sembunyikan (proses tetap berjalan)' : 'Tutup'}>
                            <X className="size-3.5" />
                        </button>
                    </div>
                </div>

                <div
                    ref={body}
                    className="min-h-72 flex-1 overflow-auto px-2 py-1.5 text-[14px] leading-[1.3]"
                    style={{ backgroundColor: palette.background, color: palette.foreground, fontFamily: "'Ubuntu Mono', 'DejaVu Sans Mono', 'Liberation Mono', monospace" }}
                >
                    {visible.map((step, index) => (
                        <CommandBlock key={step.key} step={step} canStart={index === 0 || Boolean(entered[visible[index - 1].key])} user={user} host={host} tick={tick} onEntered={onEntered} />
                    ))}

                    {allEntered && error && <p className="break-words" style={{ color: palette.red }}>error: {error}</p>}
                    {allEntered && status === 'succeeded' && <p style={{ color: palette.green }}>Pembaruan selesai. Halaman dimuat ulang otomatis…</p>}

                    {/* Selama perintah berjalan kursor menunggu di baris kosong; setelah selesai prompt baru muncul. */}
                    {allEntered && (
                        <p>
                            {!running && <Prompt user={user} host={host} />}
                            <Cursor blink />
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
