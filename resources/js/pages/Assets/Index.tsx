import { router, useForm } from '@inertiajs/react';
import { Activity, Pencil, Plus, Wifi } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { type CustomerOption, CustomerPicker } from '@/Components/CustomerPicker';
import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Badge, StatusBadge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input, Select, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

type AssetRow = {
    id: number;
    type: string;
    brand: string | null;
    model: string | null;
    serial_number: string;
    mac_address: string | null;
    ownership: string;
    status: string;
    customer_id: number | null;
    installed_at: string | null;
    notes: string | null;
    last_rx_power: string | null;
    last_seen_at: string | null;
    customer: CustomerOption | null;
};

type Filters = { search: string; status: string; type: string };

const typeLabels: Record<string, string> = { ont: 'ONT', router: 'Router', stb: 'STB', other: 'Lainnya' };

const emptyForm = { type: 'ont', brand: '', model: '', serial_number: '', mac_address: '', ownership: 'company', status: 'in_stock', installed_at: '', notes: '' };

export default function AssetsIndex({ assets, filters }: { assets: Paginated<AssetRow>; filters: Filters }) {
    const [editing, setEditing] = useState<AssetRow | null>(null);
    const [open, setOpen] = useState(false);
    const [customer, setCustomer] = useState<CustomerOption | null>(null);
    const [wifiAsset, setWifiAsset] = useState<AssetRow | null>(null);
    const [reading, setReading] = useState<number | null>(null);
    const form = useForm({ ...emptyForm, customer_id: '' });
    const wifiForm = useForm({ ssid: '', password: '' });

    const isManagedOnt = (asset: AssetRow) => asset.type === 'ont' && asset.status === 'installed';

    const readRx = (asset: AssetRow) =>
        router.post(`/assets/${asset.id}/rx-power`, {}, { preserveScroll: true, onStart: () => setReading(asset.id), onFinish: () => setReading(null) });

    const submitWifi = (event: FormEvent) => {
        event.preventDefault();

        if (wifiAsset) {
            wifiForm.post(`/assets/${wifiAsset.id}/wifi`, { preserveScroll: true, onSuccess: () => setWifiAsset(null) });
        }
    };

    const rxTone = (value: number) => (value >= -25 ? 'text-emerald-600 dark:text-emerald-400' : value >= -27 ? 'text-amber-600 dark:text-amber-400' : 'text-destructive');

    const applyFilter = (key: 'status' | 'type', value: string) =>
        router.get('/assets', { ...filters, [key]: value || undefined, search: filters.search || undefined }, { preserveState: true, replace: true });

    const openCreate = () => {
        setEditing(null);
        setCustomer(null);
        form.setData({ ...emptyForm, customer_id: '' });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (asset: AssetRow) => {
        setEditing(asset);
        setCustomer(asset.customer);
        form.setData({
            type: asset.type,
            brand: asset.brand ?? '',
            model: asset.model ?? '',
            serial_number: asset.serial_number,
            mac_address: asset.mac_address ?? '',
            ownership: asset.ownership,
            status: asset.status,
            customer_id: asset.customer_id ? String(asset.customer_id) : '',
            installed_at: asset.installed_at ?? '',
            notes: asset.notes ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const pickCustomer = (selected: CustomerOption | null) => {
        setCustomer(selected);
        form.setData('customer_id', selected ? String(selected.id) : '');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/assets/${editing.id}`, options);
        } else {
            form.post('/assets', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Inventaris ONT & Aset"
                description="Perangkat ONT dan aset lain, termasuk kepemilikan dan pelanggan pemakainya."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Perangkat
                    </Button>
                }
            />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={filters.search} placeholder="Cari serial, MAC, merek, pelanggan..." extra={{ status: filters.status || undefined, type: filters.type || undefined }} />
                    <Select className="sm:w-40" value={filters.type} onChange={(event) => applyFilter('type', event.target.value)} aria-label="Filter jenis">
                        <option value="">Semua jenis</option>
                        {Object.entries(typeLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </Select>
                    <Select className="sm:w-44" value={filters.status} onChange={(event) => applyFilter('status', event.target.value)} aria-label="Filter status">
                        <option value="">Semua status</option>
                        <option value="in_stock">Di gudang</option>
                        <option value="installed">Terpasang</option>
                        <option value="broken">Rusak</option>
                        <option value="returned">Dikembalikan</option>
                    </Select>
                </div>
                {assets.data.length === 0 ? (
                    <EmptyState title="Belum ada perangkat" description="Catat serial number atau MAC ONT agar perangkat di rumah pelanggan terlacak." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Serial number</Th>
                                    <Th>Jenis / model</Th>
                                    <Th>MAC</Th>
                                    <Th>Kepemilikan</Th>
                                    <Th>Pelanggan</Th>
                                    <Th>RX Power</Th>
                                    <Th>Status</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {assets.data.map((asset) => (
                                    <tr key={asset.id} className="hover:bg-muted/40">
                                        <Td className="num font-medium">{asset.serial_number}</Td>
                                        <Td>
                                            {typeLabels[asset.type]}
                                            <span className="text-muted-foreground"> · {[asset.brand, asset.model].filter(Boolean).join(' ') || '-'}</span>
                                        </Td>
                                        <Td className="num text-muted-foreground">{asset.mac_address ?? '-'}</Td>
                                        <Td>{asset.ownership === 'company' ? <Badge tone="blue">Milik perusahaan</Badge> : <Badge>Milik pelanggan</Badge>}</Td>
                                        <Td>
                                            {asset.customer ? (
                                                <>
                                                    <span className="num text-xs text-muted-foreground">{asset.customer.customer_code}</span> {asset.customer.name}
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </Td>
                                        <Td className="num whitespace-nowrap">
                                            {asset.last_rx_power !== null ? (
                                                <span className={rxTone(Number(asset.last_rx_power))}>{Number(asset.last_rx_power).toFixed(2)} dBm</span>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                            {asset.last_seen_at && <div className="text-[11px] text-muted-foreground">{formatDate(asset.last_seen_at)}</div>}
                                        </Td>
                                        <Td>
                                            <StatusBadge status={asset.status} />
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                {isManagedOnt(asset) && (
                                                    <>
                                                        <Button variant="success" size="icon" onClick={() => readRx(asset)} disabled={reading === asset.id} aria-label="Baca RX Power" title="Baca RX Power (GenieACS)">
                                                            <Activity className={reading === asset.id ? 'size-4 animate-pulse' : 'size-4'} />
                                                        </Button>
                                                        <Button
                                                            variant="info"
                                                            size="icon"
                                                            onClick={() => {
                                                                setWifiAsset(asset);
                                                                wifiForm.reset();
                                                                wifiForm.clearErrors();
                                                            }}
                                                            aria-label="Ubah Wi-Fi"
                                                            title="Ubah SSID / password Wi-Fi"
                                                        >
                                                            <Wifi className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                                <Button variant="edit" size="icon" onClick={() => openEdit(asset)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/assets/${asset.id}`} title={`Hapus ${asset.serial_number}?`} description="Data perangkat akan dihapus dari inventaris." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={assets} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="lg"
                title={editing ? 'Ubah Perangkat' : 'Tambah Perangkat'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="asset-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="asset-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Serial number" error={form.errors.serial_number}>
                        <Input value={form.data.serial_number} onChange={(event) => form.setData('serial_number', event.target.value)} required />
                    </Field>
                    <Field label="MAC address" error={form.errors.mac_address}>
                        <Input value={form.data.mac_address} onChange={(event) => form.setData('mac_address', event.target.value)} placeholder="AA:BB:CC:DD:EE:FF" />
                    </Field>
                    <Field label="Jenis" error={form.errors.type}>
                        <Select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                            {Object.entries(typeLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Kepemilikan" error={form.errors.ownership}>
                        <Select value={form.data.ownership} onChange={(event) => form.setData('ownership', event.target.value)}>
                            <option value="company">Milik perusahaan (dipinjamkan)</option>
                            <option value="customer">Milik pelanggan</option>
                        </Select>
                    </Field>
                    <Field label="Merek" error={form.errors.brand}>
                        <Input value={form.data.brand} onChange={(event) => form.setData('brand', event.target.value)} />
                    </Field>
                    <Field label="Model" error={form.errors.model}>
                        <Input value={form.data.model} onChange={(event) => form.setData('model', event.target.value)} />
                    </Field>
                    <Field label="Status" error={form.errors.status}>
                        <Select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                            <option value="in_stock">Di gudang</option>
                            <option value="installed">Terpasang</option>
                            <option value="broken">Rusak</option>
                            <option value="returned">Dikembalikan</option>
                        </Select>
                    </Field>
                    <Field label="Tanggal pasang" error={form.errors.installed_at}>
                        <Input type="date" value={form.data.installed_at} onChange={(event) => form.setData('installed_at', event.target.value)} />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Pelanggan pemakai" error={form.errors.customer_id} hint="Wajib untuk status Terpasang; kosongkan untuk perangkat di gudang.">
                            <CustomerPicker value={customer} onChange={pickCustomer} />
                        </Field>
                    </div>
                    <Field label="Catatan" error={form.errors.notes} className="sm:col-span-2">
                        <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </Field>
                </form>
            </Modal>

            <Modal
                open={wifiAsset !== null}
                onClose={() => setWifiAsset(null)}
                title="Ubah Wi-Fi Pelanggan"
                description={wifiAsset ? `${wifiAsset.serial_number}${wifiAsset.customer ? ` · ${wifiAsset.customer.name}` : ''} — dikirim via GenieACS (TR-069).` : undefined}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setWifiAsset(null)}>
                            Batal
                        </Button>
                        <Button type="submit" form="wifi-form" disabled={wifiForm.processing}>
                            {wifiForm.processing ? 'Mengirim...' : 'Terapkan'}
                        </Button>
                    </>
                }
            >
                <form id="wifi-form" onSubmit={submitWifi} className="grid gap-4">
                    <Field label="SSID baru" error={wifiForm.errors.ssid}>
                        <Input maxLength={32} value={wifiForm.data.ssid} onChange={(event) => wifiForm.setData('ssid', event.target.value)} required />
                    </Field>
                    <Field label="Password Wi-Fi baru" error={wifiForm.errors.password} hint="8–63 karakter (WPA2).">
                        <Input type="text" minLength={8} maxLength={63} value={wifiForm.data.password} onChange={(event) => wifiForm.setData('password', event.target.value)} required />
                    </Field>
                </form>
            </Modal>
        </>
    );
}
