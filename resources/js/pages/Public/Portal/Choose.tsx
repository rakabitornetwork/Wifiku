import { Head, router } from '@inertiajs/react';
import { Home } from 'lucide-react';
import { useState } from 'react';

import { IconBadge } from '@/Components/IconBadge';
import { PortalShell } from '@/Components/PortalShell';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';

type Account = { id: number; customer_code: string; name: string; package: string | null; address: string | null; status: string };

const tone = { active: 'green', isolated: 'red', inactive: 'slate' } as const;
const label = { active: 'Aktif', isolated: 'Diisolir', inactive: 'Nonaktif' } as const;

export default function Choose({ company, accounts }: { company: { name: string; logo: string | null }; accounts: Account[] }) {
    const [busy, setBusy] = useState<number | null>(null);

    return (
        <PortalShell company={company} loggedIn>
            <Head title="Pilih Sambungan" />
            <div className="mx-auto w-full max-w-lg px-4 py-12">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">Pilih sambungan</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Nomor WhatsApp ini terdaftar pada lebih dari satu sambungan internet.</p>
                </div>
                <ul className="space-y-3">
                    {accounts.map((account) => (
                        <li key={account.id}>
                            <button
                                type="button"
                                disabled={busy !== null}
                                onClick={() => router.post('/pelanggan/pilih', { customer_id: account.id }, { onStart: () => setBusy(account.id), onFinish: () => setBusy(null) })}
                                className="w-full text-left transition focus-visible:outline-none"
                            >
                                <Card className="p-4 transition hover:border-primary/50 hover:shadow-md focus-visible:ring-2">
                                    <div className="flex items-start gap-3">
                                        <IconBadge icon={Home} tone={account.status === 'active' ? 'emerald' : 'rose'} />
                                        <div className="min-w-0 flex-1">
                                            <div className="font-medium">{account.name}</div>
                                            <div className="num text-xs text-muted-foreground">
                                                {account.customer_code}
                                                {account.package && ` · ${account.package}`}
                                            </div>
                                            {account.address && <div className="mt-1 text-xs text-muted-foreground">{account.address}</div>}
                                        </div>
                                        <Badge tone={tone[account.status as keyof typeof tone] ?? 'slate'}>{busy === account.id ? 'Membuka...' : (label[account.status as keyof typeof label] ?? account.status)}</Badge>
                                    </div>
                                </Card>
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </PortalShell>
    );
}
