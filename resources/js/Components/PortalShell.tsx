import { Link, router } from '@inertiajs/react';
import { LogOut, Moon, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

import { Icon3D } from '@/Components/landing/Icon3D';
import { Toaster } from '@/Components/Toaster';
import { Button } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';

type Props = { company: { name: string; logo: string | null }; children: ReactNode; loggedIn?: boolean };

/** Kerangka portal pelanggan PPPoE: header ber-logo perusahaan, ganti tema, dan tombol keluar. */
export function PortalShell({ company, children, loggedIn = false }: Props) {
    const { theme, toggleTheme } = useTheme();

    return (
        <div className="flex min-h-screen flex-col bg-[radial-gradient(70%_40%_at_50%_0%,color-mix(in_oklab,var(--primary)_12%,transparent),transparent)]">
            <header className="sticky top-0 z-20 border-b bg-background/75 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
                    <Link href="/" className="flex items-center gap-2.5 text-sm font-semibold">
                        {company.logo ? <img src={company.logo} alt="" className="h-9 w-auto max-w-36 object-contain" /> : <Icon3D name="wifi" className="size-9" />}
                        <span>
                            {company.name} <span className="font-normal text-muted-foreground">Portal Pelanggan</span>
                        </span>
                    </Link>
                    <div className="flex items-center gap-1.5">
                        <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </Button>
                        {loggedIn && (
                            <Button size="sm" className="border-0 bg-gradient-to-br from-rose-500 to-red-700 text-white shadow-sm shadow-red-500/25 hover:from-rose-600 hover:to-red-800 hover:text-white" onClick={() => router.post('/pelanggan/keluar')}>
                                <LogOut className="size-3.5" /> Keluar
                            </Button>
                        )}
                    </div>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="border-t py-6 text-center text-xs text-muted-foreground">© {new Date().getFullYear()} {company.name}</footer>
            <Toaster />
        </div>
    );
}
