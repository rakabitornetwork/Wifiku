import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { LocateFixed } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/Components/ui/Button';
import { Checkbox } from '@/Components/ui/Field';
import { Modal } from '@/Components/ui/Modal';

export type PickedLocation = { latitude: string; longitude: string; address?: string; village?: string; addressFailed?: boolean };

type Position = { latitude: number; longitude: number };

type Props = {
    open: boolean;
    initial: Position | null;
    addressFilled: boolean;
    onClose: () => void;
    onConfirm: (location: PickedLocation) => void;
};

const pinIcon = L.divIcon({
    className: '',
    iconSize: [26, 26],
    iconAnchor: [13, 13],
    html: '<div style="width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#6366f1;border:3px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.5)"></div>',
});

/** Alamat dan desa dari OpenStreetMap (Nominatim). Hanya dipanggil saat pengguna menekan tombol konfirmasi. */
async function reverseGeocode({ latitude, longitude }: Position): Promise<{ address: string; village: string }> {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 8000);

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=id&lat=${latitude}&lon=${longitude}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error('geocode failed');
        }

        const data = (await response.json()) as { display_name?: string; address?: Record<string, string> };
        const parts = (data.display_name ?? '').split(', ').filter((part) => part !== 'Indonesia' && !/^\d{5}$/.test(part));
        const area = data.address ?? {};

        return { address: parts.join(', '), village: area.village ?? area.suburb ?? area.hamlet ?? area.neighbourhood ?? '' };
    } finally {
        window.clearTimeout(timer);
    }
}

function PickerMap({ position, onChange }: { position: Position | null; onChange: (position: Position) => void }) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const marker = useRef<L.Marker | null>(null);
    const changeRef = useRef(onChange);
    changeRef.current = onChange;

    useEffect(() => {
        if (!container.current) {
            return;
        }

        const instance = L.map(container.current, position ? { center: [position.latitude, position.longitude], zoom: 17 } : { center: [-2.5, 118], zoom: 5 });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(instance);
        instance.on('click', (event: L.LeafletMouseEvent) => changeRef.current({ latitude: event.latlng.lat, longitude: event.latlng.lng }));
        map.current = instance;

        // Modal baru terbuka: ukuran kontainer baru final setelah render pertama.
        const resize = window.setTimeout(() => instance.invalidateSize(), 50);

        return () => {
            window.clearTimeout(resize);
            instance.remove();
            map.current = null;
            marker.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!map.current || !position) {
            return;
        }

        const latlng: L.LatLngTuple = [position.latitude, position.longitude];

        if (marker.current) {
            marker.current.setLatLng(latlng);
        } else {
            marker.current = L.marker(latlng, { icon: pinIcon, draggable: true }).addTo(map.current);
            marker.current.on('dragend', () => {
                const point = marker.current?.getLatLng();

                if (point) {
                    changeRef.current({ latitude: point.lat, longitude: point.lng });
                }
            });
        }

        if (!map.current.getBounds().contains(latlng)) {
            map.current.setView(latlng, Math.max(map.current.getZoom(), 17));
        }
    }, [position]);

    return <div ref={container} className="h-80 w-full rounded-lg border" />;
}

export function LocationPickerModal({ open, initial, addressFilled, onClose, onConfirm }: Props) {
    const [position, setPosition] = useState<Position | null>(initial);
    const [fillAddress, setFillAddress] = useState(!addressFilled);
    const [locating, setLocating] = useState(false);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setPosition(initial);
            setFillAddress(!addressFilled);
            setMessage(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setMessage('Perangkat ini tidak mendukung GPS di browser.');

            return;
        }

        setLocating(true);
        setMessage(null);
        navigator.geolocation.getCurrentPosition(
            (result) => {
                setPosition({ latitude: result.coords.latitude, longitude: result.coords.longitude });
                setLocating(false);
            },
            (error) => {
                setMessage(error.code === error.PERMISSION_DENIED ? 'Izin lokasi ditolak. Aktifkan izin lokasi di browser, atau klik peta untuk memilih manual.' : 'Lokasi tidak dapat diambil. Coba lagi atau klik peta untuk memilih manual.');
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    const confirm = async () => {
        if (!position) {
            return;
        }

        const picked: PickedLocation = { latitude: position.latitude.toFixed(7), longitude: position.longitude.toFixed(7) };

        if (fillAddress) {
            setSaving(true);

            try {
                Object.assign(picked, await reverseGeocode(position));
            } catch {
                picked.addressFailed = true;
            }

            setSaving(false);
        }

        onConfirm(picked);
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title="Pilih posisi GPS pelanggan"
            description="Klik peta atau geser pin ke rumah pelanggan, atau gunakan lokasi perangkat ini."
            footer={
                <>
                    <Button type="button" variant="close" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="button" onClick={confirm} disabled={!position || saving}>
                        {saving ? 'Mengambil alamat...' : 'Gunakan lokasi ini'}
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" variant="outline" size="sm" onClick={useMyLocation} disabled={locating}>
                        <LocateFixed className={locating ? 'size-4 animate-pulse' : 'size-4'} /> {locating ? 'Mencari lokasi...' : 'Lokasi saya'}
                    </Button>
                    <span className="num text-xs text-muted-foreground">{position ? `${position.latitude.toFixed(7)}, ${position.longitude.toFixed(7)}` : 'Belum ada titik dipilih'}</span>
                </div>
                {message && <p className="text-sm text-destructive">{message}</p>}
                <PickerMap position={position} onChange={setPosition} />
                <Checkbox label="Isi juga Alamat dan Desa dari lokasi ini" checked={fillAddress} onChange={setFillAddress} />
                {fillAddress && <p className="text-xs text-muted-foreground">Alamat diambil dari OpenStreetMap; koordinat titik dikirim ke layanan itu. Periksa dan lengkapi (RT/RW, nomor rumah) sesudahnya.</p>}
            </div>
        </Modal>
    );
}
