import { cn } from '@/lib/utils';

type Tone = 'green' | 'red' | 'amber' | 'blue' | 'slate';

const tones: Record<Tone, string> = {
    green: 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/25 dark:text-emerald-300',
    red: 'bg-red-500/12 text-red-700 ring-red-500/25 dark:text-red-300',
    amber: 'bg-amber-500/14 text-amber-700 ring-amber-500/30 dark:text-amber-300',
    blue: 'bg-sky-500/12 text-sky-700 ring-sky-500/25 dark:text-sky-300',
    slate: 'bg-slate-500/12 text-slate-700 ring-slate-500/25 dark:text-slate-300',
};

const statuses: Record<string, { label: string; tone: Tone }> = {
    online: { label: 'Online', tone: 'green' },
    offline: { label: 'Offline', tone: 'red' },
    unknown: { label: 'Belum dicek', tone: 'slate' },
    active: { label: 'Aktif', tone: 'green' },
    isolated: { label: 'Isolir', tone: 'red' },
    inactive: { label: 'Nonaktif', tone: 'slate' },
    paid: { label: 'Lunas', tone: 'green' },
    unpaid: { label: 'Belum bayar', tone: 'amber' },
    partial: { label: 'Sebagian', tone: 'blue' },
    cancelled: { label: 'Batal', tone: 'slate' },
    unused: { label: 'Belum dipakai', tone: 'blue' },
    used: { label: 'Terpakai', tone: 'green' },
    expired: { label: 'Kedaluwarsa', tone: 'slate' },
    disabled: { label: 'Dinonaktifkan', tone: 'slate' },
    in_stock: { label: 'Di gudang', tone: 'blue' },
    installed: { label: 'Terpasang', tone: 'green' },
    broken: { label: 'Rusak', tone: 'red' },
    returned: { label: 'Dikembalikan', tone: 'slate' },
    queued: { label: 'Antre', tone: 'amber' },
    processing: { label: 'Diproses', tone: 'blue' },
    completed: { label: 'Selesai', tone: 'green' },
    failed: { label: 'Gagal', tone: 'red' },
};

export function Badge({ tone = 'slate', children, className }: { tone?: Tone; children: React.ReactNode; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', tones[tone], className)}>
            {children}
        </span>
    );
}

/** Badge status berwarna kontras; `active` (voucher terpakai) dipetakan lewat prop `variant`. */
export function StatusBadge({ status }: { status: string }) {
    const config = statuses[status] ?? { label: status, tone: 'slate' as Tone };

    return (
        <Badge tone={config.tone}>
            <span className="size-1.5 rounded-full bg-current" />
            {config.label}
        </Badge>
    );
}
