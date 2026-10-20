import L from 'leaflet';

// leaflet-draw and other plugins expect the global.
window.L = L;

const BAKU = [40.4093, 49.8671];

/** A Leaflet map in the transit world: tiles re-inked for midnight unless `porcelain`. */
export function createMap(el, { center = BAKU, zoom = 12, porcelain = false } = {}) {
    const map = L.map(el, { zoomControl: false, attributionControl: true }).setView(center, zoom);

    L.tileLayer(window.OmniDLV?.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: window.OmniDLV?.attribution ?? '&copy; OpenStreetMap contributors',
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    el.classList.add(porcelain ? 'map-porcelain' : 'map-night');

    if (!porcelain) {
        // Enamel tint: a world-sized rectangle in its own pane between tiles (200) and
        // overlays (400). Being a sibling of the tile pane inside the map pane, its
        // `mix-blend-mode: color` (app.css) actually blends with the tiles, unlike a blend
        // set on the tile pane, which Leaflet's transformed pane isolates.
        map.createPane('enamel').style.zIndex = 250;
        L.rectangle([[-85, -180], [85, 180]], {
            pane: 'enamel',
            renderer: L.svg({ pane: 'enamel', padding: 1 }),
            stroke: false,
            fillColor: '#24388a',
            fillOpacity: 1,
            interactive: false,
        }).addTo(map);
    }

    return map;
}

/** A courier's route drawn as a transit line: dark casing, ink core, station ticks on stops. */
export function transitLine(points, ink, { dim = false, surface = '#0b1230' } = {}) {
    const group = L.layerGroup();

    if (points.length > 1) {
        L.polyline(points, { color: surface, weight: 11, opacity: dim ? 0.35 : 0.9, lineCap: 'round', lineJoin: 'round', interactive: false }).addTo(group);
        L.polyline(points, { color: ink, weight: 6, opacity: dim ? 0.3 : 1, lineCap: 'round', lineJoin: 'round', interactive: false }).addTo(group);
    }

    return group;
}

export function station(latlng, ink, { done = false, dim = false, surface = '#0b1230', label = null } = {}) {
    const marker = L.circleMarker(latlng, {
        radius: 6,
        weight: 3,
        color: ink,
        fillColor: done ? ink : '#f4f2ec',
        fillOpacity: 1,
        opacity: dim ? 0.45 : 1,
    });

    if (label) {
        marker.bindTooltip(label, { className: 'transit-label', direction: 'right', offset: [8, 0] });
    }

    return marker;
}

/** Hubs are interchanges: a heavy porcelain ring. */
export function interchange(latlng, label, { surface = '#0b1230', ring = '#f4f2ec' } = {}) {
    return L.circleMarker(latlng, {
        radius: 10,
        weight: 4,
        color: ring,
        fillColor: surface,
        fillOpacity: 1,
    }).bindTooltip(label, { className: 'transit-label', direction: 'right', offset: [12, 0], permanent: true });
}

export function courierMarker(latlng, ink, initials, { online = true } = {}) {
    return L.marker(latlng, {
        icon: L.divIcon({
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            html: `<span style="display:grid;place-items:center;width:30px;height:30px;border-radius:8px;background:${ink};color:#fff;font:600 12px 'Fira Sans Condensed';letter-spacing:.06em;border:2px solid #f4f2ec;box-shadow:0 4px 12px rgb(0 0 0/.45);opacity:${online ? 1 : 0.55}">${initials}</span>`,
        }),
    });
}

export { L };
