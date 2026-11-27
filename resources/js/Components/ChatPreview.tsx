import { ExternalLink } from 'lucide-react';
import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** Teks pesan dengan tautan http(s) yang bisa diklik, seperti tampil di aplikasi chat. */
export function linkify(text: string): ReactNode[] {
    return text.split(/(https?:\/\/[^\s)]+)/g).map((part, index) =>
        /^https?:\/\//.test(part) ? (
            <a key={index} href={part} target="_blank" rel="noreferrer" className="break-all underline decoration-1 underline-offset-2 hover:opacity-80">
                {part}
            </a>
        ) : (
            part
        ),
    );
}

export type BotButton = { label: string; data?: string; url?: string };
export type BotStep = { trigger: string; title: string; replies: { text: string; buttons: BotButton[][]; request_contact: boolean }[] };

/** Satu percakapan contoh: pesan pelanggan di kanan, balasan bot di kiri; Telegram menambah tombol di bawah balasan. */
export function ChatColumn({ channel, step }: { channel: 'whatsapp' | 'telegram'; step: BotStep }) {
    const whatsapp = channel === 'whatsapp';

    return (
        <div className={cn('space-y-2 rounded-2xl p-3', whatsapp ? 'bg-[#efeae2] dark:bg-[#0b141a]' : 'bg-[#dfe9f3] dark:bg-[#0e1621]')}>
            <div className="flex items-center gap-1.5 px-1 text-[11px] font-semibold text-muted-foreground">
                <span className={cn('size-2 rounded-full', whatsapp ? 'bg-emerald-500' : 'bg-sky-500')} /> {whatsapp ? 'WhatsApp' : 'Telegram'}
            </div>
            <div className="flex justify-end">
                <div className={cn('max-w-[80%] rounded-xl rounded-tr-sm px-3 py-1.5 text-[13px] shadow-sm', whatsapp ? 'bg-[#d9fdd3] text-slate-900 dark:bg-[#005c4b] dark:text-slate-50' : 'bg-[#e3f2ff] text-slate-900 dark:bg-[#2b5278] dark:text-slate-50')}>{step.trigger}</div>
            </div>
            {step.replies.map((reply, index) => (
                <div key={index} className="max-w-[92%] space-y-1.5">
                    <div className="rounded-xl rounded-tl-sm bg-white px-3 py-2 text-[13px] leading-relaxed break-words whitespace-pre-wrap text-slate-900 shadow-sm dark:bg-[#182533] dark:text-slate-50">{linkify(reply.text)}</div>
                    {!whatsapp && reply.request_contact && (
                        <div className="rounded-lg bg-white/70 px-3 py-1.5 text-center text-xs font-medium text-sky-700 dark:bg-[#182533]/70 dark:text-sky-300">📱 Bagikan nomor saya (tombol keyboard)</div>
                    )}
                    {!whatsapp &&
                        reply.buttons.map((row, rowIndex) => (
                            <div key={rowIndex} className="flex gap-1.5">
                                {row.map((button) => (
                                    <div key={button.label} className="flex flex-1 items-center justify-center gap-1 rounded-lg bg-white/70 px-2 py-1.5 text-center text-xs font-medium text-sky-700 dark:bg-[#182533]/70 dark:text-sky-300">
                                        {button.label}
                                        {button.url && <ExternalLink className="size-3" />}
                                    </div>
                                ))}
                            </div>
                        ))}
                </div>
            ))}
        </div>
    );
}
