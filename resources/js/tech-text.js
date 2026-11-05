// TechText from React Bits (https://reactbits.dev), ported from its React component to a plain
// module because this app has no React. Props become the options of mountTechText().
//
// The original lays out one line in one font on a canvas. This port takes the letters from an
// existing element instead, so wrapping, sizes and mixed fonts stay in CSS and the text stays in
// the DOM for screen readers and search. Only the paint moves to the canvas. The text, font,
// fontSize, letterSpacing, align and color props are gone for that reason. The intro, guides and
// area reveal options are left out because this page does not use them.

const LABEL_FONT = '500 11px "Geist Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
const SPRING = 320;
const KICK_DECAY = 0.62;

const DEFAULTS = {
  accentColor: 'currentColor',
  reveal: 'letter',
  dashLength: 4,
  dashGap: 2,
  strokeWidth: 1.5,
  lineStyle: 'dashed',
  specks: 15,
  selection: true,
  labels: true,
  draggable: true,
  bounce: 0.4,
  sweep: true,
  speed: 1,
  clickKick: true
};

const approach = (current, target, dt, seconds) => current + (target - current) * (1 - Math.exp(-dt / seconds));

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const noise = (...values) => {
  let h = 2166136261;
  for (const value of values) {
    h = Math.imul(h ^ (value | 0), 16777619);
    h ^= h >>> 13;
    h = Math.imul(h, 0x5bd1e995);
    h ^= h >>> 15;
  }
  return (h >>> 0) / 4294967296;
};

const signed = value => (value > 0 ? `+${value}` : value < 0 ? `−${-value}` : '0');

