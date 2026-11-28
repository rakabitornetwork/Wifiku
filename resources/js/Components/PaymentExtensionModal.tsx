import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Field, Input, Textarea } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { formatDate, formatRupiah } from '@/lib/utils';

type Info = {
    current_until: string | null;
    status: string;
    max_days: number;
    max_date: string;
    outstanding: { count: number; amount: number };
    history: { id: number; granted_until: string; previous_until: string | null; days: number; reason: string; granted_by: string | null; created_at: string }[];
};

type Props = { customer: { id: number; name: string } | null; onClose: () => void };

const QUICK_DAYS = [7, 14, 30, 60];

function dateFromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

/** Dialog perpanjangan pembayaran (Janji Bayar) tanpa denda, lengkap dengan riwayat pemberian. */
export function PaymentExtensionModal({ customer, onClose }: Props) {
    const [info, setInfo] = useState<Info | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const form = useForm({ until: '', reason: '' });

    useEffect(() => {
        setInfo(null);
        setLoadError(null);
        form.reset();
        form.clearErrors();

        if (!customer) {
            return;
        }

        const controller = new AbortController();

        fetch(`/customers/${customer.id}/extensions`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat data perpanjangan.');
                }

                setInfo(await response.json());
            })
            .catch((exception: Error) => exception.name !== 'AbortError' && setLoadError(exception.message));

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customer?.id]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (customer) {
            form.post(`/customers/${customer.id}/extensions`, { preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <Modal
            open={customer !== null}
            title={`Perpanjangan pembayaran${customer ? ` · ${customer.name}` : ''}`}
            description="Tunda auto-isolir sampai tanggal yang disepakati, tanpa denda."
            onClose={onClose}
            footer={
                <>
                    <Button variant="close" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" form="extension-form" disabled={form.processing || info === null}>
                        {form.processing ? 'Menyimpan...' : 'Berikan perpanjangan'}
                    </Button>
                </>
            }
        >
            {loadError && <p className="text-sm text-destructive">{loadError}</p>}
            {!info && !loadError && <p className="text-sm text-muted-foreground">Memuat...</p>}
            {info && (
                <form id="extension-form" onSubmit={submit} className="space-y-4">
                    <div className="rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                        {info.outstanding.count > 0 ? (
                            <>
                                Tunggakan: <span className="num font-medium">{formatRupiah(info.outstanding.amount)}</span> dari {info.outstanding.count} invoice. Seluruhnya tetap harus dilunasi.
                            </>
                        ) : (
                            'Tidak ada tunggakan saat ini.'
                        )}
                        {info.current_until && (
                            <div className="mt-1 text-xs text-muted-foreground">
                                Perpanjangan aktif sampai <span className="num">{formatDate(info.current_until)}</span>.
                            </div>
                        )}
                    </div>

                    <Field label="Diperpanjang sampai" error={form.errors.until} hint={`Maksimal ${info.max_days} hari dari hari ini (${formatDate(info.max_date)}). Lebih lama hanya oleh Owner.`}>
                        <Input type="date" value={form.data.until} min={dateFromToday(1)} max={info.max_date} onChange={(event) => form.setData('until', event.target.value)} required />
                    </Field>
                    <div className="flex flex-wrap gap-2">
                        {QUICK_DAYS.filter((days) => days <= info.max_days).map((days) => (
                            <Button key={days} type="button" size="sm" variant="outline" onClick={() => form.setData('until', dateFromToday(days))}>
                                +{days} hari
                            </Button>
                        ))}
                    </div>

                    <Field label="Alasan / kesepakatan" error={form.errors.reason}>
                        <Textarea value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder="Mis. pelanggan menunggu gaji tanggal 5, sepakat bayar 2 bulan sekaligus." required />
                    </Field>

                    {info.history.length > 0 && (
                        <div>
                            <div className="mb-1.5 text-xs font-medium text-foreground/80">Riwayat perpanjangan</div>
                            <ul className="max-h-40 space-y-1.5 overflow-y-auto text-xs">
                                {info.history.map((item) => (
                                    <li key={item.id} className="rounded-md border px-2.5 py-1.5">
                                        <div className="flex justify-between gap-2">
                                            <span className="num font-medium">s.d. {formatDate(item.granted_until)}</span>
                                            <span className="text-muted-foreground">
                                                {formatDate(item.created_at)} · {item.granted_by ?? '-'}
                                            </span>
                                        </div>
                                        <div className="text-muted-foreground">{item.reason}</div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </form>
            )}
        </Modal>
    );
}
