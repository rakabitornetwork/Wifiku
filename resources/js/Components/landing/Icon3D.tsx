import { type ReactNode, useId } from 'react';

import { cn } from '@/lib/utils';

export type Icon3DName = 'wifi' | 'signal' | 'router' | 'shield' | 'zap' | 'radar' | 'lock' | 'headphones' | 'chat' | 'mail' | 'pin' | 'clock';

type Paint = { face: string; deep: string; glow: string };

/**
 * Glyph berkesan 3D: permukaan terang bergradasi, bayangan jatuh, dan kilau di atas ubin kaca berwarna.
 * Semua bentuk digambar langsung sebagai SVG agar tajam di layar apa pun dan tidak butuh berkas gambar.
 */
const glyphs: Record<Icon3DName, (paint: Paint) => ReactNode> = {
    wifi: ({ face, glow }) => (
        <>
            <g fill="none" stroke={face} strokeWidth="5" strokeLinecap="round">
                <path d="M24.9 38.9A10 10 0 0 1 39.1 38.9" />
                <path d="M18.6 32.6A19 19 0 0 1 45.4 32.6" />
                <path d="M12.2 26.2A28 28 0 0 1 51.8 26.2" />
            </g>
            <circle cx="32" cy="46" r="4.5" fill={glow} />
        </>
    ),
    signal: ({ face, glow }) => (
        <>
            <rect x="12" y="36" width="7.5" height="12" rx="3" fill={face} opacity="0.7" />
            <rect x="23" y="28" width="7.5" height="20" rx="3" fill={face} opacity="0.82" />
            <rect x="34" y="20" width="7.5" height="28" rx="3" fill={face} />
            <rect x="45" y="12" width="7.5" height="36" rx="3" fill={glow} />
        </>
    ),
    router: ({ face, glow, deep }) => (
        <>
            <rect x="15" y="15" width="4.5" height="22" rx="2.25" fill={face} />
            <rect x="44.5" y="15" width="4.5" height="22" rx="2.25" fill={face} />
            <rect x="9" y="33" width="46" height="16" rx="6" fill={face} />
            <rect x="9" y="43" width="46" height="6" rx="3" fill={deep} opacity="0.18" />
            <circle cx="20" cy="41" r="2.4" fill={glow} />
            <circle cx="28" cy="41" r="2.4" fill={glow} />
            <circle cx="36" cy="41" r="2.4" fill={glow} opacity="0.55" />
            <rect x="43" y="39.5" width="6" height="3" rx="1.5" fill={deep} opacity="0.35" />
        </>
    ),
    shield: ({ face, deep }) => (
        <>
            <path d="M32 9 50 16.5V31c0 12-8 20-18 24-10-4-18-12-18-24V16.5Z" fill={face} />
            <path d="M23.5 32.5 29.5 38.5 41.5 25" fill="none" stroke={deep} strokeWidth="5" strokeLinecap="round" strokeLinejoin="round" />
        </>
    ),
    zap: ({ face, glow }) => (
        <>
            <path d="M37 8 16 36h13l-3 20 22-30H34Z" fill={face} />
            <path d="M37 8 34 26h14Z" fill={glow} opacity="0.55" />
        </>
    ),
    radar: ({ face, glow }) => (
        <>
            <circle cx="32" cy="32" r="22" fill="none" stroke={face} strokeWidth="3" opacity="0.9" />
            <circle cx="32" cy="32" r="13" fill="none" stroke={face} strokeWidth="3" opacity="0.65" />
            <path d="M32 32V10a22 22 0 0 1 19 11Z" fill={glow} opacity="0.55" />
            <circle cx="44" cy="22" r="3.2" fill={glow} />
            <circle cx="32" cy="32" r="4" fill={face} />
        </>
    ),
    lock: ({ face, deep }) => (
        <>
            <path d="M22 29v-6a10 10 0 0 1 20 0v6" fill="none" stroke={face} strokeWidth="5" strokeLinecap="round" />
            <rect x="14" y="28" width="36" height="27" rx="8" fill={face} />
            <circle cx="32" cy="39.5" r="3.6" fill={deep} />
            <rect x="30.5" y="41.5" width="3" height="7" rx="1.5" fill={deep} />
        </>
    ),
    headphones: ({ face, glow }) => (
        <>
            <path d="M14 38v-6a18 18 0 0 1 36 0v6" fill="none" stroke={face} strokeWidth="5" strokeLinecap="round" />
            <rect x="9.5" y="35" width="12" height="19" rx="5.5" fill={face} />
            <rect x="42.5" y="35" width="12" height="19" rx="5.5" fill={face} />
            <path d="M48.5 54c0 5-4 7-11 7" fill="none" stroke={glow} strokeWidth="3" strokeLinecap="round" />
        </>
    ),
    chat: ({ face, deep }) => (
        <>
            <rect x="9" y="11" width="46" height="33" rx="11" fill={face} />
            <path d="M21 42 19 55l14-12Z" fill={face} />
            <circle cx="22" cy="27.5" r="3.2" fill={deep} />
            <circle cx="32" cy="27.5" r="3.2" fill={deep} />
            <circle cx="42" cy="27.5" r="3.2" fill={deep} />
        </>
    ),
    mail: ({ face, deep }) => (
        <>
            <rect x="9" y="15" width="46" height="34" rx="8" fill={face} />
            <path d="M13 21.5 32 36 51 21.5" fill="none" stroke={deep} strokeWidth="3.6" strokeLinecap="round" strokeLinejoin="round" />
        </>
    ),
    pin: ({ face, deep }) => (
        <>
            <path d="M32 8C21 8 14 16 14 25c0 13 18 31 18 31s18-18 18-31C50 16 43 8 32 8Z" fill={face} />
            <circle cx="32" cy="25" r="7.5" fill={deep} />
        </>
    ),
    clock: ({ face, deep }) => (
        <>
            <circle cx="32" cy="32" r="23" fill={face} />
            <path d="M32 18v14l9.5 6" fill="none" stroke={deep} strokeWidth="4.2" strokeLinecap="round" strokeLinejoin="round" />
        </>
    ),
};

