// Pop-up notifications. Blade renders flash messages into #toast-region; this file times
// and dismisses them, and exposes window.showToast() for messages raised in the browser.

import { EASE_IN_OUT, MOVE_MS, reducedMotion } from './motion';

const ENTER_DELAY_MS = 200;
const LEAVE_MS = 224;
const READING_MS_PER_CHAR = 60;
const MIN_VISIBLE_MS = { success: 3000, warning: 3000 };
const SWIPE_DISMISS_RATIO = 0.35;
const RUBBER_BAND_LIMIT_PX = 48;

// The toasts below slide up into the gap instead of jumping.
function removeAndCloseGap(toast) {
    const others = [...toast.parentElement.children].filter((other) => other !== toast);
    const tops = others.map((other) => other.getBoundingClientRect().top);
    toast.remove();

    if (reducedMotion()) {
        return;
    }
    others.forEach((other, index) => {
        const shift = tops[index] - other.getBoundingClientRect().top;
        if (shift) {
            other.animate(
                { transform: [`translateY(${shift}px)`, 'none'] },
                { duration: MOVE_MS, easing: EASE_IN_OUT },
            );
        }
    });
}

function dismiss(toast) {
    if (toast.classList.contains('is-leaving')) {
        return;
    }
    toast.classList.remove('is-dragging');
    toast.style.translate = '';
    toast.classList.replace('is-shown', 'is-leaving');
    setTimeout(() => removeAndCloseGap(toast), LEAVE_MS);
}

// Errors stay until closed: they are often the only copy of what went wrong with a form.
function startTimer(toast) {
    const minimum = MIN_VISIBLE_MS[toast.dataset.tone];
    if (!minimum) {
        return { start() {}, pause() {} };
    }

    const visibleMs = Math.max(minimum, toast.textContent.trim().length * READING_MS_PER_CHAR);
    let timer;
    const pause = () => clearTimeout(timer);
    const start = () => {
        pause();
        timer = setTimeout(() => dismiss(toast), visibleMs);
    };

    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', start);
    start();

    return { start, pause };
}

// Past the bound the toast still follows the finger, with less and less give the further it goes.
function rubberBand(distance) {
    const stretched = 1 - 1 / ((Math.abs(distance) * 0.55) / RUBBER_BAND_LIMIT_PX + 1);
    return Math.sign(distance) * stretched * RUBBER_BAND_LIMIT_PX;
}

// Swipe right to dismiss. Touch and pen only: a mouse drag here selects the toast's text, which
// people copy out of error messages.
function enableSwipe(toast, timer) {
    let startX = null;
    let offset = 0;

    toast.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || event.target.closest('a, button')) {
            return;
        }
        startX = event.clientX;
        offset = 0;
        toast.setPointerCapture(event.pointerId);
        toast.classList.add('is-dragging');
        timer.pause();
    });

    toast.addEventListener('pointermove', (event) => {
        if (startX === null) {
            return;
        }
        offset = event.clientX - startX;
        toast.style.translate = `${offset > 0 ? offset : rubberBand(offset)}px 0`;
    });

    const release = () => {
        if (startX === null) {
            return;
        }
        startX = null;
        if (offset > toast.offsetWidth * SWIPE_DISMISS_RATIO) {
            dismiss(toast);
            return;
        }
        toast.classList.remove('is-dragging');
        toast.style.translate = '';
        timer.start();
    };

    toast.addEventListener('pointerup', release);
    toast.addEventListener('pointercancel', release);
}

function reveal(toast) {
    toast.getBoundingClientRect(); // Commits the hidden position, or a new toast skips the slide-in.
    toast.classList.add('is-shown');
    enableSwipe(toast, startTimer(toast));
}

// Flash messages wait for the page to load and paint, so they arrive on a finished page.
function afterPageLoad(callback) {
    const schedule = () => requestAnimationFrame(() => setTimeout(callback, ENTER_DELAY_MS));

    if (document.readyState === 'complete') {
        schedule();
    } else {
        window.addEventListener('load', schedule, { once: true });
    }
}

function showToast(message, tone = 'success') {
    const region = document.getElementById('toast-region');
    const template = document.querySelector(`[data-toast-template="${tone}"]`);
    if (!region || !template) {
        return;
    }

    const toast = template.content.firstElementChild.cloneNode(true);
    toast.querySelector('[data-toast-message]').textContent = message;
    region.append(toast);
    reveal(toast);
}

document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) {
        dismiss(close.closest('[data-toast]'));
    }
});

afterPageLoad(() => document.querySelectorAll('#toast-region [data-toast]:not(.is-shown)').forEach(reveal));

window.showToast = showToast;
