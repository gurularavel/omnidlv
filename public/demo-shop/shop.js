// Nümunə Market order tracking: reads the delivery status from OmniDLV's public endpoint.
(() => {
    const API = `${(window.OMNIDLV && window.OMNIDLV.base) || location.origin}/api/tracking`;
    const TZ = 'Asia/Baku';
    const REFRESH_MS = 30000;

    const STEPS = ['Qəbul edildi', 'Kuryerə verildi', 'Yoldadır', 'Çatdırıldı'];
    const STEP_OF = { created: 0, pool: 0, assigned: 1, in_progress: 1, on_delivery: 2, completed: 3, failed: 3, cancelled: 3 };
    const PILL = {
        created: ['Hazırlanır', ''], pool: ['Hazırlanır', ''],
        assigned: ['Kuryerə verildi', 'go'], in_progress: ['Kuryerdədir', 'go'],
        on_delivery: ['Yoldadır', 'go'], completed: ['Çatdırıldı', 'done'],
        failed: ['Çatdırılmadı', 'bad'], cancelled: ['Ləğv edildi', 'bad'],
    };

    const $ = (sel) => document.querySelector(sel);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Browsers ship patchy Azerbaijani locale data ("M10" for October), so month names are ours.
    const MONTHS = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avq', 'sen', 'okt', 'noy', 'dek'];
    const parts = (iso) => Object.fromEntries(new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: TZ })
        .formatToParts(new Date(iso)).map((p) => [p.type, p.value]));
    const time = (iso) => { if (!iso) return ''; const p = parts(iso); return `${p.hour}:${p.minute}`; };
    const dayTime = (iso) => { if (!iso) return ''; const p = parts(iso); return `${p.day} ${MONTHS[p.month - 1]}, ${p.hour}:${p.minute}`; };
    const money = (n) => `${Number(n).toFixed(2).replace('.', ',')} ₼`;

    let timer = null;
    let current = null;

    async function lookup(params) {
        const res = await fetch(`${API}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json', 'Accept-Language': 'az' } });
        if (res.ok) return { ok: true, data: await res.json() };
        return { ok: false, status: res.status };
    }

    function render(t) {
        const category = t.status.category;
        const reached = STEP_OF[category] ?? 0;
        const problem = category === 'failed' || category === 'cancelled';
        const done = category === 'completed';
        const progress = (reached / 3) * 75; // the bar runs between the first and last dots

        let when = '';
        if (done) when = `Çatdırıldı: <strong>${esc(dayTime(t.status_at))}</strong>`;
        else if (problem) when = esc(t.status.description || 'Ətraflı məlumat üçün mağaza ilə əlaqə saxlayın.');
        else if (t.eta_at) when = `Təxmini çatma: <strong>${esc(time(t.eta_at))}</strong>`;
        else if (t.ends_at) when = `Çatdırılma: <strong>${esc(time(t.starts_at))}–${esc(time(t.ends_at))}</strong>`;

        const address = [t.destination_address, t.destination_address_apartment && `mənzil ${t.destination_address_apartment}`].filter(Boolean).join(', ');
        const history = [...(t.status_logs || [])].reverse()
            .map((l) => `<li><span>${esc(l.name)}</span><time datetime="${esc(l.created_at)}">${esc(dayTime(l.created_at))}</time></li>`).join('');

        $('#result').innerHTML = `
            <div class="result-head">
                <p class="state ${problem ? 'bad' : done ? 'done' : ''}">${esc(t.status.name)}</p>
                <span class="code">${esc(t.tracking_code)}</span>
            </div>
            ${when ? `<p class="when">${when}</p>` : ''}
            <ol class="steps ${problem ? 'bad' : ''}" style="--progress:${progress}%" aria-label="Çatdırılma mərhələləri">
                ${STEPS.map((label, i) => `<li class="${i <= reached ? 'reached' : ''} ${i < reached || (i === reached && t.is_final) ? 'past' : ''}" ${i === reached ? 'aria-current="step"' : ''}><span class="dot"></span>${esc(problem && i === 3 ? (PILL[category] || [label])[0] : label)}</li>`).join('')}
            </ol>
            <dl class="facts">
                <div><dt>Ünvan</dt><dd>${esc(address || '—')}</dd></div>
                <div><dt>Alıcı</dt><dd>${esc(t.destination_name || '—')} · ${esc(t.destination_phone || '')}</dd></div>
                ${t.courier ? `<div><dt>Kuryer</dt><dd>${esc(t.courier.name)}</dd></div>` : ''}
                <div><dt>Çatdıran</dt><dd>${esc(t.company.short_name || t.company.name)}</dd></div>
            </dl>
            ${history ? `<details class="history"><summary>Sifarişin tarixçəsi</summary><ol>${history}</ol></details>` : ''}
            <div class="result-foot">
                <a href="${esc(new URL(`/t/${encodeURIComponent(t.tracking_code)}`, API).href)}" target="_blank" rel="noopener">Xəritədə izlə ↗</a>
                ${t.is_final ? '' : '<span class="live">Hər 30 saniyədən bir yenilənir</span>'}
            </div>`;
    }

    function message(title, text, bad = true) {
        $('#result').innerHTML = `<div class="message ${bad ? 'bad' : ''}"><strong>${esc(title)}</strong>${esc(text)}</div>`;
    }

    async function show(params, button) {
        clearTimeout(timer);
        current = params;
        if (button) button.disabled = true;

        try {
            const res = await lookup(params);
            if (current !== params) return; // a newer search replaced this one
            if (res.ok) {
                render(res.data);
                const url = new URL(location.href);
                url.search = params.code ? `?code=${encodeURIComponent(res.data.tracking_code)}` : '';
                history.replaceState(null, '', url);
                if (!res.data.is_final) timer = setTimeout(() => show(params), REFRESH_MS);
            } else if (res.status === 404) {
                message('Sifariş tapılmadı', params.code
                    ? 'Kodu yoxlayın: hərflər və rəqəmlər SMS-dəki kimi olmalıdır.'
                    : 'Sifariş nömrəsini və telefonun son 4 rəqəmini yoxlayın.');
            } else if (res.status === 429) {
                message('Çox sorğu göndərildi', 'Bir dəqiqədən sonra yenidən yoxlayın.');
            } else if (res.status === 422) {
                message('Məlumat natamamdır', 'Kodu və ya sifariş nömrəsini tam yazın.');
            } else {
                message('Status hazırda alınmadı', 'Bir az sonra yenidən cəhd edin.');
            }
        } catch {
            message('Bağlantı yoxdur', 'İnternet bağlantısını yoxlayıb yenidən cəhd edin.');
        } finally {
            if (button) button.disabled = false;
        }
    }

    // Tabs
    const tabs = [$('#tab-code'), $('#tab-order')];
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute('aria-selected', String(on));
            document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
        document.getElementById(tab.getAttribute('aria-controls')).querySelector('input').focus();
    }));

    $('#form-code').addEventListener('submit', (e) => {
        e.preventDefault();
        show({ code: $('#code').value.trim() }, e.submitter);
    });
    $('#form-order').addEventListener('submit', (e) => {
        e.preventDefault();
        show({ order: $('#order').value.trim(), phone: $('#phone').value.trim() }, e.submitter);
    });
    $('#phone').addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4); });

    // The shop's own order list (its database), each with the live delivery status.
    const COLORS = ['#c2410c', '#0f766e', '#7c3aed', '#be123c', '#1d4ed8'];
    async function loadOrders() {
        const list = $('#orders');
        let orders = [];
        try {
            orders = (await (await fetch('orders.json', { cache: 'no-store' })).json()).orders || [];
        } catch {
            list.innerHTML = '<li class="muted">Demo sifariş yoxdur. Serverdə <code>php artisan demo:shop-orders</code> işlədin.</li>';
            return;
        }

        list.innerHTML = orders.map((o, i) => {
            const first = o.items[0];
            const more = o.items.length > 1 ? ` və daha ${o.items.length - 1}` : '';
            return `<li class="order">
                <span class="thumb" style="background:${COLORS[i % COLORS.length]}" aria-hidden="true">${esc(first.name.charAt(0))}</span>
                <span class="name">${esc(first.name)}${esc(more)}</span>
                <span class="meta">${esc(o.order_number)} · ${esc(dayTime(o.placed_at))} · ${esc(money(o.total))}</span>
                <span class="order-foot"><span class="pill" data-code="${esc(o.tracking_code)}">…</span>
                    <button type="button" class="link" data-track="${esc(o.tracking_code)}">İzlə</button></span>
            </li>`;
        }).join('');

        list.querySelectorAll('[data-track]').forEach((b) => b.addEventListener('click', () => {
            $('#code').value = b.dataset.track;
            tabs[0].click();
            show({ code: b.dataset.track });
            $('#result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }));

        for (const pill of list.querySelectorAll('[data-code]')) {
            const res = await lookup({ code: pill.dataset.code }).catch(() => null);
            const [label, kind] = res?.ok ? (PILL[res.data.status.category] || [res.data.status.name, '']) : ['—', ''];
            pill.textContent = label;
            if (kind) pill.classList.add(kind);
        }
    }

    loadOrders();

    const fromUrl = new URLSearchParams(location.search).get('code');
    if (fromUrl) {
        $('#code').value = fromUrl;
        show({ code: fromUrl });
    }
})();
