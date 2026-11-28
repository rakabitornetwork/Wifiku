import { Link, router, useForm } from '@inertiajs/react';
import { Copy, Download, ExternalLink, FileCode, HardDriveUpload, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Badge, StatusBadge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, SortTh, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { useServerSort } from '@/Hooks/useSort';
import { formatDate, formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';

type Forward = { name: string; public_port: string; target_port: string };

type ServerRow = {
    id: number;
    router_id: number;
    name: string;
    public_host: string;
    local_address: string;
    pool_start: string;
    pool_end: string;
    use_ipsec: boolean;
    sstp_port: number;
    port_block_offset: number;
    l2tp_enabled: boolean;
    sstp_enabled: boolean;
    is_active: boolean;
    setup_at: string | null;
    notes: string | null;
    accounts_count: number;
    router: { name: string };
};

type AccountRow = {
    id: number;
    vpn_server_id: number;
    name: string;
    whatsapp: string | null;
    protocol: 'l2tp' | 'sstp';
    username: string;
    remote_address: string;
    forwards: { name: string; public_port: number; target_port: number }[] | null;
    status: string;
    adopted: boolean;
    expires_at: string | null;
    monthly_price: number;
    portal_token: string;
    auto_disabled_at: string | null;
    synced_at: string | null;
    last_error: string | null;
    notes: string | null;
    server: { id: number; name: string; public_host: string };
    member: { id: number; name: string; email: string } | null;
};

type Props = { accounts: Paginated<AccountRow>; servers: ServerRow[]; routers: { id: number; name: string }[]; search: string; serverId: string; memberFilter: { id: number; name: string } | null };

/** Layanan standar; kode di akhir port publik mengikuti pola server (lihat VpnPortPlanner di backend). */
const portPresets = [
    { label: 'SSH', target: 22, suffix: 22 },
    { label: 'HTTP', target: 80, suffix: 80 },
    { label: 'OLT Hioso', target: 8070, suffix: 87 },
    { label: 'Winbox', target: 8291, suffix: 91 },
    { label: 'API RouterOS', target: 8728, suffix: 28 },
];

/** Port forward yang otomatis terisi di akun baru: SSH, HTTP, Winbox, dan API. */
const defaultPresetTargets = [22, 80, 8291, 8728];

function suggestedPort(offset: number | undefined, address: string, target: string): string {
    const suffix = portPresets.find((preset) => String(preset.target) === target)?.suffix;
    const octet = Number(address.split('.').pop());

    if (offset === undefined || suffix === undefined || !Number.isInteger(octet) || octet < 1) {
        return '';
    }

    const port = (offset + octet) * 100 + suffix;

    return port >= 1024 && port <= 65535 ? String(port) : '';
}

type ImportRow = { username: string; name: string; service: string; profile: string; remote_address: string | null; disabled: boolean; forwards: { name: string; public_port: number; target_port: number }[]; skipped_rules: number; status: 'importable' | 'imported' | 'skipped'; reason: string | null };

const protocolLabel = { l2tp: 'L2TP', sstp: 'SSTP' } as const;

const emptyServer = { router_id: '', name: '', public_host: '', local_address: '10.99.0.1', pool_start: '10.99.0.2', pool_end: '10.99.0.254', ipsec_secret: '', use_ipsec: true, sstp_port: '443', port_block_offset: '122', l2tp_enabled: true, sstp_enabled: true, is_active: true, notes: '' };

const emptyAccount = { vpn_server_id: '', name: '', whatsapp: '', protocol: 'sstp', username: '', password: '', remote_address: '', forwards: [] as Forward[], status: 'active', expires_at: '', monthly_price: '0', notes: '' };

export default function VpnIndex({ accounts, servers, routers, search, serverId, memberFilter }: Props) {
    const sort = useServerSort();
    const [serverEditing, setServerEditing] = useState<ServerRow | null>(null);
    const [serverOpen, setServerOpen] = useState(false);
    const [accountEditing, setAccountEditing] = useState<AccountRow | null>(null);
    const [accountOpen, setAccountOpen] = useState(false);
    const [scripts, setScripts] = useState<{ title: string; tabs: { label: string; hint: string; text: string | null }[]; active: number } | null>(null);
    const [installing, setInstalling] = useState<number | null>(null);
    const [importing, setImporting] = useState<{ server: ServerRow; rows: ImportRow[] | null; error: string | null; selected: string[]; price: string; expires: string; busy: boolean } | null>(null);
    const serverForm = useForm(emptyServer);
    const accountForm = useForm(emptyAccount);
    const accountErrors = accountForm.errors as Record<string, string | undefined>;
    const selectedServer = servers.find((server) => String(server.id) === accountForm.data.vpn_server_id);

    const openServerCreate = () => {
        setServerEditing(null);
        serverForm.setData(emptyServer);
        serverForm.clearErrors();
        setServerOpen(true);
    };

    const openServerEdit = (server: ServerRow) => {
        setServerEditing(server);
        serverForm.setData({
            router_id: String(server.router_id),
            name: server.name,
            public_host: server.public_host,
            local_address: server.local_address,
            pool_start: server.pool_start,
            pool_end: server.pool_end,
            ipsec_secret: '',
            use_ipsec: server.use_ipsec,
            sstp_port: String(server.sstp_port),
            port_block_offset: String(server.port_block_offset),
            l2tp_enabled: server.l2tp_enabled,
            sstp_enabled: server.sstp_enabled,
            is_active: server.is_active,
            notes: server.notes ?? '',
        });
        serverForm.clearErrors();
        setServerOpen(true);
    };

    const submitServer = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setServerOpen(false) };

        if (serverEditing) {
            serverForm.put(`/vpn/servers/${serverEditing.id}`, options);
        } else {
            serverForm.post('/vpn/servers', options);
        }
    };

    const openAccountCreate = () => {
        setAccountEditing(null);
        accountForm.setData({
            ...emptyAccount,
            vpn_server_id: servers.find((server) => server.is_active)?.id.toString() ?? '',
            // Akun baru langsung mendapat port forward standar; hapus yang tidak perlu atau tambah OLT lewat tombol preset.
            forwards: portPresets.filter((preset) => defaultPresetTargets.includes(preset.target)).map((preset) => ({ name: preset.label, public_port: '', target_port: String(preset.target) })),
        });
        accountForm.clearErrors();
        setAccountOpen(true);
    };

    const openAccountEdit = (account: AccountRow) => {
        setAccountEditing(account);
        accountForm.setData({
            vpn_server_id: String(account.vpn_server_id),
            name: account.name,
            whatsapp: account.whatsapp ?? '',
            protocol: account.protocol,
            username: account.username,
            password: '',
            remote_address: account.remote_address,
            forwards: (account.forwards ?? []).map((forward) => ({ name: forward.name, public_port: String(forward.public_port), target_port: String(forward.target_port) })),
            status: account.status,
            expires_at: account.expires_at ?? '',
            monthly_price: String(account.monthly_price),
            notes: account.notes ?? '',
        });
        accountForm.clearErrors();
        setAccountOpen(true);
    };

    const submitAccount = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setAccountOpen(false) };

        if (accountEditing) {
            accountForm.put(`/vpn/accounts/${accountEditing.id}`, options);
        } else {
            accountForm.post('/vpn/accounts', options);
        }
    };

    const updateForward = (index: number, key: keyof Forward, value: string) => {
        accountForm.setData(
            'forwards',
            accountForm.data.forwards.map((forward, position) => (position === index ? { ...forward, [key]: value } : forward)),
        );
    };

    const loadJson = async (url: string): Promise<Record<string, string> | null> => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            return response.ok ? ((await response.json()) as Record<string, string>) : null;
        } catch {
            return null;
        }
    };

    const failed = 'Gagal memuat script. Muat ulang halaman lalu coba lagi.';

    const openAccountScripts = async (account: AccountRow) => {
        const title = `Script VPN · ${account.username}`;
        const tabs = [
            { label: 'Router pelanggan', hint: 'Tempel di terminal RouterOS milik pelanggan (New Terminal di Winbox).', text: null as string | null },
            { label: 'Router server', hint: 'Tempel di terminal router server Anda bila tidak memakai tombol "Sinkron".', text: null as string | null },
        ];
        setScripts({ title, tabs, active: 0 });

        const data = await loadJson(`/vpn/accounts/${account.id}/scripts`);
        setScripts({ title, active: 0, tabs: [{ ...tabs[0], text: data?.client ?? failed }, { ...tabs[1], text: data?.server ?? failed }] });
    };

    const openServerScript = async (server: ServerRow) => {
        const title = `Script setup · ${server.name}`;
        const tab = { label: 'Router server', hint: 'Jalankan sekali di router server (terminal), atau pakai tombol "Pasang ke router" untuk lewat API.', text: null as string | null };
        setScripts({ title, tabs: [tab], active: 0 });

        const data = await loadJson(`/vpn/servers/${server.id}/script`);
        setScripts({ title, active: 0, tabs: [{ ...tab, text: data?.script ?? failed }] });
    };

    const openImport = async (server: ServerRow) => {
        setImporting({ server, rows: null, error: null, selected: [], price: '0', expires: '', busy: false });

        try {
            const response = await fetch(`/vpn/servers/${server.id}/import-preview`, { headers: { Accept: 'application/json' } });
            const data = (await response.json()) as { rows?: ImportRow[]; error?: string };

            if (!response.ok || !data.rows) {
                setImporting((current) => current && { ...current, error: data.error ?? 'Gagal membaca router.' });

                return;
            }

            const rows = data.rows;
            setImporting((current) => current && { ...current, rows, selected: rows.filter((row) => row.status === 'importable').map((row) => row.username) });
        } catch {
            setImporting((current) => current && { ...current, error: 'Gagal membaca router. Muat ulang halaman lalu coba lagi.' });
        }
    };

    const submitImport = () => {
        if (!importing) {
            return;
        }

        router.post(
            `/vpn/servers/${importing.server.id}/import`,
            { usernames: importing.selected, monthly_price: importing.price, expires_at: importing.expires || null },
            { preserveScroll: true, onStart: () => setImporting((current) => current && { ...current, busy: true }), onSuccess: () => setImporting(null), onError: () => setImporting((current) => current && { ...current, busy: false }) },
        );
    };

    const addPreset = (preset: (typeof portPresets)[number]) => {
        if (accountForm.data.forwards.some((forward) => forward.target_port === String(preset.target))) {
            return;
        }

        accountForm.setData('forwards', [...accountForm.data.forwards, { name: preset.label, public_port: '', target_port: String(preset.target) }]);
    };

    const install = (server: ServerRow) =>
        router.post(`/vpn/servers/${server.id}/install`, {}, { preserveScroll: true, onStart: () => setInstalling(server.id), onFinish: () => setInstalling(null) });

    const activeTab = scripts?.tabs[scripts.active];

    return (
        <>
            <PageHeader
                title="VPN Tunnel"
                description="Jual layanan VPN L2TP (dengan atau tanpa IPsec) dan SSTP. Script RouterOS dibuat otomatis untuk router pelanggan dan router server Anda."
                actions={
                    <Button onClick={openAccountCreate} disabled={servers.length === 0}>
                        <Plus className="size-4" /> Tambah Akun VPN
                    </Button>
                }
            />


            <Card className="mb-4">
                <CardHeader
                    title="Server VPN"
                    description="Router MikroTik Anda yang menerima koneksi tunnel dari pelanggan."
                    action={
                        <Button variant="outline" size="sm" onClick={openServerCreate}>
                            <Plus className="size-4" /> Tambah Server
                        </Button>
                    }
                />
                {servers.length === 0 ? (
                    <EmptyState title="Belum ada server VPN" description="Tambahkan router server beserta pool IP tunnel-nya, lalu pasang setup-nya ke router." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Server</Th>
                                    <Th>Router</Th>
                                    <Th>Alamat publik</Th>
                                    <Th>Pool IP tunnel</Th>
                                    <Th>Protokol</Th>
                                    <Th>Akun</Th>
                                    <Th>Status</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {servers.map((server) => (
                                    <tr key={server.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{server.name}</Td>
                                        <Td className="text-muted-foreground">{server.router.name}</Td>
                                        <Td className="num text-muted-foreground">{server.public_host}</Td>
                                        <Td className="num text-muted-foreground">
                                            {server.pool_start} – {server.pool_end}
                                        </Td>
                                        <Td>
                                            <div className="flex gap-1">
                                                {server.l2tp_enabled && <Badge tone="blue">{server.use_ipsec ? 'L2TP/IPsec' : 'L2TP'}</Badge>}
                                                {server.sstp_enabled && <Badge tone="blue">SSTP :{server.sstp_port}</Badge>}
                                            </div>
                                        </Td>
                                        <Td className="num">{server.accounts_count}</Td>
                                        <Td>
                                            <div className="flex flex-col items-center gap-1 text-center">
                                                {server.is_active ? <Badge tone="green">Dijual</Badge> : <Badge>Nonaktif</Badge>}
                                                <span className="text-xs text-muted-foreground">{server.setup_at ? `Terpasang ${formatDate(server.setup_at)}` : 'Belum dipasang'}</span>
                                            </div>
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="success" size="icon" onClick={() => install(server)} disabled={installing === server.id} aria-label="Pasang ke router" title="Pasang setup ke router lewat API">
                                                    <HardDriveUpload className={installing === server.id ? 'size-4 animate-pulse' : 'size-4'} />
                                                </Button>
                                                <Button variant="outline" size="icon" onClick={() => openImport(server)} aria-label="Impor akun dari router" title="Impor akun & port forward yang sudah ada di router">
                                                    <Download className="size-4" />
                                                </Button>
                                                <Button variant="outline" size="icon" onClick={() => openServerScript(server)} aria-label="Script setup" title="Lihat script setup server">
                                                    <FileCode className="size-4" />
                                                </Button>
                                                <Button variant="edit" size="icon" onClick={() => openServerEdit(server)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/vpn/servers/${server.id}`} title={`Hapus server ${server.name}?`} description="Server yang masih memiliki akun tidak dapat dihapus. Konfigurasi di router tidak ikut dihapus." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
            </Card>

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={search} placeholder="Cari nama, username, atau IP..." extra={{ server_id: serverId || undefined, member_id: memberFilter ? String(memberFilter.id) : undefined }} />
                    <Select
                        className="sm:w-52"
                        value={serverId}
                        onChange={(event) => router.get('/vpn', { search: search || undefined, server_id: event.target.value || undefined, member_id: memberFilter?.id }, { preserveState: true, replace: true })}
                        aria-label="Filter server"
                    >
                        <option value="">Semua server</option>
                        {servers.map((server) => (
                            <option key={server.id} value={server.id}>
                                {server.name}
                            </option>
                        ))}
                    </Select>
                </div>
                {memberFilter && (
                    <div className="flex items-center justify-between gap-3 border-b bg-accent px-4 py-2 text-sm text-accent-foreground">
                        <span>
                            Menampilkan akun milik member <strong>{memberFilter.name}</strong>
                        </span>
                        <Link href="/vpn" className="text-xs font-medium underline">
                            Tampilkan semua
                        </Link>
                    </div>
                )}
                {accounts.data.length === 0 ? (
                    <EmptyState title="Belum ada akun VPN" description="Buat akun VPN untuk pelanggan; script untuk router mereka tersedia setelah akun dibuat." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="name" state={sort}>Pelanggan</SortTh>
                                    <SortTh column="username" state={sort}>Username</SortTh>
                                    <SortTh column="protocol" state={sort}>Protokol</SortTh>
                                    <SortTh column="remote_address" state={sort}>IP tunnel</SortTh>
                                    <Th>Port forward</Th>
                                    <SortTh column="expires_at" state={sort}>Berlaku s/d</SortTh>
                                    <SortTh column="monthly_price" state={sort} className="text-right">Harga / bulan</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {accounts.data.map((account) => (
                                    <tr key={account.id} className="hover:bg-muted/40">
                                        <Td>
                                            <span className="font-medium">{account.name}</span>
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {account.server.name}
                                                {account.member && <span className="ml-1.5 rounded bg-sky-500/10 px-1 py-0.5 text-[10px] text-sky-600 dark:text-sky-400" title={account.member.email}>member: {account.member.name}</span>}
                                                {account.adopted && <span className="ml-1.5 rounded bg-muted px-1 py-0.5 text-[10px] uppercase">impor</span>}
                                            </span>
                                        </Td>
                                        <Td className="num">{account.username}</Td>
                                        <Td>
                                            <Badge tone="blue">{protocolLabel[account.protocol]}</Badge>
                                        </Td>
                                        <Td className="num text-muted-foreground">{account.remote_address}</Td>
                                        <Td>
                                            {account.forwards?.length ? (
                                                <div className="grid w-max grid-cols-[minmax(0,7rem)_auto_auto] items-center gap-x-2 gap-y-0.5 text-xs whitespace-nowrap">
                                                    {account.forwards.map((forward) => (
                                                        <button
                                                            key={forward.public_port}
                                                            type="button"
                                                            onClick={() => navigator.clipboard?.writeText(`${account.server.public_host}:${forward.public_port}`)}
                                                            title={`Salin ${account.server.public_host}:${forward.public_port} → ${account.remote_address}:${forward.target_port}`}
                                                            className="col-span-3 grid cursor-copy grid-cols-subgrid items-center rounded px-1 text-left hover:bg-muted"
                                                        >
                                                            <span className="truncate text-muted-foreground">{forward.name}</span>
                                                            <span className="num font-medium">{account.server.public_host}:{forward.public_port}</span>
                                                            <span className="num text-muted-foreground">→ {forward.target_port}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </Td>
                                        <Td className="text-muted-foreground">{formatDate(account.expires_at)}</Td>
                                        <Td className="num text-right">{formatRupiah(account.monthly_price)}</Td>
                                        <Td className="text-center">
                                            <span title={account.last_error ?? undefined}>
                                                {account.last_error ? <Badge tone="red">Gagal sinkron</Badge> : <StatusBadge status={account.status} />}
                                            </span>
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="outline" size="icon" onClick={() => openAccountScripts(account)} aria-label="Script RouterOS" title="Script RouterOS pelanggan & server">
                                                    <FileCode className="size-4" />
                                                </Button>
                                                <a
                                                    href={`/layanan-vpn/${account.portal_token}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className={buttonClass('info', 'icon')}
                                                    aria-label="Halaman bayar pelanggan"
                                                    title="Halaman tagihan & bayar pelanggan"
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                                <Button variant="success" size="icon" onClick={() => router.post(`/vpn/accounts/${account.id}/sync`, {}, { preserveScroll: true })} aria-label="Sinkron ke server" title="Pasang ulang ke router server">
                                                    <RefreshCw className="size-4" />
                                                </Button>
                                                <Button variant="edit" size="icon" onClick={() => openAccountEdit(account)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/vpn/accounts/${account.id}`} title={`Hapus akun ${account.username}?`} description="PPP secret dan port forward akun ini dicabut dari router server; tunnel pelanggan akan terputus." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={accounts} />
            </Card>

            <Modal
                open={serverOpen}
                onClose={() => setServerOpen(false)}
                size="lg"
                title={serverEditing ? 'Ubah Server VPN' : 'Tambah Server VPN'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setServerOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="vpn-server-form" disabled={serverForm.processing}>
                            {serverForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="vpn-server-form" onSubmit={submitServer} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama server" error={serverForm.errors.name}>
                        <Input value={serverForm.data.name} onChange={(event) => serverForm.setData('name', event.target.value)} placeholder="VPN Jakarta" required />
                    </Field>
                    <Field label="Router MikroTik" error={serverForm.errors.router_id} hint="Router yang sudah terdaftar di Manajemen Router.">
                        <Select value={serverForm.data.router_id} onChange={(event) => serverForm.setData('router_id', event.target.value)} required>
                            <option value="">Pilih router</option>
                            {routers.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Alamat publik (domain / IP)" error={serverForm.errors.public_host} hint="Alamat yang dihubungi router pelanggan." className="sm:col-span-2">
                        <Input value={serverForm.data.public_host} onChange={(event) => serverForm.setData('public_host', event.target.value)} placeholder="vpn.teslatech.my.id" required />
                    </Field>
                    <Field label="IP lokal server" error={serverForm.errors.local_address}>
                        <Input inputMode="decimal" value={serverForm.data.local_address} onChange={(event) => serverForm.setData('local_address', event.target.value)} required />
                    </Field>
                    <div className="hidden sm:block" />
                    <Field label="Pool awal" error={serverForm.errors.pool_start}>
                        <Input inputMode="decimal" value={serverForm.data.pool_start} onChange={(event) => serverForm.setData('pool_start', event.target.value)} required />
                    </Field>
                    <Field label="Pool akhir" error={serverForm.errors.pool_end}>
                        <Input inputMode="decimal" value={serverForm.data.pool_end} onChange={(event) => serverForm.setData('pool_end', event.target.value)} required />
                    </Field>
                    <Field
                        label="IPsec secret (L2TP)"
                        error={serverForm.errors.ipsec_secret}
                        hint={!serverForm.data.use_ipsec ? 'IPsec dimatikan: L2TP polos tanpa enkripsi tambahan.' : serverEditing ? 'Kosongkan bila tidak diubah.' : undefined}
                    >
                        <Input
                            type="password"
                            autoComplete="new-password"
                            value={serverForm.data.ipsec_secret}
                            onChange={(event) => serverForm.setData('ipsec_secret', event.target.value)}
                            placeholder={serverEditing ? '••••••••' : ''}
                            disabled={!serverForm.data.use_ipsec || !serverForm.data.l2tp_enabled}
                        />
                    </Field>
                    <Field label="Port SSTP" error={serverForm.errors.sstp_port}>
                        <Input type="number" min={1} max={65535} value={serverForm.data.sstp_port} onChange={(event) => serverForm.setData('sstp_port', event.target.value)} required />
                    </Field>
                    <Field label="Offset blok port forward" error={serverForm.errors.port_block_offset} hint="Port publik = (offset + oktet terakhir IP tunnel) × 100 + kode layanan. Kode: SSH 22, HTTP 80, OLT 87, Winbox 91, API 28." className="sm:col-span-2">
                        <Input type="number" min={1} max={400} value={serverForm.data.port_block_offset} onChange={(event) => serverForm.setData('port_block_offset', event.target.value)} required />
                    </Field>
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 sm:col-span-2">
                        <Checkbox label="L2TP" checked={serverForm.data.l2tp_enabled} onChange={(value) => serverForm.setData('l2tp_enabled', value)} />
                        <Checkbox label="Pakai IPsec untuk L2TP" checked={serverForm.data.use_ipsec} onChange={(value) => serverForm.setData('use_ipsec', value)} />
                        <Checkbox label="SSTP" checked={serverForm.data.sstp_enabled} onChange={(value) => serverForm.setData('sstp_enabled', value)} />
                        <Checkbox label="Dijual (akun baru boleh dibuat)" checked={serverForm.data.is_active} onChange={(value) => serverForm.setData('is_active', value)} />
                        {serverForm.errors.l2tp_enabled && <p className="w-full text-xs text-destructive">{serverForm.errors.l2tp_enabled}</p>}
                    </div>
                    <Field label="Catatan" error={serverForm.errors.notes} className="sm:col-span-2">
                        <Textarea value={serverForm.data.notes} onChange={(event) => serverForm.setData('notes', event.target.value)} />
                    </Field>
                </form>
            </Modal>

            <Modal
                open={accountOpen}
                onClose={() => setAccountOpen(false)}
                size="lg"
                title={accountEditing ? 'Ubah Akun VPN' : 'Tambah Akun VPN'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setAccountOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="vpn-account-form" disabled={accountForm.processing}>
                            {accountForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="vpn-account-form" onSubmit={submitAccount} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Server VPN" error={accountForm.errors.vpn_server_id}>
                        <Select value={accountForm.data.vpn_server_id} onChange={(event) => accountForm.setData('vpn_server_id', event.target.value)} disabled={accountEditing !== null} required>
                            <option value="">Pilih server</option>
                            {servers
                                .filter((server) => server.is_active || server.id === accountEditing?.vpn_server_id)
                                .map((server) => (
                                    <option key={server.id} value={server.id}>
                                        {server.name}
                                    </option>
                                ))}
                        </Select>
                    </Field>
                    <Field label="Protokol" error={accountForm.errors.protocol}>
                        <Select value={accountForm.data.protocol} onChange={(event) => accountForm.setData('protocol', event.target.value)} required>
                            {(!selectedServer || selectedServer.sstp_enabled) && <option value="sstp">SSTP</option>}
                            {(!selectedServer || selectedServer.l2tp_enabled) && <option value="l2tp">{selectedServer && !selectedServer.use_ipsec ? 'L2TP' : 'L2TP/IPsec'}</option>}
                        </Select>
                    </Field>
                    <Field label="Nama pelanggan" error={accountForm.errors.name}>
                        <Input value={accountForm.data.name} onChange={(event) => accountForm.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="No. WhatsApp" error={accountForm.errors.whatsapp}>
                        <Input inputMode="tel" value={accountForm.data.whatsapp} onChange={(event) => accountForm.setData('whatsapp', event.target.value)} placeholder="08xxxxxxxxxx" />
                    </Field>
                    <Field label="Username" error={accountForm.errors.username}>
                        <Input value={accountForm.data.username} onChange={(event) => accountForm.setData('username', event.target.value)} autoComplete="off" required />
                    </Field>
                    <Field label="Password" error={accountForm.errors.password} hint={accountEditing ? 'Kosongkan bila tidak diubah.' : 'Kosongkan untuk dibuat otomatis.'}>
                        <Input type="password" autoComplete="new-password" value={accountForm.data.password} onChange={(event) => accountForm.setData('password', event.target.value)} />
                    </Field>
                    <Field label="IP tunnel" error={accountForm.errors.remote_address} hint="Kosongkan untuk diambil otomatis dari pool server.">
                        <Input inputMode="decimal" value={accountForm.data.remote_address} onChange={(event) => accountForm.setData('remote_address', event.target.value)} />
                    </Field>
                    <Field label="Status" error={accountForm.errors.status}>
                        <Select value={accountForm.data.status} onChange={(event) => accountForm.setData('status', event.target.value)}>
                            <option value="active">Aktif</option>
                            <option value="disabled">Dinonaktifkan</option>
                            {accountEditing?.status === 'pending' && <option value="pending">Menunggu bayar</option>}
                        </Select>
                    </Field>
                    <Field label="Berlaku sampai" error={accountForm.errors.expires_at} hint="Tagihan perpanjangan terbit otomatis menjelang tanggal ini; setelah lewat, akun dinonaktifkan sampai dibayar. Kosongkan untuk akun tanpa tagihan.">
                        <Input type="date" value={accountForm.data.expires_at} onChange={(event) => accountForm.setData('expires_at', event.target.value)} />
                    </Field>
                    <Field label="Harga per bulan (Rp)" error={accountForm.errors.monthly_price}>
                        <Input type="number" min={0} value={accountForm.data.monthly_price} onChange={(event) => accountForm.setData('monthly_price', event.target.value)} required />
                    </Field>

                    <div className="space-y-2 sm:col-span-2">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium">Port forward (remote Winbox / API)</p>
                                <p className="text-xs text-muted-foreground">Port publik dikosongkan = dibuat otomatis mengikuti pola server dari IP tunnel akun.</p>
                            </div>
                            <Button variant="outline" size="sm" onClick={() => accountForm.setData('forwards', [...accountForm.data.forwards, { name: '', public_port: '', target_port: '' }])} disabled={accountForm.data.forwards.length >= 10}>
                                <Plus className="size-3.5" /> Port lain
                            </Button>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {portPresets.map((preset) => (
                                <button
                                    key={preset.target}
                                    type="button"
                                    onClick={() => addPreset(preset)}
                                    disabled={accountForm.data.forwards.length >= 10 || accountForm.data.forwards.some((forward) => forward.target_port === String(preset.target))}
                                    className="num rounded-full border px-2.5 py-1 text-xs font-medium transition hover:bg-muted disabled:opacity-40"
                                >
                                    + {preset.label} <span className="text-muted-foreground">{preset.target}</span>
                                </button>
                            ))}
                        </div>
                        {accountForm.data.forwards.map((forward, index) => (
                            <div key={index} className="grid grid-cols-[1fr_1fr_1fr_auto] items-start gap-2">
                                <Field label="Nama" error={accountErrors[`forwards.${index}.name`]}>
                                    <Input value={forward.name} onChange={(event) => updateForward(index, 'name', event.target.value)} required />
                                </Field>
                                <Field label="Port publik" error={accountErrors[`forwards.${index}.public_port`]}>
                                    <Input
                                        type="number"
                                        min={1024}
                                        max={65535}
                                        value={forward.public_port}
                                        placeholder={suggestedPort(selectedServer?.port_block_offset, accountForm.data.remote_address, forward.target_port) || 'otomatis'}
                                        onChange={(event) => updateForward(index, 'public_port', event.target.value)}
                                    />
                                </Field>
                                <Field label="Port tujuan" error={accountErrors[`forwards.${index}.target_port`]}>
                                    <Input type="number" min={1} max={65535} value={forward.target_port} onChange={(event) => updateForward(index, 'target_port', event.target.value)} required />
                                </Field>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="mt-6"
                                    aria-label="Hapus port forward"
                                    onClick={() => accountForm.setData('forwards', accountForm.data.forwards.filter((_, position) => position !== index))}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>

                    <Field label="Catatan" error={accountForm.errors.notes} className="sm:col-span-2">
                        <Textarea value={accountForm.data.notes} onChange={(event) => accountForm.setData('notes', event.target.value)} />
                    </Field>
                </form>
            </Modal>

            <Modal
                open={importing !== null}
                onClose={() => setImporting(null)}
                size="lg"
                title={`Impor akun · ${importing?.server.name ?? ''}`}
                description="Mengambil PPP secret dan port forward yang sudah ada di router. Router tidak diubah; IP, profile, dan port lama dipertahankan."
                footer={
                    <>
                        <Button variant="outline" onClick={() => setImporting(null)}>
                            Batal
                        </Button>
                        <Button onClick={submitImport} disabled={!importing || importing.busy || importing.selected.length === 0}>
                            {importing?.busy ? 'Mengimpor...' : `Impor ${importing?.selected.length ?? 0} akun`}
                        </Button>
                    </>
                }
            >
                {importing?.error && <p className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{importing.error}</p>}
                {!importing?.error && importing?.rows === null && <p className="py-6 text-center text-sm text-muted-foreground">Membaca router...</p>}
                {importing?.rows && (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Harga per bulan (Rp)" hint="Berlaku untuk semua akun yang diimpor; ubah per akun nanti.">
                                <Input type="number" min={0} value={importing.price} onChange={(event) => setImporting({ ...importing, price: event.target.value })} />
                            </Field>
                            <Field label="Berlaku sampai" hint="Kosongkan agar belum ditagih otomatis.">
                                <Input type="date" value={importing.expires} onChange={(event) => setImporting({ ...importing, expires: event.target.value })} />
                            </Field>
                        </div>
                        <div className="max-h-80 overflow-auto rounded-lg border">
                            <Table>
                                <thead>
                                    <tr>
                                        <Th />
                                        <Th>Akun</Th>
                                        <Th>IP tunnel</Th>
                                        <Th>Port forward</Th>
                                        <Th>Keterangan</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {importing.rows.map((row) => (
                                        <tr key={row.username} className={row.status === 'importable' ? 'hover:bg-muted/40' : 'opacity-60'}>
                                            <Td>
                                                <input
                                                    type="checkbox"
                                                    className="size-4 accent-[var(--primary)]"
                                                    disabled={row.status !== 'importable'}
                                                    checked={importing.selected.includes(row.username)}
                                                    onChange={(event) => setImporting({ ...importing, selected: event.target.checked ? [...importing.selected, row.username] : importing.selected.filter((username) => username !== row.username) })}
                                                    aria-label={`Pilih ${row.username}`}
                                                />
                                            </Td>
                                            <Td>
                                                <span className="num font-medium">{row.username}</span>
                                                <span className="ml-2 text-xs text-muted-foreground">{row.name !== row.username ? row.name : row.profile}</span>
                                            </Td>
                                            <Td className="num text-muted-foreground">{row.remote_address ?? '-'}</Td>
                                            <Td className="num text-xs text-muted-foreground">
                                                {row.forwards.length > 0 ? row.forwards.map((forward) => <div key={forward.public_port}>{forward.public_port} → {forward.target_port}</div>) : '-'}
                                                {row.skipped_rules > 0 && <div className="text-amber-600">{row.skipped_rules} rule rentang dilewati</div>}
                                            </Td>
                                            <Td className="text-xs">{row.status === 'importable' ? (row.disabled ? <Badge>Nonaktif di router</Badge> : <Badge tone="green">Siap diimpor</Badge>) : <span className="text-muted-foreground">{row.reason}</span>}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                open={scripts !== null}
                onClose={() => setScripts(null)}
                size="lg"
                title={scripts?.title ?? ''}
                description={activeTab?.hint}
                footer={
                    <Button variant="outline" onClick={() => activeTab?.text && navigator.clipboard?.writeText(activeTab.text)} disabled={!activeTab?.text}>
                        <Copy className="size-4" /> Salin
                    </Button>
                }
            >
                {scripts && scripts.tabs.length > 1 && (
                    <div className="mb-3 inline-flex rounded-lg border p-0.5">
                        {scripts.tabs.map((tab, index) => (
                            <button
                                key={tab.label}
                                type="button"
                                onClick={() => setScripts({ ...scripts, active: index })}
                                className={`rounded-md px-3 py-1 text-sm transition-colors ${index === scripts.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                )}
                <pre className="max-h-72 overflow-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">{activeTab?.text ?? 'Memuat...'}</pre>
                <p className="mt-3 text-xs text-muted-foreground">Script berisi password akun. Kirim hanya kepada pelanggan yang bersangkutan.</p>
            </Modal>
        </>
    );
}
