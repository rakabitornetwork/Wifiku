import { Link } from '@inertiajs/react';
import { Wifi } from 'lucide-react';

import { type Company } from './types';

const links = [
    { id: 'layanan', label: 'Layanan' },
    { id: 'harga', label: 'Harga' },
    { id: 'tentang', label: 'Tentang' },
    { id: 'proses', label: 'Proses' },
    { id: 'kontak', label: 'Kontak' },
];

export function Footer({ company, body, onLanding = true }: { company: Company; body: string; onLanding?: boolean }) {
    const href = (id: string) => (onLanding ? `#${id}` : `/#${id}`);

    return (
        <footer className="border-t bg-muted/30">
            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 md:grid-cols-[1.4fr_1fr_1fr]">
                <div>
                    <div className="flex items-center gap-2.5 text-sm font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                            <Wifi className="size-4" />
                        </span>
                        {company.name}
                    </div>
                    <p className="mt-3 max-w-sm text-sm text-muted-foreground">{body}</p>
                    <div className="mt-4 flex gap-2">
                        {company.instagram && (
                            <a href={company.instagram} target="_blank" rel="noreferrer" className="rounded-lg border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:text-foreground">
                                Instagram
                            </a>
                        )}
                        {company.facebook && (
                            <a href={company.facebook} target="_blank" rel="noreferrer" className="rounded-lg border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:text-foreground">
                                Facebook
                            </a>
                        )}
                    </div>
                </div>
                <div>
                    <h2 className="text-sm font-semibold">Navigasi</h2>
                    <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                        {links.map((link) => (
                            <li key={link.id}>
                                <a href={href(link.id)} className="hover:text-foreground">
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <h2 className="text-sm font-semibold">Lainnya</h2>
                    <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                        <li>
                            <Link href="/vpn-tunnel" className="hover:text-foreground">
                                VPN Tunnel
                            </Link>
                        </li>
                        <li>
                            <Link href="/terms-of-service" className="hover:text-foreground">
                                Terms of Service
                            </Link>
                        </li>
                        <li>
                            <Link href="/login" className="hover:text-foreground">
                                Login admin
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
            <div className="border-t py-4 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {company.name}. Semua hak dilindungi.
            </div>
        </footer>
    );
}
