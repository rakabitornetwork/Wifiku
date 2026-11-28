import { AlertTriangle, Receipt, Router, Ticket, UserCheck, Users } from 'lucide-react';

import { ActivityWidget, AttentionWidget, CollectionWidget, FinanceWidget, OverdueWidget, RoutersOfflineWidget, UpcomingWidget, VoucherTodayWidget, WeakSignalWidget, type Widgets } from '@/Components/dashboard/Widgets';
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
    widgets: Widgets;
    routers: { id: number; name: string; host: string; status: string; last_checked_at: string | null }[];
};

export default function Dashboard({ stats, widgets, routers }: Props) {
    const sort = useClientSort(routers, { name: (row) => row.name, host: (row) => row.host, status: (row) => row.status, last_checked: (row) => row.last_checked_at });
    return (
        <>
            <PageHeader title="Dashboard" description="Ringkasan operasional jaringan dan pelanggan." />

            <div className="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                <StatCard tone="blue" label="Total pelanggan" value={formatNumber(stats.customers_active + stats.customers_isolated + stats.customers_inactive)} hint={`${formatNumber(stats.customers_inactive)} nonaktif`} icon={<Users className="size-4" />} />
                <StatCard tone="green" label="Pelanggan aktif" value={formatNumber(stats.customers_active)} hint="Layanan berjalan normal" icon={<UserCheck className="size-4" />} />
                <StatCard tone="red" label="Pelanggan isolir" value={formatNumber(stats.customers_isolated)} hint="Menunggu pelunasan" icon={<AlertTriangle className="size-4" />} />
                <StatCard tone="cyan" label="Router online" value={`${stats.routers_online}/${stats.routers_total}`} hint="Status koneksi API" icon={<Router className="size-4" />} />
                <StatCard tone="amber" label="Tagihan tertunggak" value={formatRupiah(stats.outstanding)} hint={`${formatNumber(stats.invoices_unpaid)} invoice`} icon={<Receipt className="size-4" />} />
                <StatCard tone="violet" label="Voucher belum dipakai" value={formatNumber(stats.vouchers_unused)} hint="Stok voucher hotspot" icon={<Ticket className="size-4" />} />
            </div>

            {(widgets.finance || widgets.collection) && (
                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {widgets.finance && <FinanceWidget data={widgets.finance} />}
                    {widgets.collection && <CollectionWidget data={widgets.collection} />}
                </div>
            )}

            {(widgets.routers_offline || widgets.upcoming || widgets.voucher_today) && (
                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {widgets.routers_offline && <RoutersOfflineWidget data={widgets.routers_offline} />}
                    {widgets.upcoming && <UpcomingWidget data={widgets.upcoming} />}
                    {widgets.voucher_today && <VoucherTodayWidget data={widgets.voucher_today} />}
                </div>
            )}

            {(widgets.overdue || widgets.weak_signal || widgets.attention) && (
                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {widgets.overdue && <OverdueWidget data={widgets.overdue} />}
                    {widgets.weak_signal && <WeakSignalWidget data={widgets.weak_signal} />}
                    {widgets.attention && <AttentionWidget items={widgets.attention} />}
                </div>
            )}

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className={widgets.activity ? 'lg:col-span-2' : 'lg:col-span-3'}>
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
                                            <Td className="text-center">
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
                {widgets.activity && <ActivityWidget items={widgets.activity} />}
            </div>
        </>
    );
}
