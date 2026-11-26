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
            <header className="sticky top-0 z-30 border-b bg-background/75 shadow-sm shadow-indigo-500/5 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Link href="/" className="flex items-center gap-2.5 text-sm font-semibold">
                        {company.logo ? <img src={company.logo} alt="" className="h-10 w-auto max-w-40 object-contain" /> : <Icon3D name="wifi" className="size-10 drop-shadow-md" />}
                        {company.name}
                    </Link>

                    <nav className="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                        {nav.map((item) => (
                            <a key={item.id} href={href(item.id)} className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground">
                                {item.label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-1.5">
                        <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </Button>
                        <Link href="/pelanggan/masuk" className={buttonClass('outline', 'sm', 'hidden md:inline-flex')}>
                            Portal Pelanggan
                        </Link>
                        {staff && (
                            <Link href="/dashboard" className={buttonClass('ghost', 'sm', 'hidden sm:inline-flex')}>
                                Dashboard
                            </Link>
                        )}
                        {chat && (
                            <a href={chat} target="_blank" rel="noreferrer" className={buttonClass('primary', 'sm', 'hidden sm:inline-flex')}>
                                <MessageCircle className="size-3.5" /> Konsultasi
                            </a>
                        )}
                        <Button variant="outline" size="icon" className="lg:hidden" onClick={() => setOpen(!open)} aria-label={open ? 'Tutup menu' : 'Buka menu'} aria-expanded={open} aria-controls="menu-seluler">
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
                    <nav className="overflow-hidden border-b bg-background/95 shadow-lg shadow-indigo-500/5 backdrop-blur-xl" aria-label="Navigasi seluler">
                        <div className="mx-auto grid max-w-6xl gap-1 px-4 py-3">
                            {nav.map((item) => (
                                <a key={item.id} href={href(item.id)} onClick={() => setOpen(false)} className="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-muted">
                                    {item.label}
                                </a>
                            ))}
                            <Link href="/pelanggan/masuk" className="rounded-lg px-3 py-2.5 text-sm font-semibold text-primary hover:bg-muted">
                                Portal Pelanggan
                            </Link>
                            {staff && (
                                <Link href="/dashboard" className="rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-muted">
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
