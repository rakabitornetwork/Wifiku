import { Link, useForm } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

import { LocationPickerModal, type PickedLocation } from '@/Components/LocationPickerModal';
import { PageHeader } from '@/Components/PageHeader';
import { Button, buttonClass } from '@/Components/ui/Button';
import { Card, CardHeader } from '@/Components/ui/Card';
import { Field, Input, Select, Textarea } from '@/Components/ui/Field';
import { formatRupiah } from '@/lib/utils';

type Customer = {
    id: number;
    customer_code: string;
    router_id: number;
    package_id: number;
    odp_id: number | null;
    odp_port: number | null;
    nik: string | null;
    name: string;
    whatsapp: string;
    address: string | null;
    village: string | null;
    latitude: string | null;
    longitude: string | null;
    pppoe_username: string;
    billing_cycle: string;
    billing_day: number;
    due_days: number;
    installed_at: string;
    promise_to_pay_until: string | null;
    status: string;
};

type Props = {
    customer: Customer | null;
    routers: { id: number; name: string }[];
    packages: { id: number; router_id: number | null; name: string; price: number }[];
    odps: { id: number; router_id: number; name: string; code: string; total_ports: number }[];
};

const str = (value: string | number | null | undefined): string => (value === null || value === undefined ? '' : String(value));

