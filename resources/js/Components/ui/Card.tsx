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

export function StatCard({ label, value, hint, icon }: { label: string; value: string; hint?: string; icon: ReactNode }) {
    return (
        <Card className="p-5">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">{label}</span>
                <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-accent-foreground">{icon}</span>
            </div>
            <div className="num mt-3 text-2xl font-semibold tracking-tight">{value}</div>
            {hint && <div className="mt-1 text-xs text-muted-foreground">{hint}</div>}
        </Card>
    );
}
