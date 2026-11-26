import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from 'react';

export type MapOdp = {
    id: number;
    name: string;
    code: string;
    latitude: string | null;
    longitude: string | null;
    total_ports: number;
    customers_count: number;
    open_outages_count: number;
};

export type MapCustomer = { id: number; customer_code: string; name: string; latitude: string; longitude: string; status: string; odp_id: number | null; cable_path: [number, number][] | null };

type Props = {
    odps: MapOdp[];
    customers: MapCustomer[];
    showCustomers: boolean;
    showLines: boolean;
    pickMode: boolean;
    onPick: (latitude: number, longitude: number) => void;
    editLines?: boolean;
    selectedCustomerId?: number | null;
    onSelectCustomer?: (id: number | null) => void;
    onCableChange?: (customerId: number, path: [number, number][] | null) => void;
};

type CableLine = { line: L.Polyline; odp: L.LatLngTuple; end: L.LatLngTuple; waypoints: L.LatLngTuple[] };

function handleIcon(kind: 'vertex' | 'mid'): L.DivIcon {
    const size = kind === 'vertex' ? 14 : 10;
    const style = kind === 'vertex' ? 'background:#f59e0b;border:2px solid #fff' : 'background:#fff;border:2px solid #f59e0b;opacity:.85';

    return L.divIcon({
        className: '',
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
        html: `<div style="width:${size}px;height:${size}px;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.45);cursor:grab;${style}"></div>`,
    });
}

/**
 * Titik pelanggan sebagai penanda HTML berukuran tetap, bukan lingkaran vektor SVG: Leaflet menskalakan vektor selama animasi
 * zoom lalu menggambarnya ulang, sehingga titik tampak membengkak atau menciut. Penanda HTML hanya berpindah posisi.
 */
function customerIcon(color: string): L.DivIcon {
    return L.divIcon({
        className: '',
        iconSize: [12, 12],
        iconAnchor: [6, 6],
        popupAnchor: [0, -8],
        html: `<div style="width:12px;height:12px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4)"></div>`,
    });
}

/** Popup dibangun dari node DOM (textContent) agar nama pelanggan/ODP tidak pernah ditafsirkan sebagai HTML. */
function popup(title: string, lines: string[]): HTMLElement {
    const root = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = title;
    root.append(heading);

    for (const line of lines) {
        const row = document.createElement('div');
        row.textContent = line;
        root.append(row);
    }

    return root;
}

function odpColor(odp: MapOdp): string {
    if (odp.open_outages_count > 0) {
        return '#ef4444';
    }

    return odp.customers_count >= odp.total_ports ? '#f59e0b' : '#10b981';
}

const statusColor: Record<string, string> = { active: '#6366f1', isolated: '#ef4444', inactive: '#94a3b8' };

