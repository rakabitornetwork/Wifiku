import { Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, Eye, EyeOff, Send, XCircle } from 'lucide-react';
import { useState } from 'react';

import { MessagingShell } from '@/Components/MessagingShell';
import { SearchInput } from '@/Components/SearchInput';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

type Message = {
    key: string;
    channel: 'whatsapp' | 'telegram';
    destination: string;
    type: string | null;
    status: 'queued' | 'sent' | 'failed';
    body: string;
    hidden: boolean;
    error: string | null;
    attempts: number;
    created_at: string;
    sent_at: string | null;
    customer: { id: number; name: string; code: string } | null;
};

type Props = {
    messages: Paginated<Message>;
    filters: { kanal: string; status: string; jenis: string; search: string };
    types: string[];
    summary: { sent_24h: number; failed_24h: number; queued: number };
};

const typeMeta: Record<string, { label: string; icon: string }> = {
    invoice: { label: 'Tagihan', icon: '🧾' },
    reminder: { label: 'Pengingat', icon: '⏰' },
    receipt: { label: 'Bukti bayar', icon: '💳' },
    outage: { label: 'Gangguan', icon: '⚠️' },
    isolir: { label: 'Isolir / aktif kembali', icon: '🔴' },
    test: { label: 'Pesan uji', icon: '🧪' },
    password_reset: { label: 'Reset password', icon: '🔑' },
    account_security: { label: 'Kode OTP', icon: '🔐' },
    chat: { label: 'Balasan bot', icon: '🤖' },
    welcome: { label: 'Selamat datang', icon: '🎉' },
};

const statusMeta = {
    sent: { label: 'Terkirim', tone: 'green', icon: CheckCircle2 },
    failed: { label: 'Gagal', tone: 'red', icon: XCircle },
    queued: { label: 'Antre', tone: 'amber', icon: Clock3 },
} as const;

const time = (value: string | null) => (value ? new Date(value.replace(' ', 'T') + (value.includes('Z') || value.includes('+') ? '' : 'Z')).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-');

function ChannelBadge({ channel }: { channel: Message['channel'] }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset', channel === 'whatsapp' ? 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/25 dark:text-emerald-300' : 'bg-sky-500/12 text-sky-700 ring-sky-500/25 dark:text-sky-300')}>
            {channel === 'whatsapp' ? 'WhatsApp' : 'Telegram'}
        </span>
    );
}

