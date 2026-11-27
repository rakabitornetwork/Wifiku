import { type BotStep, ChatColumn } from '@/Components/ChatPreview';
import { MessagingShell } from '@/Components/MessagingShell';
import { Card } from '@/Components/ui/Card';

export default function BotReplies({ botSteps }: { botSteps: BotStep[] }) {
    return (
        <MessagingShell active="bot" description="Yang tampil di HP pelanggan saat memilih menu 1, 2, 3, 4, dan seterusnya. WhatsApp menampilkan teks bernomor; Telegram menambah tombol di bawah balasan. Datanya contoh, tetapi susunan teksnya sama dengan bot sungguhan.">
            <div className="space-y-5">
                {botSteps.map((step, index) => (
                    <Card key={`${step.title}-${index}`} className="overflow-hidden">
                        <div className="flex flex-wrap items-center gap-2 border-b px-6 py-3.5">
                            <h2 className="text-[15px] font-semibold tracking-tight">{step.title}</h2>
                            <span className="num rounded-md border bg-muted px-2 py-0.5 text-xs text-muted-foreground">Pelanggan mengirim: {step.trigger}</span>
                        </div>
                        <div className="grid gap-4 p-6 lg:grid-cols-2">
                            <ChatColumn channel="whatsapp" step={step} />
                            <ChatColumn channel="telegram" step={step} />
                        </div>
                    </Card>
                ))}
            </div>
        </MessagingShell>
    );
}
