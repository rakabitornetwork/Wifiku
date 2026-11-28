import { useForm } from '@inertiajs/react';
import { Download, KeyRound, ShieldCheck } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select } from '@/Components/ui/Field';

type Gateway = {
    id: number;
    driver: string;
    name: string;
    is_active: boolean;
    is_sandbox: boolean;
    enabled_channels: string[];
    fee_bearer: string;
    sort_order: number;
    credentials: { key: string; label: string; is_set: boolean }[];
};

const feeLabels: Record<string, string> = { merchant: 'Ditanggung merchant', customer: 'Ditanggung pelanggan' };

function GatewayCard({ gateway, mask, feeBearers, callbackUrl }: { gateway: Gateway; mask: string; feeBearers: string[]; callbackUrl: string }) {
    const [channels, setChannels] = useState<{ code: string; name: string }[] | null>(null);
    const [channelError, setChannelError] = useState<string | null>(null);

    const form = useForm({
        name: gateway.name,
        is_active: gateway.is_active,
        is_sandbox: gateway.is_sandbox,
        fee_bearer: gateway.fee_bearer,
        sort_order: String(gateway.sort_order),
        enabled_channels: gateway.enabled_channels.join(', '),
        credentials: Object.fromEntries(gateway.credentials.map((credential) => [credential.key, ''])) as Record<string, string>,
    });

    const loadChannels = async () => {
        setChannelError(null);

        try {
            const response = await fetch(`/settings/payment-gateways/${gateway.id}/channels`, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            if (!response.ok) {
                setChannelError(data.message ?? 'Gagal mengambil channel.');

                return;
            }

            setChannels(data.channels);
        } catch {
            setChannelError('Gagal menghubungi server.');
        }
    };

    const toggleChannel = (code: string) => {
        const current = form.data.enabled_channels.split(',').map((item) => item.trim()).filter(Boolean);
        const next = current.includes(code) ? current.filter((item) => item !== code) : [...current, code];
        form.setData('enabled_channels', next.join(', '));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            enabled_channels: data.enabled_channels
                .split(',')
                .map((channel) => channel.trim().toUpperCase())
                .filter(Boolean),
        }));
        form.put(`/settings/payment-gateways/${gateway.id}`, {
            preserveScroll: true,
            onSuccess: () => form.setData('credentials', Object.fromEntries(gateway.credentials.map((credential) => [credential.key, '']))),
        });
    };

    return (
        <Card>
            <CardHeader
                title={gateway.name}
                description={`Driver: ${gateway.driver}`}
                action={gateway.is_active ? <Badge tone="green">Aktif</Badge> : <Badge>Nonaktif</Badge>}
            />
            <form onSubmit={submit} className="space-y-5 p-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama tampilan" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Urutan prioritas" error={form.errors.sort_order} hint="Gateway aktif dengan angka terkecil dipakai lebih dulu.">
                        <Input type="number" min={0} max={255} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} required />
                    </Field>
                    <Field label="Biaya transaksi" error={form.errors.fee_bearer}>
                        <Select value={form.data.fee_bearer} onChange={(event) => form.setData('fee_bearer', event.target.value)}>
                            {feeBearers.map((bearer) => (
                                <option key={bearer} value={bearer}>
                                    {feeLabels[bearer] ?? bearer}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Channel aktif" error={form.errors.enabled_channels} hint="Kode channel gateway, pisahkan dengan koma. Gunakan tombol di bawah untuk memilih dari daftar resmi.">
                        <Input
                            value={form.data.enabled_channels}
                            onChange={(event) => form.setData('enabled_channels', event.target.value)}
                            placeholder={gateway.driver === 'midtrans' ? 'other_qris, bri_va' : gateway.driver === 'duitku' ? 'SP, BR' : 'QRIS, BRIVA'}
                        />
                    </Field>
                </div>

                <div className="space-y-2">
                    <Button variant="outline" size="sm" onClick={loadChannels}>
                        <Download className="size-3.5" /> Ambil daftar channel dari {gateway.name}
                    </Button>
                    {channelError && <p className="text-xs text-destructive">{channelError}</p>}
                    {channels && (
                        <div className="flex flex-wrap gap-1.5">
                            {channels.map((channel) => {
                                const selected = form.data.enabled_channels.split(',').map((item) => item.trim()).includes(channel.code);

                                return (
                                    <button
                                        key={channel.code}
                                        type="button"
                                        onClick={() => toggleChannel(channel.code)}
                                        className={`rounded-md border px-2 py-1 text-xs ${selected ? 'border-primary bg-accent text-accent-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                                        title={channel.name}
                                    >
                                        <span className="num font-medium">{channel.code}</span> · {channel.name}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                    <p className="text-xs text-muted-foreground">
                        URL callback: <span className="num break-all text-foreground">{callbackUrl}</span>
                        {gateway.driver === 'tripay' && ' (daftarkan juga di dashboard Tripay).'}
                    </p>
                </div>

                <div className="rounded-lg border bg-muted/40 p-4">
                    <div className="mb-3 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <ShieldCheck className="size-4" /> Kredensial terenkripsi. Nilai tersimpan tidak pernah ditampilkan kembali.
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {gateway.credentials.map((credential) => (
                            <Field
                                key={credential.key}
                                label={credential.label}
                                error={(form.errors as Record<string, string | undefined>)[`credentials.${credential.key}`]}
                                hint={credential.is_set ? 'Tersimpan. Kosongkan jika tidak ingin mengubah.' : 'Belum diisi.'}
                            >
                                <div className="relative">
                                    <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="password"
                                        autoComplete="new-password"
                                        className="pl-9"
                                        placeholder={credential.is_set ? mask : 'Masukkan nilai'}
                                        value={form.data.credentials[credential.key]}
                                        onChange={(event) => form.setData('credentials', { ...form.data.credentials, [credential.key]: event.target.value })}
                                    />
                                </div>
                            </Field>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap gap-5">
                        <Checkbox label="Aktifkan gateway" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                        <Checkbox label="Mode sandbox (uji coba)" checked={form.data.is_sandbox} onChange={(checked) => form.setData('is_sandbox', checked)} />
                    </div>
                    <Button variant="save" type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

export default function PaymentGateways({
    gateways,
    mask,
    feeBearers,
    callbackUrls,
}: {
    gateways: Gateway[];
    mask: string;
    feeBearers: string[];
    callbackUrls: Record<string, string>;
}) {
    return (
        <>
            <PageHeader title="Payment Gateway" description="Kredensial Duitku, Tripay, dan Midtrans dikelola di sini, bukan di file .env. Hanya Owner yang dapat mengakses." />
            <div className="space-y-4">
                {gateways.map((gateway) => (
                    <GatewayCard key={gateway.id} gateway={gateway} mask={mask} feeBearers={feeBearers} callbackUrl={callbackUrls[gateway.driver]} />
                ))}
            </div>
        </>
    );
}
