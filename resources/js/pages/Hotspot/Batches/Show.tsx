import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect } from 'react';

import { DeleteVouchersButton } from '@/Components/DeleteVouchersButton';
import { HotspotTabs } from '@/Components/HotspotTabs';
import { PageHeader } from '@/Components/PageHeader';
import { StatusBadge } from '@/Components/ui/Badge';
import { buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate, formatDuration, formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type Props = {
    batch: {
        id: number;
        batch_code: string;
        quantity: number;
        generated_count: number;
        status: string;
        router: { name: string };
        server: string | null;
        hotspot_profile: { name: string; price: number; validity_minutes: number };
        reseller: { name: string } | null;
    };
    stats: Record<string, number>;
    vouchers: Paginated<{ id: number; code: string; password: string; price: number; status: string; first_login_at: string | null; expires_at: string | null }>;
    status: string;
};

const printLayouts = [
    { layout: 'a4', label: 'A4 Grid' },
    { layout: 'thermal80', label: 'Thermal 80mm' },
    { layout: 'thermal58', label: 'Thermal 58mm' },
];

export default function BatchShow({ batch, stats, vouchers, status }: Props) {
    const sort = useServerSort();
    const inProgress = batch.status === 'queued' || batch.status === 'processing';

    useEffect(() => {
        if (!inProgress) {
            return;
        }

        const timer = window.setInterval(() => router.reload({ only: ['batch', 'stats', 'vouchers'] }), 2000);

        return () => window.clearInterval(timer);
    }, [inProgress]);

    const progress = batch.quantity > 0 ? Math.round((batch.generated_count / batch.quantity) * 100) : 0;
    const used = (stats.active ?? 0) + (stats.expired ?? 0);

    return (
        <>
            <PageHeader
                title={`Batch ${batch.batch_code}`}
                description={`${batch.hotspot_profile.name} · ${batch.router.name} (server ${batch.server ?? 'all'}) · ${formatRupiah(batch.hotspot_profile.price)} / ${formatDuration(batch.hotspot_profile.validity_minutes)}${batch.reseller ? ` · Reseller ${batch.reseller.name}` : ''}`}
                actions={
                    <div className="flex gap-2">
                        <DeleteVouchersButton url={`/hotspot/batches/${batch.id}/vouchers`} label="Hapus voucher" title={`Hapus voucher batch ${batch.batch_code}?`} stats={stats} disabled={inProgress} />
                        <Link href="/hotspot/batches" className={buttonClass('outline')}>
                            <ArrowLeft className="size-4" /> Kembali
                        </Link>
                    </div>
                }
            />
            <HotspotTabs />

            <div className="mb-4 grid gap-4 md:grid-cols-3">
                <Card className="p-5 md:col-span-2">
                    <div className="mb-2 flex items-center justify-between text-sm">
                        <span className="flex items-center gap-2 font-medium">
                            Progres generate <StatusBadge status={batch.status} />
                        </span>
                        <span className="num text-muted-foreground">
                            {batch.generated_count}/{batch.quantity}
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                        <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${progress}%` }} />
                    </div>
                    <div className="num mt-4 flex gap-6 text-sm">
                        <span>
                            <span className="text-muted-foreground">Belum dipakai </span>
                            {stats.unused ?? 0}
                        </span>
                        <span>
                            <span className="text-muted-foreground">Terpakai </span>
                            {used}
                        </span>
                    </div>
                </Card>
                <Card className="p-5">
                    <div className="mb-3 text-sm font-medium">Cetak voucher</div>
                    <div className="flex flex-col gap-2">
                        {printLayouts.map((item) => (
                            <a
                                key={item.layout}
                                href={`/hotspot/batches/${batch.id}/print?layout=${item.layout}`}
                                target="_blank"
                                rel="noreferrer"
                                className={buttonClass('outline', 'md', batch.generated_count === 0 ? 'pointer-events-none opacity-50' : '')}
                            >
                                <Printer className="size-4" /> {item.label}
                            </a>
                        ))}
                    </div>
                </Card>
            </div>

            <Card>
                <div className="border-b p-4">
                    <Select
                        className="sm:w-48"
                        value={status}
                        aria-label="Filter status"
                        onChange={(event) => router.get(`/hotspot/batches/${batch.id}`, { status: event.target.value || undefined }, { preserveState: true, replace: true })}
                    >
                        <option value="">Semua status</option>
                        <option value="unused">Belum dipakai</option>
                        <option value="active">Aktif</option>
                        <option value="expired">Kedaluwarsa</option>
                        <option value="disabled">Dinonaktifkan</option>
                    </Select>
                </div>
                {vouchers.data.length === 0 ? (
                    <EmptyState title={inProgress ? 'Voucher sedang dibuat...' : 'Tidak ada voucher'} description={inProgress ? 'Pastikan queue worker berjalan (php artisan queue:work).' : undefined} />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="code" state={sort}>Kode</SortTh>
                                    <SortTh column="price" state={sort} className="text-right">Harga</SortTh>
                                    <SortTh column="first_login" state={sort}>Login pertama</SortTh>
                                    <SortTh column="expires" state={sort}>Kedaluwarsa</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                </tr>
                            </thead>
                            <tbody>
                                {vouchers.data.map((voucher) => (
                                    <tr key={voucher.id} className="hover:bg-muted/40">
                                        <Td className="num font-mono font-medium tracking-wider">{voucher.code}</Td>
                                        <Td className="num text-right">{formatRupiah(voucher.price)}</Td>
                                        <Td className="text-muted-foreground">{formatDate(voucher.first_login_at)}</Td>
                                        <Td className="text-muted-foreground">{formatDate(voucher.expires_at)}</Td>
                                        <Td>
                                            <StatusBadge status={voucher.status} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={vouchers} />
            </Card>
        </>
    );
}
