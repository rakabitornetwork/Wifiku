import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { type HTMLAttributes, type ReactNode, type TdHTMLAttributes, type ThHTMLAttributes } from 'react';

import type { SortState } from '@/Hooks/useSort';
import { cn } from '@/lib/utils';

export function TableWrap({ children }: { children: ReactNode }) {
    return <div className="overflow-x-auto">{children}</div>;
}

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
    return <table className={cn('w-full text-left text-sm', className)} {...props} />;
}

/** Judul kolom selalu di tengah; perataan kiri/kanan dari pemanggil diabaikan agar seragam di semua halaman. */
export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
    const cleaned = className?.replace(/\btext-(left|right|start|end)\b/g, '').trim();

    return <th className={cn('border-b bg-muted/50 px-4 py-2.5 text-center text-xs font-medium whitespace-nowrap text-muted-foreground', cleaned)} {...props} />;
}

export function Td({ className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
    return <td className={cn('border-b px-4 py-3 align-middle', className)} {...props} />;
}

export function EmptyState({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-2 px-6 py-14 text-center">
            <div className="text-sm font-medium">{title}</div>
            {description && <p className="max-w-sm text-xs text-muted-foreground">{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}

/** Judul kolom yang bisa diklik untuk mengurutkan (naik → turun → bawaan). */
export function SortTh({ column, state, className, children }: { column: string; state: SortState; className?: string; children: ReactNode }) {
    const active = state.sort === column;
    const Icon = !active ? ArrowUpDown : state.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <Th className={className} aria-sort={active ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none'}>
            <button
                type="button"
                onClick={() => state.toggle(column)}
                className={cn('inline-flex items-center gap-1 font-medium hover:text-foreground', active && 'text-foreground')}
            >
                {children}
                <Icon className={cn('size-3.5', !active && 'opacity-50')} />
            </button>
        </Th>
    );
}
