import { usePage } from '@inertiajs/react';
import { type LucideIcon, Wifi } from 'lucide-react';

import { cn } from '@/lib/utils';

type Brand = { name: string; logo: string | null };

/** Nama dan logo perusahaan yang dibagikan server ke semua halaman (Pengaturan dan menu Ilustrasi Landing). */
export function useBrand(): Brand {
    return usePage<{ brand: Brand }>().props.brand;
}

const sizes = {
    sm: { box: 'h-8', square: 'size-8', image: 'h-6 max-w-28', squareImage: 'size-6', tile: 'size-8 rounded-lg', icon: 'size-4' },
    md: { box: 'h-10', square: 'size-10', image: 'h-7 max-w-32', squareImage: 'size-7', tile: 'size-10 rounded-xl', icon: 'size-5' },
    lg: { box: 'h-14', square: 'size-14', image: 'h-10 max-w-44', squareImage: 'size-10', tile: 'size-12 rounded-2xl shadow-lg', icon: 'size-6' },
} as const;

/**
 * Tanda merek: logo perusahaan yang diunggah (tampil langsung tanpa ubin, latar transparan),
 * atau ikon bawaan bila belum ada logo. `square` memaksa ubin persegi, mis. untuk sidebar yang diciutkan.
 */
export function BrandLogo({ size = 'sm', square = false, icon: Icon = Wifi, className }: { size?: keyof typeof sizes; square?: boolean; icon?: LucideIcon; className?: string }) {
    const { logo } = useBrand();
    const s = sizes[size];

    if (logo) {
        return (
            <span className={cn('flex shrink-0 items-center justify-center', square ? s.square : s.box)}>
                <img src={logo} alt="" className={cn('w-auto object-contain', square ? s.squareImage : s.image)} />
            </span>
        );
    }

    return (
        <span className={cn('flex shrink-0 items-center justify-center bg-gradient-to-br from-indigo-500 to-violet-600 text-white', s.tile, className)}>
            <Icon className={s.icon} />
        </span>
    );
}
