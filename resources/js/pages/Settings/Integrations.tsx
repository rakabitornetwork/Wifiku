import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { type FormEvent } from 'react';

import { LogoCard } from '@/Components/LogoCard';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Input, Textarea } from '@/Components/ui/Field';

type SettingField = {
    name: string;
    label: string;
    hint: string | null;
    secret: boolean;
    value: string | null;
    is_set: boolean;
};
type Group = { group: string; title: string; fields: SettingField[] };

type FormShape = { settings: Record<string, Record<string, string>> };

const multiline = new Set(['isolir.message', 'genieacs.rx_power_paths']);

export default function Integrations({ groups, logo }: { groups: Group[]; logo: string | null }) {
    const initial: FormShape = { settings: {} };

    for (const group of groups) {
        initial.settings[group.group] = {};

        for (const field of group.fields) {
            initial.settings[group.group][field.name.split('.')[1]] = field.secret ? '' : (field.value ?? '');
        }
    }

    const form = useForm<FormShape>(initial);
    const errors = form.errors as Record<string, string | undefined>;

    const setField = (name: string, value: string) => {
        const [group, key] = name.split('.');
        form.setData('settings', {
            ...form.data.settings,
            [group]: { ...form.data.settings[group], [key]: value },
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/integrations', {
            preserveScroll: true,
            onSuccess: () => {
                for (const group of groups) {
                    for (const field of group.fields.filter((item) => item.secret)) {
                        setField(field.name, '');
                    }
                }
            },
        });
    };

    return (
        <>
            <PageHeader
                title="Pengaturan"
                description="Logo dan data perusahaan, template isolir, penagihan, GenieACS, hotspot, dan VPN. WhatsApp dan Telegram ada di menu Notifikasi & Webhook. Nilai rahasia disimpan terenkripsi."
            />

            <LogoCard logo={logo} />

            <form onSubmit={submit} className="space-y-4">
                {groups.map((group) => (
                    <Card key={group.group}>
                        <CardHeader title={group.title} />
                        <div className="grid gap-4 p-5 sm:grid-cols-2">
                            {group.fields.map((field) => {
                                const [groupKey, key] = field.name.split('.');
                                const value = form.data.settings[groupKey][key];
                                const error = errors[`settings.${field.name}`];
                                const hint = field.secret ? (field.is_set ? 'Tersimpan. Kosongkan jika tidak ingin mengubah.' : 'Belum diisi.') : (field.hint ?? undefined);

                                return (
                                    <Field key={field.name} label={field.label} error={error} hint={hint} className={multiline.has(field.name) ? 'sm:col-span-2' : undefined}>
                                        {multiline.has(field.name) ? (
                                            <Textarea value={value} onChange={(event) => setField(field.name, event.target.value)} />
                                        ) : field.secret ? (
                                            <div className="relative">
                                                <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    type="password"
                                                    autoComplete="new-password"
                                                    className="pl-9"
                                                    placeholder={field.is_set ? '••••••••' : 'Masukkan nilai'}
                                                    value={value}
                                                    onChange={(event) => setField(field.name, event.target.value)}
                                                />
                                            </div>
                                        ) : (
                                            <Input value={value} onChange={(event) => setField(field.name, event.target.value)} />
                                        )}
                                    </Field>
                                );
                            })}
                        </div>
                    </Card>
                ))}

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                    </Button>
                </div>
            </form>
        </>
    );
}
