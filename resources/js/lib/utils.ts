import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

export function formatRupiah(value: number | null | undefined): string {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value ?? 0);
}

export function formatNumber(value: number | null | undefined): string {
    return new Intl.NumberFormat('id-ID').format(value ?? 0);
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

export function formatDuration(minutes: number): string {
    if (minutes % 1440 === 0) {
        return `${minutes / 1440} hari`;
    }

    if (minutes % 60 === 0) {
        return `${minutes / 60} jam`;
    }

    return `${minutes} menit`;
}
