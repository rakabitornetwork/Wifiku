import { router as inertia, useForm } from '@inertiajs/react';
import { Activity, Copy, Download, FileCode, Pencil, PlugZap, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { ImportPppModal } from '@/Components/ImportPppModal';
import { PageHeader } from '@/Components/PageHeader';
import { RouterTrafficModal } from '@/Components/RouterTrafficModal';
import { SearchInput } from '@/Components/SearchInput';
import { StatusBadge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type RouterRow = {
    id: number;
    name: string;
    host: string;
    api_port: number;
    use_ssl: boolean;
    username: string;
    isolir_profile: string;
    status: string;
    last_checked_at: string | null;
    last_error: string | null;
    notes: string | null;
    customers_count: number;
    packages_count: number;
    hotspot_profiles_count: number;
};

const emptyForm = { name: '', host: '', api_port: '8728', use_ssl: false, username: '', password: '', isolir_profile: 'ISOLIR', notes: '' };

export default function RoutersIndex({ routers, search }: { routers: Paginated<RouterRow>; search: string }) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<RouterRow | null>(null);
    const [open, setOpen] = useState(false);
    const [importing, setImporting] = useState<RouterRow | null>(null);
    const [traffic, setTraffic] = useState<RouterRow | null>(null);
    const [checking, setChecking] = useState<number | null>(null);
    const [script, setScript] = useState<{ router: RouterRow; text: string | null } | null>(null);
    const form = useForm(emptyForm);

    const check = (router: RouterRow) =>
        inertia.post(`/routers/${router.id}/check`, {}, { preserveScroll: true, onStart: () => setChecking(router.id), onFinish: () => setChecking(null) });

    const openScript = async (router: RouterRow) => {
        setScript({ router, text: null });

        try {
            const response = await fetch(`/routers/${router.id}/hotspot-script`, { headers: { Accept: 'application/json' } });
            const data = (await response.json()) as { script: string };
            setScript({ router, text: data.script });
        } catch {
            setScript({ router, text: 'Gagal memuat script. Muat ulang halaman lalu coba lagi.' });
        }
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (router: RouterRow) => {
        setEditing(router);
        form.setData({
            name: router.name,
            host: router.host,
            api_port: String(router.api_port),
            use_ssl: router.use_ssl,
            username: router.username,
            password: '',
            isolir_profile: router.isolir_profile,
            notes: router.notes ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/routers/${editing.id}`, options);
        } else {
            form.post('/routers', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Manajemen Router"
                description="Daftar router MikroTik (Multi-NAS) per POP atau desa."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Router
                    </Button>
                }
            />

            <Card>
                <div className="border-b p-4">
                    <SearchInput value={search} placeholder="Cari nama atau host..." />
                </div>
                {routers.data.length === 0 ? (
                    <EmptyState title="Belum ada router" description="Tambahkan router agar pelanggan, paket, dan voucher dapat dihubungkan ke POP-nya." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="name" state={sort}>Router / POP</SortTh>
                                    <SortTh column="host" state={sort}>Alamat API</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <SortTh column="customers" state={sort}>Pelanggan</SortTh>
                                    <SortTh column="packages" state={sort}>Paket</SortTh>
                                    <SortTh column="hotspot_profiles" state={sort}>Profil hotspot</SortTh>
                                    <SortTh column="last_checked" state={sort}>Terakhir dicek</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {routers.data.map((router) => (
                                    <tr key={router.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{router.name}</Td>
                                        <Td className="num text-muted-foreground">
                                            {router.host}:{router.api_port}
                                            {router.use_ssl && <span className="ml-1.5 text-[10px] uppercase">ssl</span>}
                                        </Td>
                                        <Td className="text-center">
                                            <span title={router.last_error ?? undefined}>
                                                <StatusBadge status={router.status} />
                                            </span>
                                        </Td>
                                        <Td className="num">{router.customers_count}</Td>
                                        <Td className="num">{router.packages_count}</Td>
                                        <Td className="num">{router.hotspot_profiles_count}</Td>
                                        <Td className="text-muted-foreground">{formatDate(router.last_checked_at)}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="success" size="icon" onClick={() => check(router)} disabled={checking === router.id} aria-label="Cek koneksi" title="Cek koneksi API">
                                                    <PlugZap className={checking === router.id ? 'size-4 animate-pulse' : 'size-4'} />
                                                </Button>
                                                <Button variant="secondary" size="icon" onClick={() => setTraffic(router)} aria-label="Live trafik" title="Live trafik interface">
                                                    <Activity className="size-4" />
                                                </Button>
                                                <Button variant="info" size="icon" onClick={() => setImporting(router)} aria-label="Impor pelanggan" title="Impor pelanggan dari PPP secret router">
                                                    <Download className="size-4" />
                                                </Button>
                                                <Button variant="warning" size="icon" onClick={() => openScript(router)} aria-label="Script hotspot" title="Script on-login hotspot">
                                                    <FileCode className="size-4" />
                                                </Button>
                                                <Button variant="edit" size="icon" onClick={() => openEdit(router)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete
                                                    url={`/routers/${router.id}`}
                                                    title={`Hapus ${router.name}?`}
                                                    description="Router yang masih dipakai pelanggan, paket, ODP, atau profil hotspot tidak dapat dihapus."
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={routers} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={editing ? 'Ubah Router' : 'Tambah Router'}
                description="Password disimpan terenkripsi dan tidak pernah ditampilkan kembali."
                footer={
                    <>
                        <Button variant="close" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="router-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="router-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama router / POP / desa" error={form.errors.name} className="sm:col-span-2">
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="IP address / IP tunnel VPN" error={form.errors.host}>
                        <Input value={form.data.host} onChange={(event) => form.setData('host', event.target.value)} required />
                    </Field>
                    <Field label="Port API" error={form.errors.api_port}>
                        <Input type="number" value={form.data.api_port} onChange={(event) => form.setData('api_port', event.target.value)} required />
                    </Field>
                    <Field label="Username" error={form.errors.username}>
                        <Input value={form.data.username} onChange={(event) => form.setData('username', event.target.value)} autoComplete="off" required />
                    </Field>
                    <Field label="Password" error={form.errors.password} hint={editing ? 'Kosongkan jika tidak ingin mengubah.' : undefined}>
                        <Input
                            type="password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            autoComplete="new-password"
                            required={!editing}
                        />
                    </Field>
                    <Field label="Profil isolir (PPP profile)" error={form.errors.isolir_profile} hint="Samakan dengan nama profile di router (PPP > Profiles).">
                        <Input value={form.data.isolir_profile} onChange={(event) => form.setData('isolir_profile', event.target.value)} required />
                    </Field>
                    <div className="flex items-end pb-2">
                        <Checkbox label="Gunakan API-SSL" checked={form.data.use_ssl} onChange={(checked) => form.setData('use_ssl', checked)} />
                    </div>
                    <Field label="Catatan" error={form.errors.notes} className="sm:col-span-2">
                        <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </Field>
                </form>
            </Modal>

            <ImportPppModal router={importing} onClose={() => setImporting(null)} />
            <RouterTrafficModal router={traffic} onClose={() => setTraffic(null)} />

            <Modal
                open={script !== null}
                onClose={() => setScript(null)}
                size="lg"
                title={`Script on-login · ${script?.router.name ?? ''}`}
                description="Tempel di IP > Hotspot > User Profiles > (profil voucher) > Scripts > On Login. Login pertama voucher akan mengaktifkan masa berlakunya."
                footer={
                    <Button variant="outline" onClick={() => script?.text && navigator.clipboard?.writeText(script.text)} disabled={!script?.text}>
                        <Copy className="size-4" /> Salin
                    </Button>
                }
            >
                <pre className="max-h-64 overflow-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">
                    {script?.text ?? 'Memuat...'}
                </pre>
                <p className="mt-3 text-xs text-muted-foreground">Script berisi token rahasia router ini. Jangan dibagikan.</p>
            </Modal>
        </>
    );
}
