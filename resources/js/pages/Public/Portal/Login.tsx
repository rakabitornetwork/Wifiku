import { Head, Link, useForm } from "@inertiajs/react";
import { Check, Moon, Sun } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";

import { Icon3D } from "@/Components/landing/Icon3D";
import { Toaster } from "@/Components/Toaster";
import { Button } from "@/Components/ui/Button";
import { Checkbox, Field, Input } from "@/Components/ui/Field";
import { useTheme } from "@/Hooks/useTheme";
import { cn } from "@/lib/utils";

type Service = "pppoe" | "vpn";
type Props = {
    company: { name: string; logo: string | null };
    service: Service;
    pendingPhone: string | null;
    resendSeconds: number;
    vpnRegistrationOpen: boolean;
};

const services: Record<Service, { tab: string; headline: string; benefits: string[] }> = {
    pppoe: {
        tab: "Internet Rumah",
        headline: "Internet rumah Anda, dalam satu halaman.",
        benefits: ["Tagihan dan pembayaran online", "Status ONU dan kualitas sinyal", "Ganti nama dan password WiFi"],
    },
    vpn: {
        tab: "VPN Tunnel",
        headline: "Akun VPN Anda, siap dipakai di MikroTik.",
        benefits: ["Kelola akun VPN Tunnel Anda", "Ambil script RouterOS siap tempel", "Tagihan dan perpanjangan akun"],
    },
};

function Brand({ company, tone }: { company: Props["company"]; tone: "dark" | "auto" }) {
    return (
        <div className="flex items-center gap-3">
            {company.logo ? (
                <span className="flex h-10 items-center rounded-xl bg-white px-2.5 shadow-sm ring-1 ring-slate-900/5">
                    <img src={company.logo} alt="" className="h-7 w-auto max-w-32 object-contain" />
                </span>
            ) : (
                <Icon3D name="wifi" className="size-10" />
            )}
            <span className={tone === "dark" ? "text-sm font-semibold text-white" : "text-sm font-semibold"}>{company.name}</span>
        </div>
    );
}

