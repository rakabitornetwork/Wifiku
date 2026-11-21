import { type HTMLAttributes, type ReactNode, type TdHTMLAttributes, type ThHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

export function TableWrap({ children }: { children: ReactNode }) {
    return <div className="overflow-x-auto">{children}</div>;
}

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
    return <table className={cn('w-full text-left text-sm', className)} {...props} />;
}

export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
    return <th className={cn('border-b bg-muted/50 px-4 py-2.5 text-xs font-medium whitespace-nowrap text-muted-foreground', className)} {...props} />;
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
