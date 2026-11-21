import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';

const tabs = [
    { label: 'Batch Voucher', href: '/hotspot/batches' },
    { label: 'Profil Hotspot', href: '/hotspot/profiles' },
    { label: 'Reseller', href: '/hotspot/resellers' },
];

export function HotspotTabs() {
    const { url } = usePage();

    return (
        <div className="mb-5 flex gap-1 border-b">
            {tabs.map((tab) => {
                const active = url === tab.href || url.startsWith(tab.href + '/') || url.startsWith(tab.href + '?');

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        className={cn(
                            '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition',
                            active ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </div>
    );
}
