import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, Megaphone } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { StatusBadge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type OutageRow = {
    id: number;
    title: string;
    message: string;
    status: string;
    notified_count: number;
    created_at: string;
    resolved_at: string | null;
    odp: { name: string; code: string };
    creator: { name: string };
};

type Props = { outages: Paginated<OutageRow>; odps: { id: number; name: string; code: string; recipients_count: number }[] };

const defaultMessage = (odpName: string) =>
    `Mohon maaf, terjadi gangguan jaringan (kabel fiber optik) di area ${odpName}. Teknisi kami sedang menuju lokasi untuk perbaikan. Kami akan mengabari kembali setelah layanan normal.`;

export default function OutagesIndex({ outages, odps }: Props) {
    const sort = useServerSort();
    const [open, setOpen] = useState(false);
    const [resolving, setResolving] = useState<OutageRow | null>(null);
    const [notify, setNotify] = useState(true);
    const form = useForm({ odp_id: '', title: 'Gangguan fiber optik', message: '' });
    const selected = odps.find((odp) => String(odp.id) === form.data.odp_id);

    const selectOdp = (id: string) => {
        const odp = odps.find((item) => String(item.id) === id);
        form.setData((current) => ({ ...current, odp_id: id, message: odp ? defaultMessage(odp.name) : '' }));
    };

    useEffect(() => {
        const odpId = new URLSearchParams(window.location.search).get('odp');
        const odp = odps.find((item) => String(item.id) === odpId);

        if (odp) {
            form.setData({ odp_id: String(odp.id), title: 'Gangguan fiber optik', message: defaultMessage(odp.name) });
            setOpen(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/outages', { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    const resolve = () => {
        if (resolving) {
            router.post(`/outages/${resolving.id}/resolve`, { notify }, { preserveScroll: true, onFinish: () => setResolving(null) });
        }
    };

    return (
        <>
            <PageHeader
                title="Tiket & Broadcast Gangguan"
                description="Umumkan gangguan satu ODP (mis. fiber putus) ke seluruh pelanggan di ODP tersebut lewat WhatsApp."
                actions={
                    <Button
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            setOpen(true);
                        }}
                    >
                        <Megaphone className="size-4" /> Umumkan Gangguan
                    </Button>
                }
            />

            <Card>
                {outages.data.length === 0 ? (
                    <EmptyState title="Belum ada gangguan tercatat" description="Pilih ODP yang bermasalah lalu kirim pengumuman massal." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="odp" state={sort}>ODP</SortTh>
                                    <SortTh column="title" state={sort}>Gangguan</SortTh>
                                    <SortTh column="notified" state={sort}>Pelanggan diberi tahu</SortTh>
                                    <SortTh column="started" state={sort}>Dimulai</SortTh>
                                    <SortTh column="resolved" state={sort}>Selesai</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {outages.data.map((outage) => (
                                    <tr key={outage.id} className="hover:bg-muted/40">
                                        <Td>
                                            <span className="num font-medium">{outage.odp.code}</span>
                                            <span className="ml-2 text-xs text-muted-foreground">{outage.odp.name}</span>
                                        </Td>
                                        <Td className="max-w-xs whitespace-normal">
                                            <div className="font-medium">{outage.title}</div>
                                            <div className="line-clamp-2 text-xs text-muted-foreground">{outage.message}</div>
                                            <div className="text-[11px] text-muted-foreground">oleh {outage.creator.name}</div>
                                        </Td>
                                        <Td className="num">{outage.notified_count}</Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">{formatDate(outage.created_at)}</Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">{formatDate(outage.resolved_at)}</Td>
                                        <Td>
                                            <StatusBadge status={outage.status === 'open' ? 'failed' : 'completed'} />
                                        </Td>
                                        <Td>
                                            <div className="flex justify-end">
                                                {outage.status === 'open' && (
                                                    <Button variant="outline" size="sm" onClick={() => setResolving(outage)}>
                                                        <CheckCircle2 className="size-3.5" /> Tandai selesai
                                                    </Button>
                                                )}
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={outages} />
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="lg"
                title="Umumkan Gangguan"
                description="Pesan dikirim ke semua pelanggan aktif dan terisolir di ODP terpilih, dibatasi 20 pesan per menit."
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="outage-form" disabled={form.processing || !selected}>
                            {form.processing ? 'Mengirim...' : `Kirim ke ${selected?.recipients_count ?? 0} pelanggan`}
                        </Button>
                    </>
                }
            >
                <form id="outage-form" onSubmit={submit} className="grid gap-4">
                    <Field label="ODP yang terganggu" error={form.errors.odp_id}>
                        <Select value={form.data.odp_id} onChange={(event) => selectOdp(event.target.value)} required>
                            <option value="">Pilih ODP</option>
                            {odps.map((odp) => (
                                <option key={odp.id} value={odp.id}>
                                    {odp.code} — {odp.name} ({odp.recipients_count} pelanggan)
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Judul" error={form.errors.title}>
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required />
                    </Field>
                    <Field label="Isi pengumuman" error={form.errors.message} hint="Sapaan 'Halo {nama}' ditambahkan otomatis di awal pesan.">
                        <Textarea rows={5} value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} required />
                    </Field>
                </form>
            </Modal>

            <Modal
                open={resolving !== null}
                onClose={() => setResolving(null)}
                title="Tandai gangguan selesai?"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setResolving(null)}>
                            Batal
                        </Button>
                        <Button onClick={resolve}>Tandai selesai</Button>
                    </>
                }
            >
                <p className="mb-4 text-sm text-muted-foreground">
                    {resolving?.odp.code} — {resolving?.title}
                </p>
                <Checkbox label="Kabari pelanggan bahwa layanan sudah normal" checked={notify} onChange={setNotify} />
            </Modal>
        </>
    );
}
