import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { type ReactNode } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { cn } from '@/lib/utils';

type Template = {
    key: string;
    label: string;
    icon: string;
    when: string;
    editable: boolean;
    customised: boolean;
    enabled: boolean;
    placeholders: Record<string, string>;
    required: string[];
    body: string;
    default_body: string;
    sample: Record<string, string>;
    whatsapp: string;
    telegram: string;
};

/** Teks pesan dengan tautan http(s) yang bisa diklik, seperti tampil di aplikasi chat. */
function linkify(text: string): ReactNode[] {
    return text.split(/(https?:\/\/[^\s)]+)/g).map((part, index) =>
        /^https?:\/\//.test(part) ? (
            <a key={index} href={part} target="_blank" rel="noreferrer" className="break-all underline decoration-1 underline-offset-2 hover:opacity-80">
                {part}
            </a>
        ) : (
            part
        ),
    );
}

function Bubble({ channel, text }: { channel: 'whatsapp' | 'telegram'; text: string }) {
    const whatsapp = channel === 'whatsapp';

    return (
        <div className={cn('rounded-2xl p-3', whatsapp ? 'bg-[#efeae2] dark:bg-[#0b141a]' : 'bg-[#dfe9f3] dark:bg-[#0e1621]')}>
            <div className="mb-1.5 flex items-center gap-1.5 px-1 text-[11px] font-semibold text-muted-foreground">
                <span className={cn('size-2 rounded-full', whatsapp ? 'bg-emerald-500' : 'bg-sky-500')} /> {whatsapp ? 'WhatsApp' : 'Telegram'}
            </div>
            <div
                className={cn(
                    'max-w-full rounded-xl rounded-tl-sm px-3 py-2 text-[13px] leading-relaxed break-words whitespace-pre-wrap shadow-sm',
                    whatsapp ? 'bg-[#d9fdd3] text-slate-900 dark:bg-[#005c4b] dark:text-slate-50' : 'bg-white text-slate-900 dark:bg-[#182533] dark:text-slate-50',
                )}
            >
                {linkify(text)}
            </div>
        </div>
    );
}

export default function MessageTemplates({ templates }: { templates: Template[] }) {
    return (
        <>
            <PageHeader title="Template Pesan" description="Pratinjau semua pesan otomatis ke pelanggan, persis seperti yang diterima di WhatsApp dan Telegram. Data di bawah adalah contoh." />

            <div className="mb-5 rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                Riwayat pesan yang benar-benar terkirim ada di menu{' '}
                <Link href="/pesan" className="font-semibold text-primary hover:underline">
                    Riwayat Pesan
                </Link>
                . Jenis pesan bisa dimatikan di{' '}
                <Link href="/settings/notifications" className="font-semibold text-primary hover:underline">
                    Notifikasi &amp; Webhook
                </Link>
                .
            </div>

            <div className="space-y-5">
                {templates.map((template) => (
                    <Card key={template.key} className="overflow-hidden">
                        <div className="flex flex-wrap items-start justify-between gap-3 border-b px-6 py-4">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-lg">{template.icon}</span>
                                <div>
                                    <h2 className="text-[15px] font-semibold tracking-tight">{template.label}</h2>
                                    <p className="mt-0.5 max-w-2xl text-xs text-muted-foreground">{template.when}</p>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {!template.enabled && <Badge tone="amber">Dimatikan</Badge>}
                                {template.customised && <Badge tone="blue">Disesuaikan</Badge>}
                                {!template.editable && (
                                    <Badge tone="slate">
                                        <Lock className="size-3" /> Dikunci
                                    </Badge>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-4 p-6 lg:grid-cols-2">
                            <Bubble channel="whatsapp" text={template.whatsapp} />
                            <Bubble channel="telegram" text={template.telegram} />
                        </div>
                        <div className="border-t px-6 py-4">
                            <div className="text-xs font-semibold text-muted-foreground">Variabel yang tersedia</div>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {Object.entries(template.placeholders).map(([name, description]) => (
                                    <span key={name} title={description} className={cn('num rounded-md border px-2 py-0.5 text-xs', template.required.includes(name) ? 'border-primary/40 bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground')}>
                                        {`{${name}}`}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </>
    );
}
