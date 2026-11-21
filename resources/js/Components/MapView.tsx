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

export type MapCustomer = { id: number; customer_code: string; name: string; latitude: string; longitude: string; status: string; odp_id: number | null };

type Props = {
    odps: MapOdp[];
    customers: MapCustomer[];
    showCustomers: boolean;
    showLines: boolean;
    pickMode: boolean;
    onPick: (latitude: number, longitude: number) => void;
};

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

export function MapView({ odps, customers, showCustomers, showLines, pickMode, onPick }: Props) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const layer = useRef<L.LayerGroup | null>(null);
    const fitted = useRef(false);
    const pickRef = useRef({ pickMode, onPick });
    pickRef.current = { pickMode, onPick };

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

        layer.current = L.layerGroup().addTo(instance);
        map.current = instance;

        return () => {
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

                L.circleMarker(position, { radius: 5, color: '#fff', weight: 1.5, fillColor: color, fillOpacity: 0.95 })
                    .bindPopup(popup(customer.name, [customer.customer_code, customer.status === 'isolated' ? 'Terisolir' : 'Aktif']))
                    .addTo(group);

                const odpPoint = customer.odp_id ? odpPosition.get(customer.odp_id) : undefined;

                if (showLines && odpPoint) {
                    L.polyline([odpPoint, position], { color, weight: 1.5, opacity: 0.55, dashArray: '4 4' }).addTo(group);
                }
            }
        }

        if (!fitted.current && points.length > 0) {
            map.current.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
            fitted.current = true;
        }
    }, [odps, customers, showCustomers, showLines]);

    useEffect(() => {
        if (container.current) {
            container.current.style.cursor = pickMode ? 'crosshair' : '';
        }
    }, [pickMode]);

    return <div ref={container} className="h-[480px] w-full rounded-b-xl" />;
}
