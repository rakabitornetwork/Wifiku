import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';

type Props = {
    url: string;
    label: string;
    title: string;
    stats: Record<string, number>;
    disabled?: boolean;
};

/** Hapus voucher dari router dan database; secara bawaan hanya yang belum dipakai. */
export function DeleteVouchersButton({ url, label, title, stats, disabled }: Props) {
    const [open, setOpen] = useState(false);
    const [includeUsed, setIncludeUsed] = useState(false);
    const [processing, setProcessing] = useState(false);

    const unused = (stats.unused ?? 0) + (stats.disabled ?? 0);
    const used = (stats.active ?? 0) + (stats.expired ?? 0);
    const count = includeUsed ? unused + used : unused;

    const submit = () =>
        router.delete(url, {
            data: { include_used: includeUsed },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });

    return (
        <>
            <Button
                variant="danger"
                disabled={disabled}
                onClick={() => {
                    setIncludeUsed(false);
                    setOpen(true);
                }}
            >
                <Trash2 className="size-4" /> {label}
            </Button>
            <Modal
                open={open}
                title={title}
                onClose={() => setOpen(false)}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button variant="danger" onClick={submit} disabled={processing || count === 0}>
                            {processing ? 'Menghapus...' : `Hapus ${count} voucher`}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-3 text-sm">
                    <p className="text-muted-foreground">
                        Voucher dihapus dari RouterOS (hotspot user beserta sesi aktifnya) lalu dari database, lewat antrean. Batch yang menjadi kosong ikut dihapus. Tindakan ini tidak bisa dibatalkan.
                    </p>
                    <dl className="num grid grid-cols-[10rem_1fr] gap-y-1">
                        <dt className="text-muted-foreground">Belum dipakai</dt>
                        <dd className="font-medium">{unused}</dd>
                        <dt className="text-muted-foreground">Sudah dipakai</dt>
                        <dd className="font-medium">{used}</dd>
                    </dl>
                    {used > 0 && (
                        <label className="flex items-start gap-2 rounded-md border border-destructive/40 p-3">
                            <input type="checkbox" checked={includeUsed} onChange={(event) => setIncludeUsed(event.target.checked)} className="mt-0.5 size-4 accent-[var(--destructive)]" />
                            <span>
                                Hapus juga <span className="font-medium">{used} voucher yang sudah dipakai</span>. Pelanggan yang sedang online akan terputus, dan penjualannya hilang dari Laporan Penjualan Voucher.
                            </span>
                        </label>
                    )}
                </div>
            </Modal>
        </>
    );
}
