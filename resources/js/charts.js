/*
 * Small SVG charts for the analytics page: stacked columns and a single line, one y-axis each.
 * Marks follow the dataviz specs: columns ≤ 24px with a 4px rounded top, 2px surface gaps,
 * 2px lines, hairline grid, one tooltip per x position, keyboard reachable.
 */

const NS = 'http://www.w3.org/2000/svg';
const PAD = { top: 12, right: 12, bottom: 28, left: 40 };

function el(name, attrs = {}, parent = null) {
    const node = document.createElementNS(NS, name);
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
    if (parent) parent.appendChild(node);
    return node;
}

/** Clean axis maximum and ticks: 0, step, 2·step… */
function niceScale(max, ticks = 4) {
    if (max <= 0) return { max: ticks, step: 1 };
    const raw = max / ticks;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
    return { max: Math.ceil(max / step) * step, step };
}

const compact = (n) => (n >= 1000 ? `${+(n / 1000).toFixed(1)}K` : `${+n.toFixed(1)}`);

/** A path for a column with a rounded top and a square base. */
function columnPath(x, y, w, h, r = 4) {
    r = Math.min(r, w / 2, h);
    return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`;
}

function frame(host, height) {
    host.replaceChildren();
    const width = Math.max(280, host.clientWidth);
    const svg = el('svg', { width, height, viewBox: `0 0 ${width} ${height}`, role: 'img', class: 'block' }, host);
    const defs = el('defs', {}, svg);
    // The failed segment also carries a 45° hatch, so it never depends on red vs green alone.
    const hatch = el('pattern', { id: `${host.id}-hatch`, width: 6, height: 6, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)' }, defs);
    el('rect', { width: 6, height: 6, fill: 'var(--chart-failed)' }, hatch);
    el('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: 'var(--surface-raised)', 'stroke-width': 2, 'stroke-opacity': 0.55 }, hatch);
    return { svg, width, height, plotW: width - PAD.left - PAD.right, plotH: height - PAD.top - PAD.bottom };
}

function yAxis(svg, f, scale, format) {
    const g = el('g', { 'aria-hidden': 'true' }, svg);
    for (let v = 0; v <= scale.max + 1e-9; v += scale.step) {
        const y = PAD.top + f.plotH - (v / scale.max) * f.plotH;
        el('line', { x1: PAD.left, x2: f.width - PAD.right, y1: y, y2: y, stroke: 'var(--hairline)', 'stroke-width': 1 }, g);
        const t = el('text', { x: PAD.left - 8, y: y + 4, 'text-anchor': 'end', class: 'chart-tick' }, g);
        t.textContent = format(v);
    }
}

function xLabels(svg, f, points, xOf) {
    const g = el('g', { 'aria-hidden': 'true' }, svg);
    const every = Math.ceil(points.length / Math.max(1, Math.floor(f.plotW / 56)));
    points.forEach((p, i) => {
        if (i % every !== 0) return;
        const t = el('text', { x: xOf(i), y: f.height - 8, 'text-anchor': 'middle', class: 'chart-tick' }, g);
        t.textContent = p.short;
    });
}

/** One tooltip per chart; rows are built with textContent (labels are data). */
function tooltip(host) {
    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    host.appendChild(tip);
    return {
        show(x, title, rows) {
            tip.replaceChildren();
            const h = document.createElement('div');
            h.className = 'chart-tip-title';
            h.textContent = title;
            tip.appendChild(h);
            for (const r of rows) {
                const row = document.createElement('div');
                row.className = 'chart-tip-row';
                const key = document.createElement('span');
                key.className = 'chart-tip-key';
                key.style.background = r.color;
                const value = document.createElement('strong');
                value.textContent = r.value;
                const label = document.createElement('span');
                label.textContent = r.label;
                row.append(key, value, label);
                tip.appendChild(row);
            }
            tip.hidden = false;
            const max = host.clientWidth - tip.offsetWidth - 4;
            tip.style.left = `${Math.max(4, Math.min(max, x - tip.offsetWidth / 2))}px`;
        },
        hide() {
            tip.hidden = true;
        },
    };
}

/**
 * Columns, stacked when several series are given.
 * series: [{ key, label, color, hatch? }] bottom to top.
 */
export function columnChart(host, { points, series, height = 220, title = (p) => p.label }) {
    const f = frame(host, height);
    const total = (p) => series.reduce((s, x) => s + (p[x.key] || 0), 0);
    const scale = niceScale(Math.max(0, ...points.map(total)));
    yAxis(f.svg, f, scale, compact);

    const band = f.plotW / Math.max(1, points.length);
    const w = Math.max(2, Math.min(24, band - 2));
    const xOf = (i) => PAD.left + band * i + band / 2;
    const yOf = (v) => PAD.top + f.plotH - (v / scale.max) * f.plotH;
    xLabels(f.svg, f, points, xOf);

    const tip = tooltip(host);
    const marks = el('g', {}, f.svg);
    points.forEach((p, i) => {
        const g = el('g', { class: 'chart-col', tabindex: 0, role: 'listitem' }, marks);
        let base = 0;
        const visible = series.filter((s) => p[s.key] > 0);
        visible.forEach((s, j) => {
            const v = p[s.key];
            const top = j === visible.length - 1;
            const y0 = yOf(base);
            const y1 = yOf(base + v);
            // 2px surface gap between stacked segments: each segment above the first starts 2px higher.
            const gap = j > 0 ? 2 : 0;
            const h = Math.max(1, y0 - y1 - gap);
            const x = xOf(i) - w / 2;
            const d = top ? columnPath(x, y1, w, h) : `M${x},${y1}h${w}v${h}h${-w}Z`;
            el('path', { d, fill: s.hatch ? `url(#${host.id}-hatch)` : s.color }, g);
            base += v;
        });
        // Hit area: the whole band, taller than the mark.
        el('rect', { x: PAD.left + band * i, y: PAD.top, width: band, height: f.plotH, fill: 'transparent' }, g);
        const rows = series.slice().reverse().map((s) => ({ color: s.color, value: compact(p[s.key] || 0), label: s.label }));
        g.setAttribute('aria-label', `${title(p)}: ${rows.map((r) => `${r.label} ${r.value}`).join(', ')}`);
        const show = () => { g.classList.add('is-hot'); tip.show(xOf(i), title(p), rows); };
        const hide = () => { g.classList.remove('is-hot'); tip.hide(); };
        g.addEventListener('pointerenter', show);
        g.addEventListener('pointerleave', hide);
        g.addEventListener('focus', show);
        g.addEventListener('blur', hide);
    });
    marks.setAttribute('role', 'list');
}

