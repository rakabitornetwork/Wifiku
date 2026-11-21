import { Link, router, useForm } from '@inertiajs/react';
import { Activity, AlertTriangle, CheckCircle2, HelpCircle, Wifi } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Button } from '@/Components/ui/Button';
import { Card, StatCard } from '@/Components/ui/Card';
import { Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { useCan } from '@/Hooks/useCan';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

type OntRow = {
    id: number;
    brand: string | null;
    model: string | null;
    serial_number: string;
    last_rx_power: string | null;
    last_seen_at: string | null;
    customer: { id: number; customer_code: string; name: string } | null;
};

type Summary = { total: number; good: number; warning: number; critical: number; unknown: number };

type Props = {
    onts: Paginated<OntRow>;
    filters: { search: string; signal: string };
    summary: Summary;
    genieacsConfigured: boolean;
};

const rxTone = (value: number) => (value >= -25 ? 'text-emerald-600 dark:text-emerald-400' : value >= -27 ? 'text-amber-600 dark:text-amber-400' : 'text-destructive');

export default function MonitoringIndex({ onts, filters, summary, genieacsConfigured }: Props) {
    const can = useCan();
    const [wifiOnt, setWifiOnt] = useState<OntRow | null>(null);
    const [reading, setReading] = useState<number | null>(null);
    const wifiForm = useForm({ ssid: '', password: '' });

    const readRx = (ont: OntRow) => router.post(`/assets/${ont.id}/rx-power`, {}, { preserveScroll: true, onStart: () => setReading(ont.id), onFinish: () => setReading(null) });

    const submitWifi = (event: FormEvent) => {
        event.preventDefault();

        if (wifiOnt) {
            wifiForm.post(`/assets/${wifiOnt.id}/wifi`, { preserveScroll: true, onSuccess: () => setWifiOnt(null) });
        }
    };

    const applyFilter = (signal: string) => router.get('/monitoring', { search: filters.search || undefined, signal: signal || undefined }, { preserveState: true, replace: true });

    return (
        <>
            <PageHeader title="Monitoring OLT & TR-069" description="Redaman optik (RX Power) dan status ONT terpasang dari GenieACS." />

            {!genieacsConfigured && (
                <Card className="mb-4 flex items-start gap-3 border-amber-500/40 p-4 text-sm">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-500" />
                    <p>
                        GenieACS belum dikonfigurasi, sehingga RX Power tidak bisa dibaca.{' '}
                        {can('settings.manage') ? (
                            <Link href="/settings/integrations" className="font-medium text-primary hover:underline">
                                Atur di Pengaturan
                            </Link>
                        ) : (
                            'Hubungi Owner untuk mengaturnya.'
                        )}
                    </p>
                </Card>
            )}

            <div className="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="ONT terpasang" value={String(summary.total)} icon={<Activity className="size-4" />} />
                <StatCard label="Sinyal baik" value={String(summary.good)} hint="≥ -25 dBm" icon={<CheckCircle2 className="size-4" />} />
                <StatCard label="Perlu perhatian" value={String(summary.warning + summary.critical)} hint={`${summary.critical} kritis (< -27 dBm)`} icon={<AlertTriangle className="size-4" />} />
                <StatCard label="Belum terbaca" value={String(summary.unknown)} icon={<HelpCircle className="size-4" />} />
            </div>

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={filters.search} placeholder="Cari serial, kode atau nama pelanggan..." extra={{ signal: filters.signal || undefined }} />
                    <Select className="sm:w-48" value={filters.signal} onChange={(event) => applyFilter(event.target.value)} aria-label="Filter sinyal">
                        <option value="">Semua sinyal</option>
                        <option value="good">Baik (≥ -25 dBm)</option>
                        <option value="warning">Waspada (-25 s/d -27)</option>
                        <option value="critical">Kritis (&lt; -27 dBm)</option>
                        <option value="unknown">Belum terbaca</option>
                    </Select>
                </div>
                {onts.data.length === 0 ? (
                    <EmptyState title="Tidak ada ONT" description="Hanya ONT berstatus Terpasang yang dipantau. Kelola perangkat di menu Inventaris ONT & Aset." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Serial number</Th>
                                    <Th>Model</Th>
                                    <Th>Pelanggan</Th>
                                    <Th>RX Power</Th>
                                    <Th>Terakhir terlihat</Th>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {onts.data.map((ont) => (
                                    <tr key={ont.id} className="hover:bg-muted/40">
                                        <Td className="num font-medium">{ont.serial_number}</Td>
                                        <Td className="text-muted-foreground">{[ont.brand, ont.model].filter(Boolean).join(' ') || '-'}</Td>
                                        <Td>
                                            {ont.customer ? (
                                                <>
                                                    <span className="num text-xs text-muted-foreground">{ont.customer.customer_code}</span> {ont.customer.name}
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </Td>
                                        <Td className="num whitespace-nowrap">
                                            {ont.last_rx_power !== null ? (
                                                <span className={rxTone(Number(ont.last_rx_power))}>{Number(ont.last_rx_power).toFixed(2)} dBm</span>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </Td>
                                        <Td className="num text-muted-foreground">{ont.last_seen_at ? formatDate(ont.last_seen_at) : '-'}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="success" size="icon" onClick={() => readRx(ont)} disabled={reading === ont.id} aria-label="Baca RX Power" title="Baca RX Power (GenieACS)">
                                                    <Activity className={reading === ont.id ? 'size-4 animate-pulse' : 'size-4'} />
                                                </Button>
                                                <Button
                                                    variant="info"
                                                    size="icon"
                                                    onClick={() => {
                                                        setWifiOnt(ont);
                                                        wifiForm.reset();
                                                        wifiForm.clearErrors();
                                                    }}
                                                    aria-label="Ubah Wi-Fi"
                                                    title="Ubah SSID / password Wi-Fi"
                                                >
                                                    <Wifi className="size-4" />
                                                </Button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={onts} />
            </Card>

            <Modal
                open={wifiOnt !== null}
                onClose={() => setWifiOnt(null)}
                title="Ubah Wi-Fi Pelanggan"
                description={wifiOnt ? `${wifiOnt.serial_number}${wifiOnt.customer ? ` · ${wifiOnt.customer.name}` : ''} — dikirim via GenieACS (TR-069).` : undefined}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setWifiOnt(null)}>
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
