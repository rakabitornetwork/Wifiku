import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

export type PaymentChannel = { code: string; label: string; group: string };

type Props = {
    channels: PaymentChannel[];
    value: string;
    onChange: (code: string) => void;
    /** Kelas untuk pilihan terpilih; menyesuaikan warna halaman pemanggil. */
    activeClass?: string;
};

/**
 * Pilihan metode pembayaran untuk pelanggan: dikelompokkan (QRIS, Transfer Bank, Minimarket, dst) dengan nama yang jelas,
 * bukan kode gateway mentah seperti "BRIVA" atau "BCAVA".
 */
export function PaymentChannelPicker({ channels, value, onChange, activeClass = 'border-primary bg-accent text-accent-foreground' }: Props) {
    const groups = channels.reduce<Record<string, PaymentChannel[]>>((carry, channel) => {
        (carry[channel.group] ??= []).push(channel);

        return carry;
    }, {});

    return (
        <div className="space-y-3">
            {Object.entries(groups).map(([group, items]) => (
                <div key={group}>
                    <p className="mb-1.5 text-xs font-semibold text-muted-foreground">{group}</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {items.map((channel) => {
                            const active = value === channel.code;

                            return (
                                <button
                                    key={channel.code}
                                    type="button"
                                    onClick={() => onChange(channel.code)}
                                    aria-pressed={active}
                                    className={cn('flex items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-left text-sm font-medium transition', active ? activeClass : 'hover:bg-muted')}
                                >
                                    <span>{channel.label}</span>
                                    {active && <Check className="size-4 shrink-0" />}
                                </button>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
