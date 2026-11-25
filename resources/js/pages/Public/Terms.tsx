import { Head, Link } from '@inertiajs/react';

import { Footer } from '@/Components/landing/Footer';
import { LandingShell } from '@/Components/landing/LandingShell';
import { type Company } from '@/Components/landing/types';

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

            <article className="mx-auto max-w-3xl px-4 py-14">
                <Link href="/" className="text-sm text-muted-foreground hover:text-foreground">
                    ← Kembali ke beranda
                </Link>
                <h1 className="mt-4 text-3xl font-semibold tracking-tight">Terms of Service</h1>
                <p className="mt-1 text-sm text-muted-foreground">Terakhir diperbarui {terms.updated_at}</p>
                <p className="mt-6 text-pretty text-muted-foreground">{terms.intro}</p>
                <div className="mt-8 space-y-7">
                    {terms.paragraphs.map((paragraph) => (
                        <section key={paragraph.heading}>
                            <h2 className="text-lg font-semibold">{paragraph.heading}</h2>
                            {paragraph.body.split('\n').map((line) => (
                                <p key={line} className="mt-2 text-pretty text-muted-foreground">
                                    {line}
                                </p>
                            ))}
                        </section>
                    ))}
                </div>
            </article>

            <Footer company={company} body="" onLanding={false} />
        </LandingShell>
    );
}
