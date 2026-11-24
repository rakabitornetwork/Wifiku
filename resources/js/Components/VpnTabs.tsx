import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';

const tabs = [
    { label: 'Akun & Server', href: '/vpn', match: (url: string) => url === '/vpn' || url.startsWith('/vpn?') },
    { label: 'Member', href: '/vpn/members', match: (url: string) => url === '/vpn/members' || url.startsWith('/vpn/members?') },
];

export function VpnTabs() {
    const { url } = usePage();

    return (
        <div className="mb-5 flex gap-1 border-b">
            {tabs.map((tab) => (
                <Link
                    key={tab.href}
                    href={tab.href}
                    className={cn('-mb-px border-b-2 px-4 py-2 text-sm font-medium transition', tab.match(url) ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground')}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
