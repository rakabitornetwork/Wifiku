import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy, Eye, EyeOff, FileCode, Plus, ShieldCheck, Wallet } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { VpnShell } from '@/Components/VpnShell';
import { StatusBadge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card, CardHeader, StatCard } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { cn, formatDate, formatRupiah } from '@/lib/utils';

type Forward = { name: string; public_port: number; target_port: number };

type AccountRow = {
    id: number;
    name: string;
    protocol: string;
    username: string;
    password: string;
    host: string;
    remote_address: string;
    forwards: Forward[];
    status: string;
    expires_at: string | null;
    monthly_price: number;
    pay_url: string | null;
    last_error: boolean;
};

type Props = {
    company: string;
    member: { name: string; email: string };
    accounts: AccountRow[];
    maxAccounts: number;
    price: number;
    protocols: ('l2tp' | 'sstp')[];
    presets: { label: string; target: number }[];
};

const defaultTargets = [22, 80, 8291, 8728];
const protocolNames = { l2tp: 'L2TP', sstp: 'SSTP' } as const;

function CopyValue({ label, value, secret = false }: { label: string; value: string; secret?: boolean }) {
    const [copied, setCopied] = useState(false);
    const [shown, setShown] = useState(false);

    const copy = () => {
        navigator.clipboard?.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <div className="flex items-center justify-between gap-2 rounded-lg bg-muted/60 px-3 py-1.5 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className="flex min-w-0 items-center gap-1.5">
                <span className="num truncate font-medium">{secret && !shown ? '••••••••••' : value}</span>
                {secret && (
                    <button type="button" onClick={() => setShown(!shown)} className="text-muted-foreground hover:text-foreground" aria-label={shown ? 'Sembunyikan' : 'Tampilkan'}>
                        {shown ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                    </button>
                )}
                <button type="button" onClick={copy} className="text-muted-foreground hover:text-foreground" aria-label={`Salin ${label}`}>
                    {copied ? <Check className="size-3.5 text-emerald-600" /> : <Copy className="size-3.5" />}
                </button>
            </span>
        </div>
    );
}

export default function Dashboard({ company, member, accounts, maxAccounts, price, protocols, presets }: Props) {
    const [creating, setCreating] = useState(false);
    const [script, setScript] = useState<{ title: string; text: string | null } | null>(null);
    const form = useForm<{ label: string; protocol: string; forwards: number[] }>({ label: '', protocol: protocols[0] ?? 'sstp', forwards: defaultTargets });
    const full = accounts.length >= maxAccounts;

    const openCreate = () => {
        form.setData({ label: '', protocol: protocols[0] ?? 'sstp', forwards: defaultTargets });
        form.clearErrors();
        setCreating(true);
    };

    const toggleTarget = (target: number) =>
        form.setData('forwards', form.data.forwards.includes(target) ? form.data.forwards.filter((item) => item !== target) : [...form.data.forwards, target]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/vpn-tunnel/akun');
    };

    const openScript = async (account: AccountRow) => {
        setScript({ title: `Script RouterOS · ${account.name}`, text: null });

        try {
            const response = await fetch(`/vpn-tunnel/akun/${account.id}/script`, { headers: { Accept: 'application/json' } });
            const data = (await response.json()) as { script?: string };
            setScript({ title: `Script RouterOS · ${account.name}`, text: data.script ?? 'Gagal memuat script.' });
        } catch {
            setScript({ title: `Script RouterOS · ${account.name}`, text: 'Gagal memuat script. Muat ulang halaman lalu coba lagi.' });
        }
    };

    return (
        <VpnShell
            company={company}
            member
            hero={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-sm text-indigo-200/80">Selamat datang,</p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl">{member.name}</h1>
                        <p className="mt-2 text-sm text-indigo-100/80">{member.email}</p>
                    </div>
                    <Button variant="add" onClick={openCreate} disabled={full || protocols.length === 0} className="h-11 px-5 ring-1 ring-white/40">
                        <Plus className="size-4" /> Buat Akun VPN
                    </Button>
                </div>
            }
        >
            <Head title="Akun VPN Saya" />
            <div className="mx-auto max-w-5xl space-y-5 px-4 pb-4">

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard tone={full ? 'amber' : 'blue'} label="Akun VPN" value={`${accounts.length} / ${maxAccounts}`} hint={full ? 'Batas akun tercapai' : `${maxAccounts - accounts.length} slot tersisa`} icon={<ShieldCheck className="size-4" />} />
                    <StatCard tone="violet" label="Harga per akun" value={formatRupiah(price)} hint="per bulan" icon={<Wallet className="size-4" />} />
                </div>

                {protocols.length === 0 && <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">Layanan sedang tidak tersedia. Silakan hubungi CS.</p>}

                {accounts.length === 0 ? (
                    <Card className="px-6 py-14 text-center">
                        <p className="text-sm font-medium">Belum ada akun VPN</p>
                        <p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">Buat akun pertama Anda, bayar lewat QRIS atau Virtual Account, lalu tempel script ke router MikroTik.</p>
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {accounts.map((account) => (
                            <Card key={account.id} className={cn(account.status === 'pending' && 'border-amber-500/40')}>
                                <CardHeader
                                    title={account.name}
                                    description={`${account.protocol} · ${formatRupiah(account.monthly_price)} / bulan`}
                                    action={account.last_error ? <span className="text-xs font-medium text-destructive">Gagal dipasang, hubungi CS</span> : <StatusBadge status={account.status} />}
                                />
                                <div className="space-y-2 p-4">
                                    <CopyValue label="Server" value={account.host} />
                                    <CopyValue label="Username" value={account.username} />
                                    <CopyValue label="Password" value={account.password} secret />
                                    <CopyValue label="IP tunnel" value={account.remote_address} />

                                    {account.forwards.length > 0 && (
                                        <div className="pt-1">
                                            <p className="mb-1 text-xs font-medium text-muted-foreground">Port forward</p>
                                            <div className="grid grid-cols-[minmax(0,7rem)_auto_auto] gap-x-2 gap-y-0.5 text-xs whitespace-nowrap">
                                                {account.forwards.map((forward) => (
                                                    <button
                                                        key={forward.public_port}
                                                        type="button"
                                                        onClick={() => navigator.clipboard?.writeText(`${account.host}:${forward.public_port}`)}
                                                        title="Klik untuk menyalin"
                                                        className="col-span-3 grid cursor-copy grid-cols-subgrid items-center rounded px-1 text-left hover:bg-muted"
                                                    >
                                                        <span className="truncate text-muted-foreground">{forward.name}</span>
                                                        <span className="num font-medium">
                                                            {account.host}:{forward.public_port}
                                                        </span>
                                                        <span className="num text-muted-foreground">→ {forward.target_port}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                                        <span className="text-xs text-muted-foreground">{account.expires_at ? `Berlaku s/d ${formatDate(account.expires_at)}` : 'Belum aktif'}</span>
                                        <div className="flex gap-2">
                                            {account.status === 'active' && (
                                                <Button variant="outline" size="sm" onClick={() => openScript(account)}>
                                                    <FileCode className="size-3.5" /> Script
                                                </Button>
                                            )}
                                            {account.pay_url && (
                                                <a href={account.pay_url} className={buttonClass(account.status === 'pending' ? 'primary' : 'warning', 'sm')}>
                                                    {account.status === 'pending' ? 'Bayar & aktifkan' : 'Perpanjang'}
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="Buat Akun VPN"
                description={`Tagihan ${formatRupiah(price)} untuk bulan pertama langsung terbit. Akun aktif setelah dibayar.`}
                footer={
                    <>
                        <Button variant="close" onClick={() => setCreating(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="member-account-form" disabled={form.processing}>
                            {form.processing ? 'Membuat...' : 'Buat & bayar'}
                        </Button>
                    </>
                }
            >
                <form id="member-account-form" onSubmit={submit} className="space-y-4">
                    <Field label="Nama akun" error={form.errors.label} hint="Untuk membedakan router, mis. “Router Rumah” atau “POP Desa A”.">
                        <Input value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} maxLength={40} autoFocus required />
                    </Field>
                    <Field label="Protokol" error={form.errors.protocol}>
                        <div className="flex gap-2">
                            {protocols.map((protocol) => (
                                <button
                                    key={protocol}
                                    type="button"
                                    onClick={() => form.setData('protocol', protocol)}
                                    className={cn('rounded-lg border px-4 py-1.5 text-sm font-medium transition', form.data.protocol === protocol ? 'border-primary bg-accent text-accent-foreground' : 'hover:bg-muted')}
                                >
                                    {protocolNames[protocol]}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <Field label="Port yang diteruskan ke router Anda" error={form.errors.forwards} hint="Nomor port publiknya dibuat otomatis.">
                        <div className="flex flex-wrap gap-2">
                            {presets.map((preset) => {
                                const active = form.data.forwards.includes(preset.target);

                                return (
                                    <button
                                        key={preset.target}
                                        type="button"
                                        onClick={() => toggleTarget(preset.target)}
                                        className={cn('num rounded-full border px-3 py-1 text-xs font-medium transition', active ? 'border-primary bg-accent text-accent-foreground' : 'hover:bg-muted')}
                                    >
                                        {active && <Check className="mr-1 inline size-3" />}
                                        {preset.label} <span className="text-muted-foreground">{preset.target}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </Field>
                </form>
            </Modal>

            <Modal
                open={script !== null}
                onClose={() => setScript(null)}
                size="lg"
                title={script?.title ?? ''}
                description="Tempel di New Terminal pada Winbox router MikroTik Anda."
                footer={
                    <Button variant="outline" onClick={() => script?.text && navigator.clipboard?.writeText(script.text)} disabled={!script?.text}>
                        <Copy className="size-4" /> Salin
                    </Button>
                }
            >
                <pre className="max-h-72 overflow-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">{script?.text ?? 'Memuat...'}</pre>
                <p className="mt-3 text-xs text-muted-foreground">Script berisi password akun Anda. Jangan dibagikan.</p>
            </Modal>
        </VpnShell>
    );
}
