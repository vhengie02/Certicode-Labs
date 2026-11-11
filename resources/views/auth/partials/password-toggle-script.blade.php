<script>
    // Show/hide password, announced to screen readers via aria-pressed / aria-label
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.querySelector('[data-eye="open"]').toggleAttribute('data-icon-off', show);
            button.querySelector('[data-eye="closed"]').toggleAttribute('data-icon-off', !show);
        });
    });
</script>
