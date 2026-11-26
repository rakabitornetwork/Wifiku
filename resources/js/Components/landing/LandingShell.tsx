import { Link } from '@inertiajs/react';
import { Menu, MessageCircle, Moon, Sun, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';

import { Toaster } from '@/Components/Toaster';
import { Button, buttonClass } from '@/Components/ui/Button';
import { useTheme } from '@/Hooks/useTheme';

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
    const href = (id: string) => (onLanding ? `#${id}` : `/#${id}`);
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin konsultasi pemasangan WiFi.`);

    return (
        <div className="flex min-h-screen flex-col">
            <header className="sticky top-0 z-30 border-b bg-background/75 shadow-sm shadow-indigo-500/5 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Link href="/" className="flex items-center gap-2.5 text-sm font-semibold">
                        <Icon3D name="wifi" className="size-10 drop-shadow-md" />
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
                        <Link href={staff ? '/dashboard' : '/login'} className={buttonClass('ghost', 'sm', 'hidden sm:inline-flex')}>
                            {staff ? 'Dashboard' : 'Login'}
                        </Link>
                        {chat && (
                            <a href={chat} target="_blank" rel="noreferrer" className={buttonClass('primary', 'sm', 'hidden sm:inline-flex')}>
                                <MessageCircle className="size-3.5" /> Konsultasi
                            </a>
                        )}
                        <Button variant="outline" size="icon" className="lg:hidden" onClick={() => setOpen(!open)} aria-label={open ? 'Tutup menu' : 'Buka menu'} aria-expanded={open}>
                            {open ? <X className="size-4" /> : <Menu className="size-4" />}
                        </Button>
                    </div>
                </div>

                {open && (
                    <nav className="border-t bg-background px-4 py-3 lg:hidden" aria-label="Navigasi seluler">
                        <div className="mx-auto grid max-w-6xl gap-1">
                            {nav.map((item) => (
                                <a key={item.id} href={href(item.id)} onClick={() => setOpen(false)} className="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-muted">
                                    {item.label}
                                </a>
                            ))}
                            <Link href={staff ? '/dashboard' : '/login'} className="rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground hover:bg-muted">
                                {staff ? 'Dashboard' : 'Login admin'}
                            </Link>
                        </div>
                    </nav>
                )}
            </header>

            <main className="flex-1">{children}</main>
            <Toaster />
        </div>
    );
}
