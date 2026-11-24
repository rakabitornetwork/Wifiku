import { cn } from '@/lib/utils';

const sizes = { sm: 'size-7 text-[11px]', md: 'size-9 text-xs', lg: 'size-24 text-2xl' } as const;

/** Inisial dari maksimal dua kata pertama nama: "Budi Santoso" → "BS". */
function initials(name: string): string {
    return (
        name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '?'
    );
}

/** Foto profil bundar; tanpa foto menampilkan inisial pada latar gradasi. */
export function Avatar({ name, url, size = 'md', className }: { name: string; url?: string | null; size?: keyof typeof sizes; className?: string }) {
    return url ? (
        <img src={url} alt={name} className={cn('shrink-0 rounded-full border object-cover', sizes[size], className)} />
    ) : (
        <span aria-label={name} className={cn('flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 font-semibold text-white', sizes[size], className)}>
            {initials(name)}
        </span>
    );
}
