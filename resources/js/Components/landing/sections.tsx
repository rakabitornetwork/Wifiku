import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Clock, Headphones, Lock, Mail, MapPin, MessageCircle, Radar, Router, ShieldCheck, Signal, Star, Wifi, Zap, type LucideIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input, Textarea } from '@/Components/ui/Field';
import { cn, formatRupiah } from '@/lib/utils';

import { type Company, type Plan, type Sections, waLink } from './types';

const icons: Record<string, LucideIcon> = { wifi: Wifi, signal: Signal, router: Router, shield: ShieldCheck, zap: Zap, radar: Radar, lock: Lock, headphones: Headphones };

function Heading({ eyebrow, title, body, center = true }: { eyebrow?: string; title: string; body?: string; center?: boolean }) {
    return (
        <div className={cn('max-w-2xl', center && 'mx-auto text-center')}>
            {eyebrow && <p className="text-xs font-semibold tracking-widest text-primary uppercase">{eyebrow}</p>}
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-balance sm:text-3xl">{title}</h2>
            {body && <p className="mt-3 text-pretty text-muted-foreground">{body}</p>}
        </div>
    );
}

function Section({ id, className, children }: { id: string; className?: string; children: React.ReactNode }) {
    return (
        <section id={id} className={cn('scroll-mt-20 px-4 py-16 sm:py-20', className)}>
            <div className="mx-auto max-w-6xl">{children}</div>
        </section>
    );
}

