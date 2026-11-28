import { router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { ConfirmDelete } from '@/Components/ConfirmDelete';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Checkbox, Field, Input } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';

type Photo = { id: number; title: string; caption: string | null; is_published: boolean; thumb: string };

type FormData = { photo: File | null; title: string; caption: string; is_published: boolean };

const empty: FormData = { photo: null, title: '', caption: '', is_published: true };

export default function GalleryIndex({ photos, limit }: { photos: Photo[]; limit: number }) {
    const form = useForm<FormData>(empty);
    const [editing, setEditing] = useState<Photo | null>(null);
    const [open, setOpen] = useState(false);

    const openCreate = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (photo: Photo) => {
        setEditing(photo);
        form.setData({ photo: null, title: photo.title, caption: photo.caption ?? '', is_published: photo.is_published });
        form.clearErrors();
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, forceFormData: true, onSuccess: () => setOpen(false) };

        if (editing) {
            router.put(`/galeri-landing/${editing.id}`, { title: form.data.title, caption: form.data.caption, is_published: form.data.is_published }, { ...options, onError: form.setError });
        } else {
            form.post('/galeri-landing', options);
        }
    };

    const toggle = (photo: Photo) =>
        router.put(`/galeri-landing/${photo.id}`, { title: photo.title, caption: photo.caption ?? '', is_published: !photo.is_published }, { preserveScroll: true });

    return (
        <>
            <PageHeader
                title="Galeri Landing"
                description={`Foto hasil instalasi yang tampil di landing page (${photos.length}/${limit}). Gunakan foto asli dan pastikan pelanggan setuju rumahnya difoto.`}
                actions={
                    <Button onClick={openCreate} disabled={photos.length >= limit}>
                        <Plus className="size-4" /> Tambah Foto
                    </Button>
                }
            />

            {photos.length === 0 ? (
                <Card className="p-10 text-center text-sm text-muted-foreground">Belum ada foto. Bagian galeri disembunyikan di landing page sampai ada foto yang tayang.</Card>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {photos.map((photo) => (
                        <Card key={photo.id} className="overflow-hidden">
                            <img src={photo.thumb} alt={photo.title} loading="lazy" className="aspect-[4/3] w-full object-cover" />
                            <div className="space-y-2 p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium">{photo.title}</div>
                                        {photo.caption && <div className="truncate text-xs text-muted-foreground">{photo.caption}</div>}
                                    </div>
                                    <Badge tone={photo.is_published ? 'green' : 'slate'}>{photo.is_published ? 'Tayang' : 'Draft'}</Badge>
                                </div>
                                <div className="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" onClick={() => toggle(photo)} aria-label={photo.is_published ? 'Sembunyikan dari landing' : 'Tayangkan di landing'}>
                                        {photo.is_published ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </Button>
                                    <Button variant="ghost" size="icon" onClick={() => openEdit(photo)} aria-label="Ubah foto">
                                        <Pencil className="size-4" />
                                    </Button>
                                    <ConfirmDelete url={`/galeri-landing/${photo.id}`} title={`Hapus foto "${photo.title}"?`} description="Foto dihapus permanen dari galeri dan server." />
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title={editing ? 'Ubah Foto' : 'Tambah Foto'}
                footer={
                    <>
                        <Button variant="close" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" form="gallery-form" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form id="gallery-form" onSubmit={submit} className="grid gap-4">
                    {!editing && (
                        <Field label="Foto (JPG, PNG, atau WebP, maks. 8 MB)" error={form.errors.photo}>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                onChange={(event) => form.setData('photo', event.target.files?.[0] ?? null)}
                                className="text-sm file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-1.5 file:text-sm"
                            />
                        </Field>
                    )}
                    <Field label="Judul" error={form.errors.title}>
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} maxLength={80} placeholder="Pemasangan di Desa Sukamaju" required />
                    </Field>
                    <Field label="Keterangan (opsional)" error={form.errors.caption}>
                        <Input value={form.data.caption} onChange={(event) => form.setData('caption', event.target.value)} maxLength={160} placeholder="Kabel dropcore rapi, ONT di ruang tamu" />
                    </Field>
                    <Checkbox label="Tayangkan di landing page" checked={form.data.is_published} onChange={(checked) => form.setData('is_published', checked)} />
                    <p className="text-xs text-muted-foreground">Foto disimpan ulang dalam format WebP sehingga data lokasi (GPS) di dalam file asli otomatis terhapus.</p>
                </form>
            </Modal>
        </>
    );
}
