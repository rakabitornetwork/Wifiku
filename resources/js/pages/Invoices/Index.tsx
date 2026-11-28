import { router, useForm, usePage } from "@inertiajs/react";
import { Banknote, ExternalLink, RefreshCw } from "lucide-react";
import { type FormEvent, useState } from "react";

import { PageHeader } from "@/Components/PageHeader";
import { SearchInput } from "@/Components/SearchInput";
import { Badge, StatusBadge } from "@/Components/ui/Badge";
import { Button, buttonClass } from "@/Components/ui/Button";
import { Card } from "@/Components/ui/Card";
import { Field, Input, Select } from "@/Components/ui/Field";
import { Modal } from "@/Components/ui/Modal";
import { Pagination } from "@/Components/ui/Pagination";
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from "@/Components/ui/Table";
import { formatDate, formatRupiah } from "@/lib/utils";
import type { Paginated } from "@/types";
import { useServerSort } from "@/Hooks/useSort";

type InvoiceRow = {
    id: number;
    invoice_number: string;
    period_start: string;
    period_end: string;
    due_date: string;
    is_prorated: boolean;
    total: number;
    amount_paid: number;
    status: string;
    customer: { id: number; customer_code: string; name: string } | null;
    vpn_account: { id: number; name: string; username: string; portal_token: string } | null;
};

type Filters = { search: string; status: string };

