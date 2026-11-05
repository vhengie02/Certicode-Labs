import './bootstrap';
import './toast';
import './class-background';
import './confirm-dialog';
import './count-up';
import './wake-bar';
import './scramble-text';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import branchedMenu from './branched-menu';
import lineSidebar from './line-sidebar';
import staggeredMenu from './staggered-menu';
import stepper from './stepper';

Alpine.plugin(collapse);
Alpine.data('branchedMenu', branchedMenu);
Alpine.data('lineSidebar', lineSidebar);
Alpine.data('staggeredMenu', staggeredMenu);
Alpine.data('stepper', stepper);

window.Alpine = Alpine;
Alpine.start();

