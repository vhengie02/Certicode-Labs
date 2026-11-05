// Pop-up notifications. Blade renders flash messages into #toast-region; this file times
// and dismisses them, and exposes window.showToast() for messages raised in the browser.

const ENTER_DELAY_MS = 200;
const LEAVE_MS = 275;
const READING_MS_PER_CHAR = 60;
const MIN_VISIBLE_MS = { success: 3000, warning: 3000 };

function dismiss(toast) {
    toast.classList.replace('is-shown', 'is-leaving');
    setTimeout(() => toast.remove(), LEAVE_MS);
}

// Errors stay until closed: they are often the only copy of what went wrong with a form.
function startTimer(toast) {
    const minimum = MIN_VISIBLE_MS[toast.dataset.tone];
    if (!minimum) {
        return;
    }

    const visibleMs = Math.max(minimum, toast.textContent.trim().length * READING_MS_PER_CHAR);
    let timer;
    const start = () => {
        timer = setTimeout(() => dismiss(toast), visibleMs);
    };
    const pause = () => clearTimeout(timer);

    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', start);
    start();
}

function reveal(toast) {
    toast.getBoundingClientRect(); // Commits the hidden position, or a new toast skips the slide-in.
    toast.classList.add('is-shown');
    startTimer(toast);
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
