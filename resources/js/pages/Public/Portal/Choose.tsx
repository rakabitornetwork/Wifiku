import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PortalShell, panel } from '@/Components/PortalShell';
import { Card } from '@/Components/ui/Card';
import { cn } from '@/lib/utils';

type Account = { id: number; customer_code: string; name: string; package: string | null; address: string | null; status: string };

const label = { active: 'Aktif', isolated: 'Diisolir', inactive: 'Nonaktif' } as const;
const dot = { active: 'bg-emerald-500', isolated: 'bg-rose-500', inactive: 'bg-slate-400' } as const;

export default function Choose({ company, accounts }: { company: { name: string; logo: string | null }; accounts: Account[] }) {
    const [busy, setBusy] = useState<number | null>(null);

    return (
        <PortalShell
            company={company}
            loggedIn
            hero={
                <div className="mx-auto max-w-lg text-center">
                    <h1 className="text-3xl font-semibold tracking-tight">Pilih sambungan</h1>
                    <p className="mt-2 text-sm text-indigo-100/80">Nomor WhatsApp ini terdaftar pada lebih dari satu sambungan internet.</p>
                </div>
            }
        >
            <Head title="Pilih Sambungan" />
            <div className="mx-auto w-full max-w-lg px-4 pb-4">
                <ul className="space-y-3">
                    {accounts.map((account) => {
                        const status = account.status as keyof typeof label;

                        return (
                            <li key={account.id} className="row-in">
                                <button
                                    type="button"
                                    disabled={busy !== null}
                                    onClick={() => router.post('/pelanggan/pilih', { customer_id: account.id }, { onStart: () => setBusy(account.id), onFinish: () => setBusy(null) })}
                                    className="w-full text-left focus-visible:outline-none"
                                >
                                    <Card className={cn('p-5 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg', panel)}>
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
                                    </Card>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </PortalShell>
    );
}
