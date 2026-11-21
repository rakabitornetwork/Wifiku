import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';
import { Table, TableWrap, Td, Th } from '@/Components/ui/Table';

type Secret = { username: string; profile: string; disabled: boolean; display_name: string; whatsapp: string; has_password: boolean; exists: boolean };
type Profile = { name: string; count: number; rate_limit: string | null; is_isolir: boolean; suggested_package_id: number | null };
type PackageOption = { id: number; name: string; price: number; mikrotik_profile: string };
type Preview = { secrets: Secret[]; profiles: Profile[]; packages: PackageOption[]; isolir_profile: string; isolir_profile_found: boolean };
type Mapping = { package: string; price: string };

type Props = { router: { id: number; name: string } | null; onClose: () => void };

export function ImportPppModal({ router, onClose }: Props) {
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const form = useForm<{ mappings: Record<string, Mapping>; billing_day: string; due_days: string; bill_from: string }>({
        mappings: {},
        billing_day: '1',
        due_days: '7',
        bill_from: 'next_cycle',
    });
    const errors = form.errors as Record<string, string | undefined>;

    useEffect(() => {
        setPreview(null);
        setError(null);

        if (!router) {
            return;
        }

        fetch(`/routers/${router.id}/import-preview`, { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? 'Gagal membaca router.');
                }

                setPreview(data as Preview);
                form.setData(
                    'mappings',
                    Object.fromEntries((data as Preview).profiles.map((profile) => [profile.name, { package: profile.suggested_package_id ? String(profile.suggested_package_id) : '', price: '' }])),
                );
            })
            .catch((exception: Error) => setError(exception.message));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [router?.id]);

    const setMapping = (profile: string, patch: Partial<Mapping>) => form.setData('mappings', { ...form.data.mappings, [profile]: { ...form.data.mappings[profile], ...patch } });

    const importable = preview?.secrets.filter((secret) => !secret.exists && secret.has_password) ?? [];
    const chosen = preview?.profiles.filter((profile) => form.data.mappings[profile.name]?.package) ?? [];
    const toImport = importable.filter((secret) => form.data.mappings[secret.profile]?.package).length;

    const submit = () => {
        if (router) {
            form.post(`/routers/${router.id}/import`, { preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <Modal
            open={router !== null}
            onClose={onClose}
            size="lg"
            title={`Impor pelanggan · ${router?.name ?? ''}`}
            description="Membaca PPP secret dari router. Router tidak diubah sama sekali."
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={!preview || toImport === 0 || form.processing}>
                        {form.processing ? 'Mengimpor...' : `Impor ${toImport} pelanggan`}
                    </Button>
                </>
            }
        >
            {error && <p className="text-sm text-destructive">{error}</p>}
            {!preview && !error && <p className="text-sm text-muted-foreground">Membaca router...</p>}

            {preview && (
                <div className="space-y-5">
                    <p className="text-sm text-muted-foreground">
                        <span className="num font-medium text-foreground">{preview.secrets.length}</span> secret PPPoE ditemukan,{' '}
                        <span className="num">{preview.secrets.filter((secret) => secret.exists).length}</span> sudah menjadi pelanggan,{' '}
                        <span className="num">{preview.secrets.filter((secret) => !secret.has_password).length}</span> tanpa password (dilewati).
                    </p>

                    {!preview.isolir_profile_found && (
                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                            Profil isolir router di aplikasi (<span className="num font-medium">{preview.isolir_profile}</span>) tidak ditemukan di router. Pelanggan yang sedang diisolir bisa terbaca sebagai Aktif. Samakan nama
                            profile isolir dengan yang ada di router (ubah di Manajemen Router &gt; Ubah) sebelum impor.
                        </p>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-semibold">1. Petakan profile ke paket</h3>
                        <TableWrap>
                            <Table>
                                <thead>
                                    <tr>
                                        <Th>Profile router</Th>
                                        <Th>Akan diimpor</Th>
                                        <Th>Paket</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {preview.profiles.map((profile) => {
                                        const mapping = form.data.mappings[profile.name] ?? { package: '', price: '' };

                                        return (
                                            <tr key={profile.name}>
                                                <Td className="align-top">
                                                    <span className="num font-medium">{profile.name}</span>
                                                    {profile.rate_limit && <div className="num text-xs text-muted-foreground">{profile.rate_limit}</div>}
                                                    {profile.is_isolir && <Badge tone="amber">Profile isolir</Badge>}
                                                </Td>
                                                <Td className="num align-top">{profile.count}</Td>
                                                <Td className="space-y-2">
                                                    <Select value={mapping.package} onChange={(event) => setMapping(profile.name, { package: event.target.value })} aria-label={`Paket untuk ${profile.name}`}>
                                                        <option value="">Lewati profile ini</option>
                                                        {!profile.is_isolir && <option value="new">+ Buat paket baru “{profile.name}”</option>}
                                                        {preview.packages.map((item) => (
                                                            <option key={item.id} value={item.id}>
                                                                {item.name} ({item.mikrotik_profile})
                                                            </option>
                                                        ))}
                                                    </Select>
                                                    {mapping.package === 'new' && (
                                                        <Field label="Harga paket baru (Rp / bulan)" error={errors[`mappings.${profile.name}.price`]}>
                                                            <Input type="number" min={0} value={mapping.price} onChange={(event) => setMapping(profile.name, { price: event.target.value })} />
                                                        </Field>
                                                    )}
                                                    {errors[`mappings.${profile.name}.package`] && <p className="text-xs text-destructive">{errors[`mappings.${profile.name}.package`]}</p>}
                                                    {profile.is_isolir && mapping.package && <p className="text-xs text-muted-foreground">Pelanggan di profile ini diimpor sebagai Terisolir; pilih paket aslinya.</p>}
                                                </Td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </Table>
                        </TableWrap>
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-semibold">2. Pengaturan tagihan</h3>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Tanggal tagihan (1–28)" error={errors.billing_day}>
                                <Input type="number" min={1} max={28} value={form.data.billing_day} onChange={(event) => form.setData('billing_day', event.target.value)} />
                            </Field>
                            <Field label="Jatuh tempo (hari)" error={errors.due_days}>
                                <Input type="number" min={1} max={60} value={form.data.due_days} onChange={(event) => form.setData('due_days', event.target.value)} />
                            </Field>
                            <Field label="Mulai ditagih" error={errors.bill_from}>
                                <Select value={form.data.bill_from} onChange={(event) => form.setData('bill_from', event.target.value)}>
                                    <option value="next_cycle">Siklus berikutnya (disarankan)</option>
                                    <option value="now">Sekarang (buat invoice prorata)</option>
                                </Select>
                            </Field>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Pelanggan lama sebaiknya mulai ditagih di siklus berikutnya agar tidak langsung dikirimi invoice. Tanggal tagihan bisa diubah per pelanggan nanti.
                        </p>
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-semibold">Contoh data yang akan dibuat</h3>
                        <TableWrap>
                            <Table>
                                <thead>
                                    <tr>
                                        <Th>Username</Th>
                                        <Th>Nama</Th>
                                        <Th>WhatsApp</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {importable.slice(0, 6).map((secret) => (
                                        <tr key={secret.username}>
                                            <Td className="num">{secret.username}</Td>
                                            <Td>{secret.display_name}</Td>
                                            <Td className="num text-muted-foreground">{secret.whatsapp || '-'}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </TableWrap>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Nama dan nomor WhatsApp diambil dari kolom comment secret. Bila kosong, nama memakai username dan nomor dikosongkan. {chosen.length === 0 && 'Pilih paket untuk minimal satu profile.'}
                        </p>
                    </div>
                </div>
            )}
        </Modal>
    );
}
