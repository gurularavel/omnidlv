/**
 * Phone fields (<input data-phone>): only digits, with one leading "+", grouped as the
 * number is typed. Azerbaijani numbers get the familiar 050 123 45 67 / +994 50 123 45 67
 * shape; other country codes are grouped in threes. The server still normalises to E.164,
 * so the spaces are only for the eye.
 */
const MAX_DIGITS = 15; // E.164 limit

/** Digits grouped by sizes; whatever is left over goes in the last group. */
function group(digits, sizes) {
    const parts = [];
    let i = 0;
    for (const size of sizes) {
        if (i >= digits.length) break;
        parts.push(digits.slice(i, i + size));
        i += size;
    }
    if (i < digits.length) parts.push(digits.slice(i));
    return parts.join(' ');
}

export function formatPhone(value) {
    const raw = String(value ?? '');
    // International when a "+" comes before the first digit ("tel: +994 …" counts too).
    const plus = raw.replace(/[^\d+]/g, '').startsWith('+');
    let digits = raw.replace(/\D/g, '');

    if (plus) {
        digits = digits.slice(0, MAX_DIGITS);
        if (digits.startsWith('994')) return '+' + group(digits, [3, 2, 3, 2, 2]); // +994 50 123 45 67
        if (digits.startsWith('90')) return '+' + group(digits, [2, 3, 3, 2, 2]);  // +90 532 123 45 67
        if (digits.startsWith('1')) return '+' + group(digits, [1, 3, 3, 4]);    // +1 415 555 0123
        if (digits.startsWith('7')) return '+' + group(digits, [1, 3, 3, 2, 2]);   // +7 912 345 67 89
        return '+' + group(digits, [3, 3, 3, 3, 3]);
    }

    if (digits.startsWith('0')) {
        digits = digits.slice(0, 11);
        // 050 123 45 67 (Azerbaijan, 10 digits) or 0532 123 45 67 (Türkiye, 11 digits)
        return group(digits, digits.length === 11 ? [4, 3, 2, 2] : [3, 3, 2, 2]);
    }
    return group(digits.slice(0, 9), [2, 3, 2, 2]); // 50 123 45 67
}

/** Re-formats in place and keeps the caret after the same digit it was after. */
function reformat(input) {
    const before = input.value;
    const caret = input.selectionStart ?? before.length;
    const digitsBeforeCaret = before.slice(0, caret).replace(/[^\d+]/g, '').length;
    const after = formatPhone(before);

    if (after === before) return;
    input.value = after;

    let seen = 0;
    let pos = 0;
    while (pos < after.length && seen < digitsBeforeCaret) {
        if (/[\d+]/.test(after[pos])) seen++;
        pos++;
    }
    if (document.activeElement === input) input.setSelectionRange(pos, pos);
}

export function initPhoneInputs(root = document) {
    // Block letters before they land, so the field never flickers with them.
    root.addEventListener('beforeinput', (e) => {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-phone')) return;
        // Only single keystrokes; a multi-character insert (autofill, IME) keeps its digits and
        // is cleaned by the input handler below.
        if (e.inputType === 'insertText' && e.data?.length === 1 && /[^\d+\s]/.test(e.data)) e.preventDefault();
    });

    // Paste, autofill and everything else are cleaned up here.
    root.addEventListener('input', (e) => {
        if (e.target instanceof HTMLInputElement && e.target.hasAttribute('data-phone')) reformat(e.target);
    });

    const formatExisting = () => root.querySelectorAll('input[data-phone]').forEach((input) => {
        if (input.value) input.value = formatPhone(input.value);
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', formatExisting);
    else formatExisting();
}
