import { router } from '@inertiajs/react';

import { PageHeader } from '@/Components/PageHeader';
import { Card, StatCard } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Field';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatNumber, formatRupiah } from '@/lib/utils';
import { useClientSort } from '@/Hooks/useSort';
import { Ticket, Wallet } from 'lucide-react';

type Props = {
    month: string;
    report: { total_count: number; total_revenue: number; rows: { profile: string; router: string; reseller: string | null; count: number; revenue: number }[] };
};

export default function VoucherSales({ month, report }: Props) {
    const sort = useClientSort(report.rows, { profile: (row) => row.profile, router: (row) => row.router, reseller: (row) => row.reseller, count: (row) => row.count, revenue: (row) => row.revenue });
    return (
        <>
            <PageHeader
                title="Penjualan Voucher"
                description="Voucher dihitung terjual saat login pertama, per profil dan reseller."
                actions={<Input type="month" className="w-44" value={month} onChange={(event) => event.target.value && router.get('/reports/voucher-sales', { month: event.target.value }, { preserveState: true })} aria-label="Bulan" />}
            />

            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                <StatCard tone="blue" label="Voucher terjual" value={formatNumber(report.total_count)} icon={<Ticket className="size-4" />} />
                <StatCard tone="green" label="Pendapatan voucher" value={formatRupiah(report.total_revenue)} icon={<Wallet className="size-4" />} />
            </div>

            <Card>
                {report.rows.length === 0 ? (
                    <EmptyState title="Belum ada voucher terjual pada bulan ini" />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="profile" state={sort}>Profil</SortTh>
                                    <SortTh column="router" state={sort}>Router</SortTh>
                                    <SortTh column="reseller" state={sort}>Reseller</SortTh>
                                    <SortTh column="count" state={sort} className="text-right">Terjual</SortTh>
                                    <SortTh column="revenue" state={sort} className="text-right">Pendapatan</SortTh>
                                </tr>
                            </thead>
                            <tbody>
                                {sort.rows.map((row, index) => (
                                    <tr key={index} className="hover:bg-muted/40">
                                        <Td className="font-medium">{row.profile}</Td>
                                        <Td>{row.router}</Td>
                                        <Td>{row.reseller ?? <span className="text-muted-foreground">Langsung</span>}</Td>
                                        <Td className="num text-right">{formatNumber(row.count)}</Td>
                                        <Td className="num text-right font-medium">{formatRupiah(row.revenue)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
            </Card>
        </>
    );
}
