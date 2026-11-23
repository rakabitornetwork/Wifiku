import { Head, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Printer } from 'lucide-react';

import { formatDuration, formatRupiah } from '@/lib/utils';

type Props = {
    batch: {
        id: number;
        batch_code: string;
        profile: { name: string; price: number; validity_minutes: number };
    };
    company: string;
    contact: { label: string; name: string | null; phone: string | null };
    loginUrl: string;
    vouchers: { id: number; code: string; password: string }[];
};

type Layout = 'a4' | 'thermal58' | 'thermal80';
type Ink = 'full' | 'lite';

const layouts: { value: Layout; label: string }[] = [
    { value: 'a4', label: 'A4 Grid' },
    { value: 'thermal80', label: 'Thermal 80mm' },
    { value: 'thermal58', label: 'Thermal 58mm' },
];

/** A4 secara bawaan memakai versi hemat tinta; `?ink=full` untuk versi berwarna penuh. */
function readInk(): Ink {
    return new URLSearchParams(window.location.search).get('ink') === 'full' ? 'full' : 'lite';
}

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

/** 4 kolom x 14 baris = 56 kartu per lembar A4. */
const PER_SHEET = 56;

/** Pola ukiran (guilloche) bergaya uang kertas: gelombang bertumpuk dan rosette, dipakai sebagai latar kartu. */
function makeEngraving(lite: boolean): string {
    const waveColor = lite ? '#0f766e' : '#0e7490';
    const rosetteColor = lite ? '#0f766e' : '#b45309';
    const waves = Array.from({ length: 14 }, (_, index) => {
        const y = 3 + index * 5.4;
        const amplitude = index % 2 === 0 ? 3.2 : -3.2;
        const segment = `q5,${-amplitude} 10,0 t10,0`;

        return `<path d="M0,${y} ${segment.concat(' t10,0'.repeat(19))}" fill="none" stroke="${waveColor}" stroke-opacity="${lite ? '.17' : '.38'}" stroke-width="${lite ? '.4' : '.55'}"/>`;
    }).join('');
    const rosette = Array.from(
        { length: 9 },
        (_, index) => `<circle cx="168" cy="40" r="${5 + index * 4.2}" fill="none" stroke="${rosetteColor}" stroke-opacity="${lite ? '.15' : '.34'}" stroke-width="${lite ? '.45' : '.6'}"/>`,
    ).join('');
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 80" preserveAspectRatio="xMidYMid slice">${waves}${rosette}</svg>`;

    return `url("data:image/svg+xml;utf8,${encodeURIComponent(svg)}")`;
}

const engravings = { full: makeEngraving(false), lite: makeEngraving(true) };

const codeFontSize = (length: number): string => (length <= 8 ? '12pt' : length <= 10 ? '11pt' : length <= 12 ? '9.5pt' : '8.5pt');

function A4Card({
    voucher,
    number,
    ink,
    batch,
    company,
    contact,
    loginUrl,
}: { voucher: Props['vouchers'][number]; number: number; ink: Ink } & Pick<Props, 'batch' | 'company' | 'contact' | 'loginUrl'>) {
    const lite = ink === 'lite';
    const contactLine = [contact.name, contact.phone].filter(Boolean).join(' · ');

    return (
        <div
            className={`voucher relative flex overflow-hidden rounded-[1.6mm] ${lite ? 'border-[0.25mm] border-[#0f766e]/70' : 'border-[0.35mm] border-[#0f766e] shadow-[inset_0_0_0_0.4mm_#fff,inset_0_0_0_0.6mm_#d97706]'}`}
            style={{
                printColorAdjust: 'exact',
                WebkitPrintColorAdjust: 'exact',
            }}
        >
            <div
                className={`flex w-[18mm] shrink-0 items-center justify-center ${lite ? 'border-r border-dashed border-[#0f766e]/50' : ''}`}
                style={{
                    background: lite ? '#ffffff' : 'linear-gradient(160deg,#0f766e 0%,#155e75 45%,#1e3a8a 100%)',
                }}
            >
                <div className="rounded-[1mm] bg-white p-[0.8mm]">
                    <QRCodeSVG value={autoLoginUrl(loginUrl, voucher.code, voucher.password)} size={56} level="M" />
                </div>
            </div>
            <div
                className="relative flex min-w-0 flex-1 flex-col justify-between px-[1.6mm] pt-[0.9mm] pb-[1.3mm]"
                style={{
                    backgroundImage: lite ? engravings.lite : `${engravings.full}, linear-gradient(135deg,#ecfeff 0%,#fffbeb 100%)`,
                    backgroundSize: '100% 100%, 100% 100%',
                }}
            >
                <div className="flex items-center justify-between gap-[1mm]">
                    <div className="truncate text-[5.5pt] leading-none font-bold tracking-wider text-[#0f766e] uppercase">{company}</div>
                    <span
                        className={`num flex size-[4mm] shrink-0 items-center justify-center rounded-full border-[0.25mm] text-[5.2pt] leading-none font-extrabold ${lite ? 'border-[#0f766e] bg-white text-[#0f766e]' : 'border-[#92400e] text-[#451a03]'}`}
                        style={{
                            background: lite ? '#ffffff' : 'radial-gradient(circle at 30% 30%,#fde68a,#f59e0b)',
                        }}
                    >
                        {number}
                    </span>
                </div>
                <div className="font-mono leading-none font-extrabold tracking-wider text-[#0f172a]" style={{ fontSize: codeFontSize(voucher.code.length) }}>
                    {voucher.code}
                </div>
                <div className="num truncate text-[6pt] leading-[1.15] font-semibold text-[#1e3a8a]">
                    {formatRupiah(batch.profile.price)} · {formatDuration(batch.profile.validity_minutes)}
                </div>
                <div className="flex items-baseline gap-[1mm] border-t border-dashed border-[#0f766e]/50 pt-[0.3mm] text-[5.2pt] leading-[1.15] text-[#334155]">
                    {contactLine ? (
                        <>
                            <span className="min-w-0 truncate">
                                <span className="font-bold">{contact.label}</span>
                                {contact.name ? ` ${contact.name}` : ''}
                            </span>
                            {contact.phone && <span className="num shrink-0 font-bold text-[#0f172a]">{contact.phone}</span>}
                        </>
                    ) : (
                        'Scan QR atau masukkan kode'
                    )}
                </div>
            </div>
        </div>
    );
}

