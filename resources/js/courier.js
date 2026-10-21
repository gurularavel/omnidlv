import Alpine from 'alpinejs';
import { api, ApiError } from './courier/api';
import { compressPhoto, keepAwake, lastKnownPoint, scanBarcode, signaturePad, startTracking, stopTracking, uploadPod } from './courier/device';
import { createMap, transitLine, station, courierMarker, L } from './map';
import { initPhoneInputs } from './phone-input';

initPhoneInputs();

window.Alpine = Alpine;

const withPoint = (body = {}) => ({ ...body, ...(lastKnownPoint() ?? {}) });
const message = (error) => (error instanceof ApiError ? error.message : String(error));

/** POST that returns the JSON body for 2xx and 4xx alike (code checks and scans answer 422/404 with data). */
async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
    if (res.status === 401 || res.status === 419) window.location.href = '/courier/login';
    return res.json();
}

/**
 * Directions: Google Maps or Waze. The courier picks each time, or saves a choice on this
 * phone (localStorage) and the button then opens that app straight away.
 */
const NAV_KEY = 'courier-nav-app';
const savedNavApp = () => {
    try {
        const app = localStorage.getItem(NAV_KEY);
        return ['google', 'waze'].includes(app) ? app : null;
    } catch {
        return null;
    }
};

Alpine.store('nav', {
    app: savedNavApp(),
    links: null,
    open(links) {
        if (!links) return;
        if (this.app && links[this.app]) return this.launch(links[this.app]);
        this.links = links;
    },
    go(app, remember = false) {
        if (remember) {
            this.app = app;
            try { localStorage.setItem(NAV_KEY, app); } catch {}
        }
        const url = this.links?.[app];
        this.links = null;
        if (url) this.launch(url);
    },
    forget() {
        this.app = null;
        try { localStorage.removeItem(NAV_KEY); } catch {}
    },
    close() {
        this.links = null;
    },
    launch(url) {
        // A new tab hands off to the installed app; fall back to this tab if popups are blocked.
        // ("noopener" would make window.open return null, so the opener is cut by hand.)
        const tab = window.open(url, '_blank');
        if (tab) tab.opener = null;
        else window.location.href = url;
    },
});

/** One task: forward steps, proof of delivery, failure. */
Alpine.data('courierTask', (initial, labels) => {
    // Non-reactive handles (canvas pad, camera stop function).
    let stopCamera = null;

    return {
        t: initial,
        labels,
        busy: false,
        error: null,
        notice: null,
        sheet: null,
        outcome: 'delivered',
        receivedBy: '',
        reason: null,
        notes: '',
        code: '',
        manualCode: '',
        scanMessage: null,
        queued: api.pending(),
        pad: null,

        init() {
            startTracking();
            window.addEventListener('courier:queue', (e) => (this.queued = e.detail));
            window.addEventListener('courier:rejected', (e) => (this.error = e.detail.message));
            api.flush();

            // "Hand over" on the home screen lands here with the confirmation already open.
            const url = new URL(window.location.href);
            if (url.searchParams.has('handover')) {
                url.searchParams.delete('handover');
                history.replaceState(null, '', url);
                if (this.t.next === 'complete' && this.t.can_complete) this.sheet = 'complete';
            }
        },

        async run(promise) {
            this.busy = true;
            this.error = null;
            try {
                const res = await promise;
                if (res && !res.queued && res.id) this.t = res;
                // Saved offline: say so plainly, or the courier walks away thinking it was sent.
                if (res?.queued) this.error = labels.queued;
                return res;
            } catch (e) {
                this.error = message(e);
            } finally {
                this.busy = false;
            }
        },

        primary() {
            const urls = {
                claim: [`/courier/tasks/${this.t.id}/claim`, {}],
                pickup: [`/courier/tasks/${this.t.id}/step`, { action: 'pickup' }],
                depart: [`/courier/tasks/${this.t.id}/step`, { action: 'depart' }],
                arrive: [`/courier/tasks/${this.t.id}/step`, { action: 'arrive' }],
            };
            const [url, body] = urls[this.t.next];
            return this.run(api.act(url, withPoint(body)));
        },

        async complete() {
            const res = await this.run(api.act(`/courier/tasks/${this.t.id}/complete`, withPoint({
                status: this.outcome,
                received_by: this.outcome === 'delivered_to_other' ? this.receivedBy : null,
            })));
            if (res) this.sheet = null;
            if (res && !res.queued && res.final) {
                this.notice = labels.handedOver;
                setTimeout(() => (window.location.href = '/courier'), 1800);
            }
        },

        async fail() {
            const res = await this.run(api.act(`/courier/tasks/${this.t.id}/fail`, withPoint({ status: this.reason, notes: this.notes })));
            if (res) this.sheet = null;
        },

        async verifyCode() {
            this.busy = true;
            this.error = null;
            try {
                // A wrong code is a 422 that still carries the task, so read the body either way.
                const res = await postJson(`/courier/tasks/${this.t.id}/code`, { code: this.code });
                if (res?.task) this.t = res.task;
                if (!res?.verified) this.error = res?.message ?? labels.wrongCode;
            } catch (e) {
                this.error = message(e);
            } finally {
                this.busy = false;
                this.code = '';
            }
        },

        async photo(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            await this.run(compressPhoto(file).then((f) => uploadPod(this.t.id, 'photo', f)));
        },

        initSignature() {
            if (!this.pad) this.pad = signaturePad(this.$refs.sig);
        },

        async saveSignature() {
            const file = await this.pad?.toFile();
            if (!file) return;
            const res = await this.run(uploadPod(this.t.id, 'signature', file));
            if (res) {
                this.pad.clear();
                this.sheet = null;
            }
        },

        async openScanner() {
            this.sheet = 'scan';
            this.scanMessage = null;
            await this.$nextTick();
            try {
                stopCamera = await scanBarcode(this.$refs.video, (code) => this.submitScan(code));
            } catch {
                this.scanMessage = labels.cameraDenied;
            }
        },

        closeScanner() {
            stopCamera?.();
            stopCamera = null;
            this.sheet = null;
        },

        async submitScan(code) {
            if (!code) return;
            const res = await postJson(`/courier/tasks/${this.t.id}/scan`, { code }).catch(() => null);

            if (res?.task) this.t = res.task;
            this.scanMessage = res?.matched ? `${labels.scanned}: ${res.item.name}` : `${code}: ${labels.noMatch}`;
            this.manualCode = '';
            if (res?.matched) setTimeout(() => this.closeScanner(), 700);
        },
    };
});