export function MapView({ odps, customers, showCustomers, showLines, pickMode, onPick, editLines = false, selectedCustomerId = null, onSelectCustomer, onCableChange }: Props) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const layer = useRef<L.LayerGroup | null>(null);
    const fitted = useRef(false);
    const pickRef = useRef({ pickMode, onPick });
    pickRef.current = { pickMode, onPick };
    const handles = useRef<L.LayerGroup | null>(null);
    const lines = useRef(new Map<number, CableLine>());
    const editRef = useRef({ editLines, selectedCustomerId, onSelectCustomer, onCableChange });
    editRef.current = { editLines, selectedCustomerId, onSelectCustomer, onCableChange };

    /** Gambar ulang pegangan titik belok untuk garis yang sedang dipilih. */
    const renderHandles = () => {
        const group = handles.current;
        group?.clearLayers();
        const { editLines: editing, selectedCustomerId: id, onCableChange: save } = editRef.current;
        const entry = id !== null ? lines.current.get(id) : undefined;

        if (!group || !editing || id === null || !entry) {
            return;
        }

        const fullPath = (): L.LatLngTuple[] => [entry.odp, ...entry.waypoints, entry.end];
        const commit = () => {
            entry.line.setLatLngs(fullPath());
            save?.(id, entry.waypoints.length > 0 ? entry.waypoints.map(([lat, lng]) => [Number(lat.toFixed(7)), Number(lng.toFixed(7))]) : null);
            renderHandles();
        };
        const tuple = (latlng: L.LatLng): L.LatLngTuple => [latlng.lat, latlng.lng];

        entry.waypoints.forEach((point, index) => {
            const marker = L.marker(point, { icon: handleIcon('vertex'), draggable: true, zIndexOffset: 1000 }).addTo(group);
            marker.on('drag', () => {
                entry.waypoints[index] = tuple(marker.getLatLng());
                entry.line.setLatLngs(fullPath());
            });
            marker.on('dragend', commit);
            const remove = () => {
                entry.waypoints.splice(index, 1);
                commit();
            };
            marker.on('dblclick', (event) => {
                L.DomEvent.stopPropagation(event);
                remove();
            });
            marker.on('contextmenu', remove);
        });

        const path = fullPath();

        for (let index = 0; index < path.length - 1; index++) {
            const middle: L.LatLngTuple = [(path[index][0] + path[index + 1][0]) / 2, (path[index][1] + path[index + 1][1]) / 2];
            const marker = L.marker(middle, { icon: handleIcon('mid'), draggable: true, zIndexOffset: 900 }).addTo(group);
            marker.on('drag', () => {
                const preview = [...entry.waypoints];
                preview.splice(index, 0, tuple(marker.getLatLng()));
                entry.line.setLatLngs([entry.odp, ...preview, entry.end]);
            });
            marker.on('dragend', () => {
                entry.waypoints.splice(index, 0, tuple(marker.getLatLng()));
                commit();
            });
        }
    };

    useEffect(() => {
        if (!container.current || map.current) {
            return;
        }

        const instance = L.map(container.current, { center: [-2.5, 118], zoom: 5 });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(instance);
        instance.on('click', (event: L.LeafletMouseEvent) => {
            if (pickRef.current.pickMode) {
                pickRef.current.onPick(Number(event.latlng.lat.toFixed(7)), Number(event.latlng.lng.toFixed(7)));
            }
        });

        instance.doubleClickZoom.disable();

        // Ukuran wadah berubah tanpa peristiwa jendela (sidebar diciutkan, transisi halaman): tanpa ini penanda dan ubin bergeser.
        const resize = new ResizeObserver(() => instance.invalidateSize({ animate: false }));
        resize.observe(container.current);
        layer.current = L.layerGroup().addTo(instance);
        handles.current = L.layerGroup().addTo(instance);
        map.current = instance;

        return () => {
            resize.disconnect();
            instance.remove();
            map.current = null;
            fitted.current = false;
        };
    }, []);

    useEffect(() => {
        const group = layer.current;

        if (!map.current || !group) {
            return;
        }

        group.clearLayers();
        lines.current.clear();
        const points: L.LatLngExpression[] = [];
        const odpPosition = new Map<number, L.LatLngTuple>();

        for (const odp of odps) {
            if (odp.latitude === null || odp.longitude === null) {
                continue;
            }

            const position: L.LatLngTuple = [Number(odp.latitude), Number(odp.longitude)];
            odpPosition.set(odp.id, position);
            points.push(position);

            L.marker(position, {
                icon: L.divIcon({
                    className: '',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                    html: `<div style="width:22px;height:22px;border-radius:6px;background:${odpColor(odp)};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
                }),
            })
                .bindPopup(popup(`${odp.code} · ${odp.name}`, [`Port terpakai: ${odp.customers_count}/${odp.total_ports}`, odp.open_outages_count > 0 ? 'Sedang ada gangguan' : 'Normal']))
                .addTo(group);
        }

        if (showCustomers) {
            for (const customer of customers) {
                const position: L.LatLngTuple = [Number(customer.latitude), Number(customer.longitude)];
                points.push(position);
                const color = statusColor[customer.status] ?? '#6366f1';

                L.marker(position, { icon: customerIcon(color), keyboard: false })
                    .bindPopup(popup(customer.name, [customer.customer_code, customer.status === 'isolated' ? 'Terisolir' : 'Aktif']))
                    .addTo(group);

                const odpPoint = customer.odp_id ? odpPosition.get(customer.odp_id) : undefined;

                if (showLines && odpPoint) {
                    const waypoints: L.LatLngTuple[] = (customer.cable_path ?? []).map(([lat, lng]) => [Number(lat), Number(lng)]);
                    const line = L.polyline([odpPoint, ...waypoints, position], { color, weight: 1.5, opacity: 0.55, dashArray: '6 6', className: 'odp-link-line' }).addTo(group);
                    lines.current.set(customer.id, { line, odp: odpPoint, end: position, waypoints });

                    // Garis tebal transparan sebagai area klik agar mudah dipilih di layar sentuh.
                    L.polyline([odpPoint, ...waypoints, position], { color, weight: 16, opacity: 0, className: 'odp-link-hit' })
                        .on('click', (event) => {
                            if (editRef.current.editLines) {
                                L.DomEvent.stopPropagation(event);
                                editRef.current.onSelectCustomer?.(customer.id);
                            }
                        })
                        .addTo(group);
                }
            }
        }

        if (!fitted.current && points.length > 0) {
            map.current.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
            fitted.current = true;
        }

        renderHandles();
    }, [odps, customers, showCustomers, showLines]);

    useEffect(() => {
        renderHandles();

        if (container.current) {
            container.current.classList.toggle('cable-editing', editLines);
        }
    }, [editLines, selectedCustomerId]);

    useEffect(() => {
        if (container.current) {
            container.current.style.cursor = pickMode ? 'crosshair' : '';
        }
    }, [pickMode]);

    return <div ref={container} className="relative isolate z-0 h-[480px] w-full rounded-b-xl" />;
}