const pageCss: Record<Layout, string> = {
    a4: '@page { size: A4; margin: 6mm; }',
    thermal80: '@page { size: 80mm auto; margin: 2mm; }',
    thermal58: '@page { size: 58mm auto; margin: 1.5mm; }',
};

export default function PrintVouchers({ batch, company, contact, loginUrl, vouchers }: Props) {
    const layout = readLayout();
    const ink = readInk();
    const thermal = layout !== 'a4';
    const sheets = Array.from({ length: Math.ceil(vouchers.length / PER_SHEET) }, (_, index) => vouchers.slice(index * PER_SHEET, (index + 1) * PER_SHEET));
    const qrSize = layout === 'thermal58' ? 84 : layout === 'thermal80' ? 104 : 72;

    const changeLayout = (value: Layout) => router.get(window.location.pathname, { layout: value, ink: value === 'a4' ? ink : undefined }, { replace: true });
    const changeInk = (value: Ink) => router.get(window.location.pathname, { layout, ink: value }, { replace: true });

    return (
        <div className="min-h-screen bg-white text-black">
            <Head title={`Cetak ${batch.batch_code}`} />
            <style>{`${pageCss[layout]} @media print { .no-print { display: none !important; } body { background: #fff !important; } .voucher { break-inside: avoid; } .a4-sheet { box-shadow: none !important; } .thermal-card { break-after: page; } }`}</style>

            <div className="no-print sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b bg-white/95 px-4 py-3 backdrop-blur">
                <div className="mr-auto text-sm">
                    <span className="font-semibold">{batch.batch_code}</span>
                    <span className="num text-neutral-500">
                        {' '}
                        · {vouchers.length} voucher
                        {!thermal && ` · ${sheets.length} lembar A4 (${PER_SHEET}/lembar)`}
                    </span>
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
                {!thermal && (
                    <div className="flex gap-1 rounded-lg bg-neutral-100 p-1">
                        {(
                            [
                                { value: 'lite', label: 'Hemat tinta' },
                                { value: 'full', label: 'Berwarna penuh' },
                            ] as { value: Ink; label: string }[]
                        ).map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => changeInk(item.value)}
                                className={`rounded-md px-3 py-1 text-xs font-medium ${ink === item.value ? 'bg-white shadow-sm' : 'text-neutral-500'}`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                )}
                <button type="button" onClick={() => window.print()} className="inline-flex h-9 items-center gap-2 rounded-lg bg-neutral-900 px-4 text-sm font-medium text-white">
                    <Printer className="size-4" /> Cetak
                </button>
            </div>

            {thermal ? (
                <div className={`mx-auto p-2 ${layout === 'thermal58' ? 'w-[58mm]' : 'w-[80mm]'}`}>
                    {vouchers.map((voucher) => (
                        <div key={voucher.id} className="voucher thermal-card mb-2 flex flex-col items-center gap-2 border border-dashed border-neutral-400 p-2 text-center">
                            <QRCodeSVG value={autoLoginUrl(loginUrl, voucher.code, voucher.password)} size={qrSize} level="M" className="shrink-0" />
                            <div className="w-full min-w-0">
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
            ) : (
                <div className="a4-sheets flex flex-col items-center gap-4 bg-neutral-200 py-4 print:gap-0 print:bg-white print:py-0">
                    {sheets.map((sheet, sheetIndex) => (
                        <div
                            key={sheetIndex}
                            className="a4-sheet grid h-[285mm] w-[198mm] grid-cols-4 grid-rows-[repeat(14,minmax(0,1fr))] gap-x-[1.5mm] gap-y-[1mm] bg-white"
                            style={{
                                breakAfter: sheetIndex < sheets.length - 1 ? 'page' : 'auto',
                            }}
                        >
                            {sheet.map((voucher, index) => (
                                <A4Card
                                    key={voucher.id}
                                    voucher={voucher}
                                    number={sheetIndex * PER_SHEET + index + 1}
                                    ink={ink}
                                    batch={batch}
                                    company={company}
                                    contact={contact}
                                    loginUrl={loginUrl}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
