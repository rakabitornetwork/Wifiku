import { Link } from '@inertiajs/react';

import { Icon3D } from './Icon3D';
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
        <footer className="border-t border-white/10 bg-[#0b1026] text-slate-300">
            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 md:grid-cols-[1.4fr_1fr_1fr]">
                <div>
                    <div className="flex items-center gap-3 text-sm font-semibold text-white">
                        {company.logo ? (
                            <span className="flex h-10 items-center rounded-xl bg-white px-2.5 shadow-sm">
                                <img src={company.logo} alt="" className="h-7 w-auto max-w-36 object-contain" />
                            </span>
                        ) : (
                            <Icon3D name="wifi" className="size-10" />
                        )}
                        {company.name}
                    </div>
                    <p className="mt-3 max-w-sm text-sm text-slate-400">{body}</p>
                    <div className="mt-4 flex gap-2">
                        {company.instagram && (
                            <a href={company.instagram} target="_blank" rel="noreferrer" className="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                                Instagram
                            </a>
                        )}
                        {company.facebook && (
                            <a href={company.facebook} target="_blank" rel="noreferrer" className="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                                Facebook
                            </a>
                        )}
                    </div>
                </div>
                <div>
                    <h2 className="text-sm font-semibold text-white">Navigasi</h2>
                    <ul className="mt-3 space-y-2 text-sm text-slate-400">
                        {links.map((link) => (
                            <li key={link.id}>
                                <a href={href(link.id)} className="transition hover:text-white">
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <h2 className="text-sm font-semibold text-white">Lainnya</h2>
                    <ul className="mt-3 space-y-2 text-sm text-slate-400">
                        <li>
                            <Link href="/vpn-tunnel" className="transition hover:text-white">
                                VPN Tunnel
                            </Link>
                        </li>
                        <li>
                            <Link href="/terms-of-service" className="transition hover:text-white">
                                Terms of Service
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
            <div className="border-t border-white/10 py-4 text-center text-xs text-slate-500">
                © {new Date().getFullYear()} {company.name}. Semua hak dilindungi.
            </div>
        </footer>
    );
}
