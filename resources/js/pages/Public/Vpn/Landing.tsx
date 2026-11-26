import { Head, Link } from '@inertiajs/react';
import { Check, KeyRound, MessageCircle, Network, Router, ShieldCheck, TerminalSquare, Zap } from 'lucide-react';

import { VpnShell } from '@/Components/VpnShell';
import { buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { formatRupiah } from '@/lib/utils';

type Props = {
    company: string;
    price: number;
    maxAccounts: number;
    registrationOpen: boolean;
    protocols: string[];
    loggedIn: boolean;
    whatsappUrl: string | null;
};

const features = [
    { icon: Network, title: 'Remote tanpa IP publik', text: 'Router di belakang CGNAT atau IP dinamis tetap bisa diakses dari mana saja lewat tunnel VPN.' },
    { icon: Router, title: 'Winbox, API, SSH, dan Web', text: 'Port forward ke router Anda dibuat otomatis, jadi Winbox dan API bisa langsung dipakai dari internet.' },
    { icon: TerminalSquare, title: 'Script siap tempel', text: 'Salin satu script RouterOS ke terminal MikroTik Anda. Tunnel terbentuk tanpa mengisi satu per satu.' },
    { icon: Zap, title: 'Aktif otomatis setelah bayar', text: 'Bayar lewat QRIS atau Virtual Account, akun langsung terpasang tanpa menunggu persetujuan admin.' },
    { icon: KeyRound, title: 'Akun terpisah per router', text: 'Satu akun untuk satu router, dengan username, password, dan IP tunnel sendiri.' },
    { icon: ShieldCheck, title: 'Tunnel terenkripsi', text: 'Koneksi memakai autentikasi MS-CHAPv2 dan enkripsi PPP (MPPE) bawaan RouterOS.' },
];

const steps = [
    { title: 'Daftar', text: 'Isi nama, email, dan nomor WhatsApp.' },
    { title: 'Buat akun VPN', text: 'Pilih protokol dan port yang ingin dibuka.' },
    { title: 'Bayar', text: 'QRIS atau Virtual Account, aktif otomatis.' },
    { title: 'Tempel script', text: 'Salin script ke router MikroTik Anda.' },
];

const faqs = [
    { q: 'Router apa yang didukung?', a: 'MikroTik RouterOS dengan klien L2TP atau SSTP. Script yang kami berikan disusun untuk RouterOS.' },
    { q: 'Apa beda L2TP dan SSTP?', a: 'Keduanya bekerja untuk remote router. SSTP memakai TCP sehingga lebih mudah melewati jaringan yang membatasi, sedangkan L2TP lebih ringan.' },
    { q: 'Berapa lama masa aktif akun?', a: 'Satu bulan sejak pembayaran. Tagihan perpanjangan dikirim lewat WhatsApp menjelang masa berlaku habis, dan akun yang tidak diperpanjang dinonaktifkan.' },
    { q: 'Apakah port yang dibuka bisa diubah?', a: 'Saat membuat akun Anda memilih layanan yang diteruskan (SSH, HTTP, Winbox, API, OLT). Untuk kebutuhan lain, hubungi CS.' },
];

export default function Landing({ company, price, maxAccounts, registrationOpen, protocols, loggedIn, whatsappUrl }: Props) {
    const cta = loggedIn
        ? { href: '/vpn-tunnel/akun', label: 'Buka akun saya' }
        : registrationOpen
          ? { href: '/vpn-tunnel/daftar', label: 'Daftar sekarang' }
          : null;

    return (
        <VpnShell company={company} showAuthLinks={!loggedIn}>
            <Head title={`VPN Tunnel untuk MikroTik · ${company}`}>
                <meta name="description" content={`Akses router MikroTik Anda dari mana saja dengan VPN Tunnel ${company}: L2TP dan SSTP, port forward Winbox/API otomatis, script siap tempel, mulai ${formatRupiah(price)} per akun per bulan.`} />
                <meta property="og:title" content={`VPN Tunnel untuk MikroTik · ${company}`} />
                <meta property="og:description" content="Remote Winbox dan API router MikroTik tanpa IP publik. Daftar, bayar, tempel script." />
                <meta property="og:type" content="website" />
            </Head>

            <section className="mx-auto max-w-5xl px-4 pt-14 pb-12 text-center sm:pt-20">
                <span className="inline-flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                    <ShieldCheck className="size-3.5 text-primary" /> Layanan VPN Tunnel {protocols.length > 0 && `· ${protocols.join(' & ')}`}
                </span>
                <h1 className="mx-auto mt-5 max-w-3xl text-3xl font-semibold tracking-tight text-balance sm:text-5xl">Akses router MikroTik Anda dari mana saja</h1>
                <p className="mx-auto mt-4 max-w-2xl text-base text-pretty text-muted-foreground">
                    Hubungkan router ke server VPN kami dan buka Winbox, API, atau web router tanpa IP publik. Daftar, bayar, tempel satu script, selesai.
                </p>
                <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
                    {cta ? (
                        <Link href={cta.href} className={buttonClass('primary', 'md', 'h-11 px-6')}>
                            {cta.label}
                        </Link>
                    ) : (
                        <span className="rounded-lg border bg-muted px-4 py-2.5 text-sm text-muted-foreground">Pendaftaran sedang ditutup</span>
                    )}
                    {!loggedIn && (
                        <Link href="/portal?layanan=vpn" className={buttonClass('outline', 'md', 'h-11 px-6')}>
                            Sudah punya akun? Masuk
                        </Link>
                    )}
                </div>
                <p className="num mt-4 text-sm text-muted-foreground">
                    Mulai <span className="font-semibold text-foreground">{formatRupiah(price)}</span> per akun / bulan
                </p>
            </section>

            <section className="mx-auto max-w-5xl px-4 pb-14">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <Card key={feature.title} className="p-5">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                                <feature.icon className="size-[18px]" />
                            </span>
                            <h2 className="mt-3 text-sm font-semibold">{feature.title}</h2>
                            <p className="mt-1.5 text-sm text-muted-foreground">{feature.text}</p>
                        </Card>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-5xl px-4 pb-14">
                <h2 className="text-center text-xl font-semibold tracking-tight">Cara memulai</h2>
                <ol className="mt-6 grid gap-4 sm:grid-cols-4">
                    {steps.map((step, index) => (
                        <li key={step.title} className="relative rounded-xl border bg-card p-4">
                            <span className="num flex size-7 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">{index + 1}</span>
                            <h3 className="mt-3 text-sm font-semibold">{step.title}</h3>
                            <p className="mt-1 text-xs text-muted-foreground">{step.text}</p>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="mx-auto max-w-md px-4 pb-14">
                <Card className="overflow-hidden border-primary/40 shadow-md">
                    <div className="bg-gradient-to-br from-indigo-500 to-violet-600 p-6 text-center text-white">
                        <p className="text-sm font-medium text-white/80">Harga per akun VPN</p>
                        <p className="num mt-1 text-4xl font-semibold">{formatRupiah(price)}</p>
                        <p className="text-sm text-white/80">per bulan</p>
                    </div>
                    <ul className="space-y-2.5 p-6 text-sm">
                        {[`Hingga ${maxAccounts} akun VPN per pelanggan`, 'Username, password, dan IP tunnel sendiri per akun', 'Port forward Winbox, API, SSH, dan Web', 'Script RouterOS siap tempel', 'Perpanjangan lewat QRIS / Virtual Account'].map((item) => (
                            <li key={item} className="flex items-start gap-2">
                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                    {cta && (
                        <div className="px-6 pb-6">
                            <Link href={cta.href} className={buttonClass('primary', 'md', 'h-11 w-full')}>
                                {cta.label}
                            </Link>
                        </div>
                    )}
                </Card>
            </section>

            <section className="mx-auto max-w-3xl px-4 pb-16">
                <h2 className="text-center text-xl font-semibold tracking-tight">Pertanyaan umum</h2>
                <div className="mt-6 space-y-3">
                    {faqs.map((faq) => (
                        <details key={faq.q} className="group rounded-xl border bg-card p-4 open:shadow-xs">
                            <summary className="cursor-pointer list-none text-sm font-medium marker:hidden">{faq.q}</summary>
                            <p className="mt-2 text-sm text-muted-foreground">{faq.a}</p>
                        </details>
                    ))}
                </div>
                {whatsappUrl && (
                    <a href={whatsappUrl} target="_blank" rel="noreferrer" className="mx-auto mt-8 flex h-11 max-w-xs items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                        <MessageCircle className="size-4" /> Tanya CS via WhatsApp
                    </a>
                )}
            </section>
        </VpnShell>
    );
}
