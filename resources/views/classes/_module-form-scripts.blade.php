{{-- Rich-text editor and file picker behaviour for the module form. --}}
<style>
    /* CKEditor follows the app theme instead of a bright white box */
    .module-editor .ck.ck-editor__main > .ck-editor__editable { min-height: 16rem; }
    html.dark .module-editor {
        --ck-color-base-background: #141414;
        --ck-color-base-foreground: #1c1c1c;
        --ck-color-base-border: #2e2e2e;
        --ck-color-text: #ededed;
        --ck-color-toolbar-background: #171717;
        --ck-color-toolbar-border: #2e2e2e;
        --ck-color-button-default-hover-background: #232323;
        --ck-color-button-on-background: #262626;
        --ck-color-button-on-hover-background: #2e2e2e;
        --ck-color-button-on-color: #3ecf8e;
        --ck-color-dropdown-panel-background: #171717;
        --ck-color-dropdown-panel-border: #2e2e2e;
        --ck-color-list-background: #171717;
        --ck-color-list-button-hover-background: #232323;
        --ck-color-list-button-on-background: #262626;
        --ck-color-list-button-on-text: #3ecf8e;
        --ck-color-input-background: #141414;
        --ck-color-input-border: #2e2e2e;
        --ck-color-input-text: #ededed;
        --ck-color-panel-background: #171717;
        --ck-color-panel-border: #2e2e2e;
        --ck-color-labeled-field-label-background: #171717;
        --ck-color-focus-border: #3ecf8e;
        --ck-color-link-default: #3ecf8e;
        --ck-color-shadow-drop: rgba(0, 0, 0, 0.4);
        --ck-color-shadow-inner: rgba(0, 0, 0, 0.3);
    }
    .module-editor .ck.ck-editor__top .ck-sticky-panel .ck-toolbar { border-radius: 0.6rem 0.6rem 0 0 !important; }
    .module-editor .ck.ck-editor__main > .ck-editor__editable { border-radius: 0 0 0.6rem 0.6rem !important; }
    .file-drop.is-dragging { border-color: #3ecf8e; background: rgba(62, 207, 142, 0.06); }
</style>
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.ClassicEditor) {
            ClassicEditor
                .create(document.querySelector('#module-content'), {
                    toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'undo', 'redo']
                })
                .catch((error) => console.error(error));
        }

        // File picker: list the chosen files and accept drag and drop
        const drop = document.querySelector('.file-drop');
        const input = document.getElementById('attachments');
        if (!drop || !input) return;
        const list = drop.querySelector('.file-drop-list');
        const emptyText = list.textContent;
        const describe = () => {
            const names = Array.from(input.files).map((f) => f.name);
            list.textContent = names.length ? names.join(', ') : emptyText;
            list.classList.toggle('text-[#ededed]', names.length > 0);
        };
        input.addEventListener('change', describe);
        ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.add('is-dragging'); }));
        ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.remove('is-dragging'); }));
        drop.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                describe();
            }
        });

        const form = input.closest('form');
        form.querySelectorAll('[data-remove-upload]').forEach((button) => {
            button.addEventListener('click', () => button.closest('li').remove());
        });

        // Cloud storage: upload each chosen file straight to it with a signed URL, then post
        // only the keys and names. Vercel refuses request bodies over 4.5 MB, so files up to
        // 20 MB can't go through the form post itself.
        const signUrl = input.dataset.directUploadUrl;
        if (!signUrl) return;
        const maxBytes = Number(input.dataset.maxBytes);
        const keyInputs = form.querySelector('.direct-upload-inputs');
        const errorBox = form.querySelector('.direct-upload-error');
        let nextIndex = form.querySelectorAll('[data-pending-upload]').length;

        const showError = (message) => {
            errorBox.textContent = message;
            errorBox.hidden = false;
        };

        const put = (url, headers, file, onProgress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);
            Object.entries(headers || {}).forEach(([name, value]) => xhr.setRequestHeader(name, value));
            xhr.upload.addEventListener('progress', (e) => { if (e.lengthComputable) onProgress(e.loaded / e.total); });
            xhr.addEventListener('load', () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`“${file.name}” could not be uploaded.`))));
            xhr.addEventListener('error', () => reject(new Error(`“${file.name}” could not be uploaded.`)));
            xhr.send(file);
        });

        form.addEventListener('submit', async (e) => {
            // Once the files are uploaded the input is cleared, so the final submit goes through
            if (!input.files.length) return;
            e.preventDefault();

            const files = Array.from(input.files);
            const tooBig = files.find((file) => file.size > maxBytes);
            if (tooBig) {
                showError(`“${tooBig.name}” is larger than 20 MB.`);
                return;
            }

            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            errorBox.hidden = true;
            const added = [];
            try {
                for (const [i, file] of files.entries()) {
                    const response = await fetch(signUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({ file_name: file.name, file_size: file.size }),
                    });
                    const upload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(upload.message || `“${file.name}” could not be uploaded.`);

                    await put(upload.url, upload.headers, file, (done) => {
                        list.textContent = `Uploading ${i + 1} of ${files.length}: ${file.name} (${Math.round(done * 100)}%)`;
                    });

                    const index = nextIndex++;
                    for (const [field, value] of [['key', upload.key], ['name', file.name]]) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = `uploaded_attachments[${index}][${field}]`;
                        hidden.value = value;
                        keyInputs.appendChild(hidden);
                        added.push(hidden);
                    }
                }
            } catch (error) {
                added.forEach((hidden) => hidden.remove());
                describe();
                showError(`${error.message} Nothing was saved. Please try again.`);
                submit.disabled = false;
                return;
            }

            input.value = '';
            list.textContent = `Uploaded ${files.length} ${files.length === 1 ? 'file' : 'files'}. Saving…`;
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    });
</script>