export default function MessagesIndex({ messages, filters, types, summary }: Props) {
    const [open, setOpen] = useState<Message | null>(null);

    const applyFilter = (key: 'kanal' | 'status' | 'jenis', value: string) =>
        router.get('/pesan', { ...filters, [key]: value || undefined, search: filters.search || undefined, page: undefined }, { preserveState: true, replace: true });

    const extra = { kanal: filters.kanal || undefined, status: filters.status || undefined, jenis: filters.jenis || undefined };

    return (
        <MessagingShell active="history" description="Pesan WhatsApp dan Telegram yang dikirim ke pelanggan, otomatis maupun balasan bot. Isi yang memuat kode OTP, tautan reset, atau password selalu disembunyikan.">

            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                {[
                    { label: 'Terkirim (24 jam)', value: summary.sent_24h, icon: Send, tone: 'text-emerald-600 dark:text-emerald-400' },
                    { label: 'Gagal (24 jam)', value: summary.failed_24h, icon: XCircle, tone: summary.failed_24h > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground' },
                    { label: 'Menunggu antrean', value: summary.queued, icon: Clock3, tone: summary.queued > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground' },
                ].map((item) => (
                    <Card key={item.label} className="flex items-center gap-3 p-4">
                        <item.icon className={cn('size-5', item.tone)} />
                        <div>
                            <div className="num text-2xl font-semibold tracking-tight">{item.value}</div>
                            <div className="text-xs text-muted-foreground">{item.label}</div>
                        </div>
                    </Card>
                ))}
            </div>

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:flex-wrap sm:items-center">
                    <SearchInput value={filters.search} placeholder="Cari pelanggan, nomor, atau isi pesan..." extra={extra} />
                    <Select className="sm:w-40" value={filters.kanal} onChange={(event) => applyFilter('kanal', event.target.value)} aria-label="Filter kanal">
                        <option value="">Semua kanal</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="telegram">Telegram</option>
                    </Select>
                    <Select className="sm:w-40" value={filters.status} onChange={(event) => applyFilter('status', event.target.value)} aria-label="Filter status">
                        <option value="">Semua status</option>
                        <option value="sent">Terkirim</option>
                        <option value="failed">Gagal</option>
                        <option value="queued">Antre</option>
                    </Select>
                    <Select className="sm:w-48" value={filters.jenis} onChange={(event) => applyFilter('jenis', event.target.value)} aria-label="Filter jenis">
                        <option value="">Semua jenis</option>
                        {types.map((type) => (
                            <option key={type} value={type}>
                                {typeMeta[type]?.icon} {typeMeta[type]?.label ?? type}
                            </option>
                        ))}
                    </Select>
                    <Link href="/settings/message-templates" className="text-sm font-semibold text-primary hover:underline sm:ml-auto">
                        Lihat template pesan
                    </Link>
                </div>

                {messages.data.length === 0 ? (
                    <EmptyState title="Belum ada pesan" description="Pesan yang dikirim ke pelanggan akan tercatat di sini." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Waktu</Th>
                                    <Th>Kanal</Th>
                                    <Th>Pelanggan</Th>
                                    <Th>Jenis</Th>
                                    <Th>Isi</Th>
                                    <Th>Status</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {messages.data.map((message) => {
                                    const status = statusMeta[message.status];
                                    const meta = message.type ? typeMeta[message.type] : null;

                                    return (
                                        <tr key={message.key} className="cursor-pointer hover:bg-muted/40" onClick={() => setOpen(message)}>
                                            <Td className="num whitespace-nowrap text-muted-foreground">{time(message.created_at)}</Td>
                                            <Td>
                                                <ChannelBadge channel={message.channel} />
                                            </Td>
                                            <Td>
                                                {message.customer ? (
                                                    <>
                                                        <div className="font-medium">{message.customer.name}</div>
                                                        <div className="num text-xs text-muted-foreground">{message.customer.code}</div>
                                                    </>
                                                ) : (
                                                    <span className="num text-sm text-muted-foreground">{message.destination}</span>
                                                )}
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                {meta ? `${meta.icon} ${meta.label}` : (message.type ?? '-')}
                                            </Td>
                                            <Td className="max-w-xs whitespace-normal">
                                                <span className={cn('line-clamp-2 text-sm', message.hidden && 'flex items-center gap-1.5 text-muted-foreground italic')}>
                                                    {message.hidden && <EyeOff className="size-3.5 shrink-0" />}
                                                    {message.hidden ? 'Isi disembunyikan' : message.body}
                                                </span>
                                            </Td>
                                            <Td className="text-center">
                                                <Badge tone={status.tone}>
                                                    <status.icon className="size-3.5" /> {status.label}
                                                </Badge>
                                            </Td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={messages} />
            </Card>

            <Modal
                open={open !== null}
                onClose={() => setOpen(null)}
                size="lg"
                title={open?.customer ? `${open.customer.name} · ${open.customer.code}` : (open?.destination ?? 'Pesan')}
                description={open ? `${open.channel === 'whatsapp' ? 'WhatsApp' : 'Telegram'} · ${time(open.created_at)}${open.sent_at ? ` · terkirim ${time(open.sent_at)}` : ''}` : undefined}
                footer={
                    <Button variant="outline" onClick={() => setOpen(null)}>
                        Tutup
                    </Button>
                }
            >
                {open && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <ChannelBadge channel={open.channel} />
                            <Badge tone={statusMeta[open.status].tone}>{statusMeta[open.status].label}</Badge>
                            {open.type && <span className="text-sm">{typeMeta[open.type] ? `${typeMeta[open.type].icon} ${typeMeta[open.type].label}` : open.type}</span>}
                            <span className="num text-xs text-muted-foreground">Tujuan: {open.destination}</span>
                            {open.attempts > 1 && <span className="text-xs text-muted-foreground">{open.attempts} percobaan</span>}
                        </div>
                        <div className={cn('rounded-xl p-4 text-sm leading-relaxed break-words whitespace-pre-wrap', open.hidden ? 'bg-muted text-muted-foreground italic' : open.channel === 'whatsapp' ? 'bg-[#d9fdd3] text-slate-900 dark:bg-[#005c4b] dark:text-slate-50' : 'bg-sky-50 text-slate-900 dark:bg-[#182533] dark:text-slate-50')}>
                            {open.hidden && <Eye className="mr-1.5 inline size-4" />}
                            {open.body}
                        </div>
                        {open.error && (
                            <p className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">
                                <strong>Galat:</strong> {open.error}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">Dibuat {formatDate(open.created_at)}.</p>
                    </div>
                )}
            </Modal>
        </MessagingShell>
    );
}
