<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#000000">
    <title>Signing you in — Certicode Labs</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #000; color: #ededed; font: 15px/1.5 system-ui, sans-serif; text-align: center; }
        p { margin: 0 24px 12px; }
        a { color: #3ecf8e; }
    </style>
</head>
<body>
    {{-- The last page of a Google sign-in that ran in the popup window --}}
    <main>
        <p id="oauth-popup-status" role="status">Signing you in…</p>
        <p><a id="oauth-popup-continue" href="{{ $target }}">Continue</a></p>
    </main>

    <script>
        (() => {
            const target = @json($target);
            const status = document.getElementById('oauth-popup-status');
            let handedOver = false;

            // Nobody answered, so the page that opened this window is gone: carry on here
            const continueHere = () => {
                if (!handedOver) {
                    window.location.replace(target);
                }
            };

            if (!('BroadcastChannel' in window)) {
                continueHere();

                return;
            }

            // window.opener is not used: Google's pages can cut that link, a channel always works
            const channel = new BroadcastChannel('certicode-oauth');

            channel.onmessage = (event) => {
                if (!event.data || event.data.type !== 'ack') {
                    return;
                }

                handedOver = true;
                document.getElementById('oauth-popup-continue').hidden = true;
                // Sign-in may also have failed: the opening page shows which
                status.textContent = 'You can close this window.';
                window.close();
            };

            channel.postMessage({ type: 'done', url: target });
            window.setTimeout(continueHere, 1500);
        })();
    </script>
</body>
</html>
