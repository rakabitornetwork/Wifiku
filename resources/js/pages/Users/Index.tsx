import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, KeyRound } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type UserRow = { id: number; name: string; email: string; phone: string | null; is_active: boolean; last_login_at: string | null; role: string | null };

const roleLabels: Record<string, { label: string; hint: string }> = {
    Owner: { label: 'Owner', hint: 'Akses penuh, termasuk pengaturan, pengguna, dan audit log.' },
    Admin: { label: 'Admin / Kasir', hint: 'Operasional harian: pelanggan, tagihan, kasir, voucher, OPEX, laporan.' },
    Teknisi: { label: 'Teknisi', hint: 'Lihat pelanggan, kelola ONT, lihat peta, umumkan gangguan.' },
};

const emptyForm = { name: '', email: '', phone: '', role: 'Teknisi', password: '', is_active: true };

export default function UsersIndex({ users }: { users: Paginated<UserRow> }) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (user: UserRow) => {
        setEditing(user);
        form.setData({ name: user.name, email: user.email, phone: user.phone ?? '', role: user.role ?? 'Teknisi', password: '', is_active: user.is_active });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/users/${editing.id}`, options);
        } else {
            form.post('/users', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Teknisi & RBAC"
                description="Akun tim dan hak aksesnya. Hanya Owner yang dapat mengelola halaman ini."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" /> Tambah Pengguna
                    </Button>
                }
            />

            <Card>
                <TableWrap>
                    <Table>
                        <thead>
                            <tr>
                                <SortTh column="name" state={sort}>Nama</SortTh>
                                <SortTh column="email" state={sort}>Email</SortTh>
                                <SortTh column="phone" state={sort}>Telepon</SortTh>
                                <SortTh column="role" state={sort}>Role</SortTh>
                                <SortTh column="last_login" state={sort}>Login terakhir</SortTh>
                                <SortTh column="status" state={sort}>Status</SortTh>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-muted/40">
                                    <Td className="font-medium">{user.name}</Td>
                                    <Td className="text-muted-foreground">{user.email}</Td>
                                    <Td className="num text-muted-foreground">{user.phone ?? '-'}</Td>
                                    <Td>
                                        <Badge tone={user.role === 'Owner' ? 'blue' : 'slate'}>{roleLabels[user.role ?? '']?.label ?? user.role ?? '-'}</Badge>
                                    </Td>
                                    <Td className="text-muted-foreground">{formatDate(user.last_login_at)}</Td>
                                    <Td>{user.is_active ? <Badge tone="green">Aktif</Badge> : <Badge>Nonaktif</Badge>}</Td>
                                    <Td>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="warning"
                                                size="icon"
                                                onClick={() => router.post(`/users/${user.id}/reset-link`, {}, { preserveScroll: true })}
                                                aria-label="Kirim tautan reset password"
                                                title={user.phone ? 'Kirim tautan reset password lewat WhatsApp' : 'Isi nomor telepon dulu untuk mengirim tautan reset'}
                                            >
                                                <KeyRound className="size-4" />
                                            </Button>
                                            <Button variant="edit" size="icon" onClick={() => openEdit(user)} aria-label="Ubah">
                                                <Pencil className="size-4" />
                                            </Button>
                                            <ConfirmDelete url={`/users/${user.id}`} title={`Hapus ${user.name}?`} description="Pengguna yang sudah memiliki riwayat transaksi tidak dapat dihapus; nonaktifkan akunnya." />
                                        </div>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                </TableWrap>
                <Pagination meta={users} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={editing ? 'Ubah Pengguna' : 'Tambah Pengguna'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="user-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="user-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nama" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Email (untuk login)" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                    </Field>
                    <Field label="No. telepon (WhatsApp)" error={form.errors.phone} hint="Format 08xx. Dipakai untuk mengirim tautan reset password.">
                        <Input inputMode="tel" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </Field>
                    <Field label="Password" error={form.errors.password} hint={editing ? 'Kosongkan jika tidak ingin mengubah.' : 'Minimal 8 karakter.'}>
                        <Input type="password" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required={!editing} />
                    </Field>
                    <Field label="Role" error={form.errors.role} hint={roleLabels[form.data.role]?.hint} className="sm:col-span-2">
                        <Select value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
                            {Object.entries(roleLabels).map(([value, item]) => (
                                <option key={value} value={value}>
                                    {item.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <div className="sm:col-span-2">
                        <Checkbox label="Akun aktif (dapat login)" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
