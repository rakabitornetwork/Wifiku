import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

import { PageHeader } from '@/Components/PageHeader';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Input, Select } from '@/Components/ui/Field';
import { formatDuration, formatRupiah } from '@/lib/utils';

type Props = {
    profiles: { id: number; name: string; price: number; validity_minutes: number; router: { name: string } }[];
    resellers: { id: number; name: string }[];
    charsets: string[];
};

const charsetLabels: Record<string, string> = {
    alphanumeric: 'Huruf besar + angka (tanpa 0/O/1/I)',
    mixed: 'Huruf besar + kecil + angka (tanpa 0/O/1/I/l)',
    numeric: 'Angka saja',
    alpha: 'Huruf besar saja (tanpa O/I)',
};

export default function BatchCreate({ profiles, resellers, charsets }: Props) {
    const form = useForm({ hotspot_profile_id: '', reseller_id: '', quantity: '50', prefix: '', code_length: '6', charset: 'alphanumeric' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/hotspot/batches');
    };

    return (
        <>
            <PageHeader title="Generate Voucher" description="Kode dibuat di antrean, jadi halaman tidak menunggu proses selesai." />

            <Card className="max-w-2xl">
                <form onSubmit={submit} className="grid gap-4 p-5 sm:grid-cols-2">
                    <Field label="Profil hotspot" error={form.errors.hotspot_profile_id} className="sm:col-span-2">
                        <Select value={form.data.hotspot_profile_id} onChange={(event) => form.setData('hotspot_profile_id', event.target.value)} required>
                            <option value="">Pilih profil</option>
                            {profiles.map((profile) => (
                                <option key={profile.id} value={profile.id}>
                                    {profile.router.name} · {profile.name} — {formatRupiah(profile.price)} / {formatDuration(profile.validity_minutes)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Reseller" error={form.errors.reseller_id} hint="Opsional; menentukan harga voucher.">
                        <Select value={form.data.reseller_id} onChange={(event) => form.setData('reseller_id', event.target.value)}>
                            <option value="">Tanpa reseller</option>
                            {resellers.map((reseller) => (
                                <option key={reseller.id} value={reseller.id}>
                                    {reseller.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Jumlah voucher" error={form.errors.quantity} hint="Maksimal 1000 per batch.">
                        <Input type="number" min={1} max={1000} value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} required />
                    </Field>
                    <Field label="Prefix kode" error={form.errors.prefix} hint="Opsional, maksimal 6 karakter.">
                        <Input value={form.data.prefix} maxLength={6} onChange={(event) => form.setData('prefix', event.target.value)} />
                    </Field>
                    <Field label="Panjang kode acak" error={form.errors.code_length}>
                        <Input type="number" min={4} max={10} value={form.data.code_length} onChange={(event) => form.setData('code_length', event.target.value)} required />
                    </Field>
                    <Field label="Karakter kode" error={form.errors.charset} className="sm:col-span-2">
                        <Select value={form.data.charset} onChange={(event) => form.setData('charset', event.target.value)}>
                            {charsets.map((charset) => (
                                <option key={charset} value={charset}>
                                    {charsetLabels[charset] ?? charset}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <div className="flex justify-end gap-2 sm:col-span-2">
                        <Link href="/hotspot/batches" className={buttonClass('outline')}>
                            Batal
                        </Link>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Membuat...' : 'Generate'}
                        </Button>
                    </div>
                </form>
            </Card>
        </>
    );
}
