import { gsap } from 'gsap';

// Alpine state for <x-staggered-menu>: a port of React Bits' StaggeredMenu. Slides the colour
// layers and panel in from the right, then rises the labels one after another.
export default function staggeredMenu() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let slide = null;
    let spin = null;
    let cycle = null;

    // Reduced motion lands every animation on its last frame, callbacks included.
    const run = (animation) => (reduceMotion.matches ? animation.progress(1) : animation);

    return {
        open: false,

        init() {
            gsap.set(this.sheets(), { xPercent: 100 });
        },

        sheets() {
            return [...this.$refs.drawer.querySelectorAll('.sm-prelayer'), this.$refs.panel];
        },

        toggle() {
            this.setOpen(!this.open);
        },

        close() {
            if (this.open) this.setOpen(false);
        },

        setOpen(next) {
            const { panel, toggle } = this.$refs;

            // Focus goes back first: an inert panel would drop it on the body.
            if (!next && panel.contains(document.activeElement)) toggle.focus();

            this.open = next;
            panel.inert = !next;
            slide?.kill();
            slide = next ? this.slideIn() : this.slideOut();
            this.spinIcon(next);
            this.cycleLabel(next);
        },

        slideIn() {
            const { drawer, panel } = this.$refs;
            const layers = [...drawer.querySelectorAll('.sm-prelayer')];
            const labels = panel.querySelectorAll('.sm-panel-itemLabel');
            const numbers = panel.querySelectorAll('.sm-panel-item');
            const footer = panel.querySelector('.sm-panel-footer');

            gsap.set(drawer, { visibility: 'visible' });
            gsap.set(labels, { yPercent: 140, rotate: 10 });
            gsap.set(numbers, { '--sm-num-opacity': 0 });
            gsap.set(footer, { y: 25, opacity: 0 });

            const timeline = gsap.timeline();
            layers.forEach((layer, index) => {
                timeline.to(layer, { xPercent: 0, duration: 0.5, ease: 'power4.out' }, index * 0.07);
            });

            const panelStart = (layers.length - 1) * 0.07 + 0.08;
            const panelDuration = 0.65;
            const itemsStart = panelStart + panelDuration * 0.15;
            timeline
                .to(panel, { xPercent: 0, duration: panelDuration, ease: 'power4.out' }, panelStart)
                .to(labels, { yPercent: 0, rotate: 0, duration: 1, ease: 'power4.out', stagger: 0.1 }, itemsStart)
                .to(numbers, { '--sm-num-opacity': 1, duration: 0.6, ease: 'power2.out', stagger: 0.08 }, itemsStart + 0.1)
                .to(footer, { y: 0, opacity: 1, duration: 0.55, ease: 'power3.out' }, panelStart + panelDuration * 0.4);

            return run(timeline);
        },

        slideOut() {
            return run(
                gsap.to(this.sheets(), {
                    xPercent: 100,
                    duration: 0.32,
                    ease: 'power3.in',
                    overwrite: 'auto',
                    onComplete: () => gsap.set(this.$refs.drawer, { visibility: 'hidden' }),
                }),
            );
        },

        spinIcon(opening) {
            spin?.kill();
            spin = run(
                gsap.to(this.$refs.icon, opening
                    ? { rotate: 225, duration: 0.8, ease: 'power4.out' }
                    : { rotate: 0, duration: 0.35, ease: 'power3.inOut' }),
            );
        },

        // Rolls through Menu and Close a few times, stopping on the new word.
        cycleLabel(opening) {
            const { label } = this.$refs;
            const [from, to] = opening ? ['Menu', 'Close'] : ['Close', 'Menu'];
            const words = [from, to, from, to];

            label.replaceChildren(...words.map((word) => {
                const line = document.createElement('span');
                line.className = 'sm-toggle-line';
                line.textContent = word;
                return line;
            }));

            cycle?.kill();
            gsap.set(label, { yPercent: 0 });
            cycle = run(
                gsap.to(label, {
                    yPercent: -((words.length - 1) / words.length) * 100,
                    duration: 0.5 + words.length * 0.07,
                    ease: 'power4.out',
                }),
            );
        },
    };
}
