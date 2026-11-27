import { Link } from '@inertiajs/react';
import { ArrowRight, Check, ChevronLeft, ChevronRight, Headphones, MessageCircle, Signal, Star, Wifi, X } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { buttonClass } from '@/Components/ui/Button';
import { Card, cardSurface } from '@/Components/ui/Card';
import { Field, Input, Textarea } from '@/Components/ui/Field';
import { cn, formatRupiah } from '@/lib/utils';

import { Reveal } from './Reveal';
import { Icon3D, type Icon3DName, isIcon3DName } from './Icon3D';
import { type Company, type GalleryItem, type LandingImages, type Plan, type Sections, waLink } from './types';

const tint = 'bg-gradient-to-b from-muted/70 via-muted/30 to-background';

function Heading({ eyebrow, title, body, center = true }: { eyebrow?: string; title: string; body?: string; center?: boolean }) {
    return (
        <Reveal className={cn('max-w-2xl', center && 'mx-auto text-center')}>
            {eyebrow && (
                <p className={cn('inline-flex items-center gap-2 text-sm font-semibold text-primary', center && 'justify-center')}>
                    <span className="h-0.5 w-6 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" /> {eyebrow}
                </p>
            )}
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{title}</h2>
            {body && <p className="mt-4 text-lg text-pretty text-muted-foreground">{body}</p>}
        </Reveal>
    );
}

function Section({ id, className, children }: { id: string; className?: string; children: React.ReactNode }) {
    return (
        <section id={id} className={cn('scroll-mt-20 px-4 py-16 sm:py-20', className)}>
            <div className="mx-auto max-w-6xl">{children}</div>
        </section>
    );
}