export default function CustomerForm({ customer, routers, packages, odps }: Props) {
    const form = useForm({
        router_id: str(customer?.router_id),
        package_id: str(customer?.package_id),
        odp_id: str(customer?.odp_id),
        odp_port: str(customer?.odp_port),
        nik: str(customer?.nik),
        name: str(customer?.name),
        whatsapp: str(customer?.whatsapp),
        address: str(customer?.address),
        village: str(customer?.village),
        latitude: str(customer?.latitude),
        longitude: str(customer?.longitude),
        pppoe_username: str(customer?.pppoe_username),
        pppoe_password: '',
        billing_cycle: customer?.billing_cycle ?? 'fixed',
        billing_day: str(customer?.billing_day ?? 1),
        due_days: str(customer?.due_days ?? 7),
        installed_at: customer?.installed_at ?? new Date().toISOString().slice(0, 10),
        status: customer?.status ?? 'active',
    });

    const routerId = Number(form.data.router_id);
    const availablePackages = useMemo(() => packages.filter((item) => item.router_id === null || item.router_id === routerId), [packages, routerId]);
    const availableOdps = useMemo(() => odps.filter((item) => item.router_id === routerId), [odps, routerId]);

    const [picking, setPicking] = useState(false);
    const [addressFailed, setAddressFailed] = useState(false);

    const applyLocation = (location: PickedLocation) => {
        form.setData((current) => ({
            ...current,
            latitude: location.latitude,
            longitude: location.longitude,
            address: location.address || current.address,
            village: location.village || current.village,
        }));
        setAddressFailed(location.addressFailed === true);
        setPicking(false);
    };

    const currentPosition = Number.isFinite(parseFloat(form.data.latitude)) && Number.isFinite(parseFloat(form.data.longitude)) ? { latitude: parseFloat(form.data.latitude), longitude: parseFloat(form.data.longitude) } : null;

    const set = (key: keyof typeof form.data) => (event: { target: { value: string } }) => form.setData(key, event.target.value);

    const changeRouter = (value: string) => {
        form.setData((current) => ({ ...current, router_id: value, package_id: '', odp_id: '', odp_port: '' }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (customer) {
            form.put(`/customers/${customer.id}`);
        } else {
            form.post('/customers');
        }
    };

    return (
        <>
            <PageHeader
                title={customer ? `Ubah Pelanggan ${customer.customer_code}` : 'Tambah Pelanggan'}
                description="ID pelanggan dibuat otomatis saat disimpan."
            />

            <form onSubmit={submit} className="space-y-4">
                <Card>
                    <CardHeader title="Data pelanggan" />
                    <div className="grid gap-4 p-5 sm:grid-cols-2">
                        <Field label="Nama lengkap" error={form.errors.name}>
                            <Input value={form.data.name} onChange={set('name')} required />
                        </Field>
                        <Field label="NIK" error={form.errors.nik} hint="16 digit (opsional).">
                            <Input inputMode="numeric" maxLength={16} value={form.data.nik} onChange={set('nik')} />
                        </Field>
                        <Field label="No. WhatsApp" error={form.errors.whatsapp} hint="Opsional. Boleh diawali 08 atau 62. Tanpa nomor, pelanggan tidak menerima notifikasi WhatsApp.">
                            <Input inputMode="tel" value={form.data.whatsapp} onChange={set('whatsapp')} />
                        </Field>
                        <Field label="Desa / Kelurahan" error={form.errors.village}>
                            <Input value={form.data.village} onChange={set('village')} />
                        </Field>
                        <Field label="Alamat" error={form.errors.address} className="sm:col-span-2">
                            <Textarea value={form.data.address} onChange={set('address')} />
                        </Field>
                        <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
                            <Button type="button" variant="outline" onClick={() => setPicking(true)}>
                                <MapPin className="size-4" /> Pilih posisi GPS
                            </Button>
                            <span className="text-xs text-muted-foreground">Isi koordinat (dan alamat) dari peta atau GPS perangkat.</span>
                        </div>
                        {addressFailed && <p className="text-sm text-destructive sm:col-span-2">Koordinat terisi, tetapi alamat otomatis tidak dapat diambil. Isi Alamat dan Desa secara manual.</p>}
                        <Field label="Latitude" error={form.errors.latitude}>
                            <Input inputMode="decimal" value={form.data.latitude} onChange={set('latitude')} placeholder="-7.2504450" />
                        </Field>
                        <Field label="Longitude" error={form.errors.longitude}>
                            <Input inputMode="decimal" value={form.data.longitude} onChange={set('longitude')} placeholder="112.7688450" />
                        </Field>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Layanan & jaringan" />
                    <div className="grid gap-4 p-5 sm:grid-cols-2">
                        <Field label="Router / POP" error={form.errors.router_id}>
                            <Select value={form.data.router_id} onChange={(event) => changeRouter(event.target.value)} required>
                                <option value="">Pilih router</option>
                                {routers.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Paket internet" error={form.errors.package_id}>
                            <Select value={form.data.package_id} onChange={set('package_id')} disabled={!routerId} required>
                                <option value="">{routerId ? 'Pilih paket' : 'Pilih router dulu'}</option>
                                {availablePackages.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} — {formatRupiah(item.price)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Username PPPoE" error={form.errors.pppoe_username}>
                            <Input value={form.data.pppoe_username} onChange={set('pppoe_username')} autoComplete="off" required />
                        </Field>
                        <Field label="Password PPPoE" error={form.errors.pppoe_password} hint={customer ? 'Kosongkan jika tidak ingin mengubah.' : undefined}>
                            <Input type="password" value={form.data.pppoe_password} onChange={set('pppoe_password')} autoComplete="new-password" required={!customer} />
                        </Field>
                        <Field label="ODP" error={form.errors.odp_id} hint={routerId && availableOdps.length === 0 ? 'Belum ada ODP pada router ini.' : undefined}>
                            <Select value={form.data.odp_id} onChange={set('odp_id')} disabled={availableOdps.length === 0}>
                                <option value="">Tanpa ODP</option>
                                {availableOdps.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.code} — {item.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Port ODP" error={form.errors.odp_port}>
                            <Input type="number" min={1} value={form.data.odp_port} onChange={set('odp_port')} disabled={!form.data.odp_id} />
                        </Field>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Penagihan & status" />
                    <div className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Tanggal pasang" error={form.errors.installed_at}>
                            <Input type="date" value={form.data.installed_at} onChange={set('installed_at')} required />
                        </Field>
                        <Field label="Siklus tagihan" error={form.errors.billing_cycle}>
                            <Select value={form.data.billing_cycle} onChange={set('billing_cycle')}>
                                <option value="fixed">Fixed Date (tanggal tetap)</option>
                                <option value="anniversary">Anniversary (mengikuti tanggal pasang)</option>
                            </Select>
                        </Field>
                        {form.data.billing_cycle === 'fixed' ? (
                            <Field label="Tanggal tagihan" error={form.errors.billing_day} hint="1–31 setiap bulan; di bulan yang lebih pendek jatuh pada hari terakhir bulan itu.">
                                <Input type="number" min={1} max={31} value={form.data.billing_day} onChange={set('billing_day')} required />
                            </Field>
                        ) : (
                            <Field label="Tanggal tagihan" hint="Otomatis mengikuti tanggal pasang.">
                                <Input value="Mengikuti tanggal pasang" disabled />
                            </Field>
                        )}
                        <Field label="Jatuh tempo (hari)" error={form.errors.due_days} hint="Setelah invoice terbit.">
                            <Input type="number" min={1} max={60} value={form.data.due_days} onChange={set('due_days')} required />
                        </Field>
                        <Field label="Status layanan" error={form.errors.status}>
                            <Select value={form.data.status} onChange={set('status')}>
                                <option value="active">Aktif</option>
                                <option value="isolated">Isolir</option>
                                <option value="inactive">Nonaktif</option>
                            </Select>
                        </Field>
                        {customer?.promise_to_pay_until && (
                            <div className="rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                Perpanjangan pembayaran aktif sampai <span className="num font-medium text-foreground">{customer.promise_to_pay_until}</span>. Ubah lewat tombol Perpanjangan di Data Pelanggan.
                            </div>
                        )}
                    </div>
                </Card>

                <div className="flex justify-end gap-2">
                    <Link href="/customers" className={buttonClass('close')}>
                        Batal
                    </Link>
                    <Button variant="save" type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan pelanggan'}
                    </Button>
                </div>
            </form>

            <LocationPickerModal open={picking} initial={currentPosition} addressFilled={form.data.address.trim() !== ''} onClose={() => setPicking(false)} onConfirm={applyLocation} />
        </>
    );
}
