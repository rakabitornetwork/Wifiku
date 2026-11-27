import { type CSSProperties, type ElementType, type ReactNode, useEffect, useRef } from 'react';

import { cn } from '@/lib/utils';

type Props = {
    as?: ElementType;
    /** Animasikan anak-anaknya satu per satu (berurutan), bukan elemen ini sekaligus. */
    stagger?: boolean;
    /** Jeda awal dalam milidetik. */
    delay?: number;
    className?: string;
    children: ReactNode;
};

/**
 * Memunculkan konten dengan gerak halus saat masuk layar.
 * Konten baru disembunyikan setelah JavaScript aktif, jadi tanpa JS / untuk mesin pencari tetap terlihat.
 */
export function Reveal({ as: Tag = 'div', stagger = false, delay = 0, className, children }: Props) {
    const ref = useRef<HTMLElement>(null);

    useEffect(() => {
        const element = ref.current;

        if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
            return;
        }

        if (stagger) {
            Array.from(element.children).forEach((child, index) => (child as HTMLElement).style.setProperty('--i', String(index)));
        }

        // Yang sudah terlihat saat halaman dibuka tidak perlu disembunyikan dulu.
        if (element.getBoundingClientRect().top < window.innerHeight * 0.9) {
            return;
        }

        element.dataset.reveal = 'hidden';

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    element.dataset.reveal = 'shown';
                    observer.disconnect();
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [stagger]);

    return (
        <Tag ref={ref} className={cn(stagger ? 'reveal-stagger' : 'reveal', className)} style={{ '--reveal-delay': `${delay}ms` } as CSSProperties}>
            {children}
        </Tag>
    );
}
