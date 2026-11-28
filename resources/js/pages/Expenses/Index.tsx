import { router, useForm } from '@inertiajs/react';
import { Paperclip, Pencil, Plus, Tags } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate, formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type Category = { id: number; name: string; type: string; expenses_count: number };
type ExpenseRow = {
    id: number;
    expense_category_id: number;
    router_id: number | null;
    amount: number;
    expense_date: string;
    description: string;
    receipt_path: string | null;
    expense_category: { name: string; type: string };
    router: { name: string } | null;
    creator: { name: string };
};

type Props = {
    expenses: Paginated<ExpenseRow>;
    total: number;
    categories: Category[];
    routers: { id: number; name: string }[];
    filters: { month: string; category_id: string };
};

const typeLabels: Record<string, string> = { bandwidth: 'Bandwidth', equipment: 'Peralatan', field: 'Lapangan', salary: 'Gaji', other: 'Lainnya' };

const today = () => new Date().toISOString().slice(0, 10);

export default function ExpensesIndex({ expenses, total, categories, routers, filters }: Props) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<ExpenseRow | null>(null);
    const [open, setOpen] = useState(false);
    const [manageCategories, setManageCategories] = useState(false);
    const form = useForm<{ expense_category_id: string; router_id: string; amount: string; expense_date: string; description: string; receipt: File | null; remove_receipt: boolean }>({
        expense_category_id: '',
        router_id: '',
        amount: '',
        expense_date: today(),
        description: '',
        receipt: null,
        remove_receipt: false,
    });
    const categoryForm = useForm({ name: '', type: 'other' });

    const applyFilter = (next: Partial<typeof filters>) => router.get('/expenses', { ...filters, ...next, category_id: (next.category_id ?? filters.category_id) || undefined }, { preserveState: true, replace: true });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (expense: ExpenseRow) => {
        setEditing(expense);
        form.setData({
            expense_category_id: String(expense.expense_category_id),
            router_id: expense.router_id ? String(expense.router_id) : '',
            amount: String(expense.amount),
            expense_date: expense.expense_date.slice(0, 10),
            description: expense.description,
            receipt: null,
            remove_receipt: false,
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, forceFormData: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/expenses/${editing.id}`, options);
        } else {
            form.transform((data) => data);
            form.post('/expenses', options);
        }
    };

    const addCategory = (event: FormEvent) => {
        event.preventDefault();
        categoryForm.post('/expense-categories', { preserveScroll: true, onSuccess: () => categoryForm.reset() });
    };

    return (
        <>
            <PageHeader
                title="Pengeluaran (OPEX)"
                description="Biaya operasional bulanan: bandwidth, peralatan, dan kebutuhan lapangan."
                actions={
                    <>
                        <Button variant="outline" onClick={() => setManageCategories(true)}>
                            <Tags className="size-4" /> Kategori
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Catat Pengeluaran
                        </Button>
                    </>
                }
            />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <Input type="month" className="sm:w-44" value={filters.month} onChange={(event) => event.target.value && applyFilter({ month: event.target.value })} aria-label="Bulan" />
                    <Select className="sm:w-56" value={filters.category_id} onChange={(event) => applyFilter({ category_id: event.target.value })} aria-label="Filter kategori">
                        <option value="">Semua kategori</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </Select>
                    <div className="num ml-auto text-sm">
                        <span className="text-muted-foreground">Total bulan ini </span>
                        <span className="text-lg font-semibold">{formatRupiah(total)}</span>
                    </div>
                </div>
                {expenses.data.length === 0 ? (
                    <EmptyState title="Belum ada pengeluaran pada periode ini" />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="date" state={sort}>Tanggal</SortTh>
                                    <SortTh column="category" state={sort}>Kategori</SortTh>
                                    <SortTh column="description" state={sort}>Keterangan</SortTh>
                                    <SortTh column="router" state={sort}>POP</SortTh>
                                    <SortTh column="amount" state={sort} className="text-right">Jumlah</SortTh>
                                    <SortTh column="creator" state={sort}>Dicatat</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.data.map((expense) => (
                                    <tr key={expense.id} className="hover:bg-muted/40">
                                        <Td className="whitespace-nowrap">{formatDate(expense.expense_date)}</Td>
                                        <Td>
                                            <Badge tone="blue">{expense.expense_category.name}</Badge>
                                        </Td>
                                        <Td className="max-w-xs whitespace-normal">{expense.description}</Td>
                                        <Td className="text-muted-foreground">{expense.router?.name ?? '-'}</Td>
                                        <Td className="num text-right font-medium">{formatRupiah(expense.amount)}</Td>
                                        <Td className="text-muted-foreground">{expense.creator.name}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                {expense.receipt_path && (
                                                    <a
                                                        href={`/expenses/${expense.id}/receipt`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        aria-label="Lihat bukti"
                                                        title="Lihat bukti"
                                                    >
                                                        <Paperclip className="size-4" />
                                                    </a>
                                                )}
                                                <Button variant="edit" size="icon" onClick={() => openEdit(expense)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/expenses/${expense.id}`} title="Hapus pengeluaran?" description={`${expense.description} (${formatRupiah(expense.amount)}) akan dikeluarkan dari laporan laba rugi.`} />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={expenses} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="lg"
                title={editing ? 'Ubah Pengeluaran' : 'Catat Pengeluaran'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="expense-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="expense-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Kategori" error={form.errors.expense_category_id}>
                        <Select value={form.data.expense_category_id} onChange={(event) => form.setData('expense_category_id', event.target.value)} required>
                            <option value="">Pilih kategori</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Tanggal" error={form.errors.expense_date}>
                        <Input type="date" value={form.data.expense_date} onChange={(event) => form.setData('expense_date', event.target.value)} required />
                    </Field>
                    <Field label="Jumlah (Rp)" error={form.errors.amount}>
                        <Input type="number" min={1} value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} required />
                    </Field>
                    <Field label="POP / router" error={form.errors.router_id} hint="Opsional, untuk biaya yang khusus satu lokasi.">
                        <Select value={form.data.router_id} onChange={(event) => form.setData('router_id', event.target.value)}>
                            <option value="">Umum</option>
                            {routers.map((router) => (
                                <option key={router.id} value={router.id}>
                                    {router.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Keterangan" error={form.errors.description} className="sm:col-span-2">
                        <Input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} required />
                    </Field>
                    <Field label="Bukti (JPG, PNG, PDF — maks. 2 MB)" error={form.errors.receipt} className="sm:col-span-2">
                        <Input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(event) => form.setData('receipt', event.target.files?.[0] ?? null)} />
                    </Field>
                    {editing?.receipt_path && (
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input type="checkbox" checked={form.data.remove_receipt} onChange={(event) => form.setData('remove_receipt', event.target.checked)} className="size-4 accent-[var(--primary)]" />
                            Hapus bukti yang tersimpan
                        </label>
                    )}
                </form>
            </Modal>

            <Modal open={manageCategories} onClose={() => setManageCategories(false)} title="Kategori Pengeluaran">
                <ul className="mb-4 divide-y rounded-lg border">
                    {categories.map((category) => (
                        <li key={category.id} className="row-in flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span>
                                {category.name} <span className="text-xs text-muted-foreground">· {typeLabels[category.type]} · {category.expenses_count} catatan</span>
                            </span>
                            <ConfirmDelete url={`/expense-categories/${category.id}`} title={`Hapus kategori ${category.name}?`} description="Kategori yang sudah dipakai pengeluaran tidak dapat dihapus." />
                        </li>
                    ))}
                </ul>
                <form onSubmit={addCategory} className="grid gap-3 sm:grid-cols-[1fr_9rem_auto] sm:items-end">
                    <Field label="Kategori baru" error={categoryForm.errors.name}>
                        <Input value={categoryForm.data.name} onChange={(event) => categoryForm.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Jenis" error={categoryForm.errors.type}>
                        <Select value={categoryForm.data.type} onChange={(event) => categoryForm.setData('type', event.target.value)}>
                            {Object.entries(typeLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Button type="submit" disabled={categoryForm.processing}>
                        Tambah
                    </Button>
                </form>
            </Modal>
        </>
    );
}
