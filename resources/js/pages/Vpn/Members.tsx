import { Link, router, useForm } from '@inertiajs/react';
import { KeyRound, MessageCircle, Pencil } from 'lucide-react';
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
import { EmptyState, SortTh, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { useServerSort } from '@/Hooks/useSort';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

type MemberRow = {
    id: number;
    name: string;
    email: string;
    whatsapp: string;
    is_active: boolean;
    created_at: string;
    last_login_at: string | null;
    accounts_count: number;
    active_accounts_count: number;
    pending_accounts_count: number;
};

type Props = { members: Paginated<MemberRow>; search: string; status: string; maxAccounts: number };

export default function VpnMembers({ members, search, status, maxAccounts }: Props) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<MemberRow | null>(null);
    const [sending, setSending] = useState<number | null>(null);
    const form = useForm({ name: '', email: '', whatsapp: '', is_active: true, disable_accounts: false });

    const openEdit = (member: MemberRow) => {
        setEditing(member);
        form.setData({ name: member.name, email: member.email, whatsapp: member.whatsapp, is_active: member.is_active, disable_accounts: false });
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(`/vpn/members/${editing.id}`, { preserveScroll: true, onSuccess: () => setEditing(null) });
        }
    };

    const sendReset = (member: MemberRow) =>
        router.post(`/vpn/members/${member.id}/reset-link`, {}, { preserveScroll: true, onStart: () => setSending(member.id), onFinish: () => setSending(null) });

    const deactivating = editing?.is_active === true && !form.data.is_active;

    return (
        <>
            <PageHeader title="Member VPN" description="Pelanggan layanan VPN Tunnel yang mendaftar sendiri lewat landing page /vpn-tunnel. Akun dan server VPN-nya ada di menu VPN Tunnel." actions={<Link href="/vpn" className="text-sm font-semibold text-primary hover:underline">Buka akun & server VPN</Link>} />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={search} placeholder="Cari nama, email, atau WhatsApp..." extra={{ status: status || undefined }} />
                    <Select
                        className="sm:w-44"
                        value={status}
                        onChange={(event) => router.get('/vpn/members', { search: search || undefined, status: event.target.value || undefined }, { preserveState: true, replace: true })}
                        aria-label="Filter status"
                    >
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Dinonaktifkan</option>
                    </Select>
                </div>
                {members.data.length === 0 ? (
                    <EmptyState title="Belum ada member" description="Pelanggan yang mendaftar di /vpn-tunnel/daftar akan muncul di sini." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="name" state={sort}>Member</SortTh>
                                    <SortTh column="email" state={sort}>Email</SortTh>
                                    <Th>WhatsApp</Th>
                                    <SortTh column="accounts" state={sort}>Akun VPN</SortTh>
                                    <SortTh column="created_at" state={sort}>Terdaftar</SortTh>
                                    <SortTh column="last_login_at" state={sort}>Login terakhir</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th>Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {members.data.map((member) => (
                                    <tr key={member.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{member.name}</Td>
                                        <Td className="text-muted-foreground">{member.email}</Td>
                                        <Td>
                                            <a href={`https://wa.me/${member.whatsapp}`} target="_blank" rel="noreferrer" className="num inline-flex items-center gap-1 text-muted-foreground hover:text-foreground">
                                                <MessageCircle className="size-3.5" /> {member.whatsapp}
                                            </a>
                                        </Td>
                                        <Td>
                                            {member.accounts_count > 0 ? (
                                                <Link href={`/vpn?member_id=${member.id}`} className="num font-medium text-primary hover:underline" title="Lihat akun member ini">
                                                    {member.accounts_count} / {maxAccounts}
                                                </Link>
                                            ) : (
                                                <span className="num text-muted-foreground">0 / {maxAccounts}</span>
                                            )}
                                            {(member.active_accounts_count > 0 || member.pending_accounts_count > 0) && (
                                                <div className="text-xs text-muted-foreground">
                                                    {member.active_accounts_count} aktif
                                                    {member.pending_accounts_count > 0 && ` · ${member.pending_accounts_count} menunggu bayar`}
                                                </div>
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">{formatDate(member.created_at)}</Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">{member.last_login_at ? formatDate(member.last_login_at) : '-'}</Td>
                                        <Td className="text-center">{member.is_active ? <Badge tone="green">Aktif</Badge> : <Badge>Dinonaktifkan</Badge>}</Td>
                                        <Td>
                                            <div className="flex justify-center gap-1">
                                                <Button variant="warning" size="icon" onClick={() => sendReset(member)} disabled={sending === member.id} aria-label="Kirim tautan reset password" title="Kirim tautan reset password lewat WhatsApp">
                                                    <KeyRound className={sending === member.id ? 'size-4 animate-pulse' : 'size-4'} />
                                                </Button>
                                                <Button variant="edit" size="icon" onClick={() => openEdit(member)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/vpn/members/${member.id}`} title={`Hapus member ${member.name}?`} description="Member yang masih memiliki akun VPN tidak dapat dihapus; nonaktifkan saja bila ingin menutup aksesnya." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={members} />
            </Card>

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title="Ubah Member"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setEditing(null)}>
                            Batal
                        </Button>
                        <Button type="submit" form="vpn-member-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="vpn-member-form" onSubmit={submit} className="space-y-4">
                    <Field label="Nama" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Email" error={form.errors.email} hint="Dipakai untuk login pelanggan.">
                        <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                    </Field>
                    <Field label="No. WhatsApp" error={form.errors.whatsapp} hint="Tujuan tagihan, bukti bayar, dan tautan reset password.">
                        <Input inputMode="tel" value={form.data.whatsapp} onChange={(event) => form.setData('whatsapp', event.target.value)} required />
                    </Field>
                    <div className="space-y-2 rounded-lg border p-3">
                        <Checkbox label="Member aktif (boleh login dan membuat akun)" checked={form.data.is_active} onChange={(checked) => form.setData((current) => ({ ...current, is_active: checked, disable_accounts: checked ? false : current.disable_accounts }))} />
                        {deactivating && (
                            <Checkbox label="Nonaktifkan juga semua akun VPN aktifnya (tunnel diputus)" checked={form.data.disable_accounts} onChange={(checked) => form.setData('disable_accounts', checked)} />
                        )}
                        {deactivating && !form.data.disable_accounts && <p className="text-xs text-muted-foreground">Tanpa centang kedua, member tidak bisa login tetapi tunnel yang sudah aktif tetap berjalan sampai masa berlakunya habis.</p>}
                    </div>
                </form>
            </Modal>
        </>
    );
}
