import { useEffect, useState } from 'react';

import { Input } from '@/Components/ui/Field';

export type CustomerOption = { id: number; customer_code: string; name: string };

type Props = {
    value: CustomerOption | null;
    onChange: (customer: CustomerOption | null) => void;
};

/** Pencarian pelanggan asinkron (kode atau nama) agar daftar pelanggan besar tidak dimuat sekaligus. */
export function CustomerPicker({ value, onChange }: Props) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<CustomerOption[]>([]);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(`/customers/search?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                setResults(response.ok ? await response.json() : []);
            } catch {
                setResults([]);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    if (value) {
        return (
            <div className="flex h-9 items-center justify-between rounded-lg border border-input bg-card px-3 text-sm">
                <span>
                    <span className="num text-muted-foreground">{value.customer_code}</span> · {value.name}
                </span>
                <button type="button" onClick={() => onChange(null)} className="text-xs text-primary hover:underline">
                    Ganti
                </button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Ketik kode atau nama pelanggan..." />
            {results.length > 0 && (
                <ul className="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border bg-card p-1 shadow-lg">
                    {results.map((customer) => (
                        <li key={customer.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    onChange(customer);
                                    setTerm('');
                                    setResults([]);
                                }}
                                className="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm hover:bg-muted"
                            >
                                <span className="num text-xs text-muted-foreground">{customer.customer_code}</span>
                                {customer.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
