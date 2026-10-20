import Alpine from 'alpinejs';
import { createMap, station, courierMarker, L } from './map';

// Recipient tracking page: destination as a station, the courier as a live marker that
// refreshes from the public tracking API while the order is still moving.
const el = document.querySelector('[data-tracking-map]');

if (el) {
    const cfg = JSON.parse(el.dataset.trackingMap);
    const map = createMap(el, { porcelain: true, zoom: 14 });
    const points = [];

    if (cfg.destination[0]) {
        station(cfg.destination, cfg.ink, { surface: '#ffffff' }).addTo(map);
        points.push(cfg.destination);
    }

    let courier = null;
    const placeCourier = (latlng) => {
        if (!latlng || latlng[0] === null) return;
        if (courier) courier.setLatLng(latlng);
        else courier = courierMarker(latlng, cfg.ink, '').addTo(map);
    };

    placeCourier(cfg.courier);
    if (cfg.courier) points.push(cfg.courier);
    if (points.length) map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 15 });

    if (cfg.live) {
        setInterval(async () => {
            const res = await fetch(`/api/tracking?code=${encodeURIComponent(cfg.code)}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const loc = data.courier?.courier_location;
            placeCourier(loc ? [loc.lat, loc.lng] : null);
            if (data.is_final) window.location.reload();
        }, 20000);
    }
}

window.Alpine = Alpine;
Alpine.start();
