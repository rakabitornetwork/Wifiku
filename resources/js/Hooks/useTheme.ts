import { useCallback, useEffect, useState } from 'react';
import { flushSync } from 'react-dom';

type Theme = 'light' | 'dark';

/**
 * Ganti tema dengan transisi halus: View Transitions API melakukan crossfade seluruh halaman
 * (termasuk gradasi dan blur yang tidak bisa dianimasikan CSS biasa). Browser tanpa dukungan
 * memakai transisi warna CSS sebagai cadangan.
 */
export function useTheme(): { theme: Theme; toggleTheme: () => void } {
    const [theme, setTheme] = useState<Theme>('light');

    useEffect(() => {
        setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    }, []);

    const toggleTheme = useCallback(() => {
        const root = document.documentElement;
        const next: Theme = root.classList.contains('dark') ? 'light' : 'dark';

        const apply = () => {
            root.classList.toggle('dark', next === 'dark');
            flushSync(() => setTheme(next));
        };

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reducedMotion) {
            apply();
        } else if (typeof document.startViewTransition === 'function') {
            document.startViewTransition(apply);
        } else {
            root.classList.add('theme-transition');
            apply();
            window.setTimeout(() => root.classList.remove('theme-transition'), 400);
        }

        try {
            localStorage.setItem('theme', next);
        } catch {
            // penyimpanan tidak tersedia, preferensi tidak disimpan
        }
    }, []);

    return { theme, toggleTheme };
}
