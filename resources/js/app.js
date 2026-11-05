import './bootstrap';
import './toast';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import branchedMenu from './branched-menu';
import lineSidebar from './line-sidebar';
import staggeredMenu from './staggered-menu';

Alpine.plugin(collapse);
Alpine.data('branchedMenu', branchedMenu);
Alpine.data('lineSidebar', lineSidebar);
Alpine.data('staggeredMenu', staggeredMenu);

window.Alpine = Alpine;
Alpine.start();

