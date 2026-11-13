// Stepper from React Bits (https://reactbits.dev), ported from its React component to Alpine
// because this app has no React. <x-stepper> renders every pane on the server, and this state
// only switches between them.
//
// A tick here means the server saw the step done, not that the viewer passed it, so a step's
// status comes from its data and the footer has no Complete button. The dots, bars and vertical
// layouts, the light theme and the completed screen are left out because this page does not use
// them.

import { EASE_IN_OUT, EASE_OUT, MOVE_MS, reducedMotion, stretch } from './motion';

const SHIFT = 36;
const ENTER_MS = 260;
const LEAVE_MS = 208;

export default function stepper({ initial = 1, total }) {
    // Titles are hidden while the row is tight, so their widths are kept from the last full layout.
    let labelWidths = [];

    return {
        current: initial,
        total,

        init() {
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
            const move = `${MOVE_MS}ms ${EASE_IN_OUT}`;
            glider.style.transition = animate
                ? `translate ${move}, width ${move}, height ${move}, opacity 200ms ease`
                : 'opacity 200ms ease';
            glider.style.translate = `${segment.offsetLeft}px ${segment.offsetTop}px`;
            glider.style.width = `${segment.offsetWidth}px`;
            glider.style.height = `${segment.offsetHeight}px`;
            glider.style.opacity = '1';
            if (animate) stretch(glider);
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
            if (Math.abs(from - to) > 0.5) {
                viewport.animate([{ height: `${from}px` }, { height: `${to}px` }], {
                    duration: MOVE_MS,
                    easing: EASE_IN_OUT,
                });
            }

            const shift = reduced ? 0 : SHIFT;
            if (shift) {
                entering.animate([{ transform: `translateX(${direction * shift}px)` }, { transform: 'none' }], {
                    duration: ENTER_MS,
                    easing: EASE_OUT,
                });
            }

            // Both panes fade at once, so the old state is still leaving as the new one arrives.
            entering.animate([{ opacity: 0 }, { opacity: 1 }], { duration: ENTER_MS, easing: EASE_OUT });
            leaving.animate([{ opacity: 1 }, { opacity: 0 }], {
                duration: LEAVE_MS,
                easing: EASE_OUT,
                fill: 'forwards',
            });
            const exit = leaving.animate(
                [{ transform: 'none' }, { transform: `translateX(${-direction * shift}px)` }],
                { duration: LEAVE_MS, easing: EASE_OUT, fill: 'forwards' },
            );
            exit.onfinish = () => {
                leaving.hidden = true;
                leaving.classList.remove('stepper__pane--leaving');
                leaving.getAnimations().forEach((run) => run.cancel());
            };
        },
    };
}
