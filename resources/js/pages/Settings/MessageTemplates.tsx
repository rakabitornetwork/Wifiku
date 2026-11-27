import { router, usePage } from '@inertiajs/react';
import { Lock, Pencil, RotateCcw } from 'lucide-react';
import { useRef, useState } from 'react';

import { linkify } from '@/Components/ChatPreview';
import { MessagingShell } from '@/Components/MessagingShell';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { cn } from '@/lib/utils';

type Template = {
    key: string;
    template: string;
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

const fill = (body: string, sample: Record<string, string>) => body.replace(/\{([a-z_]+)\}/g, (match, name: string) => sample[name] ?? match);

export default function MessageTemplates({ templates, maxLength }: { templates: Template[]; maxLength: number }) {
    const { auth } = usePage().props;
    const isOwner = auth?.user.roles.includes('Owner') ?? false;
    const [editing, setEditing] = useState<Template | null>(null);
    const [body, setBody] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const area = useRef<HTMLTextAreaElement>(null);

    const openEditor = (template: Template) => {
        setEditing(template);
        setBody(template.body);
        setError(null);
    };

    const insert = (name: string) => {
        const element = area.current;
        const token = `{${name}}`;

        if (!element) {
            setBody((current) => current + token);

            return;
        }

        const { selectionStart, selectionEnd } = element;
        setBody(body.slice(0, selectionStart) + token + body.slice(selectionEnd));
        window.setTimeout(() => {
            element.focus();
            element.setSelectionRange(selectionStart + token.length, selectionStart + token.length);
        });
    };

    const save = () =>
        editing &&
        router.put(`/settings/message-templates/${editing.template}`, { body }, {
            preserveScroll: true,
            onStart: () => {
                setSaving(true);
                setError(null);
            },
            onError: (errors) => setError(errors.body ?? 'Template tidak dapat disimpan.'),
            onSuccess: () => setEditing(null),
            onFinish: () => setSaving(false),
        });

    const reset = (template: Template) => {
        if (window.confirm(`Kembalikan "${template.label}" ke teks bawaan?`)) {
            router.delete(`/settings/message-templates/${template.template}`, { preserveScroll: true, onSuccess: () => setEditing(null) });
        }
    };

    const missing = editing ? editing.required.filter((name) => !body.includes(`{${name}}`)) : [];

    return (
        <MessagingShell active="templates" description="Pratinjau semua pesan otomatis ke pelanggan, persis seperti yang diterima di WhatsApp dan Telegram. Data di bawah adalah contoh.">
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
                                {isOwner && template.editable && (
                                    <Button size="sm" variant="outline" onClick={() => openEditor(template)}>
                                        <Pencil className="size-3.5" /> Ubah teks
                                    </Button>
                                )}
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

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                size="lg"
                title={editing ? `Ubah template: ${editing.label}` : 'Ubah template'}
                description="Satu teks dipakai untuk WhatsApp dan Telegram. Klik variabel untuk menyisipkannya di posisi kursor."
                footer={
                    <div className="flex w-full flex-wrap items-center justify-between gap-2">
                        <div>
                            {editing?.customised && (
                                <Button variant="outline" onClick={() => editing && reset(editing)}>
                                    <RotateCcw className="size-4" /> Kembalikan ke bawaan
                                </Button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => setEditing(null)}>
                                Batal
                            </Button>
                            <Button onClick={save} disabled={saving || missing.length > 0 || body.trim() === '' || body.length > maxLength}>
                                {saving ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </div>
                    </div>
                }
            >
                {editing && (
                    <div className="space-y-4">
                        <Field label="Teks pesan" error={error ?? undefined} hint={`${body.length}/${maxLength} karakter. Gunakan *teks* untuk huruf tebal di WhatsApp.`}>
                            <Textarea ref={area} rows={12} value={body} onChange={(event) => setBody(event.target.value)} className="font-mono text-[13px]" />
                        </Field>
                        <div className="flex flex-wrap gap-1.5">
                            {Object.entries(editing.placeholders).map(([name, description]) => (
                                <button
                                    key={name}
                                    type="button"
                                    title={description}
                                    onClick={() => insert(name)}
                                    className={cn('num rounded-md border px-2 py-0.5 text-xs transition hover:border-primary', editing.required.includes(name) ? 'border-primary/40 bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground')}
                                >
                                    {`{${name}}`}
                                </button>
                            ))}
                        </div>
                        {missing.length > 0 && <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm">Variabel penting harus tetap ada: {missing.map((name) => `{${name}}`).join(', ')}.</p>}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Bubble channel="whatsapp" text={fill(body, editing.sample)} />
                            <Bubble channel="telegram" text={`${editing.icon} ${fill(body, editing.sample)}`} />
                        </div>
                    </div>
                )}
            </Modal>
        </MessagingShell>
    );
}
