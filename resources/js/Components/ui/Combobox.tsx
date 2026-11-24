import { Check, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Input } from '@/Components/ui/Field';
import { cn } from '@/lib/utils';

type ComboboxProps = {
    value: string;
    options: string[];
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
};

/**
 * Input teks dengan daftar pilihan: bisa dipilih dari dropdown atau diketik bebas.
 * Daftar menampilkan semua opsi saat dibuka lewat tombol, dan tersaring saat pengguna mengetik.
 */
export function Combobox({ value, options, onChange, placeholder, required }: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const [filtering, setFiltering] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!container.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const needle = value.trim().toLowerCase();
    const visible = filtering && needle !== '' ? options.filter((option) => option.toLowerCase().includes(needle)) : options;

    return (
        <div ref={container} className="relative">
            <Input
                value={value}
                placeholder={placeholder}
                required={required}
                autoComplete="off"
                className="pr-9"
                onChange={(event) => {
                    onChange(event.target.value);
                    setFiltering(true);
                    setOpen(true);
                }}
                onKeyDown={(event) => event.key === 'Escape' && open && (event.stopPropagation(), setOpen(false))}
            />
            <button
                type="button"
                tabIndex={-1}
                aria-label="Tampilkan pilihan"
                disabled={options.length === 0}
                onClick={(event) => {
                    event.preventDefault();
                    setFiltering(false);
                    setOpen((current) => !current);
                }}
                className="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-40"
            >
                <ChevronDown className={cn('size-4 transition', open && 'rotate-180')} />
            </button>
            {open && visible.length > 0 && (
                <ul
                    role="listbox"
                    onClick={(event) => event.preventDefault()}
                    className="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-border bg-card p-1 shadow-lg"
                >
                    {visible.map((option) => (
                        <li
                            key={option}
                            role="option"
                            aria-selected={option === value}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => {
                                onChange(option);
                                setOpen(false);
                            }}
                            className="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-sm hover:bg-muted"
                        >
                            <span className="truncate">{option}</span>
                            {option === value && <Check className="size-3.5 shrink-0 text-primary" />}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
