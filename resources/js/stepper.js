// Stepper from React Bits (https://reactbits.dev), ported from its React component to Alpine
// because this app has no React. <x-stepper> renders every pane on the server, and this state
// only switches between them.
//
// A tick here means the server saw the step done, not that the viewer passed it, so a step's
// status comes from its data and the footer has no Complete button. The dots, bars and vertical
// layouts, the light theme and the completed screen are left out because this page does not use
// them.

const SPRING =
    'linear(0, 0.0603, 0.2033, 0.3821, 0.5633, 0.7258, 0.8584, 0.9574, 1.0242, 1.0634, 1.0809, 1.0828, 1.0747, 1.0613, 1.046, 1.0313, 1.0186, 1.0085, 1.0012, 0.9965, 0.994, 0.9931, 0.9933, 0.9941, 0.9953, 0.9966, 0.9978, 0.9988, 1)';
const SETTLE = 'cubic-bezier(0.22, 1, 0.36, 1)';
const SHIFT = 36;

const ease = () =>
    CSS.supports?.('transition-timing-function', 'linear(0, 1)') ? SPRING : 'cubic-bezier(0.34, 1.4, 0.64, 1)';
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export default function stepper({ initial = 1, total }) {
    // Titles are hidden while the row is tight, so their widths are kept from the last full layout.
    let labelWidths = [];

    return {
        current: initial,
        total,

        init() {
            this.$root.style.setProperty('--stepper-spring', SPRING);
            this.measure();
            new ResizeObserver(() => this.measure()).observe(this.$refs.track);
        },

        status(number, done) {
            if (done) return 'complete';
            return number === this.current ? 'active' : 'upcoming';
        },

        segments() {
            return [...this.$refs.track.querySelectorAll('.stepper__segment')];
        },

        // Drops the titles when they no longer fit on one row. Each pane repeats its own title.
        measure() {
            const { track } = this.$refs;
            if (!track.clientWidth) return;
            const segments = this.segments();
            if (!track.hasAttribute('data-tight')) {
                labelWidths = segments.map((segment) => segment.querySelector('.stepper__title').scrollWidth + 8);
            }
            const badges = segments.reduce(
                (sum, segment) => sum + segment.firstElementChild.getBoundingClientRect().width + 20,
                0,
            );
            const labels = labelWidths.reduce((sum, width) => sum + width, 0);
            const room = track.clientWidth - 8 - 4 * Math.max(0, segments.length - 1);
            track.toggleAttribute('data-tight', badges + labels > room + 0.5);
            this.place(false);
        },

        place(animate) {
            const { glider } = this.$refs;
            const segment = this.segments()[this.current - 1];
            if (!segment) return;
            const spring = ease();
            glider.style.transition = animate
                ? `transform 560ms ${spring}, width 560ms ${spring}, height 560ms ${spring}, opacity 200ms ease`
                : 'opacity 200ms ease';
            glider.style.transform = `translate3d(${segment.offsetLeft}px, ${segment.offsetTop}px, 0)`;
            glider.style.width = `${segment.offsetWidth}px`;
            glider.style.height = `${segment.offsetHeight}px`;
            glider.style.opacity = '1';
        },

        go(target) {
            const next = Math.min(this.total, Math.max(1, target));
            if (next === this.current) return;
            const direction = next > this.current ? 1 : -1;
            const panes = [...this.$refs.viewport.children];
            const leaving = panes[this.current - 1];
            const entering = panes[next - 1];
            const focused = document.activeElement;

            this.current = next;
            this.place(!reducedMotion());
            this.swap(panes, leaving, entering, direction);

            // Back and Continue hide on the first and last step, which would drop keyboard focus.
            this.$nextTick(() => {
                if (focused?.hidden || leaving.contains(focused)) entering.focus({ preventScroll: true });
            });
        },

        swap(panes, leaving, entering, direction) {
            const { viewport } = this.$refs;
            const reduced = reducedMotion();
            const from = viewport.offsetHeight;

            // A click during a transition starts over from whatever is on screen now.
            [viewport, ...panes].forEach((element) => element.getAnimations().forEach((run) => run.cancel()));
            panes.forEach((pane) => {
                pane.hidden = pane !== leaving && pane !== entering;
                pane.classList.toggle('stepper__pane--leaving', pane === leaving);
            });

            const to = viewport.offsetHeight;
            const spring = ease();
            if (Math.abs(from - to) > 0.5) {
                viewport.animate([{ height: `${from}px` }, { height: `${to}px` }], {
                    duration: reduced ? 200 : 560,
                    easing: reduced ? SETTLE : spring,
                });
            }

            const shift = reduced ? 0 : SHIFT;
            if (shift) {
                entering.animate([{ transform: `translateX(${direction * shift}px)` }, { transform: 'none' }], {
                    duration: 560,
                    easing: spring,
                });
            }
            entering.animate([{ opacity: 0 }, { opacity: 0, offset: 0.25 }, { opacity: 1 }], {
                duration: 420,
                easing: 'ease-out',
            });

            leaving.animate([{ opacity: 1 }, { opacity: 0 }], {
                duration: 140,
                easing: 'cubic-bezier(0.2, 0, 0.4, 1)',
                fill: 'forwards',
            });
            const exit = leaving.animate(
                [{ transform: 'none' }, { transform: `translateX(${-direction * shift}px)` }],
                { duration: 200, easing: 'cubic-bezier(0.4, 0, 1, 1)', fill: 'forwards' },
            );
            exit.onfinish = () => {
                leaving.hidden = true;
                leaving.classList.remove('stepper__pane--leaving');
                leaving.getAnimations().forEach((run) => run.cancel());
            };
        },
    };
}
