import { Link, router } from '@inertiajs/react';

import { Select } from '@/Components/ui/Field';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

/** Tautan pertama dan terakhir dari Laravel adalah "Previous" dan "Next"; label lainnya nomor halaman atau "...". */
function linkText(label: string, index: number, last: number): string {
    if (index === 0) {
        return '‹ Sebelumnya';
    }

    if (index === last) {
        return 'Berikutnya ›';
    }

    return label;
}

/** Ganti jumlah data per halaman dengan mempertahankan filter/pencarian yang sedang aktif, lalu kembali ke halaman 1. */
function changePerPage(perPage: number): void {
    const params = Object.fromEntries(new URLSearchParams(window.location.search));
    delete params.page;

    router.get(window.location.pathname, { ...params, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

export function Pagination({ meta }: { meta: Paginated<unknown> }) {
    // Selector tetap tampil selama data lebih banyak dari pilihan terkecil, agar bisa dikembalikan ke jumlah yang lebih kecil.
    if (meta.last_page <= 1 && meta.total <= PER_PAGE_OPTIONS[0]) {
        return null;
    }

    const last = meta.links.length - 1;
    const options = Array.from(new Set([...PER_PAGE_OPTIONS, meta.per_page])).sort((a, b) => a - b);

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 lg:flex-row">
            <div className="flex flex-col items-center gap-2 sm:flex-row sm:gap-4">
                <p className="num text-xs text-muted-foreground">
                    Menampilkan {meta.from}–{meta.to} dari {meta.total} data{meta.last_page > 1 && ` · Halaman ${meta.current_page} dari ${meta.last_page}`}
                </p>
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    Tampilkan
                    <Select className="h-8 w-20 py-0 text-xs" value={meta.per_page} onChange={(event) => changePerPage(Number(event.target.value))} aria-label="Jumlah data per halaman">
                        {options.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                    </Select>
                    data per halaman
                </label>
            </div>
            {meta.last_page > 1 && (
                <nav className="flex flex-wrap justify-center gap-1" aria-label="Pindah halaman">
                    {meta.links.map((link, index) => {
                        const text = linkText(link.label, index, last);

                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                className={cn(
                                    'num rounded-md px-2.5 py-1 text-xs transition',
                                    link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {text}
                            </Link>
                        ) : (
                            <span key={index} className="rounded-md px-2.5 py-1 text-xs text-muted-foreground/40">
                                {text}
                            </span>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
