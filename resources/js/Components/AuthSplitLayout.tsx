import { router } from '@inertiajs/react';
import { Check, LogOut, Moon, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

import { useBrand } from '@/Components/BrandLogo';
import { Icon3D } from '@/Components/landing/Icon3D';
import { Toaster } from '@/Components/Toaster';
import { useTheme } from '@/Hooks/useTheme';

type Props = {
    /** Judul besar di panel merek (hanya tampil di layar lebar). */
    headline: string;
    benefits: string[];
    /** Tampilkan tombol Keluar; nilainya alamat POST untuk keluar. */
    logoutUrl?: string;
    children: ReactNode;
};

export function BrandMark({ tone }: { tone: 'dark' | 'auto' }) {
    const brand = useBrand();

    return (
        <div className="flex items-center gap-3">
            {brand.logo ? (
                <span className="flex h-10 items-center">
                    <img src={brand.logo} alt="" className="h-7 w-auto max-w-32 object-contain" />
                </span>
            ) : (
                <Icon3D name="wifi" className="size-10" />
            )}
            <span className={tone === 'dark' ? 'text-sm font-semibold text-white' : 'text-sm font-semibold'}>{brand.name}</span>
        </div>
    );
}

/**
 * Kerangka halaman masuk dan akun pelanggan: kartu dua panel, panel merek navy di kiri (layar lebar)
 * dan isi di kanan. Dipakai bersama oleh halaman login, pilih sambungan, dan formulir akun VPN Tunnel.
 */
export function AuthSplitLayout({ headline, benefits, logoutUrl, children }: Props) {
    const { theme, toggleTheme } = useTheme();
    const brand = useBrand();
    const iconButton = 'flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:text-slate-900 dark:border-white/10 dark:bg-card dark:text-slate-300 dark:hover:text-white';

    return (
        <div className="relative flex min-h-screen flex-col bg-slate-100 dark:bg-background">
            <div className="absolute top-4 right-4 z-10 flex items-center gap-2">
                {logoutUrl && (
                    <button type="button" onClick={() => router.post(logoutUrl)} className="flex h-10 items-center gap-1.5 rounded-full bg-gradient-to-br from-rose-500 to-red-700 px-4 text-sm font-semibold text-white shadow-md shadow-red-500/25 transition hover:from-rose-600 hover:to-red-800">
                        <LogOut className="size-4" /> Keluar
                    </button>
                )}
                <button type="button" onClick={toggleTheme} aria-label="Ganti tema" className={iconButton}>
                    {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                </button>
            </div>

            <main className="flex flex-1 items-center justify-center px-4 py-14">
                <div className="grid w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-[0_24px_60px_-20px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5 lg:grid-cols-[1fr_1.1fr] dark:bg-card dark:shadow-none dark:ring-white/10">
                    <aside className="relative hidden flex-col justify-between overflow-hidden bg-[#0b1026] p-10 text-white lg:flex">
                        <div className="pointer-events-none absolute -top-24 -right-20 size-80 rounded-full bg-indigo-500/30 blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-32 -left-24 size-72 rounded-full bg-violet-600/20 blur-3xl" />
                        <div className="relative">
                            <BrandMark tone="dark" />
                        </div>
                        <div className="relative">
                            <h2 className="text-3xl leading-tight font-semibold tracking-tight text-balance">{headline}</h2>
                            <ul className="mt-8 space-y-3.5 text-sm text-indigo-50/90">
                                {benefits.map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300">
                                            <Check className="size-3.5" strokeWidth={3} />
                                        </span>
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <p className="relative text-xs text-indigo-200/60">Portal Pelanggan {brand.name}</p>
                    </aside>

                    <section className="p-7 sm:p-11">
                        <div className="mb-8 lg:hidden">
                            <BrandMark tone="auto" />
                        </div>
                        {children}
                    </section>
                </div>
            </main>

            <footer className="pb-8 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {brand.name}
            </footer>
            <Toaster />
        </div>
    );
}
