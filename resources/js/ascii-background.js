import { mountGradientAscii } from './gradient-ascii';

const container = document.getElementById('ascii-background');

if (container) {
    mountGradientAscii(container, {
        // Dark emerald at the shadow's edge up to the brand green. A white top end washes the
        // black background out to gray.
        colors: ['#0d5c3c', '#14704a', '#1f8a5c', '#3ecf8e'],
        shape: 'horizon',
        cellSize: 16,
        density: 0.55,
        // Kept low so the white and green page text stays readable on top
        gradientOpacity: 0.18,
        glyphOpacity: 0.45,
        glow: 0.1,
        // Full-viewport canvas behind a long page: no need for full retina resolution
        dpr: 1.5,
    });
}
