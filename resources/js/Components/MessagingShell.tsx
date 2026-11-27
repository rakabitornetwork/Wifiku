import { Link } from '@inertiajs/react';
import { Bell, Bot, LayoutTemplate, MessageSquareText } from 'lucide-react';
import { type ReactNode } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { cn } from '@/lib/utils';

const tabs = [
    { key: 'settings', label: 'Pengaturan & Webhook', href: '/settings/notifications', icon: Bell },
    { key: 'templates', label: 'Template Pesan', href: '/settings/message-templates', icon: LayoutTemplate },
    { key: 'bot', label: 'Balasan Bot', href: '/settings/bot-replies', icon: Bot },
    { key: 'history', label: 'Riwayat Pesan', href: '/pesan', icon: MessageSquareText },
] as const;

export type MessagingTab = (typeof tabs)[number]['key'];

/** Kerangka bersama tiga bagian pesan ke pelanggan: pengaturan saluran, template, dan riwayat. */
export function MessagingShell({ active, description, children }: { active: MessagingTab; description: string; children: ReactNode }) {
    const current = tabs.find((tab) => tab.key === active)!;

    return (
        <>
            <PageHeader title={`Pesan & Notifikasi · ${current.label}`} description={description} />
            <nav aria-label="Bagian pesan" className="mb-6 flex gap-1 overflow-x-auto rounded-xl border bg-card p-1">
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        prefetch
                        className={cn(
                            'flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            tab.key === active ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        <tab.icon className="size-4" /> {tab.label}
                    </Link>
                ))}
            </nav>
            {children}
        </>
    );
}
