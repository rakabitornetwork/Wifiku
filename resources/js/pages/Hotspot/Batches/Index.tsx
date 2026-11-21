import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { HotspotTabs } from '@/Components/HotspotTabs';
import { PageHeader } from '@/Components/PageHeader';
import { StatusBadge } from '@/Components/ui/Badge';
import { buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, Th } from '@/Components/ui/Table';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

type BatchRow = {
    id: number;
    batch_code: string;
    quantity: number;
    generated_count: number;
    status: string;
    created_at: string;
    used_count: number;
    router: { name: string };
    hotspot_profile: { name: string };
    reseller: { name: string } | null;
};

export default function BatchesIndex({ batches }: { batches: Paginated<BatchRow> }) {
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
                {batches.data.length === 0 ? (
                    <EmptyState title="Belum ada batch voucher" description="Generate voucher massal; kodenya dibuat di antrean lalu siap dicetak." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Kode batch</Th>
                                    <Th>Profil</Th>
                                    <Th>Router</Th>
                                    <Th>Reseller</Th>
                                    <Th>Voucher</Th>
                                    <Th>Terpakai</Th>
                                    <Th>Dibuat</Th>
                                    <Th>Status</Th>
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
                                        <Td>{batch.router.name}</Td>
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
