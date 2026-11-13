{{-- Without Google keys the link leads to the email-code form, which belongs in the full page --}}
@if (config('services.google.client_id') && config('services.google.client_secret'))
<script>
    // Opens Google sign-in in a small window, so the account chooser sits on top of this page.
    // The window's last page (auth.oauth-popup-done) says where to go next. With a blocked
    // pop-up or an old browser, the link works as a normal full-page redirect.
    (() => {
        if (!('BroadcastChannel' in window)) {
            return;
        }

        const channel = new BroadcastChannel('certicode-oauth');
        let waiting = false;

        document.querySelectorAll('[data-oauth-popup]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const width = 500;
                const height = 650;
                const left = window.screenX + Math.max(0, (window.outerWidth - width) / 2);
                const top = window.screenY + Math.max(0, (window.outerHeight - height) / 2);
                const url = new URL(link.href);
                url.searchParams.set('popup', '1');

                const popup = window.open(url, 'certicode-google', `popup=yes,width=${width},height=${height},left=${left},top=${top}`);
                if (!popup) {
                    return;
                }

                event.preventDefault();
                waiting = true;
                popup.focus();
            });
        });

        channel.onmessage = (event) => {
            // Only the tab that opened the window follows it, and only to this site
            if (!waiting || !event.data || event.data.type !== 'done') {
                return;
            }

            const target = new URL(event.data.url, window.location.origin);
            if (target.origin !== window.location.origin) {
                return;
            }

            waiting = false;
            channel.postMessage({ type: 'ack' });
            window.location.assign(target.href);
        };
    })();
</script>
@endif
