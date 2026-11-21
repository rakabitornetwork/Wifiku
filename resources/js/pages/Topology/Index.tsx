import { Link, useForm } from '@inertiajs/react';
import { Megaphone, MapPin, Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { type MapCustomer, type MapOdp, MapView } from '@/Components/MapView';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { useCan } from '@/Hooks/useCan';

type OdpRow = MapOdp & { router_id: number; parent_odp_id: number | null; description: string | null; router: { name: string } };

type Props = { odps: OdpRow[]; customers: MapCustomer[]; routers: { id: number; name: string }[] };

const emptyForm = { router_id: '', parent_odp_id: '', name: '', code: '', latitude: '', longitude: '', total_ports: '8', description: '' };

export default function TopologyIndex({ odps, customers, routers }: Props) {
    const can = useCan();
    const canManage = can('topology.manage');
    const [editing, setEditing] = useState<OdpRow | null>(null);
    const [open, setOpen] = useState(false);
    const [picking, setPicking] = useState(false);
    const [showCustomers, setShowCustomers] = useState(true);
    const [showLines, setShowLines] = useState(true);
    const form = useForm(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (odp: OdpRow) => {
        setEditing(odp);
        form.setData({
            router_id: String(odp.router_id),
            parent_odp_id: odp.parent_odp_id ? String(odp.parent_odp_id) : '',
            name: odp.name,
            code: odp.code,
            latitude: odp.latitude ?? '',
            longitude: odp.longitude ?? '',
            total_ports: String(odp.total_ports),
            description: odp.description ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const onPick = (latitude: number, longitude: number) => {
        form.setData((current) => ({ ...current, latitude: String(latitude), longitude: String(longitude) }));
        setPicking(false);
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/odps/${editing.id}`, options);
        } else {
            form.post('/odps', options);
        }
    };

    const withoutCoordinates = odps.filter((odp) => odp.latitude === null).length;

    return (
        <>
            <PageHeader
                title="Peta Topologi & ODP"
                description="Titik ODP, lokasi pelanggan, dan tarikan kabel dari ODP ke rumah pelanggan."
                actions={
                    canManage && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Tambah ODP
                        </Button>
                    )
                }
            />

            <Card className="mb-4 overflow-hidden">
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b px-4 py-3">
                    <Checkbox label="Pelanggan" checked={showCustomers} onChange={setShowCustomers} />
                    <Checkbox label="Garis kabel" checked={showLines} onChange={setShowLines} />
                    <div className="ml-auto flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <Legend color="#10b981" label="ODP normal" square />
                        <Legend color="#f59e0b" label="ODP penuh" square />
                        <Legend color="#ef4444" label="Gangguan" square />
                        <Legend color="#6366f1" label="Pelanggan aktif" />
                        <Legend color="#ef4444" label="Terisolir" />
                    </div>
                </div>
                {picking && (
                    <div className="flex items-center justify-between gap-3 border-b bg-accent px-4 py-2 text-sm text-accent-foreground">
                        <span className="flex items-center gap-2">
                            <MapPin className="size-4" /> Klik lokasi ODP pada peta.
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setPicking(false);
                                setOpen(true);
                            }}
                        >
                            Batal
                        </Button>
                    </div>
                )}
                <MapView odps={odps} customers={customers} showCustomers={showCustomers} showLines={showLines} pickMode={picking} onPick={onPick} />
            </Card>

            <Card>
                <CardHeader
                    title="Daftar ODP"
                    description={withoutCoordinates > 0 ? `${withoutCoordinates} ODP belum memiliki koordinat dan tidak tampil di peta.` : undefined}
                />
                {odps.length === 0 ? (
                    <EmptyState title="Belum ada ODP" description="Tambahkan ODP agar pelanggan dapat dihubungkan ke titik tiangnya." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Kode</Th>
                                    <Th>Nama</Th>
                                    <Th>Router / POP</Th>
                                    <Th>Port terpakai</Th>
                                    <Th>Koordinat</Th>
                                    <Th>Status</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {odps.map((odp) => (
                                    <tr key={odp.id} className="hover:bg-muted/40">
                                        <Td className="num font-medium">{odp.code}</Td>
                                        <Td>{odp.name}</Td>
                                        <Td>{odp.router.name}</Td>
                                        <Td className="num">
                                            {odp.customers_count}/{odp.total_ports}
                                        </Td>
                                        <Td className="num text-muted-foreground">
                                            {odp.latitude !== null ? `${Number(odp.latitude).toFixed(5)}, ${Number(odp.longitude).toFixed(5)}` : '-'}
                                        </Td>
                                        <Td>{odp.open_outages_count > 0 ? <Badge tone="red">Gangguan</Badge> : <Badge tone="green">Normal</Badge>}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                {can('outages.manage') && (
                                                    <Link href={`/outages?odp=${odp.id}`} className={buttonClass('warning', 'icon')} aria-label="Umumkan gangguan" title="Umumkan gangguan ke pelanggan ODP ini">
                                                        <Megaphone className="size-4" />
                                                    </Link>
                                                )}
                                                {canManage && (
                                                    <>
                                                        <Button variant="edit" size="icon" onClick={() => openEdit(odp)} aria-label="Ubah">
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                        <ConfirmDelete
                                                            url={`/odps/${odp.id}`}
                                                            title={`Hapus ${odp.code}?`}
                                                            description="ODP yang masih memiliki pelanggan, ODP turunan, atau riwayat gangguan tidak dapat dihapus."
                                                        />
                                                    </>
                                                )}
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="lg"
                title={editing ? 'Ubah ODP' : 'Tambah ODP'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="odp-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="odp-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Kode ODP" error={form.errors.code}>
                        <Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} placeholder="ODP-001" required />
                    </Field>
                    <Field label="Nama / lokasi tiang" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Router / POP induk" error={form.errors.router_id}>
                        <Select value={form.data.router_id} onChange={(event) => form.setData('router_id', event.target.value)} required>
                            <option value="">Pilih router</option>
                            {routers.map((router) => (
                                <option key={router.id} value={router.id}>
                                    {router.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="ODP induk (opsional)" error={form.errors.parent_odp_id}>
                        <Select value={form.data.parent_odp_id} onChange={(event) => form.setData('parent_odp_id', event.target.value)}>
                            <option value="">Tanpa induk</option>
                            {odps
                                .filter((odp) => odp.id !== editing?.id)
                                .map((odp) => (
                                    <option key={odp.id} value={odp.id}>
                                        {odp.code} — {odp.name}
                                    </option>
                                ))}
                        </Select>
                    </Field>
                    <Field label="Latitude" error={form.errors.latitude}>
                        <Input inputMode="decimal" value={form.data.latitude} onChange={(event) => form.setData('latitude', event.target.value)} />
                    </Field>
                    <Field label="Longitude" error={form.errors.longitude}>
                        <Input inputMode="decimal" value={form.data.longitude} onChange={(event) => form.setData('longitude', event.target.value)} />
                    </Field>
                    <div className="sm:col-span-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setOpen(false);
                                setPicking(true);
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }}
                        >
                            <MapPin className="size-3.5" /> Pilih lokasi di peta
                        </Button>
                    </div>
                    <Field label="Kapasitas port" error={form.errors.total_ports}>
                        <Input type="number" min={1} max={144} value={form.data.total_ports} onChange={(event) => form.setData('total_ports', event.target.value)} required />
                    </Field>
                    <Field label="Keterangan" error={form.errors.description} className="sm:col-span-2">
                        <Textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </Field>
                </form>
            </Modal>
        </>
    );
}

function Legend({ color, label, square = false }: { color: string; label: string; square?: boolean }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={square ? 'size-2.5 rounded-[3px]' : 'size-2.5 rounded-full'} style={{ background: color }} />
            {label}
        </span>
    );
}
