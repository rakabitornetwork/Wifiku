import { Link } from '@inertiajs/react';
import { Menu, MessageCircle, Moon, Sun, X } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

import { Toaster } from '@/Components/Toaster';
import { Button, buttonClass } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';
import { cn } from '@/lib/utils';

import { Icon3D } from './Icon3D';
import { type Company, waLink } from './types';

const nav = [
    { id: 'layanan', label: 'Layanan' },
    { id: 'harga', label: 'Harga' },
    { id: 'tentang', label: 'Tentang' },
    { id: 'proses', label: 'Proses' },
    { id: 'galeri', label: 'Galeri' },
    { id: 'vpn', label: 'VPN Tunnel' },
    { id: 'kontak', label: 'Kontak' },
];

type Props = { company: Company; staff: boolean; onLanding?: boolean; children: ReactNode };

/** Kerangka halaman publik perusahaan: header lengket dengan navigasi anchor, ganti tema, dan footer. */
export function LandingShell({ company, staff, onLanding = true, children }: Props) {
    const { theme, toggleTheme } = useTheme();
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const href = (id: string) => (onLanding ? `#${id}` : `/#${id}`);
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin konsultasi pemasangan WiFi.`);

    return (
        <div className="flex min-h-screen flex-col">
            <header className="sticky top-0 z-30 border-b border-white/10 bg-[#0b1026]/90 text-white shadow-lg shadow-indigo-950/20 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Link href="/" className="flex items-center gap-2.5 text-sm font-semibold">
                        {company.logo ? (
                            <span className="flex h-10 items-center rounded-xl bg-white px-2.5 shadow-sm ring-1 ring-white/10">
                                <img src={company.logo} alt="" className="h-7 w-auto max-w-36 object-contain" />
                            </span>
                        ) : (
                            <Icon3D name="wifi" className="size-10 drop-shadow-md" />
                        )}
                        {company.name}
                    </Link>

                    <nav className="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                        {nav.map((item) => (
                            <a key={item.id} href={href(item.id)} className="rounded-lg px-3 py-2 text-sm font-medium text-indigo-100/75 transition hover:bg-white/10 hover:text-white">
                                {item.label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-1.5">
                        <Button variant="ghost" size="icon" className="text-white/80 hover:bg-white/10 hover:text-white" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </Button>
                        <Link href="/portal" className={buttonClass('ghost', 'sm', 'hidden border border-white/25 text-white hover:bg-white/10 hover:text-white md:inline-flex')}>
                            Portal Pelanggan
                        </Link>
                        {staff && (
                            <Link href="/dashboard" className={buttonClass('ghost', 'sm', 'hidden text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex')}>
                                Dashboard
                            </Link>
                        )}
                        {chat && (
                            <a href={chat} target="_blank" rel="noreferrer" className={buttonClass('primary', 'sm', 'hidden bg-white text-indigo-700 shadow-sm hover:bg-white/90 hover:brightness-100 sm:inline-flex')}>
                                <MessageCircle className="size-3.5" /> Konsultasi
                            </a>
                        )}
                        <Button variant="ghost" size="icon" className="border border-white/25 bg-white/5 text-white hover:bg-white/10 hover:text-white lg:hidden" onClick={() => setOpen(!open)} aria-label={open ? 'Tutup menu' : 'Buka menu'} aria-expanded={open} aria-controls="menu-seluler">
                            {open ? <X className="size-4" /> : <Menu className="size-4" />}
                        </Button>
                    </div>
                </div>

                <div
                    id="menu-seluler"
                    aria-hidden={!open}
                    className={cn(
                        'absolute inset-x-0 top-full grid transition-[grid-template-rows,opacity,visibility] duration-300 ease-out motion-reduce:transition-none lg:hidden',
                        open ? 'visible grid-rows-[1fr] opacity-100' : 'invisible grid-rows-[0fr] opacity-0',
                    )}
                >
                    <nav className="overflow-hidden border-b border-white/10 bg-[#0b1026]/95 text-white shadow-lg shadow-indigo-950/30 backdrop-blur-xl" aria-label="Navigasi seluler">
                        <div className="mx-auto grid max-w-6xl gap-1 px-4 py-3">
                            {nav.map((item) => (
                                <a key={item.id} href={href(item.id)} onClick={() => setOpen(false)} className="rounded-lg px-3 py-2.5 text-sm font-medium text-indigo-50/90 hover:bg-white/10">
                                    {item.label}
                                </a>
                            ))}
                            <Link href="/portal" className="rounded-lg px-3 py-2.5 text-sm font-semibold text-indigo-300 hover:bg-white/10">
                                Portal Pelanggan
                            </Link>
                            {staff && (
                                <Link href="/dashboard" className="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                                    Dashboard
                                </Link>
                            )}
                        </div>
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>
            <Toaster />
        </div>
    );
}
