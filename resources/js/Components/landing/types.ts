export type Company = {
    name: string;
    tagline: string | null;
    whatsapp: string | null;
    whatsapp_display: string | null;
    email: string | null;
    address: string | null;
    hours: string | null;
    instagram: string | null;
    facebook: string | null;
};

export type Item = { icon: string; title: string; description: string };
export type Step = { title: string; description: string };

export type Sections = {
    hero: { badge: string; title: string; body: string; cta: string; secondary_cta: string; highlights: string[] };
    services: { eyebrow: string; title: string; body: string; items: Item[] };
    about: { eyebrow: string; title: string; body: string; stats: { value: string; label: string }[] };
    benefits: { eyebrow: string; title: string; body: string; items: Item[] };
    process: { eyebrow: string; title: string; body: string; steps: Step[] };
    pricing: { eyebrow: string; title: string; body: string; note: string; perks: string[]; empty: string };
    testimonials: { eyebrow: string; title: string; body: string; items: { name: string; role: string; quote: string }[] };
    cta: { title: string; body: string; button: string };
    contact: { eyebrow: string; title: string; body: string; form_note: string };
    vpn: { eyebrow: string; title: string; body: string; features: string[]; steps: Step[] };
    footer: { body: string };
};

export type Plan = { name: string; price: number; speed: string | null; description: string | null; highlight: boolean };

/** Tautan WhatsApp dengan pesan awal; null bila nomor belum diisi di Pengaturan. */
export function waLink(number: string | null, text: string): string | null {
    return number ? `https://wa.me/${number}?text=${encodeURIComponent(text)}` : null;
}
