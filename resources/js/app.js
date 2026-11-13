import './bootstrap';
import { tabIndicator } from './motion';
import './toast';
import './class-background';
import './confirm-dialog';
import './count-up';
import './wake-bar';
import './scramble-text';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import branchedMenu from './branched-menu';
import codeSlots from './code-slots';
import lineSidebar from './line-sidebar';
import staggeredMenu from './staggered-menu';
import stepper from './stepper';

Alpine.plugin(collapse);
Alpine.directive('tab-indicator', (element, _, utilities) => tabIndicator(element, utilities));
Alpine.data('branchedMenu', branchedMenu);
Alpine.data('codeSlots', codeSlots);
Alpine.data('lineSidebar', lineSidebar);
Alpine.data('staggeredMenu', staggeredMenu);
Alpine.data('stepper', stepper);

window.Alpine = Alpine;
Alpine.start();