export default function Login({ company, service: initialService, pendingPhone, resendSeconds, vpnRegistrationOpen }: Props) {
    const { theme, toggleTheme } = useTheme();
    const phoneForm = useForm({ whatsapp: "" });
    const otpForm = useForm({ otp: "" });
    const resendForm = useForm({});
    const vpnForm = useForm({ email: "", password: "", remember: false });
    const [service, setService] = useState<Service>(initialService);
    const [wait, setWait] = useState(resendSeconds);

    useEffect(() => {
        if (!pendingPhone || wait <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setWait((value) => value - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [pendingPhone, wait]);

    const requestCode = (event: FormEvent) => {
        event.preventDefault();
        phoneForm.post("/portal/kode", {
            onSuccess: () => setWait(resendSeconds),
        });
    };

    const resend = () =>
        resendForm.post("/portal/kirim-ulang", {
            preserveScroll: true,
            onSuccess: () => setWait(resendSeconds),
        });

    const verify = (event: FormEvent) => {
        event.preventDefault();
        otpForm.post("/portal/verifikasi", {
            onError: () => otpForm.reset("otp"),
        });
    };

    const switchService = (next: Service) => {
        setService(next);
        window.history.replaceState(null, "", next === "vpn" ? "/portal?layanan=vpn" : "/portal");
    };

    const loginVpn = (event: FormEvent) => {
        event.preventDefault();
        vpnForm.post("/vpn-tunnel/masuk", {
            onFinish: () => vpnForm.setData("password", ""),
        });
    };

    const submitClass = "h-12 w-full bg-indigo-600 text-base font-semibold text-white shadow-md shadow-indigo-600/25 hover:bg-indigo-700 hover:opacity-100";

    return (
        <div className="relative flex min-h-screen flex-col bg-slate-100 dark:bg-background">
            <Head title="Masuk Portal Pelanggan" />

            <button
                type="button"
                onClick={toggleTheme}
                aria-label="Ganti tema"
                className="absolute top-4 right-4 z-10 flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:text-slate-900 dark:border-white/10 dark:bg-card dark:text-slate-300 dark:hover:text-white"
            >
                {theme === "dark" ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
            </button>

            <main className="flex flex-1 items-center justify-center px-4 py-12">
                <div className="grid w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-[0_24px_60px_-20px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5 lg:grid-cols-[1fr_1.1fr] dark:bg-card dark:shadow-none dark:ring-white/10">
                    <aside className="relative hidden flex-col justify-between overflow-hidden bg-[#0b1026] p-10 text-white lg:flex">
                        <div className="pointer-events-none absolute -top-24 -right-20 size-80 rounded-full bg-indigo-500/30 blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-32 -left-24 size-72 rounded-full bg-violet-600/20 blur-3xl" />
                        <div className="relative">
                            <Brand company={company} tone="dark" />
                        </div>
                        <div className="relative">
                            <h2 className="text-3xl leading-tight font-semibold tracking-tight text-balance">{services[service].headline}</h2>
                            <ul className="mt-8 space-y-3.5 text-sm text-indigo-50/90">
                                {services[service].benefits.map((item) => (
                                    <li key={item} className="flex items-center gap-3">
                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300">
                                            <Check className="size-3.5" strokeWidth={3} />
                                        </span>
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <p className="relative text-xs text-indigo-200/60">Portal Pelanggan {company.name}</p>
                    </aside>

                    <section className="p-7 sm:p-11">
                        <div className="mb-8 lg:hidden">
                            <Brand company={company} tone="auto" />
                        </div>

                        <div role="tablist" aria-label="Pilih layanan" className="mb-8 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-background">
                            {(Object.keys(services) as Service[]).map((key) => (
                                <button
                                    key={key}
                                    type="button"
                                    role="tab"
                                    aria-selected={service === key}
                                    onClick={() => switchService(key)}
                                    className={cn(
                                        "rounded-lg px-3 py-2 text-sm font-semibold transition",
                                        service === key ? "bg-white text-indigo-700 shadow-sm dark:bg-card dark:text-indigo-300" : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    {services[key].tab}
                                </button>
                            ))}
                        </div>

                        {service === "vpn" ? (
                            <>
                                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Masuk ke akun VPN Tunnel</h1>
                                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">Kelola akun VPN dan ambil script RouterOS Anda.</p>
                                <form onSubmit={loginVpn} className="mt-8 space-y-5">
                                    <Field label="Email" error={vpnForm.errors.email}>
                                        <Input
                                            type="email"
                                            value={vpnForm.data.email}
                                            onChange={(event) => vpnForm.setData("email", event.target.value)}
                                            className="h-12 bg-slate-50 text-base dark:bg-background"
                                            autoComplete="username"
                                            required
                                        />
                                    </Field>
                                    <Field label="Password" error={vpnForm.errors.password}>
                                        <Input
                                            type="password"
                                            value={vpnForm.data.password}
                                            onChange={(event) => vpnForm.setData("password", event.target.value)}
                                            className="h-12 bg-slate-50 text-base dark:bg-background"
                                            autoComplete="current-password"
                                            required
                                        />
                                    </Field>
                                    <div className="flex items-center justify-between">
                                        <Checkbox label="Ingat saya" checked={vpnForm.data.remember} onChange={(checked) => vpnForm.setData("remember", checked)} />
                                        <Link href="/vpn-tunnel/lupa-password" className="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">
                                            Lupa password?
                                        </Link>
                                    </div>
                                    <Button type="submit" className={submitClass} disabled={vpnForm.processing}>
                                        {vpnForm.processing ? "Memproses..." : "Masuk"}
                                    </Button>
                                    {vpnRegistrationOpen && (
                                        <p className="text-center text-sm text-muted-foreground">
                                            Belum punya akun?{" "}
                                            <Link href="/vpn-tunnel/daftar" className="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">
                                                Daftar VPN Tunnel
                                            </Link>
                                        </p>
                                    )}
                                </form>
                            </>
                        ) : (
                            <>
                                <p className="text-xs font-medium text-muted-foreground">Langkah {pendingPhone ? "2" : "1"} dari 2</p>
                                <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{pendingPhone ? "Masukkan kode dari WhatsApp" : "Masuk ke portal pelanggan"}</h1>
                                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                    {pendingPhone ? (
                                        <>
                                            Jika nomor <span className="num font-semibold text-foreground">{pendingPhone}</span> terdaftar, kode 6 digit dikirim ke WhatsApp-nya. Berlaku 10 menit.
                                        </>
                                    ) : (
                                        "Masuk dengan nomor WhatsApp yang terdaftar. Kami kirim kode sekali pakai ke nomor itu."
                                    )}
                                </p>

                                {pendingPhone ? (
                                    <form onSubmit={verify} className="mt-8 space-y-5">
                                        <Field label="Kode dari WhatsApp" error={otpForm.errors.otp}>
                                            <Input
                                                value={otpForm.data.otp}
                                                onChange={(event) => otpForm.setData("otp", event.target.value.replace(/\D/g, "").slice(0, 6))}
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                placeholder="000000"
                                                className="num h-14 bg-slate-50 text-center text-2xl font-semibold tracking-[0.5em] dark:bg-background"
                                                autoFocus
                                                required
                                            />
                                        </Field>
                                        <Button type="submit" className={submitClass} disabled={otpForm.processing || otpForm.data.otp.length !== 6}>
                                            {otpForm.processing ? "Memeriksa..." : "Masuk"}
                                        </Button>
                                        <div className="flex items-center justify-between text-sm">
                                            <button
                                                type="button"
                                                className="font-semibold text-indigo-600 hover:underline disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline dark:text-indigo-300"
                                                onClick={resend}
                                                disabled={wait > 0 || resendForm.processing}
                                            >
                                                {wait > 0 ? `Kirim ulang (${wait} dtk)` : "Kirim ulang kode"}
                                            </button>
                                            <Link href="/pelanggan/masuk?ganti=1" className="text-muted-foreground hover:text-foreground hover:underline">
                                                Ganti nomor
                                            </Link>
                                        </div>
                                    </form>
                                ) : (
                                    <form onSubmit={requestCode} className="mt-8 space-y-5">
                                        <Field label="Nomor WhatsApp" error={phoneForm.errors.whatsapp} hint="Contoh: 08123456789">
                                            <Input
                                                type="tel"
                                                value={phoneForm.data.whatsapp}
                                                onChange={(event) => phoneForm.setData("whatsapp", event.target.value)}
                                                inputMode="tel"
                                                placeholder="08123456789"
                                                className="num h-12 bg-slate-50 text-base dark:bg-background"
                                                autoComplete="tel"
                                                autoFocus
                                                required
                                            />
                                        </Field>
                                        <Button type="submit" className={submitClass} disabled={phoneForm.processing || phoneForm.data.whatsapp.trim() === ""}>
                                            {phoneForm.processing ? "Mengirim..." : "Kirim kode ke WhatsApp"}
                                        </Button>
                                    </form>
                                )}
                            </>
                        )}
                    </section>
                </div>
            </main>

            <footer className="pb-8 text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} {company.name}
            </footer>
            <Toaster />
        </div>
    );
}
