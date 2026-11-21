import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';

type PackageRow = {
    id: number;
    router_id: number | null;
    name: string;
    mikrotik_profile: string;
    rate_limit: string | null;
    price: number;
    is_active: boolean;
    router: { id: number; name: string } | null;
    customers_count: number;
};

const emptyForm = { router_id: '', name: '', mikrotik_profile: '', rate_limit: '', price: '', is_active: true };

export default function PackagesIndex({ packages, routers, search }: { packages: Paginated<PackageRow>; routers: { id: number; name: string }[]; search: string }) {
    const [editing, setEditing] = useState<PackageRow | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (item: PackageRow) => {
        setEditing(item);
        form.setData({
            router_id: item.router_id ? String(item.router_id) : '',
            name: item.name,
            mikrotik_profile: item.mikrotik_profile,
            rate_limit: item.rate_limit ?? '',
            price: String(item.price),
            is_active: item.is_active,
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/packages/${editing.id}`, options);
        } else {
            form.post('/packages', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Paket Bandwidth"
                description="Paket internet PPPoE yang dijual kepada pelanggan."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Paket
                    </Button>
                }
            />

            <Card>
                <div className="border-b p-4">
                    <SearchInput value={search} placeholder="Cari nama paket..." />
                </div>
                {packages.data.length === 0 ? (
                    <EmptyState title="Belum ada paket" description="Buat paket dengan nama profile PPP yang sama persis dengan di MikroTik." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Paket</Th>
                                    <Th>Profile MikroTik</Th>
                                    <Th>Rate limit</Th>
                                    <Th>Router</Th>
                                    <Th className="text-right">Harga / bulan</Th>
                                    <Th>Pelanggan</Th>
                                    <Th>Status</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {packages.data.map((item) => (
                                    <tr key={item.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{item.name}</Td>
                                        <Td className="num text-muted-foreground">{item.mikrotik_profile}</Td>
                                        <Td className="num text-muted-foreground">{item.rate_limit ?? '-'}</Td>
                                        <Td>{item.router?.name ?? <span className="text-muted-foreground">Semua router</span>}</Td>
                                        <Td className="num text-right font-medium">{formatRupiah(item.price)}</Td>
                                        <Td className="num">{item.customers_count}</Td>
                                        <Td>{item.is_active ? <Badge tone="green">Dijual</Badge> : <Badge>Nonaktif</Badge>}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="edit" size="icon" onClick={() => openEdit(item)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete
                                                    url={`/packages/${item.id}`}
                                                    title={`Hapus paket ${item.name}?`}
                                                    description="Paket yang masih dipakai pelanggan tidak dapat dihapus; nonaktifkan saja."
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={packages} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={editing ? 'Ubah Paket' : 'Tambah Paket'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="package-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="package-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama paket" error={form.errors.name} className="sm:col-span-2">
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Home 20 Mbps" required />
                    </Field>
                    <Field label="Router" error={form.errors.router_id} hint="Kosong = berlaku di semua router.">
                        <Select value={form.data.router_id} onChange={(event) => form.setData('router_id', event.target.value)}>
                            <option value="">Semua router</option>
                            {routers.map((router) => (
                                <option key={router.id} value={router.id}>
                                    {router.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Profile PPP di MikroTik" error={form.errors.mikrotik_profile}>
                        <Input value={form.data.mikrotik_profile} onChange={(event) => form.setData('mikrotik_profile', event.target.value)} required />
                    </Field>
                    <Field label="Rate limit" error={form.errors.rate_limit} hint="Contoh: 20M/20M">
                        <Input value={form.data.rate_limit} onChange={(event) => form.setData('rate_limit', event.target.value)} />
                    </Field>
                    <Field label="Harga per bulan (Rp)" error={form.errors.price}>
                        <Input type="number" min={0} value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} required />
                    </Field>
                    <div className="sm:col-span-2">
                        <Checkbox label="Paket aktif (dapat dipilih untuk pelanggan baru)" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
