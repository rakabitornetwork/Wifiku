import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';

type Props = { url: string; title: string; description: string };

export function ConfirmDelete({ url, title, description }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });

    return (
        <>
            <Button variant="remove" size="icon" onClick={() => setOpen(true)} aria-label="Hapus">
                <Trash2 className="size-4" />
            </Button>
            <Modal
                open={open}
                title={title}
                onClose={() => setOpen(false)}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button variant="danger" onClick={submit} disabled={processing}>
                            {processing ? 'Menghapus...' : 'Hapus'}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-muted-foreground">{description}</p>
            </Modal>
        </>
    );
}
