import { AlertTriangle, Receipt, Router, Ticket, UserCheck, Users } from 'lucide-react';

import { PageHeader } from '@/Components/PageHeader';
import { Card, CardHeader, StatCard } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/Badge';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDate, formatNumber, formatRupiah } from '@/lib/utils';
import { useClientSort } from '@/Hooks/useSort';

type Props = {
    stats: {
        customers_active: number;
        customers_isolated: number;
        customers_inactive: number;
        routers_online: number;
        routers_total: number;
        invoices_unpaid: number;
        outstanding: number;
        vouchers_unused: number;
    };
    routers: { id: number; name: string; host: string; status: string; last_checked_at: string | null }[];
};

export default function Dashboard({ stats, routers }: Props) {
    const sort = useClientSort(routers, { name: (row) => row.name, host: (row) => row.host, status: (row) => row.status, last_checked: (row) => row.last_checked_at });
    return (
        <>
            <PageHeader title="Dashboard" description="Ringkasan operasional jaringan dan pelanggan." />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard tone="green" label="Pelanggan aktif" value={formatNumber(stats.customers_active)} hint={`${formatNumber(stats.customers_inactive)} nonaktif`} icon={<UserCheck className="size-4" />} />
                <StatCard tone="red" label="Pelanggan isolir" value={formatNumber(stats.customers_isolated)} hint="Menunggu pelunasan" icon={<AlertTriangle className="size-4" />} />
                <StatCard tone="blue" label="Router online" value={`${stats.routers_online}/${stats.routers_total}`} hint="Status koneksi API" icon={<Router className="size-4" />} />
                <StatCard tone="amber" label="Tagihan tertunggak" value={formatRupiah(stats.outstanding)} hint={`${formatNumber(stats.invoices_unpaid)} invoice`} icon={<Receipt className="size-4" />} />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader title="Status router" description="Multi-NAS yang terhubung ke sistem." />
                    {routers.length === 0 ? (
                        <EmptyState title="Belum ada router" description="Tambahkan router MikroTik pertama di menu Manajemen Router." />
                    ) : (
                        <TableWrap>
                            <Table>
                                <thead>
                                    <tr>
                                        <SortTh column="name" state={sort}>Router</SortTh>
                                        <SortTh column="host" state={sort}>Host</SortTh>
                                        <SortTh column="status" state={sort}>Status</SortTh>
                                        <SortTh column="last_checked" state={sort}>Terakhir dicek</SortTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sort.rows.map((router) => (
                                        <tr key={router.id}>
                                            <Td className="font-medium">{router.name}</Td>
                                            <Td className="num text-muted-foreground">{router.host}</Td>
                                            <Td>
                                                <StatusBadge status={router.status} />
                                            </Td>
                                            <Td className="text-muted-foreground">{formatDate(router.last_checked_at)}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </TableWrap>
                    )}
                </Card>
                <div className="space-y-4">
                    <StatCard tone="violet" label="Voucher belum dipakai" value={formatNumber(stats.vouchers_unused)} icon={<Ticket className="size-4" />} />
                    <StatCard
                        tone="blue"
                        label="Total pelanggan"
                        value={formatNumber(stats.customers_active + stats.customers_isolated + stats.customers_inactive)}
                        icon={<Users className="size-4" />}
                    />
                </div>
            </div>
        </>
    );
}
