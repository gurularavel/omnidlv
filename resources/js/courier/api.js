/*
 * Courier API client with an offline queue. Actions get a client UUID (X-Action-Id) so a
 * replay after reconnecting is acknowledged, not repeated, by the server. The queue lives in
 * localStorage; photos are too large for it, so uploads only run online.
 */
const QUEUE_KEY = 'omnidlv.courier.queue';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(message, status, body) {
        super(message);
        this.status = status;
        this.body = body;
    }
}

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function writeQueue(items) {
    try {
        localStorage.setItem(QUEUE_KEY, JSON.stringify(items));
    } catch {
        // Storage full or blocked: the action stays in memory for this session only.
    }
    window.dispatchEvent(new CustomEvent('courier:queue', { detail: items.length }));
}

async function send(url, { method = 'POST', body = null, actionId = null, form = null } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
    if (actionId) headers['X-Action-Id'] = actionId;
    if (body && !form) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, { method, headers, body: form ?? (body ? JSON.stringify(body) : null), credentials: 'same-origin' });
    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (res.status === 401 || res.status === 419) {
        window.location.href = '/courier/login';
        throw new ApiError('signed out', res.status, data);
    }

    if (!res.ok) {
        throw new ApiError(data?.message ?? res.statusText, res.status, data);
    }

    return data;
}

export const api = {
    get: (url) => send(url, { method: 'GET' }),

    upload: (url, formData) => send(url, { form: formData }),

    /**
     * Sends an action now; if the network is down it is queued and replayed later.
     * Resolves to the server's response, or to { queued: true } when queued.
     */
    async act(url, body = {}) {
        const actionId = crypto.randomUUID();

        try {
            return await send(url, { body, actionId });
        } catch (error) {
            if (error instanceof ApiError) throw error; // the server answered: not a network problem
            writeQueue([...readQueue(), { url, body, actionId, at: Date.now() }]);
            return { queued: true };
        }
    },

    pending: () => readQueue().length,

    /** Replays queued actions in order. Business refusals (4xx) are dropped and reported. */
    async flush() {
        const items = readQueue();
        const failed = [];
        let index = 0;

        for (; index < items.length; index++) {
            const item = items[index];
            try {
                await send(item.url, { body: item.body, actionId: item.actionId });
            } catch (error) {
                if (!(error instanceof ApiError)) break; // still offline: keep the rest for later
                failed.push({ ...item, message: error.message });
            }
        }

        writeQueue(items.slice(index));
        failed.forEach((f) => window.dispatchEvent(new CustomEvent('courier:rejected', { detail: f })));

        return { sent: index - failed.length, failed: failed.length, left: items.length - index };
    },
};

window.addEventListener('online', () => api.flush());
