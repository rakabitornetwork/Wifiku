import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { HotspotTabs } from '@/Components/HotspotTabs';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import type { Paginated } from '@/types';

type ResellerRow = { id: number; name: string; phone: string | null; discount_percent: number; is_active: boolean; voucher_batches_count: number };

const emptyForm = { name: '', phone: '', discount_percent: '0', is_active: true };

export default function Resellers({ resellers }: { resellers: Paginated<ResellerRow> }) {
    const [editing, setEditing] = useState<ResellerRow | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (reseller: ResellerRow) => {
        setEditing(reseller);
        form.setData({ name: reseller.name, phone: reseller.phone ?? '', discount_percent: String(reseller.discount_percent), is_active: reseller.is_active });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/hotspot/resellers/${editing.id}`, options);
        } else {
            form.post('/hotspot/resellers', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Voucher Hotspot"
                description="Reseller yang menerima batch voucher dengan harga khusus."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Reseller
                    </Button>
                }
            />
            <HotspotTabs />

            <Card>
                {resellers.data.length === 0 ? (
                    <EmptyState title="Belum ada reseller" description="Reseller bersifat opsional; batch tanpa reseller memakai harga jual normal." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Nama</Th>
                                    <Th>Telepon</Th>
                                    <Th>Diskon</Th>
                                    <Th>Batch</Th>
                                    <Th>Status</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {resellers.data.map((reseller) => (
                                    <tr key={reseller.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{reseller.name}</Td>
                                        <Td className="num text-muted-foreground">{reseller.phone ?? '-'}</Td>
                                        <Td className="num">{reseller.discount_percent}%</Td>
                                        <Td className="num">{reseller.voucher_batches_count}</Td>
                                        <Td>{reseller.is_active ? <Badge tone="green">Aktif</Badge> : <Badge>Nonaktif</Badge>}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="edit" size="icon" onClick={() => openEdit(reseller)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/hotspot/resellers/${reseller.id}`} title={`Hapus ${reseller.name}?`} description="Riwayat batch voucher milik reseller ini tetap tersimpan." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={resellers} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={editing ? 'Ubah Reseller' : 'Tambah Reseller'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="reseller-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="reseller-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama reseller" error={form.errors.name} className="sm:col-span-2">
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="No. telepon" error={form.errors.phone}>
                        <Input inputMode="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </Field>
                    <Field label="Diskon (%)" error={form.errors.discount_percent} hint="Dipakai bila profil tidak punya harga reseller.">
                        <Input type="number" min={0} max={100} value={form.data.discount_percent} onChange={(event) => form.setData('discount_percent', event.target.value)} required />
                    </Field>
                    <div className="sm:col-span-2">
                        <Checkbox label="Reseller aktif" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
