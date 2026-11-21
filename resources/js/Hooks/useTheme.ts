import { useCallback, useEffect, useState } from 'react';

type Theme = 'light' | 'dark';

export function useTheme(): { theme: Theme; toggleTheme: () => void } {
    const [theme, setTheme] = useState<Theme>('light');

    useEffect(() => {
        setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    }, []);

    const toggleTheme = useCallback(() => {
        const next: Theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

        document.documentElement.classList.add('[&_*]:!transition-colors', '[&_*]:!duration-200');
        document.documentElement.classList.toggle('dark', next === 'dark');
        window.setTimeout(() => document.documentElement.classList.remove('[&_*]:!transition-colors', '[&_*]:!duration-200'), 250);

        try {
            localStorage.setItem('theme', next);
        } catch {
            // penyimpanan tidak tersedia, preferensi tidak disimpan
        }

        setTheme(next);
    }, []);

    return { theme, toggleTheme };
}