const fontOf = style => `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;

export function mountTechText(element, options = {}) {
  const s = { ...DEFAULTS, ...options };
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  if (!ctx) return () => {};
  canvas.className = 'tech-text-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  // Chrome applies a canvas element's inherited CSS letter-spacing to its text, so a tight
  // heading would squash the labels together
  canvas.style.letterSpacing = 'normal';
  element.appendChild(canvas);

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
  let width = 1;
  let height = 1;
  let dpr = 1;
  let raf = 0;
  let last = performance.now();
  let visible = false;
  let alive = true;
  let live = false;
  let glyphs = [];
  let rows = [];
  let kickSize = 120;
  let accent = '#000000';
  let clock = 0;
  let pulse = 0;
  let placed = false;
  let dragging = -1;
  let cursor = '';
  const kicks = [];
  const press = { x: 0, y: 0, time: 0, index: -1 };
  const pointer = { x: 0, y: 0, inside: false };
  const grab = { x: 0, y: 0 };
  const lens = { x: 0, y: 0 };
  const frame = { x1: 0, y1: 0, x2: 0, y2: 0, alpha: 0, index: -1 };

  const sprite = (glyph, stroke) => {
    const pad = Math.ceil(s.strokeWidth * 2 + 4);
    const left = glyph.box.x1 - pad;
    const top = glyph.box.y1 - pad;
    const w = glyph.box.x2 - glyph.box.x1 + pad * 2;
    const h = glyph.box.y2 - glyph.box.y1 + pad * 2;
    const image = document.createElement('canvas');
    image.width = Math.max(1, Math.ceil(w * dpr));
    image.height = Math.max(1, Math.ceil(h * dpr));
    const c = image.getContext('2d');
    if (!c) return { image, left, top };
    c.setTransform(dpr, 0, 0, dpr, -left * dpr, -top * dpr);
    c.font = glyph.font;
    c.textAlign = 'left';
    c.textBaseline = 'alphabetic';
    if (stroke) {
      c.lineJoin = 'round';
      c.lineWidth = s.strokeWidth * 2;
      c.lineCap = 'butt';
      c.strokeStyle = glyph.ink;
      if (s.lineStyle !== 'solid') c.setLineDash([Math.max(1, s.dashLength), Math.max(1, s.dashGap)]);
      c.strokeText(glyph.char, glyph.x, glyph.baseline);
      c.setLineDash([]);
      c.globalCompositeOperation = 'destination-out';
      c.fillStyle = '#000000';
      c.fillText(glyph.char, glyph.x, glyph.baseline);
      c.globalCompositeOperation = 'source-over';
    } else {
      c.fillStyle = glyph.ink;
      c.fillText(glyph.char, glyph.x, glyph.baseline);
    }
    return { image, left, top };
  };

  // Reads every letter's position from the browser's own layout of the element
  const measure = () => {
    const origin = canvas.getBoundingClientRect();
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const range = document.createRange();
    const probe = ctx;
    glyphs = [];
    rows = [];
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const style = getComputedStyle(node.parentElement);
      const font = fontOf(style);
      probe.font = font;
      const ascent = probe.measureText('x').fontBoundingBoxAscent;
      let offset = 0;
      for (const char of node.data) {
        const start = offset;
        offset += char.length;
        if (!char.trim()) continue;
        range.setStart(node, start);
        range.setEnd(node, offset);
        const rect = range.getBoundingClientRect();
        const own = probe.measureText(char);
        const x = rect.left - origin.left;
        const baseline = rect.top - origin.top + ascent;
        const glyph = {
          char,
          font,
          ink: style.color,
          x,
          baseline,
          box: {
            x1: x - own.actualBoundingBoxLeft,
            y1: baseline - own.actualBoundingBoxAscent,
            x2: x + own.actualBoundingBoxRight,
            y2: baseline + own.actualBoundingBoxDescent
          },
          offset: { x: 0, y: 0 },
          velocity: { x: 0, y: 0 },
          outline: 0
        };
        glyph.fill = sprite(glyph, false);
        glyph.dashes = sprite(glyph, true);

        // A letter left of the one before it starts a new wrapped line
        const previous = glyphs[glyphs.length - 1];
        if (!previous || x < previous.x) rows.push({ left: glyph.box.x1, right: glyph.box.x2, y: baseline, size: 0 });
        const row = rows[rows.length - 1];
        row.left = Math.min(row.left, glyph.box.x1);
        row.right = Math.max(row.right, glyph.box.x2);
        row.size = Math.max(row.size, parseFloat(style.fontSize));
        glyphs.push(glyph);
      }
    }
    kickSize = parseFloat(getComputedStyle(element).fontSize) || kickSize;
    dragging = -1;
    frame.index = -1;
  };

  const glyphAt = (x, y) => {
    let best = -1;
    let bestDistance = Infinity;
    glyphs.forEach((glyph, i) => {
      const x1 = glyph.box.x1 + glyph.offset.x;
      const x2 = glyph.box.x2 + glyph.offset.x;
      const y1 = glyph.box.y1 + glyph.offset.y;
      const y2 = glyph.box.y2 + glyph.offset.y;
      const dx = x < x1 ? x1 - x : x > x2 ? x - x2 : 0;
      const dy = y < y1 ? y1 - y : y > y2 ? y - y2 : 0;
      const d = Math.hypot(dx, dy);
      if (d < bestDistance) {
        bestDistance = d;
        best = i;
      }
    });
    return bestDistance < 28 ? best : -1;
  };

  const blit = (art, dx, dy) => {
    ctx.drawImage(art.image, Math.round((art.left + dx) * dpr), Math.round((art.top + dy) * dpr));
  };

  const crisp = value => (Math.round(value * dpr) + 0.5) / dpr;

  const perimeterPoint = (distance, w, h) => {
    let d = ((distance % (2 * (w + h))) + 2 * (w + h)) % (2 * (w + h));
    if (d < w) return [frame.x1 + d, frame.y1, 0, -1];
    d -= w;
    if (d < h) return [frame.x2, frame.y1 + d, 1, 0];
    d -= h;
    if (d < w) return [frame.x2 - d, frame.y2, 0, 1];
    d -= w;
    return [frame.x1, frame.y2 - d, -1, 0];
  };

  const drawSpecks = a => {
    const w = frame.x2 - frame.x1;
    const h = frame.y2 - frame.y1;
    if (w < 2 || h < 2) return;
    const perimeter = 2 * (w + h);
    const seed = frame.index + 1;
    const grid = 3;
    ctx.fillStyle = accent;
    ctx.strokeStyle = accent;

    for (let k = 0; k < s.specks; k++) {
      const period = 0.5 + noise(seed, k, 11) * 1.2;
      const t = pulse / period + noise(seed, k, 17);
      const cycle = Math.floor(t);
      const life = t - cycle;
      if (life > 0.7) continue;
      const [px, py, nx, ny] = perimeterPoint(noise(seed, k, cycle) * perimeter, w, h);
      const pick = noise(seed, k, cycle, 2);
      const size = pick < 0.46 ? 2 : pick < 0.7 ? 3 : pick < 0.84 ? 5 : pick < 0.94 ? 8 : 11;
      const large = size >= 8;
      const out = (large ? 9 : 4) + Math.floor(noise(seed, k, cycle, 1) * 5) * grid;
      const x = frame.x1 + Math.round((px + nx * out - frame.x1) / grid) * grid;
      const y = frame.y1 + Math.round((py + ny * out - frame.y1) / grid) * grid;
      const tone = noise(seed, k, cycle, 3);
      const blink = life < 0.06 || (life > 0.32 && life < 0.36) ? 0.35 : 1;
      const alpha = a * (large ? 0.3 + 0.4 * tone : 0.3 + 0.6 * tone) * blink;
      const left = Math.round(x - size / 2);
      const top = Math.round(y - size / 2);
      ctx.globalAlpha = alpha;
      if (tone < 0.26 || (large && tone < 0.78)) {
        ctx.strokeRect(left + 0.5, top + 0.5, size, size);
        if (large && tone > 0.5) ctx.fillRect(Math.round(x) - 1, Math.round(y) - 1, 2, 2);
      } else {
        ctx.fillRect(left, top, size, size);
      }
    }

    for (let j = 0; j < 2; j++) {
      const head = (pulse * 0.42 * s.speed + j * 0.5) * perimeter;
      for (let i = 0; i < 4; i++) {
        const [x, y] = perimeterPoint(head - i * 6, w, h);
        const size = i === 0 ? 3 : 2;
        ctx.globalAlpha = a * [0.95, 0.55, 0.32, 0.16][i];
        ctx.fillRect(Math.round(x - size / 2), Math.round(y - size / 2), size, size);
      }
    }
    ctx.globalAlpha = 1;
  };

  const drawFrame = () => {
    const glyph = glyphs[frame.index];
    if (!glyph || frame.alpha < 0.01) return;
    const a = frame.alpha;
    const x1 = crisp(frame.x1);
    const y1 = crisp(frame.y1);
    const x2 = crisp(frame.x2);
    const y2 = crisp(frame.y2);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.fillStyle = accent;
    ctx.strokeStyle = accent;
    ctx.lineWidth = 1;

    const moved = Math.hypot(glyph.offset.x, glyph.offset.y);
    if (moved > 1) {
      const hx = (glyph.box.x1 + glyph.box.x2) / 2;
      const hy = (glyph.box.y1 + glyph.box.y2) / 2;
      ctx.beginPath();
      ctx.moveTo(hx, hy);
      ctx.lineTo(hx + glyph.offset.x, hy + glyph.offset.y);
      ctx.setLineDash([3, 4]);
      ctx.globalAlpha = 0.45 * a;
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.beginPath();
      ctx.rect(Math.round(hx) - 2, Math.round(hy) - 2, 4, 4);
      ctx.globalAlpha = 0.7 * a;
      ctx.fill();
    }

    ctx.beginPath();
    ctx.rect(x1, y1, x2 - x1, y2 - y1);
    ctx.globalAlpha = 0.5 * a;
    ctx.stroke();

    ctx.beginPath();
    for (const [cx, cy] of [
      [x1, y1],
      [x2, y1],
      [x2, y2],
      [x1, y2]
    ]) {
      ctx.rect(Math.round(cx) - 2, Math.round(cy) - 2, 5, 5);
    }
    ctx.globalAlpha = 0.95 * a;
    ctx.fill();
    ctx.globalAlpha = 1;

    if (s.specks > 0) drawSpecks(a);

    if (s.labels) {
      ctx.font = LABEL_FONT;
      ctx.textAlign = 'left';
      ctx.textBaseline = 'bottom';
      ctx.fillStyle = accent;
      ctx.globalAlpha = a;
      const label =
        moved > 1
          ? `${signed(Math.round(glyph.offset.x))}, ${signed(Math.round(-glyph.offset.y))}`
          : `${glyph.char}  ${Math.round(glyph.box.x2 - glyph.box.x1)} × ${Math.round(glyph.box.y2 - glyph.box.y1)}`;
      ctx.fillText(label, Math.round(frame.x1), Math.round(frame.y1) - 7);
      ctx.globalAlpha = 1;
    }
    ctx.setTransform(1, 0, 0, 1, 0, 0);
  };

  const kick = index => {
    const strength = kickSize * 11;
    kicks.push({ index, at: pulse, velocity: strength });
    for (let d = 1; d < glyphs.length; d++) {
      const velocity = strength * Math.pow(KICK_DECAY, d);
      if (velocity < 30) break;
      for (const j of [index - d, index + d]) {
        if (j >= 0 && j < glyphs.length) kicks.push({ index: j, at: pulse + d * 0.055, velocity });
      }
    }
    wake();
  };

  const tick = now => {
    raf = 0;
    const dt = Math.min(0.05, Math.max(0.001, (now - last) / 1000));
    last = now;
    const damping = 2 * Math.sqrt(SPRING) * (1 - clamp(s.bounce, 0, 1) * 0.96);
    const current = getComputedStyle(element).color || '#000000';
    accent = /^(currentcolor|inherit)$/i.test(s.accentColor) ? current : s.accentColor;

    const sweeping = s.sweep && !reducedMotion && !pointer.inside && dragging < 0 && rows.length > 0;
    if (sweeping) clock += dt * s.speed;
    pulse += dt;
    let targetX = pointer.x;
    let targetY = pointer.y;
    if (sweeping) {
      // Each half period crosses one wrapped line, so the sweep snakes down the heading
      const phase = clock * 0.45;
      const row = rows[Math.floor(phase / Math.PI) % rows.length];
      targetX = row.left + (row.right - row.left) * (0.5 - 0.5 * Math.cos(phase));
      targetY = row.y - row.size * (0.35 + 0.05 * Math.sin(clock * 0.8));
    }
    const active = pointer.inside || sweeping || dragging >= 0;
    if (active && !placed) {
      lens.x = targetX;
      lens.y = targetY;
    }
    if (active) {
      const lag = pointer.inside ? 0.05 : 0.22;
      lens.x = approach(lens.x, targetX, dt, lag);
      lens.y = approach(lens.y, targetY, dt, lag);
    }
    placed = active;

    for (let k = kicks.length - 1; k >= 0; k--) {
      if (kicks[k].at > pulse) continue;
      const glyph = glyphs[kicks[k].index];
      if (glyph) glyph.velocity.y -= kicks[k].velocity;
      kicks.splice(k, 1);
    }

    let moving = false;
    glyphs.forEach((glyph, i) => {
      const { offset, velocity, box } = glyph;
      if (i === dragging) {
        // Kept inside the canvas so a dragged letter is never cut off at its edge
        offset.x = approach(offset.x, clamp(pointer.x - grab.x, -box.x1, width - box.x2), dt, 0.03);
        offset.y = approach(offset.y, clamp(pointer.y - grab.y, -box.y1, height - box.y2), dt, 0.03);
        velocity.x = 0;
        velocity.y = 0;
        moving = true;
        return;
      }
      if (Math.abs(offset.x) < 0.05 && Math.abs(offset.y) < 0.05 && Math.hypot(velocity.x, velocity.y) < 0.5) {
        offset.x = 0;
        offset.y = 0;
        velocity.x = 0;
        velocity.y = 0;
        return;
      }
      velocity.x += (-SPRING * offset.x - damping * velocity.x) * dt;
      velocity.y += (-SPRING * offset.y - damping * velocity.y) * dt;
      offset.x += velocity.x * dt;
      offset.y += velocity.y * dt;
      moving = true;
    });

    const focus = dragging >= 0 ? dragging : active ? glyphAt(lens.x, lens.y) : -1;
    if (focus >= 0 && s.selection) {
      const glyph = glyphs[focus];
      const bx1 = glyph.box.x1 + glyph.offset.x - 6;
      const by1 = glyph.box.y1 + glyph.offset.y - 6;
      const bx2 = glyph.box.x2 + glyph.offset.x + 6;
      const by2 = glyph.box.y2 + glyph.offset.y + 6;
      if (frame.index < 0 || frame.alpha < 0.02) {
        frame.x1 = bx1;
        frame.y1 = by1;
        frame.x2 = bx2;
        frame.y2 = by2;
      }
      const glide = focus === dragging ? 0.02 : 0.08;
      frame.x1 = approach(frame.x1, bx1, dt, glide);
      frame.y1 = approach(frame.y1, by1, dt, glide);
      frame.x2 = approach(frame.x2, bx2, dt, glide);
      frame.y2 = approach(frame.y2, by2, dt, glide);
      frame.index = focus;
    }
    frame.alpha = approach(frame.alpha, focus >= 0 && s.selection ? 1 : 0, dt, 0.1);

    glyphs.forEach((glyph, i) => {
      const target = s.reveal === 'letter' && i === focus && i !== dragging ? 1 : 0;
      glyph.outline = approach(glyph.outline, target, dt, 0.09);
      if (Math.abs(glyph.outline - target) > 0.002) moving = true;
      else glyph.outline = target;
    });

    const hovering = focus >= 0 && pointer.inside;
    const nextCursor =
      dragging >= 0 ? 'grabbing' : hovering && s.draggable ? 'grab' : hovering && s.clickKick ? 'pointer' : '';
    if (nextCursor !== cursor) {
      cursor = nextCursor;
      element.style.cursor = nextCursor;
    }

    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalAlpha = 1;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (const glyph of glyphs) {
      const moved = Math.hypot(glyph.offset.x, glyph.offset.y);
      if (moved > 1) {
        ctx.globalAlpha = Math.min(1, moved / 24) * 0.55;
        blit(glyph.dashes, 0, 0);
        ctx.globalAlpha = 1;
      }
    }
    for (const glyph of glyphs) {
      if (glyph.outline < 0.999) {
        ctx.globalAlpha = 1 - glyph.outline;
        blit(glyph.fill, glyph.offset.x, glyph.offset.y);
      }
      if (glyph.outline > 0.001) {
        ctx.globalAlpha = glyph.outline;
        blit(glyph.dashes, glyph.offset.x, glyph.offset.y);
      }
      ctx.globalAlpha = 1;
    }
    drawFrame();

    // The DOM text only goes transparent once the canvas has painted over it
    if (!live) {
      live = true;
      element.classList.add('tech-text-live');
    }

    const settling = moving || kicks.length > 0 || (frame.alpha > 0.01 && frame.alpha < 0.99);
    if ((active || settling) && visible && alive) raf = requestAnimationFrame(tick);
  };

  function wake() {
    if (raf || !visible || !alive) return;
    last = performance.now();
    raf = requestAnimationFrame(tick);
  }

  const resize = () => {
    width = Math.max(1, canvas.clientWidth);
    height = Math.max(1, canvas.clientHeight);
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    measure();
    wake();
  };

  const locate = e => {
    const rect = canvas.getBoundingClientRect();
    pointer.x = e.clientX - rect.left;
    pointer.y = e.clientY - rect.top;
  };
  const onMove = e => {
    locate(e);
    pointer.inside = true;
    wake();
  };
  const onLeave = () => {
    if (dragging >= 0) return;
    pointer.inside = false;
    wake();
  };
  const onDown = e => {
    locate(e);
    pointer.inside = true;
    const primary = e.pointerType !== 'mouse' || e.button === 0;
    const index = primary ? glyphAt(pointer.x, pointer.y) : -1;
    press.x = pointer.x;
    press.y = pointer.y;
    press.time = performance.now();
    press.index = index;
    if (s.draggable && index >= 0) {
      dragging = index;
      grab.x = pointer.x - glyphs[index].offset.x;
      grab.y = pointer.y - glyphs[index].offset.y;
      element.setPointerCapture?.(e.pointerId);
    }
    wake();
  };
  const onUp = e => {
    if (dragging >= 0) {
      dragging = -1;
      element.releasePointerCapture?.(e.pointerId);
      const rect = element.getBoundingClientRect();
      pointer.inside =
        e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom;
    }
    if (
      e.type === 'pointerup' &&
      press.index >= 0 &&
      s.clickKick &&
      !reducedMotion &&
      performance.now() - press.time < 450
    ) {
      const rect = canvas.getBoundingClientRect();
      if (Math.hypot(e.clientX - rect.left - press.x, e.clientY - rect.top - press.y) < 6) kick(press.index);
    }
    press.index = -1;
    wake();
  };

  element.addEventListener('pointermove', onMove, { passive: true });
  element.addEventListener('pointerenter', onMove, { passive: true });
  element.addEventListener('pointerdown', onDown, { passive: true });
  element.addEventListener('pointerup', onUp, { passive: true });
  element.addEventListener('pointercancel', onUp, { passive: true });
  element.addEventListener('pointerleave', onLeave, { passive: true });

  const resizeObserver = new ResizeObserver(resize);
  const intersectionObserver = new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    wake();
  });

  // Measuring before the web fonts arrive would place every letter for the fallback font
  const start = () => {
    if (!alive) return;
    resizeObserver.observe(element);
    intersectionObserver.observe(element);
  };
  const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
  const loads = [];
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    if (node.data.trim()) loads.push(document.fonts?.load(fontOf(getComputedStyle(node.parentElement)), node.data));
  }
  Promise.all(loads).then(start, start);

  return () => {
    alive = false;
    cancelAnimationFrame(raf);
    resizeObserver.disconnect();
    intersectionObserver.disconnect();
    element.classList.remove('tech-text-live');
    element.style.cursor = '';
    element.removeEventListener('pointermove', onMove);
    element.removeEventListener('pointerenter', onMove);
    element.removeEventListener('pointerdown', onDown);
    element.removeEventListener('pointerup', onUp);
    element.removeEventListener('pointercancel', onUp);
    element.removeEventListener('pointerleave', onLeave);
    canvas.remove();
  };
}
