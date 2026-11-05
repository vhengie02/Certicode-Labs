import { gsap } from 'gsap';

// Scramble effect for text that is loading, after the ScrambledText component from React Bits.
//
// GSAP's ScrambleTextPlugin writes to innerHTML, and this text comes from the search box and from
// user-named classes, so the letters are written here with textContent instead. The original's
// pointer effects are left out because nothing is hovered while it loads.

const CHARS = '.:';
const SECONDS_PER_SWAP = 1 / 15;

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const randomChar = () => CHARS[Math.floor(Math.random() * CHARS.length)];

function animate(el, text, vars) {
    const chars = Array.from(text);
    const state = { revealed: 0 };
    let lastSwap = -1;

    // Spaces stay, so the scrambled text keeps the shape of its words
    const write = () => {
        const shown = Math.floor(state.revealed * chars.length);
        el.textContent = chars.map((char, i) => (i < shown || /\s/.test(char) ? char : randomChar())).join('');
    };

    write();

    const tween = gsap.to(state, {
        revealed: 1,
        ease: 'none',
        ...vars,
        onUpdate: () => {
            const swap = Math.floor(tween.totalTime() / SECONDS_PER_SWAP);
            if (swap === lastSwap) return;
            lastSwap = swap;
            write();
        },
        onComplete: () => {
            el.textContent = text;
        },
    });

    return () => tween.kill();
}

// Scrambles and decodes text until the returned function is called
function loop(el, text) {
    if (reducedMotion()) {
        el.textContent = text;
        return () => {};
    }

    return animate(el, text, { duration: 0.6, repeat: -1, yoyo: true, repeatDelay: 0.1 });
}

// Decodes text once, from scrambled characters to the real letters
function decode(el, text, delay = 0) {
    if (reducedMotion()) {
        el.textContent = text;
        return () => {};
    }

    return animate(el, text, { duration: 0.5, delay });
}

window.scrambleText = { enabled: () => !reducedMotion(), loop, decode };
