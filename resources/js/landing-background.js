import './ascii-background';
import { mountTechText } from './tech-text';

const heroTitle = document.getElementById('hero-title');

if (heroTitle) {
    mountTechText(heroTitle, { accentColor: '#3ecf8e' });
}
