// Alpine state for <x-branched-menu>: folds its sections, follows the page section in view,
// and keeps the rail marker beside the active section's header.
import { stretch } from './motion';

export default function branchedMenu({ active, open }) {
    return {
        active,
        open,
        spyPausedUntil: 0,

        init() {
            const links = [...this.$root.querySelectorAll('.branched-menu__item')];
            const heads = [...this.$root.querySelectorAll('.branched-menu__head')];
            const ids = links.map((link) => link.getAttribute('href').slice(1));

            const hash = window.location.hash.slice(1);
            if (ids.includes(hash)) this.pick(hash);

            const place = (glide) => {
                const marker = this.$refs.marker;
                const link = links[ids.indexOf(this.active)];
                const section = link ? Number(link.closest('[data-section]').dataset.section) : -1;
                const head = heads[section];
                const on = Boolean(head) && this.isOpen(section);
                const top = `${head?.offsetTop + (head?.offsetHeight - marker.offsetHeight) / 2}px`;
                if (!glide) marker.style.transition = 'none';
                if (glide && on && marker.hasAttribute('data-on') && marker.style.top !== top) stretch(marker, 'y');
                if (on) marker.style.top = top;
                marker.toggleAttribute('data-on', on);
                if (!glide) {
                    void marker.offsetHeight;
                    marker.style.transition = '';
                }
            };

            this.$watch('active', () => place(true));
            this.$watch('open', () => place(true));
            place(false);

            // A fold changes the menu's height, so the marker follows it without gliding.
            new ResizeObserver(() => place(false)).observe(this.$root);

            // scrollend does not bubble, so only a capturing listener hears it from <main>. The spy
            // reports after scrollend, so the pause outlasts it briefly.
            document.addEventListener('scrollend', () => {
                this.spyPausedUntil = Math.min(this.spyPausedUntil, Date.now() + 300);
            }, true);

            if (!('IntersectionObserver' in window)) return;
            const spy = new IntersectionObserver((entries) => {
                if (Date.now() < this.spyPausedUntil) return;
                entries.forEach((entry) => {
                    if (entry.isIntersecting) this.active = entry.target.id;
                });
            }, { rootMargin: '-30% 0px -60% 0px' });
            ids.forEach((id) => {
                const target = document.getElementById(id);
                if (target) spy.observe(target);
            });
        },

        isOpen(section) {
            return this.open.includes(section);
        },

        toggle(section) {
            this.open = this.isOpen(section)
                ? this.open.filter((index) => index !== section)
                : [...this.open, section];
        },

        // The scroll to a picked section passes its neighbours through the spy's band, so the pick
        // wins until scrollend. The timeout covers a pick that scrolls nothing, which fires no scrollend.
        pick(value) {
            this.active = value;
            this.spyPausedUntil = Date.now() + 1000;
        },
    };
}
