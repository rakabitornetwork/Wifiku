import { Head } from '@inertiajs/react';

import { Footer } from '@/Components/landing/Footer';
import { LandingShell } from '@/Components/landing/LandingShell';
import { About, Benefits, Contact, Cta, Hero, Pricing, Process, Services, Testimonials, VpnSection } from '@/Components/landing/sections';
import { type Company, type Plan, type Sections } from '@/Components/landing/types';

type Props = {
    company: Company;
    sections: Sections;
    plans: Plan[];
    vpn: { price: number; registrationOpen: boolean; protocols: string[] };
    staff: boolean;
    seo: { title: string; description: string; canonical: string; jsonLd: Record<string, unknown> };
};

export default function Landing({ company, sections, plans, vpn, staff, seo }: Props) {
    // "<" di-escape agar teks dari Pengaturan tidak bisa menutup tag script lebih awal.
    const jsonLd = JSON.stringify(seo.jsonLd).replace(/</g, '\\u003c');

    return (
        <LandingShell company={company} staff={staff}>
            <Head>
                <title>{seo.title}</title>
                <meta name="description" content={seo.description} />
                <link rel="canonical" href={seo.canonical} />
                <meta property="og:type" content="website" />
                <meta property="og:locale" content="id_ID" />
                <meta property="og:title" content={seo.title} />
                <meta property="og:description" content={seo.description} />
                <meta property="og:url" content={seo.canonical} />
                <script type="application/ld+json">{jsonLd}</script>
            </Head>

            <Hero content={sections.hero} company={company} />
            <Services content={sections.services} />
            <Pricing content={sections.pricing} plans={plans} company={company} />
            <About content={sections.about} />
            <Benefits content={sections.benefits} />
            <Process content={sections.process} />
            <Testimonials content={sections.testimonials} />
            <Cta content={sections.cta} company={company} />
            <VpnSection content={sections.vpn} vpn={vpn} />
            <Contact content={sections.contact} company={company} />
            <Footer company={company} body={sections.footer.body} />
        </LandingShell>
    );
}
