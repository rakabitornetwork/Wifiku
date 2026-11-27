import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, BellRing, Bot, Check, Copy, KeyRound, MessageCircle, QrCode, Send, Webhook, Wifi } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input, Select } from '@/Components/ui/Field';
import { cn, formatDate } from '@/lib/utils';

type SettingField = { name: string; label: string; hint: string | null; secret: boolean; value: string | null; is_set: boolean };
type Group = { group: string; title: string; fields: SettingField[] };
type FormShape = { settings: Record<string, Record<string, string>> };
type WhatsappResult = { connected: boolean; state: string; message: string; qr?: string | null; pairing_code?: string | null };
type TelegramResult = { ok: boolean; message: string };

function postJson<T>(url: string): Promise<T> {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

    return fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token } }).then((response) => response.json());
}

/** Ikon per jenis pesan otomatis ke pelanggan, sama dengan ikon yang tampil di pesan Telegram. */
const topicIcons: Record<string, string> = { invoice: '🧾', reminder: '⏰', due_today: '⏳', receipt: '💳', isolated: '🔴', reopened: '🟢', extension: '🤝', outage: '⚠️' };

function Toggle({ label, checked, onChange, hint }: { label: string; checked: boolean; onChange: (value: boolean) => void; hint?: string }) {
    return (
        <label className="flex cursor-pointer items-start gap-3 text-sm">
            <input type="checkbox" className="mt-0.5 size-4 accent-[var(--primary)]" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>
                <span className="font-medium">{label}</span>
                {hint && <span className="block text-xs text-muted-foreground">{hint}</span>}
            </span>
        </label>
    );
}

