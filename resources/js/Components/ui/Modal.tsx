import { X } from 'lucide-react';
import { type ReactNode, useEffect } from 'react';

import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    title: string;
    description?: string;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'md' | 'lg';
};

export function Modal({ open, title, description, onClose, children, footer, size = 'md' }: Props) {
    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label={title}>
            <div className="absolute inset-0 animate-fade-in bg-[#0b1026]/60 backdrop-blur-[3px]" onClick={onClose} />
            <div
                className={cn(
                    'relative flex max-h-[92vh] w-full animate-fade-in flex-col rounded-t-2xl border bg-card shadow-[0_30px_80px_-20px_rgba(15,23,42,0.5)] sm:rounded-2xl',
                    size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-lg',
                )}
            >
                <div className="flex items-start justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
                        {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
                    </div>
                    <Button variant="ghost" size="icon" onClick={onClose} aria-label="Tutup">
                        <X className="size-4" />
                    </Button>
                </div>
                <div className="overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t px-5 py-3">{footer}</div>}
            </div>
        </div>
    );
}
