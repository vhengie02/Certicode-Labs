// Alpine state for .line-sidebar: a port of React Bits' LineSidebar. Rows near the cursor ease a
// --effect value (0..1) toward 1, and the CSS derives colour, shift and marker length from it.
const FALLOFF_CURVES = {
    linear: (p) => p,
    smooth: (p) => p * p * (3 - 2 * p),
    sharp: (p) => p * p * p,
};

export default function lineSidebar({ proximityRadius = 80, falloff = 'smooth', smoothing = 100 } = {}) {
    return {
        frame: null,

        init() {
            const rows = [...this.$root.querySelectorAll('.line-sidebar__row')];
            const ease = FALLOFF_CURVES[falloff] ?? FALLOFF_CURVES.linear;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const targets = rows.map(() => 0);
            const current = rows.map(() => 0);
            let focused = -1;
            let last = 0;

            // One loop eases every row with frame-rate independent smoothing, so colour, shift and
            // marker length move together.
            const run = (now) => {
                const dt = Math.min((now - last) / 1000, 0.05);
                last = now;
                const k = reduceMotion.matches ? 1 : 1 - Math.exp(-dt / (Math.max(smoothing, 1) / 1000));

                let moving = false;
                rows.forEach((row, i) => {
                    const target = Math.max(targets[i], focused === i ? 1 : 0);
                    const next = current[i] + (target - current[i]) * k;
                    const settled = Math.abs(target - next) < 0.0015;
                    current[i] = settled ? target : next;
                    row.style.setProperty('--effect', current[i].toFixed(4));
                    if (!settled) moving = true;
                });

                this.frame = moving ? requestAnimationFrame(run) : null;
            };

            const start = () => {
                if (this.frame !== null) return;
                last = performance.now();
                this.frame = requestAnimationFrame(run);
            };

            // Touch is skipped: a finger scrolling the page would drag the effect along with it.
            this.$root.addEventListener('pointermove', (event) => {
                if (event.pointerType === 'touch') return;
                rows.forEach((row, i) => {
                    const rect = row.getBoundingClientRect();
                    const distance = Math.abs(event.clientY - (rect.top + rect.height / 2));
                    targets[i] = ease(Math.max(0, 1 - distance / proximityRadius));
                });
                start();
            });

            this.$root.addEventListener('pointerleave', () => {
                targets.fill(0);
                start();
            });

            // Keyboard users get the same highlight on the row they have tabbed to.
            this.$root.addEventListener('focusin', (event) => {
                focused = event.target.matches(':focus-visible')
                    ? rows.findIndex((row) => row.contains(event.target))
                    : -1;
                start();
            });

            this.$root.addEventListener('focusout', () => {
                focused = -1;
                start();
            });
        },

        destroy() {
            if (this.frame !== null) cancelAnimationFrame(this.frame);
        },
    };
}