export default function InvoicesIndex({
    invoices,
    filters,
}: {
    invoices: Paginated<InvoiceRow>;
    filters: Filters;
}) {
    const sort = useServerSort();
    const { props } = usePage();
    const canCollect =
        props.auth?.user.permissions.includes("payments.collect") ?? false;
    const [paying, setPaying] = useState<InvoiceRow | null>(null);
    const form = useForm({ amount: "" });

    const applyFilter = (status: string) =>
        router.get(
            "/invoices",
            {
                status: status || undefined,
                search: filters.search || undefined,
            },
            { preserveState: true, replace: true },
        );

    const openPayment = (invoice: InvoiceRow) => {
        setPaying(invoice);
        form.setData("amount", String(invoice.total - invoice.amount_paid));
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (paying) {
            form.post(`/invoices/${paying.id}/payments`, {
                preserveScroll: true,
                onSuccess: () => setPaying(null),
            });
        }
    };

    const isOverdue = (invoice: InvoiceRow) =>
        (invoice.status === "unpaid" || invoice.status === "partial") &&
        new Date(invoice.due_date) < new Date(new Date().toDateString());

    return (
        <>
            <PageHeader
                title="Tagihan & Invoice"
                description="Invoice terbit otomatis setiap hari pukul 00:10 sesuai tanggal tagihan pelanggan, termasuk prorata bulan pertama."
                actions={
                    canCollect && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    "/invoices/generate",
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw className="size-4" /> Generate sekarang
                        </Button>
                    )
                }
            />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput
                        value={filters.search}
                        placeholder="Cari no. invoice, nama, atau kode pelanggan..."
                        extra={{ status: filters.status || undefined }}
                    />
                    <Select
                        className="sm:w-44"
                        value={filters.status}
                        onChange={(event) => applyFilter(event.target.value)}
                        aria-label="Filter status"
                    >
                        <option value="">Semua status</option>
                        <option value="unpaid">Belum bayar</option>
                        <option value="partial">Sebagian</option>
                        <option value="paid">Lunas</option>
                        <option value="cancelled">Batal</option>
                    </Select>
                </div>
                {invoices.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada invoice"
                        description="Invoice muncul setelah generator harian berjalan untuk pelanggan aktif."
                    />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="invoice_number" state={sort}>No. invoice</SortTh>
                                    <SortTh column="customer" state={sort}>Pelanggan</SortTh>
                                    <SortTh column="period" state={sort}>Periode</SortTh>
                                    <SortTh column="due_date" state={sort}>Jatuh tempo</SortTh>
                                    <SortTh column="total" state={sort} className="text-right">Total</SortTh>
                                    <SortTh column="remaining" state={sort} className="text-right">Sisa</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.map((invoice) => (
                                    <tr
                                        key={invoice.id}
                                        className="hover:bg-muted/40"
                                    >
                                        <Td className="num font-medium whitespace-nowrap">
                                            {invoice.invoice_number}
                                            {invoice.is_prorated && (
                                                <Badge
                                                    tone="blue"
                                                    className="ml-2"
                                                >
                                                    Prorata
                                                </Badge>
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {invoice.customer ? (
                                                <>
                                                    <span className="num text-xs text-muted-foreground">
                                                        {invoice.customer.customer_code}
                                                    </span>{" "}
                                                    {invoice.customer.name}
                                                </>
                                            ) : (
                                                <>
                                                    <Badge tone="blue" className="mr-1.5">
                                                        VPN
                                                    </Badge>
                                                    {invoice.vpn_account?.name}
                                                    <span className="num ml-1.5 text-xs text-muted-foreground">
                                                        {invoice.vpn_account?.username}
                                                    </span>
                                                </>
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">
                                            {formatDate(invoice.period_start)} –{" "}
                                            {formatDate(invoice.period_end)}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {formatDate(invoice.due_date)}
                                            {isOverdue(invoice) && (
                                                <Badge
                                                    tone="red"
                                                    className="ml-2"
                                                >
                                                    Lewat
                                                </Badge>
                                            )}
                                        </Td>
                                        <Td className="num text-right whitespace-nowrap">
                                            {formatRupiah(invoice.total)}
                                        </Td>
                                        <Td className="num text-right font-medium whitespace-nowrap">
                                            {formatRupiah(
                                                invoice.total -
                                                    invoice.amount_paid,
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <StatusBadge
                                                status={invoice.status}
                                            />
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <div className="flex justify-end gap-1">
                                                {canCollect &&
                                                    (invoice.status ===
                                                        "unpaid" ||
                                                        invoice.status ===
                                                            "partial") && (
                                                        <Button
                                                            variant="success"
                                                            size="sm"
                                                            onClick={() =>
                                                                openPayment(
                                                                    invoice,
                                                                )
                                                            }
                                                        >
                                                            <Banknote className="size-3.5" />{" "}
                                                            Bayar tunai
                                                        </Button>
                                                    )}
                                                <a
                                                    href={invoice.customer ? `/isolir/${invoice.customer.customer_code}` : `/layanan-vpn/${invoice.vpn_account?.portal_token}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className={buttonClass(
                                                        "info",
                                                        "icon",
                                                    )}
                                                    aria-label="Buka halaman pembayaran pelanggan"
                                                    title="Halaman pembayaran pelanggan"
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={invoices} />
            </Card>

            <Modal
                open={paying !== null}
                onClose={() => setPaying(null)}
                title="Pembayaran Tunai"
                description={
                    paying
                        ? `${paying.invoice_number} · ${paying.customer?.name ?? paying.vpn_account?.name}`
                        : undefined
                }
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setPaying(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="cash-form"
                            disabled={form.processing}
                        >
                            {form.processing
                                ? "Menyimpan..."
                                : "Catat pembayaran"}
                        </Button>
                    </>
                }
            >
                {paying && (
                    <form
                        id="cash-form"
                        onSubmit={submit}
                        className="space-y-4"
                    >
                        <div className="num grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3 text-sm">
                            <div>
                                <div className="text-xs text-muted-foreground">
                                    Total
                                </div>
                                {formatRupiah(paying.total)}
                            </div>
                            <div>
                                <div className="text-xs text-muted-foreground">
                                    Sisa tagihan
                                </div>
                                <span className="font-semibold">
                                    {formatRupiah(
                                        paying.total - paying.amount_paid,
                                    )}
                                </span>
                            </div>
                        </div>
                        <Field
                            label="Nominal diterima (Rp)"
                            error={form.errors.amount}
                            hint="Boleh sebagian; layanan terisolir dibuka otomatis bila tidak ada tunggakan lewat tempo."
                        >
                            <Input
                                type="number"
                                min={1}
                                max={paying.total - paying.amount_paid}
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData("amount", event.target.value)
                                }
                                autoFocus
                                required
                            />
                        </Field>
                    </form>
                )}
            </Modal>
        </>
    );
}