/**
 * Home: the next stop owns the screen; the rest of the day lives in the drawer.
 * Refreshes every 30 s and whenever the app comes back to the foreground.
 */
Alpine.data('courierBoard', (initial, labels) => {
    let map = null;
    let mapEl = null;
    let layer = null;

    return {
        b: initial,
        labels,
        busy: false,
        error: null,
        menu: false,
        drawer: false,
        tab: 'route',
        queued: api.pending(),
        gpsDenied: false,

        get n() {
            return this.b.next;
        },

        init() {
            window.addEventListener('courier:queue', (e) => (this.queued = e.detail));
            window.addEventListener('courier:rejected', (e) => (this.error = e.detail.message));
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.refresh());
            setInterval(() => this.refresh(), 30000);

            if (this.b.shift.active) this.track(true);
            api.flush().then(() => this.refresh());
            // The map container only exists while on shift with a next stop: redraw on either change.
            this.$watch('b.next', () => this.$nextTick(() => this.drawMap()));
            this.$watch('b.shift.active', () => this.$nextTick(() => this.drawMap()));
            this.$nextTick(() => this.drawMap());
        },

        track(on) {
            document.body.dataset.shift = on ? 'on' : 'off';
            if (on) {
                startTracking((s) => (this.gpsDenied = !s.ok && s.denied));
                keepAwake(true);
            } else {
                stopTracking();
                keepAwake(false);
            }
        },

        async refresh() {
            try {
                this.b = await api.get('/courier/data');
            } catch {
                // Offline: keep showing the last state.
            }
        },

        async run(promise) {
            this.busy = true;
            this.error = null;
            try {
                const res = await promise;
                if (res?.queued) this.error = labels.queued;
                await this.refresh();
                return res;
            } catch (e) {
                this.error = message(e);
            } finally {
                this.busy = false;
            }
        },

        async shift(on) {
            const res = await this.run(api.act(on ? '/courier/shift/start' : '/courier/shift/end', withPoint()));
            if (res && !this.error) {
                this.track(on);
                this.menu = false;
            }
        },

        primary() {
            const n = this.n;
            // Handing over needs proof (photo, signature, code): that lives on the task screen.
            if (n.next === 'complete') {
                window.location.href = `${n.url}?handover=1`;
                return;
            }
            const action = { pickup: 'pickup', depart: 'depart', arrive: 'arrive' }[n.next];
            const url = n.next === 'claim' ? `/courier/tasks/${n.id}/claim` : `/courier/tasks/${n.id}/step`;
            return this.run(api.act(url, withPoint(action ? { action } : {})));
        },

        claim(task) {
            return this.run(api.act(`/courier/tasks/${task.id}/claim`, withPoint()));
        },

        drawMap() {
            const el = this.$refs.map;
            const n = this.n;
            if (!el || !n || n.target.lat === null) return;

            if (mapEl !== el) {
                map?.remove();
                // A glance strip, not a map to operate: no controls, no gestures.
                map = createMap(el, { porcelain: true, zoom: 14, controls: false });
                map.dragging.disable();
                map.scrollWheelZoom.disable();
                map.touchZoom.disable();
                map.doubleClickZoom.disable();
                layer = L.layerGroup().addTo(map);
                mapEl = el;
            }

            layer.clearLayers();
            const ink = this.b.courier.ink;
            const stop = [n.target.lat, n.target.lng];
            const points = [stop];
            const here = lastKnownPoint() ?? this.b.position;

            if (here) {
                const me = [here.lat, here.lng];
                transitLine([me, stop], ink, { surface: '#ffffff' }).addTo(layer);
                courierMarker(me, ink, '').addTo(layer);
                points.push(me);
            }
            station(stop, ink, { surface: '#ffffff' }).addTo(layer);
            map.fitBounds(L.latLngBounds(points), { paddingTopLeft: [32, 32], paddingBottomRight: [44, 36], maxZoom: 15 });
            setTimeout(() => map.invalidateSize(), 50);
        },
    };
});

/** Shift on/off, shared by the board. */
export async function setShift(on) {
    const res = await api.act(on ? '/courier/shift/start' : '/courier/shift/end', withPoint());
    if (on) {
        startTracking();
        keepAwake(true);
    } else {
        stopTracking();
        keepAwake(false);
    }
    return res;
}

window.OmniCourier = { api, setShift, startTracking, keepAwake };

Alpine.start();
