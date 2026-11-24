import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export type SortDirection = 'asc' | 'desc';
export type SortState = { sort: string; direction: SortDirection; toggle: (column: string) => void };

/** Urutan berikutnya: naik → turun → urutan bawaan (kosong). */
function nextSort(current: { sort: string; direction: SortDirection }, column: string): { sort: string; direction: SortDirection } {
    if (current.sort !== column) {
        return { sort: column, direction: 'asc' };
    }

    return current.direction === 'asc' ? { sort: column, direction: 'desc' } : { sort: '', direction: 'asc' };
}

/**
 * Sort sisi server untuk tabel berpaginasi: `sort` & `direction` tersimpan di URL,
 * filter/pencarian lain dipertahankan, dan halaman kembali ke 1.
 */
export function useServerSort(): SortState {
    const params = new URLSearchParams(window.location.search);
    const sort = params.get('sort') ?? '';
    const direction: SortDirection = params.get('direction') === 'desc' ? 'desc' : 'asc';

    const toggle = (column: string) => {
        const next = nextSort({ sort, direction }, column);
        const query = Object.fromEntries(new URLSearchParams(window.location.search));
        delete query.page;
        delete query.sort;
        delete query.direction;

        router.get(window.location.pathname, { ...query, ...(next.sort ? next : {}) }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return { sort, direction, toggle };
}

/** Sort sisi klien untuk tabel yang seluruh datanya sudah dimuat (tanpa paginasi). */
export function useClientSort<T>(rows: T[], accessors: Record<string, (row: T) => string | number | null | undefined>): SortState & { rows: T[] } {
    const [state, setState] = useState<{ sort: string; direction: SortDirection }>({ sort: '', direction: 'asc' });

    const sorted = useMemo(() => {
        const accessor = accessors[state.sort];

        if (!accessor) {
            return rows;
        }

        const factor = state.direction === 'asc' ? 1 : -1;

        return [...rows].sort((a, b) => {
            const left = accessor(a);
            const right = accessor(b);

            if (left == null && right == null) {
                return 0;
            }

            if (left == null) {
                return 1;
            }

            if (right == null) {
                return -1;
            }

            if (typeof left === 'number' && typeof right === 'number') {
                return (left - right) * factor;
            }

            return String(left).localeCompare(String(right), 'id', { numeric: true, sensitivity: 'base' }) * factor;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows, state]);

    return { ...state, rows: sorted, toggle: (column) => setState((current) => nextSort(current, column)) };
}
