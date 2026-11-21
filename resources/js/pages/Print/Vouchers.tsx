import { Head, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Printer } from 'lucide-react';

import { formatDuration, formatRupiah } from '@/lib/utils';

type Props = {
    batch: { id: number; batch_code: string; profile: { name: string; price: number; validity_minutes: number } };
    company: string;
    loginUrl: string;
    vouchers: { id: number; code: string; password: string }[];
};

type Layout = 'a4' | 'thermal58' | 'thermal80';

const layouts: { value: Layout; label: string }[] = [
    { value: 'a4', label: 'A4 Grid' },
    { value: 'thermal80', label: 'Thermal 80mm' },
    { value: 'thermal58', label: 'Thermal 58mm' },
];

function readLayout(): Layout {
    const value = new URLSearchParams(window.location.search).get('layout');

    return value === 'thermal58' || value === 'thermal80' ? value : 'a4';
}

/** URL auto-login hotspot MikroTik: tempel kode ke halaman /login lewat query string. */
function autoLoginUrl(loginUrl: string, code: string, password: string): string {
    try {
        const url = new URL(loginUrl);
        url.searchParams.set('username', code);
        url.searchParams.set('password', password);

        return url.toString();
    } catch {
        return `${loginUrl}?username=${encodeURIComponent(code)}&password=${encodeURIComponent(password)}`;
    }
}

const pageCss: Record<Layout, string> = {
    a4: '@page { size: A4; margin: 8mm; }',
    thermal80: '@page { size: 80mm auto; margin: 2mm; }',
    thermal58: '@page { size: 58mm auto; margin: 1.5mm; }',
};

export default function PrintVouchers({ batch, company, loginUrl, vouchers }: Props) {
    const layout = readLayout();
    const thermal = layout !== 'a4';
    const qrSize = layout === 'thermal58' ? 84 : layout === 'thermal80' ? 104 : 72;

    const changeLayout = (value: Layout) => router.get(window.location.pathname, { layout: value }, { replace: true });

    return (
        <div className="min-h-screen bg-white text-black">
            <Head title={`Cetak ${batch.batch_code}`} />
            <style>{`${pageCss[layout]} @media print { .no-print { display: none !important; } body { background: #fff !important; } .voucher { break-inside: avoid; } .thermal-card { break-after: page; } }`}</style>

            <div className="no-print sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b bg-white/95 px-4 py-3 backdrop-blur">
                <div className="mr-auto text-sm">
                    <span className="font-semibold">{batch.batch_code}</span>
                    <span className="num text-neutral-500"> · {vouchers.length} voucher</span>
                </div>
                <div className="flex gap-1 rounded-lg bg-neutral-100 p-1">
                    {layouts.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => changeLayout(item.value)}
                            className={`rounded-md px-3 py-1 text-xs font-medium ${layout === item.value ? 'bg-white shadow-sm' : 'text-neutral-500'}`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
                <button type="button" onClick={() => window.print()} className="inline-flex h-9 items-center gap-2 rounded-lg bg-neutral-900 px-4 text-sm font-medium text-white">
                    <Printer className="size-4" /> Cetak
                </button>
            </div>

            <div className={thermal ? `mx-auto p-2 ${layout === 'thermal58' ? 'w-[58mm]' : 'w-[80mm]'}` : 'grid grid-cols-3 gap-2 p-4 print:p-0'}>
                {vouchers.map((voucher) => (
                    <div
                        key={voucher.id}
                        className={`voucher flex items-center gap-2 border border-dashed border-neutral-400 ${thermal ? 'thermal-card mb-2 flex-col p-2 text-center' : 'p-3'}`}
                    >
                        <QRCodeSVG value={autoLoginUrl(loginUrl, voucher.code, voucher.password)} size={qrSize} level="M" className="shrink-0" />
                        <div className={`min-w-0 ${thermal ? 'w-full' : 'flex-1'}`}>
                            <div className="truncate text-[10px] font-semibold tracking-wide text-neutral-500 uppercase">{company}</div>
                            <div className="text-xs font-medium">{batch.profile.name}</div>
                            <div className="my-1 font-mono text-lg leading-none font-bold tracking-widest">{voucher.code}</div>
                            <div className="num text-[10px] text-neutral-600">
                                {formatRupiah(batch.profile.price)} · {formatDuration(batch.profile.validity_minutes)}
                            </div>
                            <div className="mt-0.5 text-[9px] text-neutral-400">Scan QR atau masukkan kode</div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
