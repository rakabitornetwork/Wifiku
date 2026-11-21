import { router } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';
import type { Toast } from '@/types';

type Item = Toast & { id: number };

/** Menampilkan flash `toast` dari server pada setiap respons Inertia. */
export function Toaster() {
    const [items, setItems] = useState<Item[]>([]);

    useEffect(() => {
        return router.on('flash', (event) => {
            const toast = event.detail.flash.toast;

            if (!toast) {
                return;
            }

            const id = Date.now() + Math.random();
            setItems((current) => [...current, { ...toast, id }]);
            window.setTimeout(() => setItems((current) => current.filter((item) => item.id !== id)), 4500);
        });
    }, []);

    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite">
            {items.map((item) => (
                <div
                    key={item.id}
                    className={cn(
                        'glass pointer-events-auto flex animate-fade-in items-start gap-2.5 rounded-xl border px-4 py-3 text-sm shadow-lg',
                        item.type === 'error' && 'border-destructive/40',
                    )}
                >
                    {item.type === 'success' ? (
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                    ) : (
                        <XCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                    )}
                    <span>{item.message}</span>
                </div>
            ))}
        </div>
    );
}
