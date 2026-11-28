import { Link, router } from '@inertiajs/react';
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { PaymentExtensionModal } from '@/Components/PaymentExtensionModal';
import { SearchInput } from '@/Components/SearchInput';
import { StatusBadge } from '@/Components/ui/Badge';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import type { Paginated } from '@/types';
import { useCan } from '@/Hooks/useCan';
import { useServerSort } from '@/Hooks/useSort';
import { formatDate } from '@/lib/utils';

type CustomerRow = {
    id: number;
    customer_code: string;
    name: string;
    whatsapp: string;
    pppoe_username: string;
    billing_cycle: string;
    billing_day: number;
    status: string;
    promise_to_pay_until: string | null;
    router: { name: string } | null;
    package: { name: string } | null;
    odp: { name: string } | null;
};

type Props = {
    customers: Paginated<CustomerRow>;
    routers: { id: number; name: string }[];
    filters: { search: string; status: string; router_id: string; kontak: string; sort: string; direction: 'asc' | 'desc' };
};

export default function CustomersIndex({ customers, routers, filters }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [selectAll, setSelectAll] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [deleting, setDeleting] = useState(false);

    // Pilihan hanya berlaku untuk halaman dan filter yang sedang dilihat.
    useEffect(() => {
        setSelected([]);
        setSelectAll(false);
    }, [customers.current_page, filters.search, filters.status, filters.router_id]);

    const pageIds = customers.data.map((customer) => customer.id);
    const pageSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const selectedCount = selectAll ? customers.total : selected.length;

    const togglePage = (checked: boolean) => {
        setSelected(checked ? pageIds : []);
        setSelectAll(false);
    };

    const toggleOne = (id: number, checked: boolean) => {
        setSelected((current) => (checked ? [...current, id] : current.filter((item) => item !== id)));
        setSelectAll(false);
    };

    const deleteSelected = () =>
        router.delete('/customers', {
            data: selectAll ? { select_all: true, search: filters.search || undefined, status: filters.status || undefined, router_id: filters.router_id || undefined, kontak: filters.kontak || undefined } : { ids: selected },
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setConfirming(false);
            },
            onSuccess: () => {
                setSelected([]);
                setSelectAll(false);
            },
        });

    const applyFilter = (key: 'status' | 'router_id' | 'kontak', value: string) =>
        router.get('/customers', { ...filters, [key]: value || undefined, search: filters.search || undefined, sort: filters.sort || undefined, direction: filters.sort ? filters.direction : undefined, page: undefined }, { preserveState: true, replace: true });

    const sort = useServerSort();
    const can = useCan();
    const [extending, setExtending] = useState<{ id: number; name: string } | null>(null);

    return (
        <>
            <PageHeader
                title="Data Pelanggan"
                description="Pelanggan PPPoE beserta paket, router, dan status layanannya."
                actions={
                    <Link href="/customers/create" className={buttonClass()}>
                        <Plus className="size-4" /> Tambah Pelanggan
                    </Link>
                }
            />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={filters.search} placeholder="Cari nama, kode, username, WhatsApp..." extra={{ status: filters.status || undefined, router_id: filters.router_id || undefined, kontak: filters.kontak || undefined, sort: filters.sort || undefined, direction: filters.sort ? filters.direction : undefined }} />
                    <Select className="sm:w-44" value={filters.status} onChange={(event) => applyFilter('status', event.target.value)} aria-label="Filter status">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="isolated">Isolir</option>
                        <option value="inactive">Nonaktif</option>
                    </Select>
                    <Select className="sm:w-48" value={filters.kontak} onChange={(event) => applyFilter('kontak', event.target.value)} aria-label="Filter kontak">
                        <option value="">Semua kontak</option>
                        <option value="tanpa-wa">Tanpa nomor WhatsApp</option>
                    </Select>
                    <Select className="sm:w-48" value={filters.router_id} onChange={(event) => applyFilter('router_id', event.target.value)} aria-label="Filter router">
                        <option value="">Semua router</option>
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                </div>
                {selectedCount > 0 && (
                    <div className="flex flex-col gap-2 border-b bg-accent/40 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span className="num font-medium">{selectedCount}</span> pelanggan dipilih
                            {!selectAll && pageSelected && customers.total > pageIds.length && (
                                <button type="button" className="ml-2 font-medium text-primary hover:underline" onClick={() => setSelectAll(true)}>
                                    Pilih semua {customers.total} hasil
                                </button>
                            )}
                            {selectAll && (
                                <button type="button" className="ml-2 font-medium text-primary hover:underline" onClick={() => togglePage(true)}>
                                    Hanya halaman ini
                                </button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => togglePage(false)}>
                                Batal pilih
                            </Button>
                            <Button variant="danger" size="sm" onClick={() => setConfirming(true)}>
                                <Trash2 className="size-3.5" /> Hapus terpilih
                            </Button>
                        </div>
                    </div>
                )}
                {customers.data.length === 0 ? (
                    <EmptyState title="Tidak ada pelanggan" description="Ubah filter pencarian atau tambahkan pelanggan baru." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th className="w-10">
                                        <input type="checkbox" checked={pageSelected} onChange={(event) => togglePage(event.target.checked)} aria-label="Pilih semua di halaman ini" className="size-4 rounded border-input accent-[var(--primary)]" />
                                    </Th>
                                    <SortTh column="customer_code" state={sort}>ID</SortTh>
                                    <SortTh column="name" state={sort}>Nama</SortTh>
                                    <SortTh column="whatsapp" state={sort}>WhatsApp</SortTh>
                                    <SortTh column="pppoe_username" state={sort}>Username PPPoE</SortTh>
                                    <SortTh column="package" state={sort}>Paket</SortTh>
                                    <SortTh column="router" state={sort}>Router / ODP</SortTh>
                                    <SortTh column="billing_day" state={sort}>Tagihan</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {customers.data.map((customer) => (
                                    <tr key={customer.id} className="hover:bg-muted/40">
                                        <Td>
                                            <input
                                                type="checkbox"
                                                checked={selectAll || selected.includes(customer.id)}
                                                disabled={selectAll}
                                                onChange={(event) => toggleOne(customer.id, event.target.checked)}
                                                aria-label={`Pilih ${customer.name}`}
                                                className="size-4 rounded border-input accent-[var(--primary)]"
                                            />
                                        </Td>
                                        <Td className="num text-muted-foreground">{customer.customer_code}</Td>
                                        <Td className="font-medium">{customer.name}</Td>
                                        <Td className="num text-muted-foreground">{customer.whatsapp || '-'}</Td>
                                        <Td className="num">{customer.pppoe_username}</Td>
                                        <Td>{customer.package?.name}</Td>
                                        <Td>
                                            {customer.router?.name}
                                            {customer.odp && <span className="text-muted-foreground"> · {customer.odp.name}</span>}
                                        </Td>
                                        <Td className="num text-muted-foreground">
                                            {customer.billing_cycle === 'fixed' ? 'Tgl' : 'Anniv.'} {customer.billing_day}
                                        </Td>
                                        <Td className="text-center">
                                            <StatusBadge status={customer.status} />
                                            {customer.promise_to_pay_until && customer.promise_to_pay_until.slice(0, 10) >= new Date().toISOString().slice(0, 10) && (
                                                <span className="num ml-2 text-[11px] text-muted-foreground">Janji s.d. {formatDate(customer.promise_to_pay_until)}</span>
                                            )}
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                {can('payments.collect') && customer.status !== 'inactive' && (
                                                    <Button variant="warning" size="icon" onClick={() => setExtending({ id: customer.id, name: customer.name })} aria-label="Perpanjangan pembayaran" title="Perpanjangan pembayaran (Janji Bayar)">
                                                        <CalendarClock className="size-4" />
                                                    </Button>
                                                )}
                                                <Link href={`/customers/${customer.id}/edit`} className={buttonClass('edit', 'icon')} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Link>
                                                <ConfirmDelete
                                                    url={`/customers/${customer.id}`}
                                                    title={`Hapus ${customer.name}?`}
                                                    description="Pelanggan dipindahkan ke arsip dan secret PPPoE-nya dihapus dari router (sesi aktif diputus). Riwayat tagihan dan pembayaran tetap tersimpan."
                                                />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={customers} />
            </Card>

            <PaymentExtensionModal customer={extending} onClose={() => setExtending(null)} />

            <Modal
                open={confirming}
                title={`Hapus ${selectedCount} pelanggan?`}
                onClose={() => setConfirming(false)}
                footer={
                    <>
                        <Button variant="close" onClick={() => setConfirming(false)}>
                            Batal
                        </Button>
                        <Button variant="danger" onClick={deleteSelected} disabled={deleting}>
                            {deleting ? 'Menghapus...' : `Hapus ${selectedCount} pelanggan`}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-muted-foreground">
                    Pelanggan dipindahkan ke arsip, secret PPPoE-nya dihapus dari router masing-masing (sesi aktif diputus), dan username-nya bisa dipakai lagi. Riwayat tagihan dan pembayaran tetap tersimpan.
                    {selectAll && ' Seluruh hasil sesuai filter saat ini ikut dihapus, termasuk di halaman lain.'}
                </p>
            </Modal>
        </>
    );
}

