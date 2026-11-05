// Pop-up notifications. Blade renders flash messages into #toast-region; this file times
// and dismisses them, and exposes window.showToast() for messages raised in the browser.

const LEAVE_MS = 160;
const READING_MS_PER_CHAR = 60;
const MIN_VISIBLE_MS = { success: 5000, warning: 8000 };

function dismiss(toast) {
    toast.classList.add('is-leaving');
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

function showToast(message, tone = 'success') {
    const region = document.getElementById('toast-region');
    const template = document.querySelector(`[data-toast-template="${tone}"]`);
    if (!region || !template) {
        return;
    }

    const toast = template.content.firstElementChild.cloneNode(true);
    toast.querySelector('[data-toast-message]').textContent = message;
    region.append(toast);
    startTimer(toast);
}

document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) {
        dismiss(close.closest('[data-toast]'));
    }
});

document.querySelectorAll('#toast-region [data-toast]').forEach(startTimer);

window.showToast = showToast;
