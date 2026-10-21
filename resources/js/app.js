import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './echo';
import { createMap, transitLine, station, interchange, courierMarker, L } from './map';
import 'leaflet-draw'; // after ./map, which sets window.L
import { initPhoneInputs } from './phone-input';

initPhoneInputs();

window.Alpine = Alpine;
Alpine.plugin(focus);

Alpine.data('clock', (timezone) => ({
    time: '',
    init() {
        const fmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: timezone });
        const tick = () => (this.time = fmt.format(new Date()));
        tick();
        setInterval(tick, 15000);
    },
}));

Alpine.data('liveStatus', () => ({
    connected: true,
    init() {
        const connection = window.Echo?.connector?.pusher?.connection;
        if (!connection) return;
        const sync = () => (this.connected = connection.state === 'connected');
        connection.bind('state_change', sync);
        sync();
    },
}));

/**
 * Live monitor: map with courier lines + attention list. Data comes from /app/live (JSON);
 * Echo status events trigger a refresh, with polling as the fallback when sockets are down.
 */
Alpine.data('liveMonitor', (endpoint, initial) => {
    // Leaflet objects must stay outside Alpine's reactive proxies.
    let map;
    let layer;

    return {
    data: initial,
    selected: null,
    loading: false,

    init() {
        map = createMap(this.$refs.map);
        layer = L.layerGroup().addTo(map);
        this.draw(true);

        window.Echo?.private(`company.${window.OmniDLV.companyId}`)
            .listen('.task.status', () => this.refresh())
            .listen('.courier.location', () => this.refresh());

        setInterval(() => this.refresh(), 30000);
    },

    async refresh(fit = false) {
        this.loading = true;
        try {
            const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (res.ok) this.data = await res.json();
            this.draw(fit);
        } finally {
            this.loading = false;
        }
    },

    select(id) {
        this.selected = this.selected === id ? null : id;
        this.draw(false);
        const line = this.data.lines.find((l) => l.courier_id === this.selected);
        if (line && line.stops.length) {
            map.flyToBounds(L.latLngBounds(line.path), { padding: [60, 60], duration: 0.6 });
        }
    },

    draw(fit) {
        layer.clearLayers();
        const bounds = [];

        for (const line of this.data.lines) {
            const dim = this.selected !== null && this.selected !== line.courier_id;
            transitLine(line.path, line.ink, { dim }).addTo(layer);
            line.stops.forEach((s) => {
                station([s.lat, s.lng], line.ink, { dim, done: s.done, label: `${s.code} · ${s.minutes_label}` }).addTo(layer);
                bounds.push([s.lat, s.lng]);
            });
        }

        for (const hub of this.data.hubs) {
            interchange([hub.lat, hub.lng], hub.name).addTo(layer);
            bounds.push([hub.lat, hub.lng]);
        }

        for (const c of this.data.couriers) {
            if (c.lat === null) continue;
            courierMarker([c.lat, c.lng], c.ink, c.initials, { online: c.is_online })
                .on('click', () => this.select(c.id))
                .bindTooltip(c.name, { className: 'transit-label', direction: 'top', offset: [0, -16] })
                .addTo(layer);
            bounds.push([c.lat, c.lng]);
        }

        if (fit && bounds.length) {
            map.fitBounds(bounds, { padding: [48, 48], maxZoom: 14 });
        }
    },
    };
});

/** Single point picker for hubs and manual address pins. */
Alpine.data('pointPicker', (lat, lng) => ({
    lat,
    lng,
    init() {
        const has = lat !== null && lng !== null;
        const map = createMap(this.$refs.map, { center: has ? [lat, lng] : undefined, zoom: has ? 15 : 12 });
        let marker = has ? interchange([lat, lng], '').addTo(map) : null;

        map.on('click', (e) => {
            this.lat = e.latlng.lat.toFixed(6);
            this.lng = e.latlng.lng.toFixed(6);
            marker?.remove();
            marker = interchange(e.latlng, '').addTo(map);
        });
        setTimeout(() => map.invalidateSize(), 50);
    },
}));

/** Team coverage polygon editor (leaflet-draw). Stores [[lat, lng], ...] in a hidden input. */
Alpine.data('polygonEditor', (initial, ink, hubs) => ({
    value: initial ? JSON.stringify(initial) : '',
    init() {
        const map = createMap(this.$refs.map);
        const drawn = new L.FeatureGroup().addTo(map);
        const style = { color: ink, weight: 3, fillColor: ink, fillOpacity: 0.18 };

        hubs.forEach((h) => interchange([h.lat, h.lng], h.name).addTo(map));

        if (initial && initial.length > 2) {
            const poly = L.polygon(initial, style).addTo(drawn);
            map.fitBounds(poly.getBounds(), { padding: [40, 40] });
        } else if (hubs.length) {
            map.fitBounds(hubs.map((h) => [h.lat, h.lng]), { padding: [80, 80], maxZoom: 13 });
        }

        map.addControl(new L.Control.Draw({
            position: 'topright',
            edit: { featureGroup: drawn },
            draw: { polygon: { allowIntersection: false, shapeOptions: style }, polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false },
        }));

        const sync = () => {
            const layers = drawn.getLayers();
            this.value = layers.length
                ? JSON.stringify(layers[0].getLatLngs()[0].map((p) => [+p.lat.toFixed(6), +p.lng.toFixed(6)]))
                : '';
        };

        map.on(L.Draw.Event.CREATED, (e) => {
            drawn.clearLayers();
            drawn.addLayer(e.layer);
            sync();
        });
        map.on(L.Draw.Event.EDITED, sync);
        map.on(L.Draw.Event.DELETED, sync);
        setTimeout(() => map.invalidateSize(), 50);
    },
}));

/** Read-only route/points preview (task detail). */
Alpine.data('routePreview', (points, ink) => ({
    init() {
        const map = createMap(this.$refs.map, { zoom: 13 });
        const latlngs = points.map((p) => [p.lat, p.lng]);
        transitLine(latlngs, ink).addTo(map);
        points.forEach((p) => (p.hub ? interchange([p.lat, p.lng], p.label) : station([p.lat, p.lng], ink, { label: p.label })).addTo(map));
        if (latlngs.length) map.fitBounds(latlngs, { padding: [50, 50], maxZoom: 15 });
        setTimeout(() => map.invalidateSize(), 50);
    },
}));

// One courier's planned route: road geometry when the router drew it, stop to stop otherwise.
Alpine.data('planMap', (plan) => ({
    init() {
        const map = createMap(this.$refs.map, { zoom: 12 });
        const stops = plan.stops.map((s) => [s.lat, s.lng]);
        const hub = plan.hub ? [plan.hub.lat, plan.hub.lng] : null;
        const line = plan.geometry.length > 1 ? plan.geometry : [...(hub ? [hub] : []), ...stops];

        if (line.length > 1) transitLine(line, plan.ink).addTo(map);
        if (hub) interchange(hub, plan.hub.label).addTo(map);
        plan.stops.forEach((s) => station([s.lat, s.lng], plan.ink, { done: s.done, label: s.label }).addTo(map));

        const bounds = [...line, ...stops];
        if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
        setTimeout(() => map.invalidateSize(), 50);
    },
}));

Alpine.start();