export function Hero({ content, company }: { content: Sections['hero']; company: Company }) {
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin konsultasi gratis pemasangan WiFi.`);

    return (
        <section className="relative overflow-hidden px-4 pt-14 pb-16 sm:pt-20 sm:pb-24">
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_20%_0%,color-mix(in_oklab,var(--primary)_16%,transparent),transparent),radial-gradient(45%_40%_at_90%_30%,color-mix(in_oklab,oklch(0.62_0.2_305)_14%,transparent),transparent)]" />
            <div className="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <span className="inline-flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
                        <Wifi className="size-3.5 text-primary" /> {content.badge}
                    </span>
                    <h1 className="mt-5 text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        {content.title}
                    </h1>
                    <p className="mt-5 max-w-xl text-lg text-pretty text-muted-foreground">{content.body}</p>
                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        <a href={chat ?? '#kontak'} {...(chat ? { target: '_blank', rel: 'noreferrer' } : {})} className={buttonClass('primary', 'md', 'h-12 px-6 text-base')}>
                            <MessageCircle className="size-4" /> {content.cta}
                        </a>
                        <a href="#harga" className={buttonClass('outline', 'md', 'h-12 px-6 text-base')}>
                            {content.secondary_cta} <ArrowRight className="size-4" />
                        </a>
                    </div>
                    <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                        {content.highlights.map((item) => (
                            <li key={item} className="flex items-center gap-1.5">
                                <Check className="size-4 text-emerald-600" /> {item}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative mx-auto aspect-square w-full max-w-md" aria-hidden="true">
                    <div className="absolute inset-0 rounded-[2.5rem] bg-gradient-to-br from-indigo-500 via-violet-600 to-fuchsia-600 shadow-2xl shadow-indigo-500/30" />
                    <svg viewBox="0 0 200 200" className="absolute inset-0 size-full text-white">
                        {[78, 58, 38, 18].map((radius, index) => (
                            <circle key={radius} cx="100" cy="150" r={radius} fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeDasharray={`${radius * 2.2} ${radius * 4}`} strokeDashoffset={-radius * 0.15} opacity={0.35 + index * 0.18} transform="rotate(-90 100 150) rotate(0 100 150)" />
                        ))}
                        <circle cx="100" cy="150" r="7" fill="currentColor" />
                    </svg>
                    <div className="absolute top-6 -left-3 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg sm:-left-6">
                        <span className="mr-1.5 inline-block size-2 rounded-full bg-emerald-500" /> Sinyal merata
                    </div>
                    <div className="absolute right-0 bottom-10 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg sm:-right-4">
                        <Signal className="mr-1.5 inline size-3.5 text-primary" /> Survey gratis
                    </div>
                    <div className="absolute bottom-2 left-6 rounded-xl border bg-card px-3 py-2 text-xs font-medium shadow-lg">
                        <Headphones className="mr-1.5 inline size-3.5 text-primary" /> Support WhatsApp
                    </div>
                </div>
            </div>
        </section>
    );
}

function IconCard({ item }: { item: { icon: string; title: string; description: string } }) {
    const Icon = icons[item.icon] ?? Wifi;

    return (
        <Card className="p-6 transition hover:-translate-y-0.5 hover:shadow-md">
            <span className="flex size-11 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                <Icon className="size-5" />
            </span>
            <h3 className="mt-4 font-semibold">{item.title}</h3>
            <p className="mt-1.5 text-sm text-muted-foreground">{item.description}</p>
        </Card>
    );
}

export function Services({ content }: { content: Sections['services'] }) {
    return (
        <Section id="layanan">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.items.map((item) => (
                    <IconCard key={item.title} item={item} />
                ))}
            </div>
        </Section>
    );
}

export function About({ content }: { content: Sections['about'] }) {
    return (
        <Section id="tentang" className="bg-muted/40">
            <div className="grid items-center gap-10 lg:grid-cols-2">
                <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} center={false} />
                {content.stats.length > 0 && (
                    <dl className="grid grid-cols-3 gap-3">
                        {content.stats.map((stat) => (
                            <Card key={stat.label} className="p-5 text-center">
                                <dt className="sr-only">{stat.label}</dt>
                                <dd className="num bg-gradient-to-br from-indigo-600 to-violet-600 bg-clip-text text-3xl font-semibold text-transparent">{stat.value}</dd>
                                <p className="mt-1 text-xs text-muted-foreground" aria-hidden="true">
                                    {stat.label}
                                </p>
                            </Card>
                        ))}
                    </dl>
                )}
            </div>
        </Section>
    );
}

export function Benefits({ content }: { content: Sections['benefits'] }) {
    return (
        <Section id="keunggulan">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.items.map((item) => (
                    <IconCard key={item.title} item={item} />
                ))}
            </div>
        </Section>
    );
}

export function Process({ content }: { content: Sections['process'] }) {
    return (
        <Section id="proses" className="bg-muted/40">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {content.steps.map((step, index) => (
                    <li key={step.title} className="relative rounded-xl border bg-card p-6">
                        <span className="num flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-semibold text-white shadow-md shadow-indigo-500/25">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <h3 className="mt-4 font-semibold">{step.title}</h3>
                        <p className="mt-1.5 text-sm text-muted-foreground">{step.description}</p>
                    </li>
                ))}
            </ol>
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
                <div className="mt-10 grid items-stretch gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const chat = waLink(company.whatsapp, `Halo ${company.name}, saya tertarik dengan paket ${plan.name}.`);

                        return (
                            <Card key={plan.name} className={cn('relative flex flex-col p-6', plan.highlight && 'border-primary shadow-xl shadow-indigo-500/15 ring-1 ring-primary/40')}>
                                {plan.highlight && <span className="absolute -top-3 left-6 rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-3 py-0.5 text-xs font-semibold text-white">Paling banyak diambil</span>}
                                <h3 className="text-lg font-semibold">{plan.name}</h3>
                                {plan.description && <p className="mt-1 text-sm text-muted-foreground">{plan.description}</p>}
                                <p className="mt-5 flex items-baseline gap-1.5">
                                    <span className="num text-3xl font-semibold tracking-tight">{formatRupiah(plan.price)}</span>
                                    <span className="text-sm text-muted-foreground">per bulan</span>
                                </p>
                                <ul className="mt-5 space-y-2.5 text-sm">
                                    {plan.speed && (
                                        <li className="flex items-start gap-2">
                                            <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" /> <span>Kecepatan hingga <strong>{plan.speed}</strong></span>
                                        </li>
                                    )}
                                    {content.perks.map((perk) => (
                                        <li key={perk} className="flex items-start gap-2">
                                            <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" /> <span>{perk}</span>
                                        </li>
                                    ))}
                                </ul>
                                <a href={chat ?? '#kontak'} {...(chat ? { target: '_blank', rel: 'noreferrer' } : {})} className={buttonClass(plan.highlight ? 'primary' : 'outline', 'md', 'mt-auto w-full')}>
                                    Ambil paket ini
                                </a>
                            </Card>
                        );
                    })}
                </div>
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
        <Section id="testimoni" className="bg-muted/40">
            <Heading eyebrow={content.eyebrow} title={content.title} body={content.body} />
            <div className="mt-10 grid gap-4 md:grid-cols-3">
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
            </div>
        </Section>
    );
}

export function Cta({ content, company }: { content: Sections['cta']; company: Company }) {
    const chat = waLink(company.whatsapp, `Halo ${company.name}, saya ingin memasang WiFi di rumah.`);

    return (
        <section className="px-4 py-12">
            <div className="relative mx-auto max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 px-6 py-14 text-center text-white shadow-2xl shadow-indigo-500/25 sm:px-12">
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
        <Section id="vpn" className="bg-muted/40">
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
                        <li key={step.title} className="flex gap-4 rounded-xl border bg-card p-5">
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
        company.whatsapp_display && { icon: MessageCircle, label: 'WhatsApp', value: company.whatsapp_display, href: waLink(company.whatsapp, `Halo ${company.name}`) },
        company.email && { icon: Mail, label: 'Email', value: company.email, href: `mailto:${company.email}` },
        company.address && { icon: MapPin, label: 'Alamat', value: company.address, href: null },
        company.hours && { icon: Clock, label: 'Jam operasional', value: company.hours, href: null },
    ].filter(Boolean) as { icon: LucideIcon; label: string; value: string; href: string | null }[];

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
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                        <row.icon className="size-[18px]" />
                                    </span>
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