export function Hero({ content, company, image }: { content: Sections['hero']; company: Company; image: LandingImages['hero'] }) {
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin konsultasi gratis pemasangan WiFi.`);

    return (
        <section className={cn('relative isolate overflow-hidden px-4 pt-14 pb-16 sm:pt-20 sm:pb-24', image && 'flex min-h-[34rem] items-center sm:min-h-[40rem]')}>
            {image ? (
                <>
                    <img src={image.url} alt="" fetchPriority="high" className="hero-zoom pointer-events-none absolute inset-0 -z-20 size-full object-cover" />
                    <div className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/90 via-slate-950/65 to-slate-950/25" />
                    {image.is_ai && <span className="absolute right-4 bottom-4 rounded bg-black/45 px-1.5 py-0.5 text-[10px] text-white">Ilustrasi</span>}
                </>
            ) : (
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_20%_0%,color-mix(in_oklab,var(--primary)_16%,transparent),transparent),radial-gradient(45%_40%_at_90%_30%,color-mix(in_oklab,oklch(0.62_0.2_305)_14%,transparent),transparent)]" />
            )}
            <div className={cn('mx-auto grid w-full max-w-6xl items-center gap-12', !image && 'lg:grid-cols-[1.1fr_0.9fr]')}>
                <div className={cn(image && 'max-w-2xl text-white')}>
                    <span style={{ '--d': 0 } as React.CSSProperties} className={cn('hero-in inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium', image ? 'border-white/25 bg-white/10 text-white backdrop-blur' : 'bg-card text-muted-foreground')}>
                        <Wifi className="size-3.5 text-primary" /> {content.badge}
                    </span>
                    <h1 style={{ '--d': 1 } as React.CSSProperties} className="hero-in mt-6 text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        {content.title}
                    </h1>
                    <p style={{ '--d': 2 } as React.CSSProperties} className={cn('hero-in mt-5 max-w-xl text-lg text-pretty', image ? 'text-white/85' : 'text-muted-foreground')}>{content.body}</p>
                    <div style={{ '--d': 3 } as React.CSSProperties} className="hero-in mt-8 flex flex-wrap items-center gap-3">
                        <a href={chat ?? '#kontak'} {...(chat ? { target: '_blank', rel: 'noreferrer' } : {})} className={buttonClass('primary', 'md', 'h-12 px-6 text-base')}>
                            <MessageCircle className="size-4" /> {content.cta}
                        </a>
                        <a href="#harga" className={buttonClass('outline', 'md', cn('h-12 px-6 text-base', image && 'border-white/40 bg-white/10 text-white backdrop-blur hover:bg-white/20 hover:text-white'))}>
                            {content.secondary_cta} <ArrowRight className="size-4" />
                        </a>
                    </div>
                    <ul style={{ '--d': 4 } as React.CSSProperties} className={cn('hero-in mt-8 flex flex-wrap gap-2.5 text-sm', image ? 'text-white/90' : 'text-muted-foreground')}>
                        {content.highlights.map((item) => (
                            <li key={item} className={cn('flex items-center gap-1.5 rounded-full px-3.5 py-1.5', image ? 'border border-white/20 bg-white/10 backdrop-blur' : 'border bg-card')}>
                                <Check className={cn('size-4', image ? 'text-emerald-400' : 'text-emerald-600')} /> {item}
                            </li>
                        ))}
                    </ul>
                </div>

                {!image && (
                <div style={{ '--d': 2 } as React.CSSProperties} className="hero-in relative mx-auto aspect-square w-full max-w-md" aria-hidden="true">
                        <>
                            <div className="absolute inset-0 rounded-[2.5rem] bg-gradient-to-br from-indigo-500 via-violet-600 to-fuchsia-600 shadow-2xl shadow-indigo-500/30" />
                            <svg viewBox="0 0 200 200" className="absolute inset-0 size-full text-white">
                                {[78, 58, 38, 18].map((radius, index) => (
                                    <circle key={radius} cx="100" cy="150" r={radius} fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeDasharray={`${radius * 2.2} ${radius * 4}`} strokeDashoffset={-radius * 0.15} opacity={0.35 + index * 0.18} transform="rotate(-90 100 150) rotate(0 100 150)" />
                                ))}
                                <circle cx="100" cy="150" r="7" fill="currentColor" className="hero-pulse" style={{ transformOrigin: '100px 150px' }} />
                            </svg>
                        </>
                    <div style={{ '--d': 0 } as React.CSSProperties} className="hero-float absolute top-6 -left-3 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg sm:-left-6">
                        <span className="hero-pulse mr-1.5 inline-block size-2 rounded-full bg-emerald-500" /> Sinyal merata
                    </div>
                    <div style={{ '--d': 1 } as React.CSSProperties} className="hero-float absolute right-0 bottom-10 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg sm:-right-4">
                        <Signal className="mr-1.5 inline size-3.5 text-primary" /> Survey gratis
                    </div>
                    <div style={{ '--d': 2 } as React.CSSProperties} className="hero-float absolute bottom-2 left-6 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg">
                        <Headphones className="mr-1.5 inline size-3.5 text-primary" /> Support WhatsApp
                    </div>
                </div>
                )}
            </div>
        </section>
    );
}

function IconCard({ item }: { item: { icon: string; title: string; description: string } }) {
    const name: Icon3DName = isIcon3DName(item.icon) ? item.icon : 'wifi';

    return (
        <Card className="group relative overflow-hidden rounded-2xl bg-gradient-to-b from-card to-muted/40 p-6 transition duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl hover:shadow-indigo-500/10">
            <div className="pointer-events-none absolute -top-12 -right-12 size-40 rounded-full bg-indigo-500/10 blur-2xl transition duration-300 group-hover:bg-indigo-500/20" />
            <Icon3D name={name} className="relative size-16 drop-shadow-lg transition duration-300 group-hover:scale-105" />
            <h3 className="relative mt-5 text-lg font-semibold tracking-tight">{item.title}</h3>
            <p className="relative mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
        </Card>
    );
}

export function Services({ content }: { content: Sections['services'] }) {
    return (
        <Section id="layanan">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <Reveal stagger className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.items.map((item) => (
                    <IconCard key={item.title} item={item} />
                ))}
            </Reveal>
        </Section>
    );
}

export function About({ content, image }: { content: Sections['about']; image: LandingImages['about'] }) {
    return (
        <Section id="tentang" className={tint}>
            <div className="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} center={false} />
                    {image && (
                        <div className="relative mt-6">
                            <img src={image.url} alt="" loading="lazy" className="aspect-[4/3] w-full rounded-2xl border object-cover" />
                            {image.is_ai && <span className="absolute right-3 bottom-3 rounded bg-black/45 px-1.5 py-0.5 text-[10px] text-white">Ilustrasi</span>}
                        </div>
                    )}
                </div>
                {content.stats.length > 0 && (
                    <Reveal as="dl" stagger className="grid grid-cols-3 gap-3">
                        {content.stats.map((stat) => (
                            <Card key={stat.label} className="p-5 text-center">
                                <dt className="sr-only">{stat.label}</dt>
                                <dd className="num bg-gradient-to-br from-indigo-600 to-violet-600 bg-clip-text text-3xl font-semibold text-transparent">{stat.value}</dd>
                                <p className="mt-1 text-xs text-muted-foreground" aria-hidden="true">
                                    {stat.label}
                                </p>
                            </Card>
                        ))}
                    </Reveal>
                )}
            </div>
        </Section>
    );
}

export function Benefits({ content }: { content: Sections['benefits'] }) {
    return (
        <Section id="keunggulan">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <Reveal stagger className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.items.map((item) => (
                    <IconCard key={item.title} item={item} />
                ))}
            </Reveal>
        </Section>
    );
}

export function Process({ content }: { content: Sections['process'] }) {
    return (
        <Section id="proses" className={tint}>
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <Reveal as="ol" stagger className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.steps.map((step, index) => (
                    <li key={step.title} className={cn(cardSurface, 'relative p-6')}>
                        <span className="num flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-semibold text-white shadow-md shadow-indigo-500/25">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <h3 className="mt-4 font-semibold">{step.title}</h3>
                        <p className="mt-1.5 text-sm text-muted-foreground">{step.description}</p>
                    </li>
                ))}
            </Reveal>
        </Section>
    );
}

export function Pricing({ content, plans, company }: { content: Sections['pricing']; plans: Plan[]; company: Company }) {
    return (
        <Section id="harga">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            {plans.length === 0 ? (
                <Card className="mx-auto mt-10 max-w-xl p-8 text-center">
                    <p className="text-muted-foreground">{content.empty}</p>
                    {waLink(company.whatsapp, `Halo ${company.name}, saya ingin tahu paket dan harga di area saya.`) && (
                        <a href={waLink(company.whatsapp, `Halo ${company.name}, saya ingin tahu paket dan harga di area saya.`) ?? '#'} target="_blank" rel="noreferrer" className={buttonClass('primary', 'md', 'mt-5')}>
                            <MessageCircle className="size-4" /> Tanya paket
                        </a>
                    )}
                </Card>
            ) : (
                <Reveal stagger className="mt-10 grid items-stretch gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const chat = waLink(company.whatsapp, `Halo ${company.name}, saya tertarik dengan paket ${plan.name}.`);

                        return (
                            <Card key={plan.name} className={cn('relative flex flex-col rounded-2xl p-7 transition duration-300 hover:-translate-y-1', plan.highlight ? 'border-transparent bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 text-white shadow-2xl shadow-violet-600/40 ring-2 ring-amber-300 md:-my-3 md:py-10' : 'hover:shadow-xl hover:shadow-indigo-500/10')}>
                                {plan.highlight && <span className="absolute -top-3 left-6 rounded-full bg-amber-300 px-3 py-0.5 text-xs font-bold text-slate-900 shadow-md">Paling banyak diambil</span>}
                                <h3 className="text-lg font-semibold">{plan.name}</h3>
                                {plan.description && <p className={cn('mt-1 text-sm', plan.highlight ? 'text-white/85' : 'text-muted-foreground')}>{plan.description}</p>}
                                <p className="mt-5 flex items-baseline gap-1.5">
                                    <span className="num text-3xl font-semibold tracking-tight">{formatRupiah(plan.price)}</span>
                                    <span className={cn('text-sm', plan.highlight ? 'text-white/85' : 'text-muted-foreground')}>per bulan</span>
                                </p>
                                <ul className="mt-6 mb-7 space-y-3 text-sm">
                                    {plan.speed && (
                                        <li className="flex items-start gap-2">
                                            <Check className={cn('mt-0.5 size-4 shrink-0', plan.highlight ? 'text-amber-300' : 'text-emerald-600')} /> <span>Kecepatan hingga <strong>{plan.speed}</strong></span>
                                        </li>
                                    )}
                                    {content.perks.map((perk) => (
                                        <li key={perk} className="flex items-start gap-2">
                                            <Check className={cn('mt-0.5 size-4 shrink-0', plan.highlight ? 'text-amber-300' : 'text-emerald-600')} /> <span>{perk}</span>
                                        </li>
                                    ))}
                                </ul>
                                <a href={chat ?? '#kontak'} {...(chat ? { target: '_blank', rel: 'noreferrer' } : {})} className={buttonClass(plan.highlight ? 'primary' : 'outline', 'md', cn('mt-auto h-11 w-full', plan.highlight && 'border-0 bg-white text-indigo-700 hover:bg-white/90'))}>
                                    Ambil paket ini
                                </a>
                            </Card>
                        );
                    })}
                </Reveal>
            )}
            <p className="mt-6 text-center text-sm text-muted-foreground">{content.note}</p>
        </Section>
    );
}

export function Testimonials({ content }: { content: Sections['testimonials'] }) {
    if (content.items.length === 0) {
        return null;
    }

    return (
        <Section id="testimoni" className={tint}>
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <Reveal stagger className="mt-10 grid gap-4 md:grid-cols-3">
                {content.items.map((item) => (
                    <Card key={item.name} className="flex flex-col p-6">
                        <div className="flex gap-0.5 text-amber-500" aria-label="Bintang 5 dari 5">
                            {Array.from({ length: 5 }, (_, index) => (
                                <Star key={index} className="size-4 fill-current" />
                            ))}
                        </div>
                        <blockquote className="mt-3 flex-1 text-sm text-pretty">“{item.quote}”</blockquote>
                        <figcaption className="mt-5 flex items-center gap-3 border-t pt-4">
                            <span className="flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white">
                                {item.name
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((part) => part.charAt(0))
                                    .join('')}
                            </span>
                            <span className="text-sm">
                                <span className="block font-medium">{item.name}</span>
                                <span className="block text-xs text-muted-foreground">{item.role}</span>
                            </span>
                        </figcaption>
                    </Card>
                ))}
            </Reveal>
        </Section>
    );
}

export function Cta({ content, company, image }: { content: Sections['cta']; company: Company; image: LandingImages['cta'] }) {
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin memasang WiFi di rumah.`);

    return (
        <section className="px-4 py-12">
            <div className="relative mx-auto max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 px-6 py-14 text-center text-white shadow-2xl shadow-indigo-500/25 sm:px-12">
                {image && (
                    <>
                        <img src={image.url} alt="" loading="lazy" className="pointer-events-none absolute inset-0 size-full object-cover opacity-40 mix-blend-overlay" />
                        <div className="pointer-events-none absolute inset-0 bg-indigo-950/30" />
                    </>
                )}
                <div className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-white/10 blur-3xl" />
                <h2 className="relative mx-auto max-w-2xl text-2xl font-semibold tracking-tight text-balance sm:text-4xl">{content.title}</h2>
                <p className="relative mx-auto mt-3 max-w-xl text-white/85">{content.body}</p>
                <a href={chat ?? '#kontak'} {...(chat ? { target: '_blank', rel: 'noreferrer' } : {})} className="relative mt-8 inline-flex h-12 items-center gap-2 rounded-xl bg-white px-6 text-base font-semibold text-indigo-700 shadow-lg transition hover:bg-white/90">
                    <MessageCircle className="size-4" /> {content.button}
                </a>
            </div>
        </section>
    );
}

