import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu, Moon, PanelLeftClose, PanelLeftOpen, Sun, X } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

import { BrandLogo, useBrand } from '@/Components/BrandLogo';
import { LiveStatus } from '@/Components/LiveStatus';
import { Toaster } from '@/Components/Toaster';
import { Avatar } from '@/Components/ui/Avatar';
import { Button } from '@/Components/ui/Button';
import { PageSkeleton } from '@/Components/ui/Skeleton';
import { useTheme } from '@/Hooks/useTheme';
import { cn } from '@/lib/utils';
import { type NavItem, navigation } from '@/lib/nav';

const STORAGE_KEY = 'sidebar-collapsed';

function readCollapsed(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function NavLink({ item, collapsed, active, onNavigate }: { item: NavItem; collapsed: boolean; active: boolean; onNavigate: () => void }) {
    const Icon = item.icon;
    const base = 'group relative flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition';

    if (!item.href) {
        return (
            <div className={cn(base, 'cursor-not-allowed text-slate-400 dark:text-slate-500')} title={`${item.label} (segera hadir)`}>
                <Icon className="size-[18px] shrink-0" />
                {!collapsed && (
                    <>
                        <span className="truncate">{item.label}</span>
                        <span className="ml-auto rounded bg-slate-900/[0.06] px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-white/10 dark:text-slate-300">Segera</span>
                    </>
                )}
            </div>
        );
    }

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            title={collapsed ? item.label : undefined}
            className={cn(
                base,
                active
                    ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/30 before:absolute before:inset-y-2 before:left-0 before:w-[3px] before:rounded-full before:bg-white/70 dark:bg-none dark:bg-white/[0.1] dark:shadow-none dark:before:bg-indigo-400'
                    : 'text-slate-700 hover:bg-white/70 hover:text-indigo-700 hover:shadow-sm dark:text-slate-300 dark:hover:bg-white/[0.06] dark:hover:text-white dark:hover:shadow-none',
            )}
        >
            <Icon className={cn('size-[18px] shrink-0 transition-colors', !active && 'text-indigo-500 group-hover:text-indigo-600 dark:text-inherit dark:group-hover:text-inherit')} />
            {!collapsed && <span className="truncate">{item.label}</span>}
        </Link>
    );
}

