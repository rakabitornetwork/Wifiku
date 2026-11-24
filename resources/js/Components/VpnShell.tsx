import { Link, router } from '@inertiajs/react';
import { LogOut, Moon, ShieldCheck, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

import { Toaster } from '@/Components/Toaster';
import { buttonClass, Button } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';

type Props = {
    company: string;
    children: ReactNode;
    /** Tampilkan tombol keluar (portal member) atau tombol Masuk/Daftar (halaman publik). */
    member?: boolean;
    showAuthLinks?: boolean;
};

/** Kerangka halaman publik layanan VPN Tunnel: header ber-logo, ganti tema, dan footer. */
export function VpnShell({ company, children, member = false, showAuthLinks = true }: Props) {
    const { theme, toggleTheme } = useTheme();

    return (
        <div className="flex min-h-screen flex-col bg-[radial-gradient(70%_40%_at_50%_0%,color-mix(in_oklab,var(--primary)_12%,transparent),transparent)]">
            <header className="sticky top-0 z-20 border-b bg-background/80 backdrop-blur">
                <div className="mx-auto flex h-14 max-w-5xl items-center justify-between gap-3 px-4">
                    <Link href="/vpn-tunnel" className="flex items-center gap-2.5 text-sm font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                            <ShieldCheck className="size-4" />
                        </span>
                        {company} <span className="font-normal text-muted-foreground">VPN Tunnel</span>
                    </Link>
                    <div className="flex items-center gap-1.5">
                        <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </Button>
                        {member ? (
                            <Button variant="outline" size="sm" onClick={() => router.post('/vpn-tunnel/keluar')}>
                                <LogOut className="size-3.5" /> Keluar
                            </Button>
                        ) : (
                            showAuthLinks && (
                                <>
                                    <Link href="/vpn-tunnel/masuk" className={buttonClass('ghost', 'sm')}>
                                        Masuk
                                    </Link>
                                    <Link href="/vpn-tunnel/daftar" className={buttonClass('primary', 'sm')}>
                                        Daftar
                                    </Link>
                                </>
                            )
                        )}
                    </div>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="border-t py-6 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {company}. Layanan VPN Tunnel untuk MikroTik.
            </footer>
            <Toaster />
        </div>
    );
}
