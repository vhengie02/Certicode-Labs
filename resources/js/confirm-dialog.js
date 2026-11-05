// Styled replacement for window.confirm(). A form opts in with data-confirm="message", plus
// data-confirm-title and data-confirm-action for the heading and button. Scripts call
// window.confirmDialog(), which resolves to true when the action is confirmed.

const approved = new WeakSet();

function confirmDialog({ title, message, action = 'Confirm' }) {
    const dialog = document.getElementById('confirm-dialog');

    dialog.querySelector('#confirm-dialog-title').textContent = title;
    dialog.querySelector('#confirm-dialog-message').textContent = message;
    dialog.querySelector('[data-confirm-button]').textContent = action;

    // Esc closes without a button, so a stale value from the last use must not count as a yes
    dialog.returnValue = '';

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
        dialog.showModal();
    });
}

// Capture phase, so the form's own submit handlers only run once the action is confirmed
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('[data-confirm]') || approved.delete(form)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const { submitter } = event;
    const { confirm: message, confirmTitle: title, confirmAction: action } = form.dataset;

    if (await confirmDialog({ title, message, action })) {
        approved.add(form);
        form.requestSubmit(submitter);
    }
}, true);

// The panel fills the dialog, so a click landing on the dialog itself is on the backdrop
document.getElementById('confirm-dialog')?.addEventListener('click', (event) => {
    if (event.target === event.currentTarget) {
        event.currentTarget.close();
    }
});

window.confirmDialog = confirmDialog;
