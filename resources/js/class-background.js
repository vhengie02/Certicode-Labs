// Card background field in the class form. A chosen picture is shrunk here before it uploads, so a
// phone photo fits the upload limit and loses its location data, then shows on the "Your picture" tile.

const MAX_WIDTH = 1600;
const UNREADABLE = "That picture couldn't be read. Use a JPG, PNG or WebP file.";

async function shrink(file) {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_WIDTH / bitmap.width);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff'; // JPEG has no transparency, and would turn clear pixels black
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
    if (!blob) {
        throw new Error('Could not encode the picture');
    }

    return new File([blob], 'background.jpg', { type: 'image/jpeg' });
}

function showError(field, message) {
    const error = field.querySelector('[data-class-background-error]');
    error.textContent = message;
    error.hidden = !message;
}

function showPreview(field, src) {
    const preview = field.querySelector('[data-class-background-preview]');
    preview.src = src;
    preview.hidden = !src;
}

function clearFile(field) {
    field.querySelector('input[type="file"]').value = '';
    showPreview(field, field.querySelector('[data-class-background-preview]').dataset.savedSrc);
    showError(field, '');
}

document.addEventListener('change', async (event) => {
    const input = event.target;
    const field = input.closest?.('[data-class-background]');
    if (!field) {
        return;
    }

    if (input.type === 'radio') {
        if (input.value !== 'picture' && field.querySelector('input[type="file"]')) {
            clearFile(field);
        }
        return;
    }

    const file = input.files[0];
    if (!file) {
        clearFile(field);
        return;
    }

    try {
        const small = await shrink(file);
        const files = new DataTransfer();
        files.items.add(small);
        input.files = files.files;

        showPreview(field, URL.createObjectURL(small));
        showError(field, '');
        field.querySelector('input[value="picture"]').checked = true;
    } catch {
        input.value = '';
        showError(field, UNREADABLE);
    }
});

// "Your picture" has nothing to show yet, so choosing it goes straight to the file picker
document.addEventListener('click', (event) => {
    const field = event.target.closest?.('[data-class-background]');
    const radio = event.target.matches?.('input[value="picture"]') ? event.target : null;
    if (field && radio && field.querySelector('[data-class-background-preview]').hidden) {
        field.querySelector('input[type="file"]')?.click();
    }
});
