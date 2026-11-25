import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Combobox } from '@/Components/ui/Combobox';
import { Checkbox, Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type PackageRow = {
    id: number;
    router_id: number | null;
    name: string;
    mikrotik_profile: string;
    rate_limit: string | null;
    price: number;
    is_active: boolean;
    show_on_landing: boolean;
    landing_highlight: boolean;
    landing_description: string | null;
    router: { id: number; name: string } | null;
    customers_count: number;
};

const emptyForm = { router_id: '', name: '', mikrotik_profile: '', rate_limit: '', price: '', is_active: true, show_on_landing: false, landing_highlight: false, landing_description: '' };

export default function PackagesIndex({ packages, routers, search, routerId }: { packages: Paginated<PackageRow>; routers: { id: number; name: string }[]; search: string; routerId: string }) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<PackageRow | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);
    const [profiles, setProfiles] = useState<string[]>([]);
    const [profilesLoading, setProfilesLoading] = useState(false);
    const [profilesError, setProfilesError] = useState<string | null>(null);

    // Daftar PPP profile dibaca langsung dari router yang dipilih; "Semua router" tidak punya daftar.
    useEffect(() => {
        setProfiles([]);
        setProfilesError(null);

        if (!open || !form.data.router_id) {
            return;
        }

        const controller = new AbortController();
        setProfilesLoading(true);
        fetch(`/packages/routers/${form.data.router_id}/ppp-profiles`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? 'Gagal membaca profile PPP.');
                }

                setProfiles(data.profiles);
                setProfilesLoading(false);
            })
            .catch((exception: Error) => {
                if (exception.name !== 'AbortError') {
                    setProfilesError(exception.message);
                    setProfilesLoading(false);
                }
            });

        return () => controller.abort();
    }, [open, form.data.router_id]);

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
            show_on_landing: item.show_on_landing,
            landing_highlight: item.landing_highlight,
            landing_description: item.landing_description ?? '',
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
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={search} placeholder="Cari nama paket..." extra={{ router_id: routerId || undefined }} />
                    <Select
                        className="sm:w-48"
                        value={routerId}
                        onChange={(event) => router.get('/packages', { search: search || undefined, router_id: event.target.value || undefined }, { preserveState: true, replace: true })}
                        aria-label="Filter router"
                    >
                        <option value="">Semua router</option>
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                </div>
                {packages.data.length === 0 ? (
                    <EmptyState title="Belum ada paket" description="Buat paket dengan nama profile PPP yang sama persis dengan di MikroTik." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="name" state={sort}>Paket</SortTh>
                                    <SortTh column="mikrotik_profile" state={sort}>Profile MikroTik</SortTh>
                                    <SortTh column="rate_limit" state={sort}>Rate limit</SortTh>
                                    <SortTh column="router" state={sort}>Router</SortTh>
                                    <SortTh column="price" state={sort} className="text-right">Harga / bulan</SortTh>
                                    <SortTh column="customers" state={sort}>Pelanggan</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
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
                    <Field
                        label="Profile PPP di MikroTik"
                        error={form.errors.mikrotik_profile}
                        hint={
                            profilesError ??
                            (!form.data.router_id
                                ? 'Pilih router untuk melihat daftar profile; atau ketik nama baru.'
                                : profilesLoading
                                  ? 'Membaca profile dari router...'
                                  : 'Pilih dari daftar router, atau ketik nama baru (akan dibuat di router).')
                        }
                    >
                        <Combobox
                            value={form.data.mikrotik_profile}
                            options={profiles}
                            onChange={(value) => form.setData('mikrotik_profile', value)}
                            placeholder={profiles.length > 0 ? 'Pilih profile...' : undefined}
                            required
                        />
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
                    <div className="space-y-3 rounded-lg border p-3 sm:col-span-2">
                        <p className="text-xs font-medium text-muted-foreground">Landing page publik</p>
                        <Checkbox label="Tampilkan di bagian Harga landing page" checked={form.data.show_on_landing} onChange={(checked) => form.setData('show_on_landing', checked)} />
                        {form.data.show_on_landing && (
                            <>
                                <Checkbox label="Sorot sebagai “Paling banyak diambil”" checked={form.data.landing_highlight} onChange={(checked) => form.setData('landing_highlight', checked)} />
                                <Field label="Deskripsi singkat" error={form.errors.landing_description} hint="Satu kalimat untuk kartu harga, maksimal 160 karakter.">
                                    <Input value={form.data.landing_description} maxLength={160} onChange={(event) => form.setData('landing_description', event.target.value)} />
                                </Field>
                            </>
                        )}
                    </div>
                </form>
            </Modal>
        </>
    );
}
