import { router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { PageHeader } from '@/Components/PageHeader';
import { SearchInput } from '@/Components/SearchInput';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState } from '@/Components/ui/Table';
import type { Paginated } from '@/types';

type Activity = {
    id: number;
    log_name: string;
    event: string | null;
    description: string;
    causer: string;
    subject: string | null;
    changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> } | null;
    properties: Record<string, unknown> | null;
    created_at: string;
};

type Props = { activities: Paginated<Activity>; logNames: string[]; filters: { search: string; log: string } };

const show = (value: unknown): string => (value === null || value === undefined ? '∅' : typeof value === 'object' ? JSON.stringify(value) : String(value));

const eventTone = (event: string | null) => (event === 'deleted' ? 'red' : event === 'created' ? 'green' : 'slate');

const formatDateTime = (value: string) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));

export default function AuditLogIndex({ activities, logNames, filters }: Props) {
    return (
        <>
            <PageHeader title="Audit Log Aktivitas" description="Rekaman perubahan data, pembayaran tunai, pengaturan, dan penghapusan. Hanya Owner yang dapat melihat." />

            <Card>
                <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center">
                    <SearchInput value={filters.search} placeholder="Cari deskripsi..." extra={{ log: filters.log || undefined }} />
                    <Select
                        className="sm:w-52"
                        value={filters.log}
                        aria-label="Filter jenis"
                        onChange={(event) => router.get('/audit-log', { log: event.target.value || undefined, search: filters.search || undefined }, { preserveState: true, replace: true })}
                    >
                        <option value="">Semua jenis</option>
                        {logNames.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </Select>
                </div>

                {activities.data.length === 0 ? (
                    <EmptyState title="Belum ada aktivitas tercatat" />
                ) : (
                    <ul className="divide-y">
                        {activities.data.map((activity) => {
                            const changed = activity.changes?.attributes ?? {};
                            const old = activity.changes?.old ?? {};
                            const keys = Object.keys(changed);
                            const hasDetail = keys.length > 0 || (activity.properties && Object.keys(activity.properties).length > 0);

                            return (
                                <li key={activity.id} className="row-in">
                                    <details className="group">
                                        <summary className={`flex list-none items-start gap-3 px-4 py-3 ${hasDetail ? 'cursor-pointer hover:bg-muted/40' : ''}`}>
                                            <ChevronRight className={`mt-0.5 size-4 shrink-0 text-muted-foreground transition group-open:rotate-90 ${hasDetail ? '' : 'invisible'}`} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                                    <Badge tone={eventTone(activity.event)}>{activity.log_name}</Badge>
                                                    <span className="font-medium">{activity.description}</span>
                                                </div>
                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {activity.causer} · {formatDateTime(activity.created_at)}
                                                    {activity.subject && <span className="num"> · {activity.subject}</span>}
                                                </div>
                                            </div>
                                        </summary>
                                        {hasDetail && (
                                            <div className="num ml-11 space-y-1 px-4 pb-3 text-xs">
                                                {keys.map((key) => (
                                                    <div key={key}>
                                                        <span className="text-muted-foreground">{key}: </span>
                                                        {key in old && <span className="text-destructive line-through">{show(old[key])}</span>} <span className="text-emerald-600 dark:text-emerald-400">{show(changed[key])}</span>
                                                    </div>
                                                ))}
                                                {activity.properties &&
                                                    Object.entries(activity.properties).map(([key, value]) => (
                                                        <div key={key}>
                                                            <span className="text-muted-foreground">{key}: </span>
                                                            {show(value)}
                                                        </div>
                                                    ))}
                                            </div>
                                        )}
                                    </details>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <Pagination meta={activities} />
            </Card>
        </>
    );
}
