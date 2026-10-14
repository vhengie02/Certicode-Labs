{{-- Styles for the certificate sheet. Sizes use cqw so the sheet scales like paper. --}}
<style>
    .cert-sheet-wrap { container-type: inline-size; width: 100%; }
    .cert-sheet {
        --cert-ink: #101410;
        --cert-dim: #59615a;
        --cert-line: #d9dfda;
        --cert-brand: #127a4f;
        position: relative; width: 100%; aspect-ratio: 297 / 210;
        background: #ffffff; color: var(--cert-ink);
        border-radius: 1.2cqw; overflow: hidden;
        box-shadow: 0 30px 80px -30px rgba(0, 0, 0, 0.55);
        font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
    }
    /* Faint guilloche-style rings behind the text */
    .cert-sheet::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background:
            repeating-radial-gradient(circle at 88% 115%, rgba(18, 122, 79, 0.06) 0 0.18cqw, transparent 0.18cqw 1.1cqw),
            repeating-radial-gradient(circle at 8% -20%, rgba(18, 122, 79, 0.05) 0 0.18cqw, transparent 0.18cqw 1.1cqw);
    }
    .cert-frame {
        position: absolute; inset: 2.6cqw; display: flex; flex-direction: column;
        border: 0.12cqw solid var(--cert-ink); outline: 0.08cqw solid var(--cert-line); outline-offset: -0.9cqw;
        padding: 2.6cqw 3.6cqw 2.2cqw;
    }
    .cert-head { display: flex; justify-content: space-between; align-items: center; }
    .cert-brand { display: flex; align-items: center; gap: 0.8cqw; font-size: 1.6cqw; font-weight: 600; letter-spacing: -0.02em; }
    .cert-brand-dim { color: var(--cert-dim); font-weight: 500; }
    .cert-logo { width: 2.2cqw; height: 2.2cqw; color: var(--cert-ink); }
    .cert-code { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 1.05cqw; letter-spacing: 0.08em; color: var(--cert-dim); }

    .cert-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .cert-eyebrow { margin: 0 0 1.4cqw; font-family: 'Geist Mono', ui-monospace, monospace; font-size: 1.15cqw; letter-spacing: 0.32em; text-transform: uppercase; color: var(--cert-brand); }
    .cert-lead { margin: 0; font-size: 1.3cqw; color: var(--cert-dim); }
    .cert-name { margin: 0.4cqw 0 0.8cqw; font-family: 'Instrument Serif', Georgia, serif; font-style: italic; font-weight: 400; font-size: 5.6cqw; line-height: 1.05; letter-spacing: -0.01em; color: var(--cert-ink); text-wrap: balance; }
    .cert-class { margin: 0.5cqw 0 1cqw; font-size: 2.3cqw; font-weight: 700; letter-spacing: -0.02em; max-width: 80%; text-wrap: balance; }
    .cert-statement { margin: 0; max-width: 62%; font-size: 1.15cqw; line-height: 1.55; color: var(--cert-dim); text-wrap: balance; }
    .cert-stats { display: flex; gap: 0; margin: 2cqw 0 0; border: 0.08cqw solid var(--cert-line); border-radius: 0.8cqw; }
    .cert-stats > div { padding: 0.9cqw 2.2cqw; text-align: center; }
    .cert-stats > div + div { border-left: 0.08cqw solid var(--cert-line); }
    .cert-stats dt { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 0.85cqw; letter-spacing: 0.14em; text-transform: uppercase; color: var(--cert-dim); }
    .cert-stats dd { margin: 0.3cqw 0 0; font-size: 2.1cqw; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--cert-ink); }

    .cert-foot { display: grid; grid-template-columns: 1fr 1fr auto; gap: 3cqw; align-items: end; }
    .cert-sign { display: flex; flex-direction: column; gap: 0.5cqw; }
    .cert-sign-name { font-family: 'Instrument Serif', Georgia, serif; font-style: italic; font-size: 1.9cqw; color: var(--cert-ink); }
    .cert-sign-line { height: 0.1cqw; background: var(--cert-ink); width: 80%; }
    .cert-sign-role { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 0.85cqw; letter-spacing: 0.14em; text-transform: uppercase; color: var(--cert-dim); }
    .cert-qr { display: flex; align-items: center; gap: 1.2cqw; }
    .cert-qr-code { width: 9cqw; height: 9cqw; padding: 0.5cqw; border: 0.08cqw solid var(--cert-line); border-radius: 0.6cqw; background: #fff; }
    .cert-qr-code svg { width: 100%; height: 100%; display: block; }
    .cert-qr-text { display: flex; flex-direction: column; gap: 0.3cqw; max-width: 13cqw; }
    .cert-qr-title { font-size: 1.1cqw; font-weight: 700; color: var(--cert-brand); }
    .cert-qr-url { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 0.8cqw; color: var(--cert-dim); word-break: break-all; line-height: 1.4; }

    @media print {
        @page { size: A4 landscape; margin: 0; }
        html, body { background: #fff !important; }
        body * { visibility: hidden !important; }
        .cert-print, .cert-print * { visibility: visible !important; }
        .cert-print { position: absolute; left: 0; top: 0; width: 100%; }
        .cert-print .cert-sheet { border-radius: 0; box-shadow: none; }
        .cert-print-transcript { break-before: page; padding: 12mm; color: #000 !important; }
        .cert-print-transcript * { color: #000 !important; border-color: #ccc !important; background: transparent !important; }
        .no-print { display: none !important; }
    }
</style>
