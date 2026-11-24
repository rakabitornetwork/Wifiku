import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Input } from '@/Components/ui/Field';

type Props = {
    value: string;
    placeholder?: string;
    extra?: Record<string, string | undefined>;
};

/** Pencarian server-side dengan debounce; mempertahankan filter lain lewat `extra`. */
export function SearchInput({ value, placeholder = 'Cari...', extra = {} }: Props) {
    const [term, setTerm] = useState(value);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            const current = new URLSearchParams(window.location.search);
            const sort = current.get('sort');

            router.get(window.location.pathname, { ...extra, search: term || undefined, ...(sort ? { sort, direction: current.get('direction') ?? 'asc' } : {}) }, { preserveState: true, replace: true });
        }, 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    return (
        <div className="relative w-full sm:w-72">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder={placeholder} className="pl-9" />
        </div>
    );
}