/** Bot pelanggan: saklar, alamat webhook, dan tombol memasangnya di Evolution API atau Telegram. */
function BotSection({
    title,
    description,
    enabled,
    onToggle,
    url,
    secretInUrl,
    installLabel,
    onInstall,
    onInspect,
    busy,
    result,
}: {
    title: string;
    description: string;
    enabled: boolean;
    onToggle: (on: boolean) => void;
    url: string;
    secretInUrl: boolean;
    installLabel: string;
    onInstall: () => void;
    onInspect?: () => void;
    busy: boolean;
    result: TelegramResult | null;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // Tanpa izin clipboard: alamat tetap bisa disalin manual dari kolom.
        }
    };

    return (
        <div className="space-y-4 border-t p-6">
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/12 text-violet-600 dark:text-violet-300">
                        <Bot className="size-[18px]" />
                    </span>
                    <div>
                        <h3 className="text-sm font-semibold">{title}</h3>
                        <p className="mt-0.5 max-w-xl text-xs leading-relaxed text-muted-foreground">{description}</p>
                    </div>
                </div>
                <Toggle label="Aktifkan" checked={enabled} onChange={onToggle} />
            </div>
            <div className={cn('space-y-3', !enabled && 'opacity-60')}>
                <Field
                    label="Alamat webhook"
                    hint={
                        secretInUrl
                            ? 'Memuat rahasia pengesah; jangan dibagikan. Dipasang otomatis oleh tombol di bawah.'
                            : 'Telegram memakai header rahasia terpisah; dipasang otomatis oleh tombol di bawah.'
                    }
                >
                    <div className="flex gap-2">
                        <Input readOnly value={url} className="num min-w-0 flex-1 text-xs" onFocus={(event) => event.currentTarget.select()} />
                        <Button type="button" variant="outline" onClick={copy} aria-label="Salin alamat webhook">
                            {copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                        </Button>
                    </div>
                </Field>
                {result && <p className={result.ok ? 'text-sm text-emerald-600 dark:text-emerald-400' : 'text-sm text-destructive'}>{result.message}</p>}
                <div className="flex flex-wrap gap-2">
                    <Button type="button" disabled={busy} onClick={onInstall}>
                        <Webhook className="size-4" /> {busy ? 'Memasang...' : installLabel}
                    </Button>
                    {onInspect && (
                        <Button type="button" variant="outline" disabled={busy} onClick={onInspect}>
                            Periksa status webhook
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Notifications({
    groups,
    whatsappQueue,
    webhooks,
}: {
    groups: Group[];
    whatsappQueue: { stalled: number; oldest_at: string | null };
    webhooks: { whatsapp: string; telegram: string };
}) {
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
    const [tgResult, setTgResult] = useState<TelegramResult | null>(null);
    const [tgBusy, setTgBusy] = useState<'status' | 'test' | null>(null);
    const [hookBusy, setHookBusy] = useState<'whatsapp' | 'telegram' | null>(null);
    const [hookResult, setHookResult] = useState<{ whatsapp: TelegramResult | null; telegram: TelegramResult | null }>({ whatsapp: null, telegram: null });
    const errors = form.errors as Record<string, string | undefined>;
    const fieldOf = (name: string) => groups.flatMap((group) => group.fields).find((field) => field.name === name);

    const setField = (name: string, value: string) => {
        const [group, key] = name.split('.');
        form.setData('settings', { ...form.data.settings, [group]: { ...form.data.settings[group], [key]: value } });
    };

    const value = (name: string) => {
        const [group, key] = name.split('.');

        return form.data.settings[group]?.[key] ?? '';
    };

    const runWhatsapp = async (action: 'status' | 'connect') => {
        setWaBusy(action);

        try {
            setWaResult(await postJson<WhatsappResult>(`/settings/integrations/whatsapp-${action}`));
        } catch {
            setWaResult({ connected: false, state: 'error', message: 'Gagal menghubungi server.' });
        } finally {
            setWaBusy(null);
        }
    };

    const runTelegram = async (action: 'status' | 'test') => {
        setTgBusy(action);

        try {
            setTgResult(await postJson<TelegramResult>(`/settings/telegram/${action}`));
        } catch {
            setTgResult({ ok: false, message: 'Gagal menghubungi server.' });
        } finally {
            setTgBusy(null);
        }
    };

    const runHook = async (channel: 'whatsapp' | 'telegram', path: string) => {
        setHookBusy(channel);

        try {
            const result = await postJson<TelegramResult>(path);
            setHookResult((current) => ({ ...current, [channel]: result }));
        } catch {
            setHookResult((current) => ({ ...current, [channel]: { ok: false, message: 'Gagal menghubungi server.' } }));
        } finally {
            setHookBusy(null);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => {
                for (const field of groups.flatMap((group) => group.fields).filter((item) => item.secret)) {
                    setField(field.name, '');
                }
            },
        });
    };

    const apiKey = fieldOf('whatsapp.api_key');
    const botToken = fieldOf('telegram.bot_token');
    const telegramOn = value('telegram.enabled') === '1';

    return (
        <>
            <PageHeader
                title="Notifikasi & Webhook"
                description="Saluran pesan keluar aplikasi: WhatsApp (Evolution API) untuk pelanggan dan admin, serta Telegram untuk peringatan admin. Nilai rahasia disimpan terenkripsi."
            />

            <form onSubmit={submit} className="space-y-5">
                <Card>
                    <div className="flex items-start justify-between gap-4 p-6 pb-0">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/12 text-emerald-600 dark:text-emerald-300">
                                <MessageCircle className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-[15px] font-semibold tracking-tight">WhatsApp — Evolution API</h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">Sidecar HTTP (Baileys di Docker). Isi URL Evolution, API key, dan nama instance, lalu hubungkan dengan scan QR.</p>
                            </div>
                        </div>
                        <Toggle label="Aktifkan" checked={value('whatsapp.enabled') !== '0'} onChange={(on) => setField('whatsapp.enabled', on ? '1' : '0')} />
                    </div>
                    <div className="grid gap-4 p-6 sm:grid-cols-2">
                        <Field label="Base URL" error={errors['settings.whatsapp.base_url']} hint={fieldOf('whatsapp.base_url')?.hint ?? undefined}>
                            <Input value={value('whatsapp.base_url')} placeholder="https://evo.example.com" onChange={(event) => setField('whatsapp.base_url', event.target.value)} />
                        </Field>
                        <Field label="Nama instance" error={errors['settings.whatsapp.instance']}>
                            <Input value={value('whatsapp.instance')} onChange={(event) => setField('whatsapp.instance', event.target.value)} />
                        </Field>
                        <Field label="API key" error={errors['settings.whatsapp.api_key']} hint={apiKey?.is_set ? 'Tersimpan. Kosongkan jika tidak diganti.' : 'Belum diisi.'}>
                            <Input
                                type="password"
                                autoComplete="new-password"
                                placeholder={apiKey?.is_set ? '••••••••' : 'Masukkan API key'}
                                value={value('whatsapp.api_key')}
                                onChange={(event) => setField('whatsapp.api_key', event.target.value)}
                            />
                        </Field>
                        <Field label="Nomor tes (62...)" error={errors['settings.whatsapp.test_phone']}>
                            <Input value={value('whatsapp.test_phone')} placeholder="62812xxxx" onChange={(event) => setField('whatsapp.test_phone', event.target.value)} />
                        </Field>
                    </div>
                    {whatsappQueue.stalled > 0 && (
                        <div className="mx-6 mb-5 flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                            <p>
                                <strong className="num">{whatsappQueue.stalled}</strong> pesan WhatsApp tertahan di antrean
                                {whatsappQueue.oldest_at ? ` sejak ${formatDate(whatsappQueue.oldest_at)}` : ''}. Queue worker kemungkinan tidak berjalan; jalankan{' '}
                                <code className="num">php artisan queue:work</code> (dan <code className="num">php artisan schedule:work</code> untuk tugas terjadwal).
                            </p>
                        </div>
                    )}
                    <div className="space-y-3 border-t p-6">
                        <div className="text-xs font-semibold text-muted-foreground">Status &amp; koneksi</div>
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
                                disabled={!value('whatsapp.test_phone')}
                                onClick={() => router.post('/settings/integrations/whatsapp-test', { phone: value('whatsapp.test_phone') }, { preserveScroll: true })}
                            >
                                <Send className="size-4" /> Kirim pesan uji
                            </Button>
                            <Button type="button" disabled={waBusy !== null} onClick={() => runWhatsapp('status')}>
                                <Wifi className="size-4" /> {waBusy === 'status' ? 'Memeriksa...' : 'Tes koneksi'}
                            </Button>
                        </div>
                    </div>
                    <BotSection
                        title="Bot pelanggan WhatsApp"
                        description="Pelanggan yang nomornya terdaftar bisa membalas pesan Anda untuk cek tagihan, status ONU, dan ganti WiFi. Pasang webhook agar Evolution API meneruskan pesan masuk ke aplikasi ini."
                        enabled={value('whatsapp.bot_enabled') === '1'}
                        onToggle={(on) => setField('whatsapp.bot_enabled', on ? '1' : '0')}
                        url={webhooks.whatsapp}
                        secretInUrl
                        installLabel="Pasang webhook ke Evolution"
                        onInstall={() => runHook('whatsapp', '/settings/whatsapp/webhook')}
                        busy={hookBusy === 'whatsapp'}
                        result={hookResult.whatsapp}
                    />
                </Card>

                <Card>
                    <div className="flex items-start justify-between gap-4 p-6 pb-0">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/12 text-sky-600 dark:text-sky-300">
                                <Send className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-[15px] font-semibold tracking-tight">Telegram — peringatan admin</h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Bot Telegram mengirim peringatan operasional ke Anda atau grup tim. Buat bot lewat @BotFather, kirim satu pesan ke bot itu, lalu isi token dan chat ID.
                                </p>
                            </div>
                        </div>
                        <Toggle label="Aktifkan" checked={telegramOn} onChange={(on) => setField('telegram.enabled', on ? '1' : '0')} />
                    </div>
                    <div className="grid gap-4 p-6 sm:grid-cols-2">
                        <Field
                            label="Token bot"
                            error={errors['settings.telegram.bot_token']}
                            hint={botToken?.is_set ? 'Tersimpan. Kosongkan jika tidak diganti.' : (botToken?.hint ?? 'Belum diisi.')}
                        >
                            <div className="relative">
                                <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="password"
                                    autoComplete="new-password"
                                    className="pl-9"
                                    placeholder={botToken?.is_set ? '••••••••' : '123456789:AAxxxx'}
                                    value={value('telegram.bot_token')}
                                    onChange={(event) => setField('telegram.bot_token', event.target.value)}
                                />
                            </div>
                        </Field>
                        <Field label="Chat ID tujuan" error={errors['settings.telegram.chat_id']} hint={fieldOf('telegram.chat_id')?.hint ?? undefined}>
                            <Input value={value('telegram.chat_id')} placeholder="-1001234567890" onChange={(event) => setField('telegram.chat_id', event.target.value)} />
                        </Field>
                        <Field
                            label="Pesan otomatis ke pelanggan"
                            className="sm:col-span-2"
                            error={errors['settings.telegram.customer_delivery']}
                            hint={fieldOf('telegram.customer_delivery')?.hint ?? undefined}
                        >
                            <Select value={value('telegram.customer_delivery') || 'both'} onChange={(event) => setField('telegram.customer_delivery', event.target.value)}>
                                <option value="off">Matikan (hanya WhatsApp)</option>
                                <option value="both">WhatsApp dan Telegram</option>
                                <option value="prefer">Utamakan Telegram (WhatsApp bila belum tertaut)</option>
                            </Select>
                        </Field>
                    </div>
                    <div className={cn('space-y-3 border-t px-6 py-5', !telegramOn && 'opacity-60')}>
                        <div className="text-xs font-semibold text-muted-foreground">Kirim peringatan untuk</div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Toggle
                                label="Router offline dan pulih"
                                checked={value('telegram.alert_router') !== '0'}
                                onChange={(on) => setField('telegram.alert_router', on ? '1' : '0')}
                                hint="Hanya saat status berubah, bukan tiap pengecekan."
                            />
                            <Toggle label="Gangguan ODP baru" checked={value('telegram.alert_outage') !== '0'} onChange={(on) => setField('telegram.alert_outage', on ? '1' : '0')} />
                            <Toggle
                                label="Pesan WhatsApp gagal"
                                checked={value('telegram.alert_whatsapp') !== '0'}
                                onChange={(on) => setField('telegram.alert_whatsapp', on ? '1' : '0')}
                                hint="Setelah semua percobaan kirim habis."
                            />
                            <Toggle
                                label="Isolir atau buka isolir gagal"
                                checked={value('telegram.alert_isolation') !== '0'}
                                onChange={(on) => setField('telegram.alert_isolation', on ? '1' : '0')}
                                hint="Termasuk pelanggan yang sudah bayar tetapi belum terbuka."
                            />
                        </div>
                    </div>
                    <div className="space-y-3 border-t p-6">
                        <div className="text-xs font-semibold text-muted-foreground">Status &amp; koneksi</div>
                        <p className={tgResult === null ? 'text-sm text-muted-foreground' : tgResult.ok ? 'text-sm text-emerald-600 dark:text-emerald-400' : 'text-sm text-destructive'}>
                            {tgResult?.message ?? 'Simpan pengaturan terlebih dahulu, lalu klik Tes koneksi.'}
                        </p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Button type="button" variant="outline" disabled={tgBusy !== null || !botToken?.is_set} onClick={() => runTelegram('test')}>
                                <Send className="size-4" /> {tgBusy === 'test' ? 'Mengirim...' : 'Kirim pesan uji'}
                            </Button>
                            <Button type="button" disabled={tgBusy !== null || !botToken?.is_set} onClick={() => runTelegram('status')}>
                                <Wifi className="size-4" /> {tgBusy === 'status' ? 'Memeriksa...' : 'Tes koneksi'}
                            </Button>
                        </div>
                    </div>
                    <BotSection
                        title="Bot pelanggan Telegram"
                        description="Pelanggan menautkan Telegram dengan membagikan nomornya, lalu memakai menu berikon untuk tagihan, status ONU, dan ganti WiFi. Pasang webhook agar Telegram meneruskan pesan ke aplikasi ini."
                        enabled={value('telegram.bot_enabled') === '1'}
                        onToggle={(on) => setField('telegram.bot_enabled', on ? '1' : '0')}
                        url={webhooks.telegram}
                        secretInUrl={false}
                        installLabel="Pasang webhook Telegram"
                        onInstall={() => runHook('telegram', '/settings/telegram/webhook')}
                        onInspect={() => runHook('telegram', '/settings/telegram/webhook-info')}
                        busy={hookBusy === 'telegram'}
                        result={hookResult.telegram}
                    />
                </Card>

                <Card>
                    <div className="flex items-start gap-3 p-6 pb-0">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/14 text-amber-600 dark:text-amber-300">
                            <BellRing className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-[15px] font-semibold tracking-tight">Pesan otomatis ke pelanggan</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">Pilih jenis pesan yang dikirim. Berlaku untuk WhatsApp dan Telegram sekaligus. Kode OTP login portal selalu dikirim.</p>
                        </div>
                    </div>
                    <div className="grid gap-x-8 gap-y-4 p-6 sm:grid-cols-2">
                        {(groups.find((group) => group.group === 'notify')?.fields ?? []).map((field) => (
                            <Toggle
                                key={field.name}
                                label={`${topicIcons[field.name.split('.')[1]] ?? '🔔'} ${field.label}`}
                                hint={field.hint ?? undefined}
                                checked={value(field.name) !== '0'}
                                onChange={(on) => setField(field.name, on ? '1' : '0')}
                            />
                        ))}
                    </div>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                    </Button>
                </div>
            </form>
        </>
    );
}
