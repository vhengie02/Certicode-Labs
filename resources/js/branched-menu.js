// Alpine state for <x-branched-menu>: folds its sections, follows the page section in view,
// and keeps the rail marker beside the active section's header.
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
                if (!glide) marker.style.transition = 'none';
                if (on) marker.style.top = `${head.offsetTop + (head.offsetHeight - marker.offsetHeight) / 2}px`;
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

        // The jump to a picked section can leave a neighbour in the spy's band, so the pick wins briefly.
        pick(value) {
            this.active = value;
            this.spyPausedUntil = Date.now() + 300;
        },
    };
}
