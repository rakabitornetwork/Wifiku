import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';

import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, StatCard } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Field';
import { Table, Td } from '@/Components/ui/Table';
import { formatRupiah } from '@/lib/utils';
import { Banknote, PiggyBank, TrendingDown, TrendingUp } from 'lucide-react';

type Report = {
    month: string;
    revenue: { billing: number; billing_by_method: Record<string, number>; vouchers: number; total: number };
    expenses: { total: number; by_category: { name: string; type: string; amount: number }[] };
    net_profit: number;
    margin: number | null;
};

type Trend = { month: string; revenue: number; expenses: number; net_profit: number }[];

const methodLabels: Record<string, string> = { cash: 'Tunai (kasir)', gateway: 'Online (QRIS/VA)', transfer: 'Transfer' };

const monthLabel = (month: string) => new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date(`${month}-01T00:00:00`));
const shortMonth = (month: string) => new Intl.DateTimeFormat('id-ID', { month: 'short' }).format(new Date(`${month}-01T00:00:00`));

export default function ProfitLoss({ report, trend }: { report: Report; trend: Trend }) {
    const peak = Math.max(1, ...trend.flatMap((item) => [item.revenue, item.expenses]));
    const profitable = report.net_profit >= 0;

    return (
        <>
            <PageHeader
                title="Laporan Laba Rugi"
                description={`Periode ${monthLabel(report.month)}. Pendapatan dihitung saat uang diterima; voucher saat login pertama.`}
                actions={
                    <>
                        <Input type="month" className="w-44" value={report.month} onChange={(event) => event.target.value && router.get('/reports/profit-loss', { month: event.target.value }, { preserveState: true })} aria-label="Bulan" />
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="size-4" /> Cetak
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Pendapatan" value={formatRupiah(report.revenue.total)} icon={<TrendingUp className="size-4" />} />
                <StatCard label="Pengeluaran (OPEX)" value={formatRupiah(report.expenses.total)} icon={<TrendingDown className="size-4" />} />
                <StatCard label={profitable ? 'Laba bersih' : 'Rugi bersih'} value={formatRupiah(report.net_profit)} hint={report.margin !== null ? `Margin ${report.margin.toLocaleString('id-ID')}%` : undefined} icon={<PiggyBank className="size-4" />} />
                <StatCard label="Tagihan internet" value={formatRupiah(report.revenue.billing)} hint={`Voucher ${formatRupiah(report.revenue.vouchers)}`} icon={<Banknote className="size-4" />} />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Pendapatan" />
                    <Table>
                        <tbody>
                            {Object.entries(report.revenue.billing_by_method).map(([method, amount]) => (
                                <tr key={method}>
                                    <Td>Tagihan — {methodLabels[method] ?? method}</Td>
                                    <Td className="num text-right">{formatRupiah(amount)}</Td>
                                </tr>
                            ))}
                            <tr>
                                <Td>Penjualan voucher hotspot</Td>
                                <Td className="num text-right">{formatRupiah(report.revenue.vouchers)}</Td>
                            </tr>
                            <tr className="bg-muted/50 font-semibold">
                                <Td>Total pendapatan</Td>
                                <Td className="num text-right">{formatRupiah(report.revenue.total)}</Td>
                            </tr>
                        </tbody>
                    </Table>
                </Card>

                <Card>
                    <CardHeader title="Pengeluaran per kategori" />
                    <Table>
                        <tbody>
                            {report.expenses.by_category.length === 0 && (
                                <tr>
                                    <Td className="text-muted-foreground">Belum ada pengeluaran</Td>
                                    <Td />
                                </tr>
                            )}
                            {report.expenses.by_category.map((category) => (
                                <tr key={category.name}>
                                    <Td>{category.name}</Td>
                                    <Td className="num text-right">{formatRupiah(category.amount)}</Td>
                                </tr>
                            ))}
                            <tr className="bg-muted/50 font-semibold">
                                <Td>Total pengeluaran</Td>
                                <Td className="num text-right">{formatRupiah(report.expenses.total)}</Td>
                            </tr>
                            <tr className="font-semibold">
                                <Td className={profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}>{profitable ? 'Laba bersih' : 'Rugi bersih'}</Td>
                                <Td className={`num text-right ${profitable ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}`}>{formatRupiah(report.net_profit)}</Td>
                            </tr>
                        </tbody>
                    </Table>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader title="Tren 6 bulan" description="Pendapatan dan pengeluaran per bulan." />
                <div className="p-5">
                    <div className="flex h-44 items-end gap-3" role="img" aria-label="Grafik batang pendapatan dan pengeluaran 6 bulan terakhir">
                        {trend.map((item) => (
                            <div key={item.month} className="flex flex-1 flex-col items-center gap-2">
                                <div className="flex h-36 w-full items-end justify-center gap-1">
                                    <div className="w-1/2 max-w-8 rounded-t bg-primary" style={{ height: `${(item.revenue / peak) * 100}%`, minHeight: item.revenue > 0 ? 2 : 0 }} title={`Pendapatan ${formatRupiah(item.revenue)}`} />
                                    <div className="w-1/2 max-w-8 rounded-t bg-amber-500" style={{ height: `${(item.expenses / peak) * 100}%`, minHeight: item.expenses > 0 ? 2 : 0 }} title={`Pengeluaran ${formatRupiah(item.expenses)}`} />
                                </div>
                                <span className="text-xs text-muted-foreground">{shortMonth(item.month)}</span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 flex gap-4 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5"><span className="size-2.5 rounded-sm bg-primary" /> Pendapatan</span>
                        <span className="inline-flex items-center gap-1.5"><span className="size-2.5 rounded-sm bg-amber-500" /> Pengeluaran</span>
                    </div>
                    <table className="sr-only">
                        <caption>Tren pendapatan dan pengeluaran</caption>
                        <tbody>
                            {trend.map((item) => (
                                <tr key={item.month}>
                                    <th>{item.month}</th>
                                    <td>{item.revenue}</td>
                                    <td>{item.expenses}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </>
    );
}

