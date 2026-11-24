import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu, Moon, PanelLeftClose, PanelLeftOpen, Sun, Wifi, X } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';

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
    const base = 'group flex h-9 items-center gap-3 rounded-lg px-2.5 text-sm font-medium transition';

    if (!item.href) {
        return (
            <div className={cn(base, 'cursor-not-allowed text-muted-foreground/50')} title={`${item.label} (segera hadir)`}>
                <Icon className="size-[18px] shrink-0" />
                {!collapsed && (
                    <>
                        <span className="truncate">{item.label}</span>
                        <span className="ml-auto rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium">Segera</span>
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
            className={cn(base, active ? 'bg-accent text-accent-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground')}
        >
            <Icon className="size-[18px] shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
        </Link>
    );
}

export function AppLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const user = props.auth?.user;
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

    const isActive = (item: NavItem) => !!item.match && (url === item.match || url.startsWith(item.match + '/') || url.startsWith(item.match + '?'));

    const sidebar = (compact: boolean) => (
        <div className="flex h-full flex-col">
            <div className={cn('flex h-14 shrink-0 items-center gap-2.5 border-b border-sidebar-border px-4', compact && 'justify-center px-0')}>
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                    <Wifi className="size-4" />
                </div>
                {!compact && (
                    <div className="leading-tight">
                        <div className="text-sm font-semibold">{props.appName}</div>
                        <div className="text-[11px] text-muted-foreground">Manajemen ISP</div>
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
                                <div className="mb-1.5 px-2.5 text-[11px] font-semibold tracking-wide text-muted-foreground/70 uppercase">{group.title}</div>
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
                    'fixed inset-y-0 left-0 z-30 hidden border-r border-sidebar-border bg-sidebar transition-[width] duration-200 lg:block',
                    collapsed ? 'w-[68px]' : 'w-64',
                )}
            >
                {sidebar(collapsed)}
            </aside>

            {mobileOpen && <div className="fixed inset-0 z-40 animate-fade-in bg-black/50 lg:hidden" onClick={() => setMobileOpen(false)} />}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 w-72 border-r border-sidebar-border bg-sidebar shadow-xl transition-transform duration-200 lg:hidden',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <Button variant="ghost" size="icon" className="absolute top-3 right-3" onClick={() => setMobileOpen(false)} aria-label="Tutup menu">
                    <X className="size-4" />
                </Button>
                {sidebar(false)}
            </aside>

            <div className={cn('transition-[padding] duration-200', collapsed ? 'lg:pl-[68px]' : 'lg:pl-64')}>
                <header className="glass sticky top-0 z-20 flex h-14 items-center gap-2 border-b px-4 sm:px-6">
                    <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Buka menu">
                        <Menu className="size-5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="hidden lg:inline-flex" onClick={toggleCollapsed} aria-label="Ciutkan sidebar">
                        {collapsed ? <PanelLeftOpen className="size-[18px]" /> : <PanelLeftClose className="size-[18px]" />}
                    </Button>
                    <div className="ml-auto flex items-center gap-1.5">
                        <Button variant="ghost" size="icon" onClick={toggleTheme} aria-label="Ganti tema">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </Button>
                        <div className="mx-1 hidden h-6 w-px bg-border sm:block" />
                        <Link href="/profil" className="flex items-center gap-2.5 rounded-lg px-1.5 py-1 transition hover:bg-muted" title="Akun saya" aria-label="Akun saya">
                            <span className="hidden text-right leading-tight sm:block">
                                <span className="block text-sm font-medium">{user?.name}</span>
                                <span className="block text-[11px] text-muted-foreground">{user?.roles.join(', ')}</span>
                            </span>
                            <Avatar name={user?.name ?? ''} url={user?.avatar_url} />
                        </Link>
                        <Button variant="ghost" size="icon" onClick={() => router.post('/logout')} aria-label="Keluar">
                            <LogOut className="size-[18px]" />
                        </Button>
                    </div>
                </header>
                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6">
                    {navigating ? <PageSkeleton /> : <div className="animate-fade-in">{children}</div>}
                </main>
            </div>
            <Toaster />
        </div>
    );
}
