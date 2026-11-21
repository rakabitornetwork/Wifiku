import { cn } from '@/lib/utils';

export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('animate-pulse rounded-md bg-muted', className)} />;
}

export function PageSkeleton() {
    return (
        <div className="space-y-6" aria-busy="true" aria-label="Memuat">
            <div className="space-y-2">
                <Skeleton className="h-7 w-56" />
                <Skeleton className="h-4 w-80 max-w-full" />
            </div>
            <div className="rounded-xl border bg-card p-5">
                <Skeleton className="mb-5 h-9 w-72 max-w-full" />
                <div className="space-y-3">
                    {Array.from({ length: 7 }).map((_, index) => (
                        <Skeleton key={index} className="h-9 w-full" />
                    ))}
                </div>
            </div>
        </div>
    );
}
