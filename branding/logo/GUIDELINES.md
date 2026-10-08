# Certicode Labs — logo guidelines

**The mark:** *Typed C* — the letter C followed by a text cursor. Your initial, mid-keystroke: the work is seen as it
is written. The cursor is the ownable detail; never use the C without it.

## Files (`final/`)

| Use | File |
|---|---|
| Symbol, dark backgrounds | `certicode-symbol-on-dark.svg` |
| Symbol, light backgrounds | `certicode-symbol-on-light.svg` |
| Symbol, one colour | `certicode-symbol-black.svg`, `-white.svg`, `-mono-3ecf8e.svg`, `-mono-127a4f.svg` |
| Symbol at 32 px or smaller | `certicode-symbol-small.svg` (heavier C, taller cursor) |
| Horizontal lockup | `certicode-horizontal-{on-dark,on-light,black,white}.svg` |
| Stacked lockup | `certicode-stacked-{on-dark,on-light,black,white}.svg` |
| Wordmark only | `certicode-wordmark-{on-dark,on-light,black,white}.svg` |
| App icon (two-colour tile) | `certicode-app-icon.svg` |
| Favicon / browser tab | `certicode-favicon.svg`, `web/favicon.ico` |
| Web/PWA icons | `web/` (favicon 16/32/48, apple-touch-icon 180, icon 192/512, maskable 512) |

All artwork is outlined paths: no live text, rasters, filters or gradients.

## Colour

| Role | HEX | RGB | Use |
|---|---|---|---|
| Ink | `#0b0c0b` | 11 12 11 | Background (dark), C on light |
| Paper | `#ececea` | 236 236 234 | C on dark |
| Emerald | `#3ecf8e` | 62 207 142 | Cursor on dark — 9.8:1 on Ink |
| Deep emerald | `#127a4f` | 18 122 79 | Cursor on light — 5.4:1 on white |
| Muted | `#a6a9a4` / `#5b5f59` | — | "Labs" on dark / on light |

Bright emerald `#3ecf8e` is only 2.0:1 on white. Never put it on a light background; use deep emerald instead.

## Clear space and minimum size

- **Clear space:** keep a margin equal to the cursor's width (66/256 of the symbol's height) on every side.
- **Minimum size:** symbol 16 px (use the small version at 16–32 px); horizontal lockup 120 px wide;
  stacked lockup 72 px wide. Below that, use the symbol alone.

## Typography

The wordmark is Geist (SIL Open Font License) — *Certicode* in Bold (700), *Labs* in Regular (400), tracking −3 % / −2 %,
outlined. Use Geist for headings and UI; Instrument Serif italic only for single accent words.

## Don't

- Use the C without the cursor, or move the cursor above the baseline.
- Recolour the cursor anything other than emerald (or the one-colour versions).
- Put bright emerald on a light background.
- Stretch, rotate, outline, add shadows/gradients, or place the mark on a busy photo without a solid tile.
- Rebuild the wordmark in live text.

## Motion

The cursor may blink (1.1 s, steps, emerald) in loading states and the hero — the same rhythm as the landing page caret.

## Notes

- The concepts and test sheets are in `concepts/`, `preview.html` and `presentation.html` (`slides/` as PNG).
- Trademark clearance is not guaranteed; run a professional search before registering.