/** A single series line; gaps where the value is null (no deliveries that day). */
export function lineChart(host, { points, key, label, color, height = 180, max = null, format = (v) => v, title = (p) => p.label }) {
    const f = frame(host, height);
    const values = points.map((p) => p[key]);
    const scale = max ? { max, step: max / 4 } : niceScale(Math.max(0, ...values.filter((v) => v !== null)));
    yAxis(f.svg, f, scale, format);

    const band = f.plotW / Math.max(1, points.length);
    const xOf = (i) => PAD.left + band * i + band / 2;
    const yOf = (v) => PAD.top + f.plotH - (v / scale.max) * f.plotH;
    xLabels(f.svg, f, points, xOf);

    let d = '';
    let pen = false;
    values.forEach((v, i) => {
        if (v === null) { pen = false; return; }
        d += `${pen ? 'L' : 'M'}${xOf(i)},${yOf(v)}`;
        pen = true;
    });
    el('path', { d, fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, f.svg);

    // Lone points (a value between two empty days) and the last point get a dot so they are seen.
    values.forEach((v, i) => {
        const lone = v !== null && (values[i - 1] ?? null) === null && (values[i + 1] ?? null) === null;
        const last = v !== null && i === values.findLastIndex((x) => x !== null);
        if (lone || last) el('circle', { cx: xOf(i), cy: yOf(v), r: 4, fill: color, stroke: 'var(--surface-raised)', 'stroke-width': 2 }, f.svg);
    });

    const tip = tooltip(host);
    const cross = el('line', { y1: PAD.top, y2: PAD.top + f.plotH, stroke: 'var(--hairline-strong)', 'stroke-width': 1, visibility: 'hidden' }, f.svg);
    const dot = el('circle', { r: 5, fill: color, stroke: 'var(--surface-raised)', 'stroke-width': 2, visibility: 'hidden' }, f.svg);
    const hit = el('rect', { x: PAD.left, y: PAD.top, width: f.plotW, height: f.plotH, fill: 'transparent', tabindex: 0 }, f.svg);
    hit.setAttribute('aria-label', label);

    let index = points.length - 1;
    const at = (i) => {
        index = Math.max(0, Math.min(points.length - 1, i));
        const v = values[index];
        cross.setAttribute('x1', xOf(index));
        cross.setAttribute('x2', xOf(index));
        cross.setAttribute('visibility', 'visible');
        if (v !== null) {
            dot.setAttribute('cx', xOf(index));
            dot.setAttribute('cy', yOf(v));
            dot.setAttribute('visibility', 'visible');
        } else {
            dot.setAttribute('visibility', 'hidden');
        }
        tip.show(xOf(index), title(points[index]), [{ color, value: v === null ? '—' : format(v), label }]);
    };
    const off = () => { cross.setAttribute('visibility', 'hidden'); dot.setAttribute('visibility', 'hidden'); tip.hide(); };
    hit.addEventListener('pointermove', (e) => {
        const r = f.svg.getBoundingClientRect();
        at(Math.floor((e.clientX - r.left - PAD.left) / band));
    });
    hit.addEventListener('pointerleave', off);
    hit.addEventListener('focus', () => at(index));
    hit.addEventListener('blur', off);
    hit.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            at(index + (e.key === 'ArrowLeft' ? -1 : 1));
        }
    });
}

/** Re-draws on resize so the charts always fill their plate. */
export function responsive(host, draw) {
    draw();
    let width = host.clientWidth;
    new ResizeObserver(() => {
        if (Math.abs(host.clientWidth - width) > 4) {
            width = host.clientWidth;
            draw();
        }
    }).observe(host);
}
