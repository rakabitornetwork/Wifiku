import {
    Activity,
    Boxes,
    Images,
    Sparkles,
    CreditCard,
    LayoutDashboard,
    Map,
    Megaphone,
    Package,
    Receipt,
    Router,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    TrendingUp,
    UserCog,
    Users,
    Wallet,
    Wifi,
    type LucideIcon,
} from 'lucide-react';

export type NavItem = {
    label: string;
    href?: string;
    icon: LucideIcon;
    /** Awalan path yang dianggap aktif. */
    match?: string;
    /** Modul yang dikerjakan pada fase berikutnya. */
    soon?: boolean;
    /** Permission yang dibutuhkan; menu disembunyikan bila tidak dimiliki. */
    permission?: string;
};

export type NavGroup = { title: string; items: NavItem[] };

export const navigation: NavGroup[] = [
    {
        title: 'Main',
        items: [
            { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, match: '/dashboard' },
            { label: 'Peta Topologi & ODP', href: '/topology', icon: Map, match: '/topology', permission: 'topology.view' },
        ],
    },
    {
        title: 'Pelanggan',
        items: [
            { label: 'Data Pelanggan', href: '/customers', icon: Users, match: '/customers', permission: 'customers.view' },
            { label: 'Paket Bandwidth', href: '/packages', icon: Package, match: '/packages', permission: 'packages.manage' },
            { label: 'Voucher Hotspot', href: '/hotspot/batches', icon: Wifi, match: '/hotspot', permission: 'vouchers.manage' },
        ],
    },
    {
        title: 'Network',
        items: [
            { label: 'Manajemen Router', href: '/routers', icon: Router, match: '/routers', permission: 'routers.manage' },
            { label: 'VPN Tunnel', href: '/vpn', icon: ShieldCheck, match: '/vpn', permission: 'vpn.manage' },
            { label: 'Monitoring OLT & TR-069', href: '/monitoring', icon: Activity, match: '/monitoring', permission: 'assets.manage' },
            { label: 'Inventaris ONT & Aset', href: '/assets', icon: Boxes, match: '/assets', permission: 'assets.manage' },
        ],
    },
    {
        title: 'Keuangan & Billing',
        items: [
            { label: 'Tagihan & Invoice', href: '/invoices', icon: Receipt, match: '/invoices', permission: 'invoices.view' },
            { label: 'Penjualan Voucher', href: '/reports/voucher-sales', icon: ShoppingCart, match: '/reports/voucher-sales', permission: 'reports.view' },
            { label: 'Pengeluaran (OPEX)', href: '/expenses', icon: Wallet, match: '/expenses', permission: 'expenses.manage' },
            { label: 'Laporan Laba Rugi', href: '/reports/profit-loss', icon: TrendingUp, match: '/reports/profit-loss', permission: 'reports.view' },
        ],
    },
    {
        title: 'Sistem & Tim',
        items: [
            { label: 'Tiket & Broadcast Gangguan', href: '/outages', icon: Megaphone, match: '/outages', permission: 'outages.manage' },
            { label: 'Teknisi & RBAC', href: '/users', icon: UserCog, match: '/users', permission: 'users.manage' },
            { label: 'Audit Log', href: '/audit-log', icon: ScrollText, match: '/audit-log', permission: 'audit.view' },
            { label: 'Ilustrasi Landing', href: '/ilustrasi-landing', icon: Sparkles, match: '/ilustrasi-landing', permission: 'settings.manage' },
            { label: 'Galeri Landing', href: '/galeri-landing', icon: Images, match: '/galeri-landing', permission: 'settings.manage' },
            { label: 'Payment Gateway', href: '/settings/payment-gateways', icon: CreditCard, match: '/settings/payment-gateways', permission: 'settings.manage' },
            { label: 'Pengaturan', href: '/settings/integrations', icon: Settings, match: '/settings/integrations', permission: 'settings.manage' },
        ],
    },
];

