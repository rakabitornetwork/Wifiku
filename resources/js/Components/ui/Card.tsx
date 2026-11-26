import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('rounded-2xl border bg-card text-card-foreground shadow-[0_1px_2px_rgba(15,23,42,0.05),0_14px_30px_-18px_rgba(15,23,42,0.22)] dark:shadow-[0_1px_0_rgba(255,255,255,0.03)_inset]', className)}
            {...props}
        />
    );
}

export function CardHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b px-6 py-4">
            <div>
                <h2 className="text-[15px] font-semibold tracking-tight">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}

type StatTone = 'default' | 'green' | 'red' | 'blue' | 'amber' | 'violet' | 'slate';

/**
 * Kartu angka ringkas dengan isian gradasi yang menyampaikan makna: hijau = baik/pemasukan, merah = masalah/pengeluaran,
 * kuning = perlu perhatian, biru = netral/informasi, ungu = harga/nilai, abu = belum diketahui.
 */
const solid = 'border-transparent text-white';

const statTones: Record<StatTone, { card: string; icon: string }> = {
    default: { card: `${solid} bg-gradient-to-br from-indigo-500 to-violet-700 shadow-lg shadow-indigo-600/25`, icon: 'bg-white/20 text-white' },
    green: { card: `${solid} bg-gradient-to-br from-emerald-500 to-teal-700 shadow-lg shadow-emerald-600/25`, icon: 'bg-white/20 text-white' },
    red: { card: `${solid} bg-gradient-to-br from-rose-500 to-red-700 shadow-lg shadow-rose-600/25`, icon: 'bg-white/20 text-white' },
    blue: { card: `${solid} bg-gradient-to-br from-sky-500 to-indigo-700 shadow-lg shadow-sky-600/25`, icon: 'bg-white/20 text-white' },
    amber: { card: `${solid} bg-gradient-to-br from-amber-500 to-orange-700 shadow-lg shadow-amber-600/25`, icon: 'bg-white/20 text-white' },
    violet: { card: `${solid} bg-gradient-to-br from-violet-500 to-fuchsia-700 shadow-lg shadow-violet-600/25`, icon: 'bg-white/20 text-white' },
    slate: { card: `${solid} bg-gradient-to-br from-slate-500 to-slate-700 shadow-lg shadow-slate-600/20`, icon: 'bg-white/20 text-white' },
};

export function StatCard({ label, value, hint, icon, tone = 'default' }: { label: string; value: string; hint?: string; icon: ReactNode; tone?: StatTone }) {
    const styles = statTones[tone];

    return (
        <Card className={cn('relative overflow-hidden p-5', styles.card)}>
            <span className="pointer-events-none absolute -top-10 -right-8 size-32 rounded-full bg-white/10" />
            <div className="relative flex items-center justify-between gap-3">
                <span className="text-sm font-medium text-white/85">{label}</span>
                <span className={cn('flex size-9 items-center justify-center rounded-xl ring-1 ring-white/20', styles.icon)}>{icon}</span>
            </div>
            <div className="num relative mt-3 text-3xl font-semibold tracking-tight">{value}</div>
            {hint && <div className="relative mt-1 text-xs text-white/80">{hint}</div>}
        </Card>
    );
}
