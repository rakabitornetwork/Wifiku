import { router, useForm } from '@inertiajs/react';
import { Download, Pencil, Plus } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { HotspotTabs } from '@/Components/HotspotTabs';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';
import { EmptyState, Table, TableWrap, Td, SortTh, Th } from '@/Components/ui/Table';
import { formatDuration, formatRupiah } from '@/lib/utils';
import type { Paginated } from '@/types';
import { useServerSort } from '@/Hooks/useSort';

type ProfileRow = {
    id: number;
    router_id: number;
    name: string;
    mikrotik_profile: string;
    price: number;
    reseller_price: number | null;
    validity_minutes: number;
    uptime_limit_minutes: number | null;
    data_limit_mb: number | null;
    rate_limit: string | null;
    shared_users: number;
    is_active: boolean;
    router: { name: string };
    vouchers_count: number;
};

type RouterProfile = { name: string; rate_limit: string | null; shared_users: number; price: number; validity_minutes: number; exists: boolean };

const unitMinutes: Record<string, number> = { minutes: 1, hours: 60, days: 1440 };

const emptyForm = {
    router_id: '',
    name: '',
    mikrotik_profile: '',
    price: '',
    reseller_price: '',
    validity_value: '1',
    validity_unit: 'days',
    uptime_limit_minutes: '',
    data_limit_mb: '',
    rate_limit: '',
    shared_users: '1',
    is_active: true,
};

function splitValidity(minutes: number): { value: string; unit: string } {
    if (minutes % 1440 === 0) {
        return { value: String(minutes / 1440), unit: 'days' };
    }

    if (minutes % 60 === 0) {
        return { value: String(minutes / 60), unit: 'hours' };
    }

    return { value: String(minutes), unit: 'minutes' };
}