export function VpnSection({ content, vpn }: { content: Sections['vpn']; vpn: { price: number; registrationOpen: boolean; protocols: string[] } }) {
    return (
        <Section id="vpn" className={tint}>
            <div className="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} center={false} />
                    <ul className="mt-6 space-y-3 text-sm">
                        {content.features.map((feature) => (
                            <li key={feature} className="flex items-start gap-2.5">
                                <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600">
                                    <Check className="size-3.5" />
                                </span>
                                {feature}
                            </li>
                        ))}
                    </ul>
                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        {vpn.registrationOpen ? (
                            <Link href="/vpn-tunnel/daftar" className={buttonClass('primary', 'md', 'h-11 px-5')}>
                                Daftar VPN Tunnel
                            </Link>
                        ) : (
                            <span className="rounded-lg border bg-muted px-4 py-2.5 text-sm text-muted-foreground">Pendaftaran sedang ditutup</span>
                        )}
                        <Link href="/vpn-tunnel" className={buttonClass('outline', 'md', 'h-11 px-5')}>
                            Pelajari lebih lanjut
                        </Link>
                    </div>
                    <p className="num mt-4 text-sm text-muted-foreground">
                        Mulai <span className="font-semibold text-foreground">{formatRupiah(vpn.price)}</span> per akun / bulan{vpn.protocols.length > 0 && ` · ${vpn.protocols.join(' & ')}`}
                    </p>
                </div>
                <ol className="space-y-3">
                    {content.steps.map((step, index) => (
                        <li key={step.title} className={cn(cardSurface, 'flex gap-4 p-5')}>
                            <span className="num flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-semibold text-white">{index + 1}</span>
                            <span>
                                <span className="block font-semibold">{step.title}</span>
                                <span className="block text-sm text-muted-foreground">{step.description}</span>
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
        </Section>
    );
}

export function Contact({ content, company }: { content: Sections['contact']; company: Company }) {
    const [name, setName] = useState('');
    const [message, setMessage] = useState('');
    const send = (event: FormEvent) => {
        event.preventDefault();
        const text = `Halo ${company.name}, saya ${name.trim()}.\n\n${message.trim()}`;
        const link = waLink(company.whatsapp, text);

        if (link) {
            window.open(link, '_blank', 'noopener');
        }
    };

    const rows = [
        company.whatsapp_display && { icon: 'chat' as const, label: 'WhatsApp', value: company.whatsapp_display, href: waLink(company.whatsapp, `Halo ${company.name}`) },
        company.email && { icon: 'mail' as const, label: 'Email', value: company.email, href: `mailto:${company.email}` },
        company.address && { icon: 'pin' as const, label: 'Alamat', value: company.address, href: null },
        company.hours && { icon: 'clock' as const, label: 'Jam operasional', value: company.hours, href: null },
    ].filter(Boolean) as { icon: Icon3DName; label: string; value: string; href: string | null }[];

    return (
        <Section id="kontak">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <div className="mt-10 grid gap-6 lg:grid-cols-2">
                <Card className="p-6">
                    {rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Informasi kontak belum diisi.</p>
                    ) : (
                        <ul className="space-y-5">
                            {rows.map((row) => (
                                <li key={row.label} className="flex items-start gap-4">
                                    <Icon3D name={row.icon} className="size-12 drop-shadow-md" />
                                    <span className="min-w-0 text-sm">
                                        <span className="block text-xs text-muted-foreground">{row.label}</span>
                                        {row.href ? (
                                            <a href={row.href} {...(row.href.startsWith('http') ? { target: '_blank', rel: 'noreferrer' } : {})} className="num font-medium break-words hover:text-primary hover:underline">
                                                {row.value}
                                            </a>
                                        ) : (
                                            <span className="font-medium">{row.value}</span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                <Card className="p-6">
                    {company.whatsapp ? (
                        <form onSubmit={send} className="space-y-4">
                            <Field label="Nama Anda">
                                <Input value={name} onChange={(event) => setName(event.target.value)} autoComplete="name" required />
                            </Field>
                            <Field label="Kebutuhan Anda" hint={content.form_note}>
                                <Textarea rows={4} value={message} onChange={(event) => setMessage(event.target.value)} placeholder="Contoh: rumah 2 lantai, sinyal lemah di lantai atas." required />
                            </Field>
                            <button type="submit" className={buttonClass('primary', 'md', 'w-full')}>
                                <MessageCircle className="size-4" /> Kirim via WhatsApp
                            </button>
                        </form>
                    ) : (
                        <p className="text-sm text-muted-foreground">Formulir pesan akan tersedia setelah nomor WhatsApp diisi di Pengaturan.</p>
                    )}
                </Card>
            </div>
        </Section>
    );
}

export function Gallery({ content, photos }: { content: Sections['gallery']; photos: GalleryItem[] }) {
    const [active, setActive] = useState<number | null>(null);

    const move = (step: number) => setActive((current) => (current === null ? null : (current + step + photos.length) % photos.length));

    useEffect(() => {
        if (active === null) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setActive(null);
            } else if (event.key === 'ArrowRight') {
                setActive((current) => (current === null ? null : (current + 1) % photos.length));
            } else if (event.key === 'ArrowLeft') {
                setActive((current) => (current === null ? null : (current - 1 + photos.length) % photos.length));
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [active, photos.length]);

    if (photos.length === 0) {
        return null;
    }

    const current = active === null ? null : photos[active];

    return (
        <Section id="galeri">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <div className="mt-10 grid grid-cols-2 gap-3 md:grid-cols-3">
                {photos.map((photo, index) => (
                    <button
                        key={photo.id}
                        type="button"
                        onClick={() => setActive(index)}
                        className="group relative aspect-[4/3] overflow-hidden rounded-xl border bg-muted text-left focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    >
                        <img src={photo.thumb} alt={photo.title} loading="lazy" className="size-full object-cover transition duration-300 group-hover:scale-105" />
                        <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 pt-8 text-xs font-medium text-white">{photo.title}</span>
                    </button>
                ))}
            </div>

            {current && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true" aria-label={current.title} onClick={() => setActive(null)}>
                    <button type="button" className="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" onClick={() => setActive(null)} aria-label="Tutup">
                        <X className="size-5" />
                    </button>
                    {photos.length > 1 && (
                        <>
                            <button type="button" className="absolute left-3 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" onClick={(event) => { event.stopPropagation(); move(-1); }} aria-label="Foto sebelumnya">
                                <ChevronLeft className="size-6" />
                            </button>
                            <button type="button" className="absolute right-3 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" onClick={(event) => { event.stopPropagation(); move(1); }} aria-label="Foto berikutnya">
                                <ChevronRight className="size-6" />
                            </button>
                        </>
                    )}
                    <figure className="max-h-full max-w-4xl" onClick={(event) => event.stopPropagation()}>
                        <img src={current.full} alt={current.title} className="max-h-[78vh] w-auto rounded-lg object-contain" />
                        <figcaption className="mt-3 text-center text-sm text-white">
                            <span className="font-medium">{current.title}</span>
                            {current.caption && <span className="block text-xs text-white/70">{current.caption}</span>}
                        </figcaption>
                    </figure>
                </div>
            )}
        </Section>
    );
}
