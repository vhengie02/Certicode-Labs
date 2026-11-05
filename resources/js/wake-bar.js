// The bars of WakeSlider from React Bits (https://reactbits.dev), used as a read-only progress bar.
// This app has no React, and nothing here is dragged, so the pointer, keyboard and spring code is
// left out. Blade renders the bars lit to the final value (x-wake-bar). This module replays the
// fill from 0 in step with count-up.js, raising a wake of bars around the moving edge.

import { playOnView } from './count-up';

const REFERENCE_BARS = 32;
const REACH = 6;
const MIN_REACH = 1.5;
const SKEW = 0.6;
const WAKE_RISE = 8;

function mountWakeBar(el) {
    // Releases the hold in wake-bar.css that keeps the final fill from flashing before the climb.
    el.dataset.wakeBar = 'mounted';

    const percent = Number(el.getAttribute('aria-valuenow'));
    if (!percent || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const bars = [...el.querySelectorAll('.wake-bar__bar')];
    const rest = Number(getComputedStyle(el).getPropertyValue('--wake-bar-rest'));
    const scale = bars.length / REFERENCE_BARS;

    playOnView(el, (fraction) => {
        const head = (percent / 100) * fraction * bars.length;
        // The eased speed falls as (1 - fraction)^(2/3), so the wake settles as the fill does.
        // The rise keeps the bars flat until the fill starts moving.
        const wake = (1 - fraction) ** (2 / 3) * Math.min(1, fraction * WAKE_RISE);
        const reach = (MIN_REACH + (REACH - MIN_REACH) * wake) * scale;

        bars.forEach((bar, index) => {
            bar.dataset.on = String(index < Math.round(head));

            const distance = index - (head - 0.5);
            const radius = distance < 0 ? reach * (1 + SKEW) : reach * (1 - SKEW / 2);
            const lift = Math.abs(distance) < radius ? wake * Math.cos((Math.PI * distance) / (2 * radius)) ** 2 : 0;
            bar.style.transform = `scaleY(${rest + lift * (1 - rest)})`;
        });
    });
}

document.querySelectorAll('[data-wake-bar]').forEach(mountWakeBar);
