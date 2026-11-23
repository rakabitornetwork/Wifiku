import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('rounded-xl border bg-card text-card-foreground shadow-xs', className)} {...props} />;
}

export function CardHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b px-5 py-4">
            <div>
                <h2 className="text-sm font-semibold">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}

type StatTone = 'default' | 'green' | 'red' | 'blue' | 'amber';

const solid = 'border-transparent text-white shadow-sm';

const statTones: Record<StatTone, { card: string; icon: string; value: string; muted: string }> = {
    default: { card: '', icon: 'bg-accent text-accent-foreground', value: '', muted: 'text-muted-foreground' },
    green: { card: `${solid} bg-gradient-to-br from-emerald-500 to-teal-700`, icon: 'bg-white/20 text-white', value: 'text-white', muted: 'text-white/80' },
    red: { card: `${solid} bg-gradient-to-br from-rose-500 to-red-700`, icon: 'bg-white/20 text-white', value: 'text-white', muted: 'text-white/80' },
    blue: { card: `${solid} bg-gradient-to-br from-sky-500 to-indigo-700`, icon: 'bg-white/20 text-white', value: 'text-white', muted: 'text-white/80' },
    amber: { card: `${solid} bg-gradient-to-br from-amber-500 to-orange-700`, icon: 'bg-white/20 text-white', value: 'text-white', muted: 'text-white/90' },
};

export function StatCard({ label, value, hint, icon, tone = 'default' }: { label: string; value: string; hint?: string; icon: ReactNode; tone?: StatTone }) {
    const styles = statTones[tone];

    return (
        <Card className={cn('p-5', styles.card)}>
            <div className="flex items-center justify-between">
                <span className={cn('text-xs font-medium', styles.muted)}>{label}</span>
                <span className={cn('flex size-8 items-center justify-center rounded-lg', styles.icon)}>{icon}</span>
            </div>
            <div className={cn('num mt-3 text-2xl font-semibold tracking-tight', styles.value)}>{value}</div>
            {hint && <div className={cn('mt-1 text-xs', styles.muted)}>{hint}</div>}
        </Card>
    );
}
