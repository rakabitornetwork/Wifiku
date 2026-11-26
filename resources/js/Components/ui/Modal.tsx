import { X } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';

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

/** Lama animasi tutup (ms); jendela baru dilepas dari DOM setelahnya. Harus sama dengan durasi animate-modal-out di app.css. */
const CLOSE_MS = 200;

export function Modal(props: Props) {
    const { open, onClose } = props;
    const latest = useRef(props);
    const [mounted, setMounted] = useState(open);

    // Selama animasi tutup, isi terakhir tetap ditampilkan walau pemanggil sudah mengosongkan datanya (mis. router yang dipilih).
    if (open) {
        latest.current = props;
    }

    const { title, description, children, footer, size = 'md' } = open ? props : latest.current;

    useEffect(() => {
        if (open) {
            setMounted(true);

            return;
        }

        const timer = window.setTimeout(() => setMounted(false), CLOSE_MS);

        return () => window.clearTimeout(timer);
    }, [open]);

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

    if (!open && !mounted) {
        return null;
    }

    return (
        <div className={cn('fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4', !open && 'pointer-events-none')} role="dialog" aria-modal="true" aria-label={title} aria-hidden={!open}>
            <div className={cn('absolute inset-0 bg-[#0b1026]/60 backdrop-blur-[3px]', open ? 'animate-backdrop-in' : 'animate-backdrop-out')} onClick={onClose} />
            <div
                className={cn(
                    'relative flex max-h-[92vh] w-full flex-col rounded-t-2xl border bg-card shadow-[0_30px_80px_-20px_rgba(15,23,42,0.5)] sm:rounded-2xl',
                    open ? 'animate-modal-in' : 'animate-modal-out',
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
