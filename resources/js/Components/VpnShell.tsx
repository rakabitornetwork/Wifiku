import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Moon, ShieldCheck, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

import { BrandLogo } from '@/Components/BrandLogo';
import { Toaster } from '@/Components/Toaster';
import { Button } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';
import { cn } from '@/lib/utils';

type Props = {
    company: string;
    children: ReactNode;
    /** Tampilkan menu dan tombol keluar (portal member) atau tombol Masuk/Daftar (halaman publik). */
    member?: boolean;
    showAuthLinks?: boolean;
    /** Ringkasan di pita navy; konten halaman lalu menimpa pita. Tanpa hero, pita hanya memuat header. */
    hero?: ReactNode;
};

const memberLinks = [
    { label: 'Akun', href: '/vpn-tunnel/akun' },
    { label: 'Tagihan', href: '/vpn-tunnel/tagihan' },
];

/** Kerangka layanan VPN Tunnel, senada dengan portal pelanggan: pita navy berisi header (dan hero), konten di atas latar abu kebiruan. */
export function VpnShell({ company, children, member = false, showAuthLinks = true, hero }: Props) {
    const { theme, toggleTheme } = useTheme();
    const { url } = usePage();
    const ghost = 'rounded-lg px-3 py-1.5 text-sm font-medium transition';

    return (
        <div className="flex min-h-screen flex-col bg-slate-100 dark:bg-background">
            <div className="relative overflow-hidden bg-[#0b1026] text-white">
                <div className="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full bg-indigo-500/25 blur-3xl" />
                <header className="relative border-b border-white/10">
                    <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
                        <Link href="/vpn-tunnel" className="flex min-w-0 items-center gap-3 text-sm font-semibold">
                            <BrandLogo size="sm" icon={ShieldCheck} />
                            <span className="truncate">
                                {company} <span className="font-normal text-indigo-200/70">VPN Tunnel</span>
                            </span>
                        </Link>
                        <div className="flex items-center gap-1.5">
                            <Button variant="ghost" size="icon" className="text-white/80 hover:bg-white/10 hover:text-white" onClick={toggleTheme} aria-label="Ganti tema">
                                {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                            </Button>
                            {member ? (
                                <>
                                    {memberLinks.map((link) => (
                                        <Link key={link.href} href={link.href} className={cn(ghost, url.startsWith(link.href) ? 'bg-white/10 text-white' : 'text-indigo-100/70 hover:text-white')}>
                                            {link.label}
                                        </Link>
                                    ))}
                                    <Button size="sm" className="border-0 bg-gradient-to-br from-rose-500 to-red-700 text-white shadow-sm shadow-red-500/25 hover:from-rose-600 hover:to-red-800 hover:text-white" onClick={() => router.post('/vpn-tunnel/keluar')}>
                                        <LogOut className="size-3.5" /> Keluar
                                    </Button>
                                </>
                            ) : (
                                showAuthLinks && (
                                    <>
                                        <Link href="/portal?layanan=vpn" className={cn(ghost, 'text-indigo-100/80 hover:text-white')}>
                                            Masuk
                                        </Link>
                                        <Link href="/vpn-tunnel/daftar" className="rounded-lg bg-white px-3.5 py-1.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-white/90">
                                            Daftar
                                        </Link>
                                    </>
                                )
                            )}
                        </div>
                    </div>
                </header>
                {hero && <div className="relative mx-auto max-w-5xl px-4 pt-8 pb-20 lg:pt-10 lg:pb-24">{hero}</div>}
            </div>
            <main className={cn('relative z-10 flex-1', hero && '-mt-14 lg:-mt-16')}>{children}</main>
            <footer className="py-8 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {company}. Layanan VPN Tunnel untuk MikroTik.
            </footer>
            <Toaster />
        </div>
    );
}
