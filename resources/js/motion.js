// Motion that CSS can't do alone. The curves and timings mirror resources/css/motion.css.

export const EASE_OUT = 'cubic-bezier(0.23, 1, 0.32, 1)';
export const EASE_IN_OUT = 'cubic-bezier(0.77, 0, 0.175, 1)';
export const MOVE_MS = 240;

const STRETCH = 1.12;

export const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Stretches a sliding part along its direction of travel, and lets it settle as it arrives. It runs
// on `scale`, so the element must slide on `translate`: `scale` would also multiply a translate()
// inside `transform`.
export function stretch(element, axis = 'x') {
    if (reducedMotion()) return;
    const peak = axis === 'x' ? `${STRETCH} 1` : `1 ${STRETCH}`;
    element.animate({ scale: ['1 1', peak, '1 1'] }, { duration: MOVE_MS, easing: EASE_IN_OUT });
}

// One quick swell that tells the user an action worked.
function pulseSuccess(element) {
    if (!element || reducedMotion()) return;
    element.animate(
        { transform: ['scale(1)', 'scale(1.06)', 'scale(1)'] },
        { duration: 320, easing: EASE_OUT },
    );
}

// Keeps a tab strip's one underline under the selected tab, so it travels between tabs instead of
// each tab drawing its own. Used as x-tab-indicator on a positioned role="tablist".
export function tabIndicator(list, { cleanup }) {
    const bar = document.createElement('span');
    bar.className = 'motion-tab-indicator';
    bar.setAttribute('aria-hidden', 'true');
    list.append(bar);

    let shownOn = null;

    const place = () => {
        const tab = list.querySelector('[aria-selected="true"]');
        const inset = parseFloat(getComputedStyle(tab ?? list).paddingLeft) || 0;
        const width = tab ? tab.offsetWidth - inset * 2 : 0;

        // A closed drawer has no width yet, so the first real placement must not slide in from 0.
        if (width <= 0) {
            bar.style.opacity = '0';
            shownOn = null;
            return;
        }

        size.observe(tab); // A count badge can change the tab's width without any selection change.
        const glide = shownOn !== null && shownOn !== tab;
        if (shownOn === null) bar.style.transition = 'none';
        bar.style.translate = `${tab.offsetLeft + inset}px 0`;
        bar.style.transform = `scaleX(${width})`;
        bar.style.opacity = '1';
        if (shownOn === null) {
            void bar.offsetWidth;
            bar.style.transition = '';
        }
        if (glide) stretch(bar);
        shownOn = tab;
    };

    const selection = new MutationObserver(place);
    selection.observe(list, { subtree: true, attributes: true, attributeFilter: ['aria-selected'] });
    const size = new ResizeObserver(place);
    size.observe(list);
    place();

    cleanup(() => {
        selection.disconnect();
        size.disconnect();
        bar.remove();
    });
}

// Theme change seen through a circle growing out of the toggle. Browsers without view transitions
// switch straight away.
export function revealTheme(trigger, apply) {
    if (!document.startViewTransition || !trigger || reducedMotion()) {
        apply();
        return;
    }

    const { left, top, width, height } = trigger.getBoundingClientRect();
    const x = left + width / 2;
    const y = top + height / 2;
    const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

    document.startViewTransition(apply).ready.then(() => {
        document.documentElement.animate(
            { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
            { duration: 400, easing: EASE_IN_OUT, pseudoElement: '::view-transition-new(root)' },
        );
    });
}

// A switch is a sr-only checkbox beside its .motion-knob, so its change event finds the knob.
document.addEventListener('change', (event) => {
    const knob = event.target.parentElement?.querySelector(':scope > .motion-knob');
    if (knob) stretch(knob);
});

window.pulseSuccess = pulseSuccess;
window.revealTheme = revealTheme;
