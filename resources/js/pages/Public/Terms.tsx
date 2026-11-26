import { Head, Link } from '@inertiajs/react';

import { Footer } from '@/Components/landing/Footer';
import { LandingShell } from '@/Components/landing/LandingShell';
import { type Company } from '@/Components/landing/types';
import { cardSurface } from '@/Components/ui/Card';
import { cn } from '@/lib/utils';

type Props = {
    company: Company;
    terms: { updated_at: string; intro: string; paragraphs: { heading: string; body: string }[] };
};

export default function Terms({ company, terms }: Props) {
    return (
        <LandingShell company={company} staff={false} onLanding={false}>
            <Head title={`Terms of Service · ${company.name}`}>
                <meta name="description" content={`Ketentuan layanan ${company.name}.`} />
            </Head>

            <section className="relative overflow-hidden bg-[#0b1026] pt-12 pb-28 text-white">
                <div className="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full bg-indigo-500/25 blur-3xl" />
                <div className="relative mx-auto max-w-3xl px-4">
                    <Link href="/" className="text-sm text-indigo-200/80 transition hover:text-white">
                        ← Kembali ke beranda
                    </Link>
                    <h1 className="mt-5 text-4xl font-semibold tracking-tight sm:text-5xl">Terms of Service</h1>
                    <p className="mt-3 text-sm text-indigo-100/75">Terakhir diperbarui {terms.updated_at}</p>
                </div>
            </section>

            <article className="relative z-10 mx-auto -mt-16 max-w-3xl px-4 pb-16">
                <div className={cn(cardSurface, 'p-7 sm:p-10')}>
                    <p className="text-lg text-pretty text-muted-foreground">{terms.intro}</p>
                    <div className="mt-9 space-y-8">
                        {terms.paragraphs.map((paragraph) => (
                            <section key={paragraph.heading}>
                                <h2 className="text-lg font-semibold tracking-tight">{paragraph.heading}</h2>
                                {paragraph.body.split('\n').map((line) => (
                                    <p key={line} className="mt-2 leading-relaxed text-pretty text-muted-foreground">
                                        {line}
                                    </p>
                                ))}
                            </section>
                        ))}
                    </div>
                </div>
            </article>

            <Footer company={company} body="" onLanding={false} />
        </LandingShell>
    );
}