export function isIcon3DName(value: string): value is Icon3DName {
    return value in glyphs;
}

export function Icon3D({ name, className }: { name: Icon3DName; className?: string }) {
    const uid = useId().replace(/:/g, '');
    const paint: Paint = { face: `url(#${uid}f)`, deep: `url(#${uid}d)`, glow: `url(#${uid}g)` };

    return (
        <svg viewBox="0 0 64 64" role="img" aria-hidden="true" className={cn('size-14 shrink-0', className)}>
            <defs>
                <linearGradient id={`${uid}t`} x1="0.15" y1="0" x2="0.85" y2="1">
                    <stop offset="0" stopColor="#8b8fff" />
                    <stop offset="0.55" stopColor="#5b4cf0" />
                    <stop offset="1" stopColor="#4a2fc4" />
                </linearGradient>
                <linearGradient id={`${uid}s`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#fff" stopOpacity="0.55" />
                    <stop offset="1" stopColor="#fff" stopOpacity="0" />
                </linearGradient>
                <linearGradient id={`${uid}f`} x1="0" y1="0" x2="0.4" y2="1">
                    <stop offset="0" stopColor="#ffffff" />
                    <stop offset="1" stopColor="#c9ccff" />
                </linearGradient>
                <linearGradient id={`${uid}d`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#5b4cf0" />
                    <stop offset="1" stopColor="#3b2aa8" />
                </linearGradient>
                <linearGradient id={`${uid}g`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#a5f3fc" />
                    <stop offset="1" stopColor="#22d3ee" />
                </linearGradient>
                <filter id={`${uid}w`} x="-20%" y="-20%" width="140%" height="150%">
                    <feDropShadow dx="0" dy="2.2" stdDeviation="1.6" floodColor="#1e1065" floodOpacity="0.45" />
                </filter>
                <clipPath id={`${uid}c`}>
                    <rect x="2" y="2" width="60" height="60" rx="17" />
                </clipPath>
            </defs>

            <rect x="2" y="4" width="60" height="60" rx="17" fill="#312e81" opacity="0.35" />
            <rect x="2" y="2" width="60" height="60" rx="17" fill={`url(#${uid}t)`} />
            <g clipPath={`url(#${uid}c)`}>
                <ellipse cx="26" cy="-2" rx="44" ry="30" fill={`url(#${uid}s)`} />
                <rect x="2" y="2" width="60" height="60" rx="17" fill="none" stroke="#fff" strokeOpacity="0.28" strokeWidth="1.5" />
            </g>
            <g filter={`url(#${uid}w)`}>{glyphs[name](paint)}</g>
        </svg>
    );
}
