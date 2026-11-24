import { Head, Link, useForm } from '@inertiajs/react';
import { Wifi } from 'lucide-react';
import { type FormEvent } from 'react';

import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input } from '@/Components/ui/Field';
import { useTheme } from '@/Hooks/useTheme';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });
    useTheme();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent)] px-4">
            <Head title="Masuk" />
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <div className="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg">
                        <Wifi className="size-6" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Wifi Teslatech</h1>
                        <p className="text-sm text-muted-foreground">Masuk ke panel manajemen ISP</p>
                    </div>
                </div>
                <Card className="glass p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Email" error={errors.email}>
                            <Input
                                type="email"
                                autoComplete="username"
                                autoFocus
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                required
                            />
                        </Field>
                        <Field label="Password" error={errors.password}>
                            <Input
                                type="password"
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                required
                            />
                        </Field>
                        <div className="flex items-center justify-between">
                            <Checkbox label="Ingat saya" checked={data.remember} onChange={(checked) => setData('remember', checked)} />
                            <Link href="/lupa-password" className="text-sm font-medium text-primary hover:underline">
                                Lupa password?
                            </Link>
                        </div>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Memproses...' : 'Masuk'}
                        </Button>
                    </form>
                </Card>
            </div>
        </div>
    );
}
