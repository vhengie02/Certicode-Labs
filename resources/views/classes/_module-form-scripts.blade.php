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
    });
</script>
