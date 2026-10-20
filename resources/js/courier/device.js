import { api } from './api';

/*
 * Phone capabilities: GPS while on shift, screen wake lock, photo compression, signature pad and
 * barcode scanning. iOS stops web GPS in the background, so tracking runs while the app is open
 * and the wake lock keeps the screen on during a shift.
 */

let watchId = null;
let wakeLock = null;
let lastSent = 0;
let lastPoint = null;

export function lastKnownPoint() {
    return lastPoint;
}

export function startTracking(onUpdate = () => {}) {
    if (!('geolocation' in navigator) || watchId !== null) return;

    watchId = navigator.geolocation.watchPosition(
        async (pos) => {
            lastPoint = { lat: +pos.coords.latitude.toFixed(6), lng: +pos.coords.longitude.toFixed(6) };
            onUpdate({ ok: true, accuracy: pos.coords.accuracy });

            // Send at most every 15 s; the server thins history further.
            if (Date.now() - lastSent < 15000) return;
            lastSent = Date.now();

            const battery = await navigator.getBattery?.().then((b) => Math.round(b.level * 100)).catch(() => null);
            fetch('/courier/location', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    ...lastPoint,
                    accuracy: Math.round(pos.coords.accuracy),
                    speed: pos.coords.speed !== null ? Math.max(0, pos.coords.speed * 3.6) : null,
                    heading: pos.coords.heading,
                    battery,
                }),
            }).catch(() => {});
        },
        (err) => onUpdate({ ok: false, denied: err.code === err.PERMISSION_DENIED }),
        { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 },
    );
}

export function stopTracking() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    watchId = null;
}

export async function keepAwake(on) {
    try {
        if (on && 'wakeLock' in navigator && !wakeLock) {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => (wakeLock = null));
        } else if (!on && wakeLock) {
            await wakeLock.release();
            wakeLock = null;
        }
    } catch {
        // Not supported or refused (battery saver): the app still works, the screen may sleep.
    }
}

// Wake locks drop when the tab hides; take it back when the courier returns.
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && document.body.dataset.shift === 'on') keepAwake(true);
});

/** Downscales a camera photo to max 1600px JPEG (~200–400 KB) before upload. */
export async function compressPhoto(file, maxSide = 1600, quality = 0.72) {
    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) return file;

    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    return blob ? new File([blob], 'pod.jpg', { type: 'image/jpeg' }) : file;
}

export async function uploadPod(taskId, type, file) {
    const form = new FormData();
    form.append('type', type);
    form.append('file', file);
    if (lastPoint) {
        form.append('lat', lastPoint.lat);
        form.append('lng', lastPoint.lng);
    }
    return api.upload(`/courier/tasks/${taskId}/pod`, form);
}

/** Minimal signature pad on a canvas; returns a PNG File, or null when empty. */
export function signaturePad(canvas) {
    const ctx = canvas.getContext('2d');
    const ratio = window.devicePixelRatio || 1;
    let drawing = false;
    let dirty = false;

    const size = () => {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0b1230';
    };
    size();

    const at = (e) => {
        const rect = canvas.getBoundingClientRect();
        return [e.clientX - rect.left, e.clientY - rect.top];
    };

    canvas.addEventListener('pointerdown', (e) => {
        drawing = true;
        dirty = true;
        canvas.setPointerCapture(e.pointerId);
        ctx.beginPath();
        ctx.moveTo(...at(e));
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!drawing) return;
        ctx.lineTo(...at(e));
        ctx.stroke();
    });
    ['pointerup', 'pointercancel'].forEach((ev) => canvas.addEventListener(ev, () => (drawing = false)));

    return {
        clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            dirty = false;
        },
        isEmpty: () => !dirty,
        async toFile() {
            if (!dirty) return null;
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
            return new File([blob], 'signature.png', { type: 'image/png' });
        },
    };
}

/**
 * Barcode scanning through the camera. Uses the native BarcodeDetector where it exists
 * (Android Chrome) and loads ZXing only when it does not (iOS Safari).
 */
export async function scanBarcode(video, onCode) {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    video.srcObject = stream;
    await video.play();

    let stopped = false;
    const stop = () => {
        stopped = true;
        stream.getTracks().forEach((t) => t.stop());
    };

    if ('BarcodeDetector' in window) {
        const detector = new window.BarcodeDetector();
        const loop = async () => {
            if (stopped) return;
            const codes = await detector.detect(video).catch(() => []);
            if (codes.length) {
                onCode(codes[0].rawValue);
                stop();
                return;
            }
            requestAnimationFrame(loop);
        };
        loop();
    } else {
        const { BrowserMultiFormatReader } = await import('@zxing/browser');
        const reader = new BrowserMultiFormatReader();
        const controls = await reader.decodeFromVideoElement(video, (result) => {
            if (result && !stopped) {
                onCode(result.getText());
                controls.stop();
                stop();
            }
        });
    }

    return stop;
}
