import { createInertiaApp } from '@inertiajs/react';

import { AppLayout } from '@/Layouts/AppLayout';

createInertiaApp({
    layout: (name) => {
        if (name.startsWith('Auth/') || name.startsWith('Public/') || name.startsWith('Print/')) {
            return null;
        }

        return AppLayout;
    },
    progress: { color: '#6366f1' },
});