export function AppLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const brand = useBrand();
    const permissions = user?.permissions ?? [];
    const { theme, toggleTheme } = useTheme();

    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [navigating, setNavigating] = useState(false);

    useEffect(() => setCollapsed(readCollapsed()), []);

    useEffect(() => {
        let timer: number | undefined;

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.method === 'get' && visit.preserveState !== true) {
                timer = window.setTimeout(() => setNavigating(true), 150);
            }
        });
        const offFinish = router.on('finish', () => {
            window.clearTimeout(timer);
            setNavigating(false);
        });

        return () => {
            window.clearTimeout(timer);
            offStart();
            offFinish();
        };
    }, []);

    const toggleCollapsed = () => {
        setCollapsed((current) => {
            try {
                localStorage.setItem(STORAGE_KEY, current ? '0' : '1');
            } catch {
                // preferensi sidebar tidak tersimpan
            }

            return !current;
        });
    };

    const matches = (url: string, match: string) => url === match || url.startsWith(match + '/') || url.startsWith(match + '?');
    const isActive = (item: NavItem) => !(item.exclude ?? []).some((path) => matches(url, path)) && [item.match, ...(item.alsoMatch ?? [])].some((match) => !!match && matches(url, match));

    const sidebar = (compact: boolean) => (
        <div className="flex h-full flex-col">
            <div className={cn('flex h-16 shrink-0 items-center gap-2.5 border-b border-sidebar-border px-4 text-slate-900 dark:text-white', compact && 'justify-center px-0')}>
                <BrandLogo size="sm" square={compact} className="shadow-lg shadow-indigo-500/30" />
                {!compact && (
                    <div className="leading-tight">
                        <div className="text-sm font-semibold">{brand.name}</div>
                        <div className="text-[11px] text-indigo-700/70 dark:text-slate-400">Manajemen ISP</div>
                    </div>
                )}
            </div>
            <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                {navigation.map((group) => {
                    const items = group.items.filter((item) => !item.permission || permissions.includes(item.permission));

                    if (items.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group.title}>
                            {!compact && (
                                <div className="mb-2 px-3 text-xs font-semibold text-indigo-700/75 dark:text-slate-400">{group.title}</div>
                            )}
                            {compact && <div className="mx-2 mb-1.5 border-t border-sidebar-border" />}
                            <div className="space-y-0.5">
                                {items.map((item) => (
                                    <NavLink key={item.label} item={item} collapsed={compact} active={isActive(item)} onNavigate={() => setMobileOpen(false)} />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </nav>
        </div>
    );

    return (
        <div className="min-h-screen">
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 hidden border-r border-sidebar-border bg-sidebar-gradient transition-[width] duration-200 lg:block',
                    collapsed ? 'w-[68px]' : 'w-64',
                )}
            >
                {sidebar(collapsed)}
            </aside>

            {mobileOpen && <div className="fixed inset-0 z-40 animate-fade-in bg-black/50 lg:hidden" onClick={() => setMobileOpen(false)} />}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 w-72 border-r border-sidebar-border bg-sidebar-gradient shadow-xl transition-transform duration-200 lg:hidden',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <Button variant="ghost" size="icon" className="absolute top-3.5 right-3 text-slate-300 hover:bg-white/10 hover:text-white" onClick={() => setMobileOpen(false)} aria-label="Tutup menu">
                    <X className="size-4" />
                </Button>
                {sidebar(false)}
            </aside>

            <div className={cn('flex min-h-screen flex-col transition-[padding] duration-200', collapsed ? 'lg:pl-[68px]' : 'lg:pl-64')}>
                <header className="glass bg-navbar-gradient sticky top-0 z-20 flex h-16 items-center gap-3 border-b px-4 shadow-[0_10px_28px_-22px_rgba(79,70,229,0.55)] after:pointer-events-none after:absolute after:inset-x-0 after:bottom-[-1px] after:h-px after:bg-gradient-to-r after:from-transparent after:via-primary/60 after:to-transparent sm:px-6">
                    <Button variant="ghost" size="icon" className="rounded-xl border border-border/70 bg-card/60 shadow-xs hover:bg-card lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Buka menu">
                        <Menu className="size-5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="hidden rounded-xl border border-border/70 bg-card/60 shadow-xs hover:bg-card lg:inline-flex" onClick={toggleCollapsed} aria-label="Ciutkan sidebar">
                        {collapsed ? <PanelLeftOpen className="size-[18px]" /> : <PanelLeftClose className="size-[18px]" />}
                    </Button>
                    <div className="ml-auto flex items-center gap-2.5">
                        <LiveStatus />
                        <Button variant="ghost" size="icon" className="rounded-full border border-border/70 bg-card/60 shadow-xs hover:bg-card" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px] text-amber-400" /> : <Moon className="size-[18px] text-indigo-500" />}
                        </Button>
                        <div className="mx-0.5 hidden h-7 w-px bg-gradient-to-b from-transparent via-border to-transparent sm:block" />
                        <Link
                            href="/profil"
                            className="flex items-center gap-2.5 rounded-full border border-border/70 bg-card/60 py-1 pr-1 pl-1 shadow-xs transition hover:border-primary/40 hover:bg-card sm:pr-4"
                            title="Akun saya"
                            aria-label="Akun saya"
                        >
                            <span className="rounded-full ring-2 ring-primary/30 ring-offset-2 ring-offset-card">
                                <Avatar name={user?.name ?? ''} url={user?.avatar_url} />
                            </span>
                            <span className="hidden text-left leading-tight sm:block">
                                <span className="block text-sm font-semibold">{user?.name}</span>
                                <span className="block text-[11px] font-medium text-primary">{user?.roles.join(', ')}</span>
                            </span>
                        </Link>
                        <Button
                            size="sm"
                            className="h-9 gap-1.5 rounded-full border-0 bg-gradient-to-br from-rose-500 to-red-700 px-3.5 font-semibold text-white shadow-md shadow-red-500/25 hover:from-rose-600 hover:to-red-800 hover:opacity-100"
                            onClick={() => router.post('/logout')}
                            aria-label="Keluar dari akun"
                        >
                            <LogOut className="size-4" />
                            Keluar
                        </Button>
                    </div>
                </header>
                <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
                    {navigating ? <PageSkeleton /> : <div className="animate-fade-in">{children}</div>}
                </main>

                <footer className="sticky bottom-0 z-10 bg-background/80 backdrop-blur-md">
                    <p className="px-4 py-3 text-center text-xs text-muted-foreground">
                        © {new Date().getFullYear()} <span className="font-semibold text-foreground">{brand.name}</span> · Manajemen ISP
                    </p>
                </footer>
            </div>
            <Toaster />
        </div>
    );
}
