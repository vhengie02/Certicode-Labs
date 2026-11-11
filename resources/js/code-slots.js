// Code Slots from React Bits (https://reactbits.dev), ported from its React component to Alpine
// because this app has no React. <x-code-slots> renders the row, and this state sits on the form
// around it.
//
// The original leaves checking the code to its caller. Here a full code is posted to `url` as
// JSON, and the answer picks the ending: the success wash, then a reload, or the drain with the
// server's message. Motion's springs became CSS transitions with no overshoot, because
// motion.css rules out bounce. Masking, the controlled value and the colour props are left out
// because this page does not use them.

import { reducedMotion } from './motion';

const CASCADE_MS = 20;
const SETTLE_MS = 300;
const WASH_MS = 1100;

const digitsOf = (raw) => String(raw ?? '').replace(/\D/g, '');

export default function codeSlots({ url, length = 6 }) {
    let drainTimer;

    return {
        slots: Array(length).fill(''),
        // The last digit each slot held, so it stays readable while it drains
        shown: Array(length).fill(''),
        delays: Array(length).fill(0),
        active: 0,
        focused: false,
        status: 'idle',
        message: '',
        sending: false,
        draining: false,

        init() {
            // Without this script the row stays a plain text field that posts the form
            this.$root.querySelector('.code-slots').setAttribute('data-live', '');
            this.$refs.input.required = false;
        },

        destroy() {
            clearTimeout(drainTimer);
        },

        get full() {
            return this.slots.every(Boolean);
        },

        get locked() {
            return this.sending || this.draining || this.status === 'success';
        },

        get caretShown() {
            return this.focused && !this.locked && !this.slots[this.active];
        },

        get progress() {
            if (this.status === 'success') return 'Code accepted';
            return `${this.slots.filter(Boolean).length} of ${length} digits entered`;
        },

        firstEmpty() {
            const index = this.slots.indexOf('');
            return index === -1 ? length - 1 : index;
        },

        insert(raw, from = this.active) {
            const digits = digitsOf(raw);
            if (!digits || this.locked) return;
            const wasFull = this.full;
            const step = reducedMotion() ? 0 : CASCADE_MS;
            let index = from;
            for (const digit of digits) {
                if (index >= length) break;
                this.slots[index] = digit;
                this.shown[index] = digit;
                this.delays[index] = (index - from) * step;
                index += 1;
            }
            this.status = 'idle';
            this.message = '';
            this.active = Math.min(index, length - 1);
            if (!wasFull && this.full) this.submit();
        },

        clear(index, stepBack = false) {
            if (this.slots[index]) {
                this.slots[index] = '';
                this.delays[index] = 0;
                this.status = 'idle';
            }
            if (stepBack) this.active = index;
        },

        key(event) {
            if (this.locked || event.metaKey || event.ctrlKey || event.altKey) return;
            const moves = {
                ArrowLeft: () => (this.active = Math.max(this.active - 1, 0)),
                ArrowRight: () => (this.active = Math.min(this.active + 1, length - 1)),
                Home: () => (this.active = 0),
                End: () => (this.active = length - 1),
                Delete: () => this.clear(this.active),
                Backspace: () => {
                    if (this.slots[this.active]) this.clear(this.active);
                    else if (this.active > 0) this.clear(this.active - 1, true);
                },
            };
            if (/^[0-9]$/.test(event.key)) {
                event.preventDefault();
                this.insert(event.key);
            } else if (moves[event.key]) {
                event.preventDefault();
                moves[event.key]();
            }
        },

        paste(event) {
            this.insert(event.clipboardData.getData('text'), 0);
        },

        // Phone keyboards and one-time-code autofill arrive here instead of keydown
        typed(event) {
            const digits = digitsOf(event.target.value);
            event.target.value = '';
            this.insert(digits, digits.length === 1 ? this.active : 0);
        },

        point(event) {
            event.preventDefault();
            if (!this.locked) {
                const slots = [...this.$refs.row.querySelectorAll('.code-slots__slot')];
                const index = slots.findIndex((slot) => event.clientX < slot.getBoundingClientRect().right);
                this.active = Math.min(index === -1 ? length - 1 : index, this.firstEmpty());
            }
            this.$refs.input.focus();
        },

        async submit() {
            if (!this.full || this.locked) return;
            this.sending = true;
            let response;
            let body = {};
            try {
                response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ code: this.slots.join('') }),
                });
                body = await response.json().catch(() => ({}));
            } catch (error) {
                this.sending = false;
                this.reject("We couldn't reach the server. Check your connection and try again.");
                return;
            }
            this.sending = false;

            if (response.ok) {
                this.status = 'success';
                setTimeout(() => window.location.reload(), reducedMotion() ? 400 : WASH_MS);
            } else if (response.status === 419) {
                this.reject('This page expired. Reload it and try again.');
            } else if (response.status === 429) {
                this.reject('Too many tries. Wait a minute, then try again.');
            } else {
                this.reject(body.errors?.code?.[0] ?? 'Something went wrong. Please try again.');
            }
        },

        // Drains the digits last to first under the danger tint, which stays until the next digit
        reject(message) {
            const step = reducedMotion() ? 0 : CASCADE_MS;
            const filled = this.slots.flatMap((digit, index) => (digit ? [index] : [])).reverse();
            filled.forEach((index, order) => {
                this.delays[index] = order * step;
                this.slots[index] = '';
            });
            this.message = message;
            this.status = 'error';
            this.active = 0;
            this.draining = true;
            clearTimeout(drainTimer);
            drainTimer = setTimeout(() => (this.draining = false), Math.max(filled.length - 1, 0) * step + SETTLE_MS);
        },
    };
}
