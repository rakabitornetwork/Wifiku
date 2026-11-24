import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * Indikator "Live": titik hijau berdenyut selama browser terhubung ke internet.
 * Berubah merah dan "Offline" saat koneksi browser terputus, sehingga pengguna tahu aksi tidak akan sampai ke server.
 */
export function LiveStatus() {
    const [online, setOnline] = useState(true);

    useEffect(() => {
        const update = () => setOnline(navigator.onLine);

        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);

        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-semibold',
                online ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
            )}
            data-tip={online ? 'Status: Live — terhubung ke server' : 'Status: Offline — koneksi internet terputus'}
            role="status"
        >
            <span className="relative flex size-2">
                {online && <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70" />}
                <span className={cn('relative inline-flex size-2 rounded-full', online ? 'bg-emerald-500' : 'bg-red-500')} />
            </span>
            <span className="hidden sm:inline">{online ? 'Live' : 'Offline'}</span>
            <span className="sr-only sm:hidden">{online ? 'Live' : 'Offline'}</span>
        </span>
    );
}
