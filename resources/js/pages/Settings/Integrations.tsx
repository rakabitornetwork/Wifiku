import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, KeyRound, MessageCircle, QrCode, Send, Wifi } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Input, Textarea } from '@/Components/ui/Field';
import { formatDate } from '@/lib/utils';

type SettingField = {
    name: string;
    label: string;
    hint: string | null;
    secret: boolean;
    value: string | null;
    is_set: boolean;
};
type Group = { group: string; title: string; fields: SettingField[] };

type FormShape = { settings: Record<string, Record<string, string>> };

type WhatsappResult = {
    connected: boolean;
    state: string;
    message: string;
    qr?: string | null;
    pairing_code?: string | null;
};

function postJson(url: string): Promise<WhatsappResult> {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

    return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
    }).then((response) => response.json());
}

const multiline = new Set(['isolir.message', 'genieacs.rx_power_paths']);

export default function Integrations({ groups, whatsappQueue }: { groups: Group[]; whatsappQueue: { stalled: number; oldest_at: string | null } }) {
    const initial: FormShape = { settings: {} };

    for (const group of groups) {
        initial.settings[group.group] = {};

        for (const field of group.fields) {
            initial.settings[group.group][field.name.split('.')[1]] = field.secret ? '' : (field.value ?? '');
        }
    }

    const form = useForm<FormShape>(initial);
    const [waResult, setWaResult] = useState<WhatsappResult | null>(null);
    const [waBusy, setWaBusy] = useState<'status' | 'connect' | null>(null);
    const errors = form.errors as Record<string, string | undefined>;

    const setField = (name: string, value: string) => {
        const [group, key] = name.split('.');
        form.setData('settings', {
            ...form.data.settings,
            [group]: { ...form.data.settings[group], [key]: value },
        });
    };

    const runWhatsapp = async (action: 'status' | 'connect') => {
        setWaBusy(action);

        try {
            setWaResult(await postJson(`/settings/integrations/whatsapp-${action}`));
        } catch {
            setWaResult({
                connected: false,
                state: 'error',
                message: 'Gagal menghubungi server.',
            });
        } finally {
            setWaBusy(null);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => {
                for (const group of groups) {
                    for (const field of group.fields.filter((item) => item.secret)) {
                        setField(field.name, '');
                    }
                }
            },
        });
    };

    const waValue = (key: string) => form.data.settings.whatsapp[key];
    const waField = groups.find((group) => group.group === 'whatsapp')?.fields.find((field) => field.name === 'whatsapp.api_key');

    const whatsappCard = (group: Group) => (
        <Card key={group.group}>
            <div className="flex items-start justify-between gap-4 p-5 pb-0">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                        <MessageCircle className="size-4" />
                    </span>
                    <div>
                        <h2 className="text-sm font-semibold">WhatsApp — Evolution API</h2>
                        <p className="text-xs text-muted-foreground">Sidecar HTTP (Baileys di Docker). Isi URL Evolution, API key, dan nama instance, lalu hubungkan dengan scan QR.</p>
                    </div>
                </div>
                <label className="flex shrink-0 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        className="size-4 accent-[var(--primary)]"
                        checked={waValue('enabled') !== '0'}
                        onChange={(event) => setField('whatsapp.enabled', event.target.checked ? '1' : '0')}
                    />
                    Aktifkan
                </label>
            </div>
            <div className="grid gap-4 p-5 sm:grid-cols-2">
                <Field label="Base URL" error={errors['settings.whatsapp.base_url']} hint={group.fields.find((field) => field.name === 'whatsapp.base_url')?.hint ?? undefined}>
                    <Input value={waValue('base_url')} placeholder="https://evo.example.com" onChange={(event) => setField('whatsapp.base_url', event.target.value)} />
                </Field>
                <Field label="Nama instance" error={errors['settings.whatsapp.instance']}>
                    <Input value={waValue('instance')} onChange={(event) => setField('whatsapp.instance', event.target.value)} />
                </Field>
                <Field label="API key" error={errors['settings.whatsapp.api_key']} hint={waField?.is_set ? 'Tersimpan. Kosongkan jika tidak diganti.' : 'Belum diisi.'}>
                    <Input
                        type="password"
                        autoComplete="new-password"
                        placeholder={waField?.is_set ? '••••••••' : 'Masukkan API key'}
                        value={waValue('api_key')}
                        onChange={(event) => setField('whatsapp.api_key', event.target.value)}
                    />
                </Field>
                <Field label="Nomor tes (62...)" error={errors['settings.whatsapp.test_phone']}>
                    <Input value={waValue('test_phone')} placeholder="62812xxxx" onChange={(event) => setField('whatsapp.test_phone', event.target.value)} />
                </Field>
            </div>
            {whatsappQueue.stalled > 0 && (
                <div className="mx-5 mb-5 flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                    <p>
                        <strong className="num">{whatsappQueue.stalled}</strong> pesan WhatsApp tertahan di antrean
                        {whatsappQueue.oldest_at ? ` sejak ${formatDate(whatsappQueue.oldest_at)}` : ''}. Queue worker kemungkinan tidak berjalan; jalankan{' '}
                        <code className="num">php artisan queue:work</code> (dan <code className="num">php artisan schedule:work</code> untuk tugas terjadwal).
                    </p>
                </div>
            )}
            <div className="space-y-3 border-t p-5">
                <div className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">Status &amp; koneksi</div>
                <p className={waResult === null ? 'text-sm text-muted-foreground' : waResult.connected ? 'text-sm text-emerald-600 dark:text-emerald-400' : 'text-sm text-destructive'}>
                    {waResult?.message ?? 'Simpan pengaturan terlebih dahulu, lalu klik Tes koneksi.'}
                </p>
                {waResult?.qr && <img src={waResult.qr} alt="QR WhatsApp" className="size-56 rounded-lg border bg-white p-2" />}
                {waResult?.pairing_code && (
                    <p className="num text-sm">
                        Pairing code: <strong>{waResult.pairing_code}</strong>
                    </p>
                )}
                <div className="grid gap-2 sm:grid-cols-3">
                    <Button type="button" variant="outline" disabled={waBusy !== null} onClick={() => runWhatsapp('connect')}>
                        <QrCode className="size-4" /> {waBusy === 'connect' ? 'Memuat...' : 'Hubungkan / QR'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!waValue('test_phone')}
                        onClick={() => router.post('/settings/integrations/whatsapp-test', { phone: waValue('test_phone') }, { preserveScroll: true })}
                    >
                        <Send className="size-4" /> Kirim pesan uji
                    </Button>
                    <Button type="button" disabled={waBusy !== null} onClick={() => runWhatsapp('status')}>
                        <Wifi className="size-4" /> {waBusy === 'status' ? 'Memeriksa...' : 'Tes koneksi'}
                    </Button>
                </div>
            </div>
        </Card>
    );

    return (
        <>
            <PageHeader title="Pengaturan" description="WhatsApp Gateway, GenieACS, template halaman isolir, dan pengingat tagihan. Nilai rahasia disimpan terenkripsi." />

            <form onSubmit={submit} className="space-y-4">
                {groups.map((group) =>
                    group.group === 'whatsapp' ? (
                        whatsappCard(group)
                    ) : (
                        <Card key={group.group}>
                            <CardHeader title={group.title} />
                            <div className="grid gap-4 p-5 sm:grid-cols-2">
                                {group.fields.map((field) => {
                                    const [groupKey, key] = field.name.split('.');
                                    const value = form.data.settings[groupKey][key];
                                    const error = errors[`settings.${field.name}`];
                                    const hint = field.secret ? (field.is_set ? 'Tersimpan. Kosongkan jika tidak ingin mengubah.' : 'Belum diisi.') : (field.hint ?? undefined);

                                    return (
                                        <Field key={field.name} label={field.label} error={error} hint={hint} className={multiline.has(field.name) ? 'sm:col-span-2' : undefined}>
                                            {multiline.has(field.name) ? (
                                                <Textarea value={value} onChange={(event) => setField(field.name, event.target.value)} />
                                            ) : field.secret ? (
                                                <div className="relative">
                                                    <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        type="password"
                                                        autoComplete="new-password"
                                                        className="pl-9"
                                                        placeholder={field.is_set ? '••••••••' : 'Masukkan nilai'}
                                                        value={value}
                                                        onChange={(event) => setField(field.name, event.target.value)}
                                                    />
                                                </div>
                                            ) : (
                                                <Input value={value} onChange={(event) => setField(field.name, event.target.value)} />
                                            )}
                                        </Field>
                                    );
                                })}
                            </div>
                        </Card>
                    ),
                )}

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                    </Button>
                </div>
            </form>
        </>
    );
}
