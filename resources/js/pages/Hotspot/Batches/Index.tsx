import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { DeleteVouchersButton } from '@/Components/DeleteVouchersButton';
import { HotspotTabs } from '@/Components/HotspotTabs';
import { PageHeader } from '@/Components/PageHeader';
import { StatusBadge } from '@/Components/ui/Badge';
import { buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type BatchRow = {
    id: number;
    batch_code: string;
    quantity: number;
    generated_count: number;
    status: string;
    created_at: string;
    used_count: number;
    router: { name: string };
    server: string | null;
    hotspot_profile: { name: string };
    reseller: { name: string } | null;
};

export default function BatchesIndex({ batches, routers, routerId, routerStats }: { batches: Paginated<BatchRow>; routers: { id: number; name: string }[]; routerId: string; routerStats: Record<string, number> | null }) {
    const sort = useServerSort();
    return (
        <>
            <PageHeader
                title="Voucher Hotspot"
                description="Batch voucher yang sudah dibuat, lengkap dengan progres pemakaian."
                actions={
                    <Link href="/hotspot/batches/create" className={buttonClass()}>
                        <Plus className="size-4" /> Generate Voucher
                    </Link>
                }
            />
            <HotspotTabs />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <Select
                        className="sm:w-48"
                        value={routerId}
                        onChange={(event) => router.get('/hotspot/batches', { router_id: event.target.value || undefined }, { preserveState: true, replace: true })}
                        aria-label="Filter router"
                    >
                        <option value="">Semua router</option>
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                    {routerId && routerStats && (
                        <DeleteVouchersButton
                            url={`/hotspot/routers/${routerId}/vouchers`}
                            label="Hapus voucher router ini"
                            title={`Hapus voucher router ${routers.find((item) => String(item.id) === routerId)?.name ?? ''}?`}
                            stats={routerStats}
                        />
                    )}
                </div>
                {batches.data.length === 0 ? (
                    <EmptyState title={routerId ? 'Tidak ada batch di router ini' : 'Belum ada batch voucher'} description="Generate voucher massal; kodenya dibuat di antrean lalu siap dicetak." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="batch_code" state={sort}>Kode batch</SortTh>
                                    <SortTh column="profile" state={sort}>Profil</SortTh>
                                    <SortTh column="router" state={sort}>Router</SortTh>
                                    <SortTh column="reseller" state={sort}>Reseller</SortTh>
                                    <SortTh column="vouchers" state={sort}>Voucher</SortTh>
                                    <SortTh column="used" state={sort}>Terpakai</SortTh>
                                    <SortTh column="created" state={sort}>Dibuat</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                </tr>
                            </thead>
                            <tbody>
                                {batches.data.map((batch) => (
                                    <tr key={batch.id} className="hover:bg-muted/40">
                                        <Td>
                                            <Link href={`/hotspot/batches/${batch.id}`} className="num font-medium text-primary hover:underline">
                                                {batch.batch_code}
                                            </Link>
                                        </Td>
                                        <Td>{batch.hotspot_profile.name}</Td>
                                        <Td>
                                            {batch.router.name}
                                            <span className="text-muted-foreground"> · {batch.server ?? 'all'}</span>
                                        </Td>
                                        <Td>{batch.reseller?.name ?? <span className="text-muted-foreground">-</span>}</Td>
                                        <Td className="num">
                                            {batch.generated_count}/{batch.quantity}
                                        </Td>
                                        <Td className="num">{batch.used_count}</Td>
                                        <Td className="text-muted-foreground">{formatDate(batch.created_at)}</Td>
                                        <Td>
                                            <StatusBadge status={batch.status} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={batches} />
            </Card>
        </>
    );
}