export default function HotspotProfiles({ profiles, routers }: { profiles: Paginated<ProfileRow>; routers: { id: number; name: string }[] }) {
    const sort = useServerSort();
    const [editing, setEditing] = useState<ProfileRow | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);
    const [importOpen, setImportOpen] = useState(false);
    const [importRouterId, setImportRouterId] = useState('');
    const [routerProfiles, setRouterProfiles] = useState<RouterProfile[] | null>(null);
    const [importError, setImportError] = useState<string | null>(null);
    const [importLoading, setImportLoading] = useState(false);
    const [chosen, setChosen] = useState<string[]>([]);
    const [importing, setImporting] = useState(false);

    useEffect(() => {
        setRouterProfiles(null);
        setImportError(null);
        setChosen([]);

        if (!importOpen || !importRouterId) {
            return;
        }

        setImportLoading(true);
        fetch(`/hotspot/profile-import/${importRouterId}`, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? 'Gagal membaca router.');
                }

                setRouterProfiles(data.profiles);
                setChosen((data.profiles as RouterProfile[]).filter((profile) => !profile.exists).map((profile) => profile.name));
            })
            .catch((exception: Error) => setImportError(exception.message))
            .finally(() => setImportLoading(false));
    }, [importOpen, importRouterId]);

    const submitImport = () =>
        router.post(`/hotspot/profile-import/${importRouterId}`, { names: chosen }, { preserveScroll: true, onStart: () => setImporting(true), onFinish: () => setImporting(false), onSuccess: () => setImportOpen(false) });

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (profile: ProfileRow) => {
        const validity = splitValidity(profile.validity_minutes);
        setEditing(profile);
        form.setData({
            router_id: String(profile.router_id),
            name: profile.name,
            mikrotik_profile: profile.mikrotik_profile,
            price: String(profile.price),
            reseller_price: profile.reseller_price === null ? '' : String(profile.reseller_price),
            validity_value: validity.value,
            validity_unit: validity.unit,
            uptime_limit_minutes: profile.uptime_limit_minutes === null ? '' : String(profile.uptime_limit_minutes),
            data_limit_mb: profile.data_limit_mb === null ? '' : String(profile.data_limit_mb),
            rate_limit: profile.rate_limit ?? '',
            shared_users: String(profile.shared_users),
            is_active: profile.is_active,
        });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform(({ validity_value, validity_unit, ...rest }) => ({
            ...rest,
            validity_minutes: Math.round(Number(validity_value) * unitMinutes[validity_unit]),
        }));
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.put(`/hotspot/profiles/${editing.id}`, options);
        } else {
            form.post('/hotspot/profiles', options);
        }
    };

    return (
        <>
            <PageHeader
                title="Voucher Hotspot"
                description="Profil harga dan masa aktif voucher per router."
                actions={
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setImportOpen(true)}>
                            <Download className="size-4" /> Impor dari Router
                        </Button>
                        <Button variant="add" onClick={openCreate}>
                            <Plus className="size-4" /> Tambah Profil
                        </Button>
                    </div>
                }
            />
            <HotspotTabs />

            <Card>
                {profiles.data.length === 0 ? (
                    <EmptyState title="Belum ada profil hotspot" description="Buat profil yang namanya sama dengan Hotspot User Profile di MikroTik." />
                ) : (
                    <TableWrap>
                        <Table>
                            <thead>
                                <tr>
                                    <SortTh column="name" state={sort}>Profil</SortTh>
                                    <SortTh column="router" state={sort}>Router</SortTh>
                                    <SortTh column="mikrotik_profile" state={sort}>Profile MikroTik</SortTh>
                                    <SortTh column="price" state={sort} className="text-right">Harga jual</SortTh>
                                    <SortTh column="validity" state={sort}>Masa aktif</SortTh>
                                    <SortTh column="vouchers" state={sort}>Voucher</SortTh>
                                    <SortTh column="status" state={sort}>Status</SortTh>
                                    <Th className="text-right">Aksi</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {profiles.data.map((profile) => (
                                    <tr key={profile.id} className="hover:bg-muted/40">
                                        <Td className="font-medium">{profile.name}</Td>
                                        <Td>{profile.router.name}</Td>
                                        <Td className="num text-muted-foreground">{profile.mikrotik_profile}</Td>
                                        <Td className="num text-right font-medium">{formatRupiah(profile.price)}</Td>
                                        <Td className="num">{formatDuration(profile.validity_minutes)}</Td>
                                        <Td className="num">{profile.vouchers_count}</Td>
                                        <Td className="text-center">{profile.is_active ? <Badge tone="green">Dijual</Badge> : <Badge>Nonaktif</Badge>}</Td>
                                        <Td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="edit" size="icon" onClick={() => openEdit(profile)} aria-label="Ubah">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDelete url={`/hotspot/profiles/${profile.id}`} title={`Hapus profil ${profile.name}?`} description="Profil yang sudah dipakai voucher tidak dapat dihapus." />
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </TableWrap>
                )}
                <Pagination meta={profiles} />
            </Card>

            <Modal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                size="lg"
                title="Impor Profil Hotspot dari Router"
                description="Membaca Hotspot User Profile yang sudah ada di RouterOS. Router tidak diubah."
                footer={
                    <>
                        <Button variant="close" onClick={() => setImportOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={submitImport} disabled={importing || chosen.length === 0}>
                            {importing ? 'Mengimpor...' : `Impor ${chosen.length} profil`}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4">
                    <Select value={importRouterId} onChange={(event) => setImportRouterId(event.target.value)} aria-label="Router sumber">
                        <option value="">Pilih router</option>
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                    {importLoading && <p className="text-sm text-muted-foreground">Membaca profil dari router...</p>}
                    {importError && <p className="text-sm text-destructive">{importError}</p>}
                    {routerProfiles && routerProfiles.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada Hotspot User Profile selain bawaan di router ini.</p>}
                    {routerProfiles && routerProfiles.length > 0 && (
                        <>
                            <div className="max-h-80 overflow-auto rounded-md border">
                                <Table>
                                    <thead>
                                        <tr>
                                            <Th className="w-10" />
                                            <Th>Profile</Th>
                                            <Th>Rate limit</Th>
                                            <Th className="text-right">Harga</Th>
                                            <Th>Masa aktif</Th>
                                            <Th>Status</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {routerProfiles.map((profile) => (
                                            <tr key={profile.name}>
                                                <Td>
                                                    <input
                                                        type="checkbox"
                                                        disabled={profile.exists}
                                                        checked={chosen.includes(profile.name)}
                                                        onChange={(event) => setChosen((current) => (event.target.checked ? [...current, profile.name] : current.filter((name) => name !== profile.name)))}
                                                        aria-label={`Pilih ${profile.name}`}
                                                        className="size-4 rounded border-input accent-[var(--primary)]"
                                                    />
                                                </Td>
                                                <Td className="num font-medium">{profile.name}</Td>
                                                <Td className="num text-muted-foreground">{profile.rate_limit ?? '-'}</Td>
                                                <Td className="num text-right">{profile.price > 0 ? formatRupiah(profile.price) : '-'}</Td>
                                                <Td className="num">{formatDuration(profile.validity_minutes)}</Td>
                                                <Td className="text-center">{profile.exists ? <Badge>Sudah ada</Badge> : <Badge tone="blue">Baru</Badge>}</Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                            </div>
                            <p className="text-xs text-muted-foreground">Harga dan masa aktif dibaca dari skrip on-login (format Mikhmon). Profil tanpa harga diimpor dalam keadaan nonaktif; isi harganya dulu sebelum dijual.</p>
                        </>
                    )}
                </div>
            </Modal>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="lg"
                title={editing ? 'Ubah Profil Hotspot' : 'Tambah Profil Hotspot'}
                footer={
                    <>
                        <Button variant="close" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button variant="save" type="submit" form="profile-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="profile-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Router" error={form.errors.router_id}>
                        <Select value={form.data.router_id} onChange={(event) => form.setData('router_id', event.target.value)} required>
                            <option value="">Pilih router</option>
                            {routers.map((router) => (
                                <option key={router.id} value={router.id}>
                                    {router.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Nama profil" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Voucher 1 Hari" required />
                    </Field>
                    <Field label="Hotspot User Profile di MikroTik" error={form.errors.mikrotik_profile}>
                        <Input value={form.data.mikrotik_profile} onChange={(event) => form.setData('mikrotik_profile', event.target.value)} required />
                    </Field>
                    <Field label="Rate limit" error={form.errors.rate_limit} hint="Contoh: 5M/5M">
                        <Input value={form.data.rate_limit} onChange={(event) => form.setData('rate_limit', event.target.value)} />
                    </Field>
                    <Field label="Harga jual (Rp)" error={form.errors.price}>
                        <Input type="number" min={0} value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} required />
                    </Field>
                    <Field label="Harga reseller (Rp)" error={form.errors.reseller_price} hint="Kosong = harga jual dipotong diskon reseller.">
                        <Input type="number" min={0} value={form.data.reseller_price} onChange={(event) => form.setData('reseller_price', event.target.value)} />
                    </Field>
                    <Field label="Masa aktif (sejak login pertama)" error={(form.errors as Record<string, string | undefined>).validity_minutes}>
                        <div className="flex gap-2">
                            <Input type="number" min={1} value={form.data.validity_value} onChange={(event) => form.setData('validity_value', event.target.value)} required />
                            <Select className="w-28" value={form.data.validity_unit} onChange={(event) => form.setData('validity_unit', event.target.value)}>
                                <option value="minutes">Menit</option>
                                <option value="hours">Jam</option>
                                <option value="days">Hari</option>
                            </Select>
                        </div>
                    </Field>
                    <Field label="Shared users" error={form.errors.shared_users}>
                        <Input type="number" min={1} max={255} value={form.data.shared_users} onChange={(event) => form.setData('shared_users', event.target.value)} required />
                    </Field>
                    <Field label="Batas uptime (menit)" error={form.errors.uptime_limit_minutes}>
                        <Input type="number" min={1} value={form.data.uptime_limit_minutes} onChange={(event) => form.setData('uptime_limit_minutes', event.target.value)} />
                    </Field>
                    <Field label="Batas kuota (MB)" error={form.errors.data_limit_mb}>
                        <Input type="number" min={1} value={form.data.data_limit_mb} onChange={(event) => form.setData('data_limit_mb', event.target.value)} />
                    </Field>
                    <div className="sm:col-span-2">
                        <Checkbox label="Profil aktif (dapat dipakai untuk batch baru)" checked={form.data.is_active} onChange={(checked) => form.setData('is_active', checked)} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
