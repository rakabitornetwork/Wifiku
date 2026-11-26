import { type LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

export type IconTone = 'indigo' | 'emerald' | 'amber' | 'sky' | 'violet' | 'rose' | 'cyan' | 'slate';

const tones: Record<IconTone, string> = {
    indigo: 'bg-indigo-500/12 text-indigo-600 ring-indigo-500/20 dark:text-indigo-300',
    emerald: 'bg-emerald-500/12 text-emerald-600 ring-emerald-500/20 dark:text-emerald-300',
    amber: 'bg-amber-500/14 text-amber-600 ring-amber-500/25 dark:text-amber-300',
    sky: 'bg-sky-500/12 text-sky-600 ring-sky-500/20 dark:text-sky-300',
    violet: 'bg-violet-500/12 text-violet-600 ring-violet-500/20 dark:text-violet-300',
    rose: 'bg-rose-500/12 text-rose-600 ring-rose-500/20 dark:text-rose-300',
    cyan: 'bg-cyan-500/12 text-cyan-600 ring-cyan-500/20 dark:text-cyan-300',
    slate: 'bg-slate-500/12 text-slate-600 ring-slate-500/20 dark:text-slate-300',
};

/** Ikon Lucide di dalam ubin berwarna lembut; dekoratif, jadi disembunyikan dari pembaca layar. */
export function IconBadge({ icon: Icon, tone = 'indigo', size = 'md', className }: { icon: LucideIcon; tone?: IconTone; size?: 'sm' | 'md' | 'lg'; className?: string }) {
    return (
        <span aria-hidden="true" className={cn('inline-flex shrink-0 items-center justify-center rounded-xl ring-1 ring-inset', tones[tone], size === 'sm' ? 'size-7 rounded-lg' : size === 'lg' ? 'size-12 rounded-2xl' : 'size-9', className)}>
            <Icon className={size === 'sm' ? 'size-3.5' : size === 'lg' ? 'size-6' : 'size-[18px]'} />
        </span>
    );
}
