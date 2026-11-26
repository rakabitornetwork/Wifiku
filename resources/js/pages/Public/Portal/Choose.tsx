import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { AuthSplitLayout } from '@/Components/AuthSplitLayout';
import { cn } from '@/lib/utils';

type Account = { id: number; customer_code: string; name: string; package: string | null; address: string | null; status: string };

const label = { active: 'Aktif', isolated: 'Diisolir', inactive: 'Nonaktif' } as const;
const dot = { active: 'bg-emerald-500', isolated: 'bg-rose-500', inactive: 'bg-slate-400' } as const;

export default function Choose({ accounts }: { company: { name: string; logo: string | null }; accounts: Account[] }) {
    const [busy, setBusy] = useState<number | null>(null);

    return (
        <AuthSplitLayout
            headline="Internet rumah Anda, dalam satu halaman."
            benefits={['Tagihan dan pembayaran online', 'Status ONU dan kualitas sinyal', 'Ganti nama dan password WiFi']}
            logoutUrl="/pelanggan/keluar"
        >
            <Head title="Pilih Sambungan" />

            <p className="text-xs font-medium text-muted-foreground">{accounts.length} sambungan ditemukan</p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Pilih sambungan</h1>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">Nomor WhatsApp ini terdaftar pada lebih dari satu sambungan internet. Pilih yang ingin Anda buka.</p>

            <ul className="mt-8 space-y-3">
                {accounts.map((account) => {
                    const status = account.status as keyof typeof label;

                    return (
                        <li key={account.id} className="row-in">
                            <button
                                type="button"
                                disabled={busy !== null}
                                onClick={() => router.post('/pelanggan/pilih', { customer_id: account.id }, { onStart: () => setBusy(account.id), onFinish: () => setBusy(null) })}
                                className="w-full rounded-2xl border bg-slate-50 p-4 text-left transition hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-white hover:shadow-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60 dark:bg-background dark:hover:bg-card"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="font-semibold">{account.name}</div>
                                        <div className="num mt-0.5 text-xs text-muted-foreground">
                                            {account.customer_code}
                                            {account.package && ` · ${account.package}`}
                                        </div>
                                        {account.address && <div className="mt-1.5 text-xs text-muted-foreground">{account.address}</div>}
                                    </div>
                                    <span className="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold whitespace-nowrap">
                                        <span className={cn('size-2 rounded-full', dot[status] ?? 'bg-slate-400')} /> {busy === account.id ? 'Membuka...' : (label[status] ?? account.status)}
                                    </span>
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </AuthSplitLayout>
    );
}
