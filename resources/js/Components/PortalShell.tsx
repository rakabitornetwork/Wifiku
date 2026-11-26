import { Link, router } from '@inertiajs/react';
import { LogOut, Moon, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

import { Icon3D } from '@/Components/landing/Icon3D';
import { Toaster } from '@/Components/Toaster';
import { Button } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';
import { cn } from '@/lib/utils';

type Props = { company: { name: string; logo: string | null }; children: ReactNode; loggedIn?: boolean; hero?: ReactNode };

/** Kelas kartu portal: putih bersih di mode terang dengan bayangan berlapis, permukaan gelap di mode gelap. */
export const panel = 'rounded-2xl border-slate-200/90 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.05),0_12px_28px_-14px_rgba(15,23,42,0.18)] dark:border-white/10 dark:bg-card dark:shadow-none';

/**
 * Kerangka portal pelanggan PPPoE: pita navy pekat berisi header (dan ringkasan akun bila ada `hero`),
 * lalu konten di atas latar abu kebiruan; kartu pertama menimpa pita agar halaman terasa berlapis.
 */
export function PortalShell({ company, children, loggedIn = false, hero }: Props) {
    const { theme, toggleTheme } = useTheme();

    return (
        <div className="flex min-h-screen flex-col bg-slate-100 dark:bg-background">
            <div className="relative overflow-hidden bg-[#0b1026] text-white">
                <div className="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full bg-indigo-500/25 blur-3xl" />
                <header className="relative border-b border-white/10">
                    <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
                        <Link href="/" className="flex min-w-0 items-center gap-3 text-sm font-semibold">
                            {company.logo ? (
                                <span className="flex h-9 items-center rounded-lg bg-white px-2">
                                    <img src={company.logo} alt="" className="h-6 w-auto max-w-32 object-contain" />
                                </span>
                            ) : (
                                <Icon3D name="wifi" className="size-9" />
                            )}
                            <span className="truncate">
                                {company.name} <span className="font-normal text-indigo-200/70">Portal Pelanggan</span>
                            </span>
                        </Link>
                        <div className="flex items-center gap-1.5">
                            <Button variant="ghost" size="icon" className="text-white/80 hover:bg-white/10 hover:text-white" onClick={toggleTheme} aria-label="Ganti tema">
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
                <div className={cn('relative mx-auto max-w-5xl px-4', hero ? 'pt-8 pb-20' : 'pt-10 pb-24')}>{hero}</div>
            </div>
            <main className="-mt-14 flex-1">{children}</main>
            <footer className="py-8 text-center text-xs text-muted-foreground">© {new Date().getFullYear()} {company.name}</footer>
            <Toaster />
        </div>
    );
}
