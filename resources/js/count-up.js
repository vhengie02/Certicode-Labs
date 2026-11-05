// CountUp from React Bits (https://reactbits.dev), ported from its React component to a plain
// module because this app has no React. Mark an element with data-count-up and it counts from 0
// to the number Blade rendered inside it, once, when it scrolls into view. Text after the number,
// such as a percent sign, stays in place.
//
// The target stays in the server's HTML, so the number is correct without JavaScript and with
// reduced motion. Only the original's count mode with easeOut is ported. The roll mode, the other
// easings, compact format and replay on hover are left out because nothing here uses them.

const DURATION_MS = 1000;

const easeOut = (t) => 1 - (1 - t) ** 3;

function play(render) {
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min(1, (now - start) / DURATION_MS);
        render(easeOut(progress));
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    };

    requestAnimationFrame(tick);
}

// Calls render with 0 now, then with an eased fraction up to 1 once el scrolls into view.
export function playOnView(el, render) {
    render(0);

    const observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            observer.disconnect();
            play(render);
        }
    });
    observer.observe(el);
}

function mountCountUp(el) {
    // Releases the hold in app.css that keeps the final number from flashing before the count.
    el.dataset.countUp = 'mounted';

    const [, number, suffix] = el.textContent.trim().match(/^(\d+(?:\.\d+)?)(.*)$/) ?? [];
    if (!Number(number) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const decimals = number.split('.')[1]?.length ?? 0;
    playOnView(el, (fraction) => {
        el.textContent = `${(Number(number) * fraction).toFixed(decimals)}${suffix}`;
    });
}

document.querySelectorAll('[data-count-up]').forEach(mountCountUp);
