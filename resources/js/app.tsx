import { createInertiaApp } from '@inertiajs/react';

import { AppLayout } from '@/Layouts/AppLayout';
import { installTooltips } from '@/lib/tooltip';

installTooltips();

createInertiaApp({
    layout: (name) => {
        if (name.startsWith('Auth/') || name.startsWith('Public/') || name.startsWith('Print/')) {
            return null;
        }

        return AppLayout;
    },
    // Judul tab: "Judul halaman — Nama perusahaan" (nama dari Pengaturan, ikut berubah tanpa muat ulang).
    title: (title, page) => {
        const name = (page.props as { brand?: { name?: string } }).brand?.name ?? '';

        if (!name) {
            return title;
        }

        return !title || title.toLowerCase().includes(name.toLowerCase()) ? title || name : `${title} — ${name}`;
    },
    progress: { color: '#6366f1' },
});
