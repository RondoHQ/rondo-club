/**
 * Generate PWA icons from the club crest.
 *
 * Produces the four icons referenced by the manifest and the iOS <link> tag:
 * - icon-192x192.png             (Android home screen, manifest purpose "any")
 * - icon-512x512.png             (Android splash + install dialog)
 * - icon-512x512-maskable.png    (Android adaptive icon, purpose "maskable")
 * - apple-touch-icon-180x180.png (iOS Add to Home Screen)
 *
 * Two rules drive the output and both matter:
 *
 * 1. Every icon is flattened onto an opaque background. iOS does not support
 *    transparency in apple-touch-icon — it composites what it gets onto black,
 *    which is how the previous icon set ended up as a logo on a black tile.
 * 2. The maskable variant keeps the crest at 70% of the canvas. Android crops
 *    adaptive icons to a circle/squircle and only the middle 80% is guaranteed
 *    to survive, so anything larger loses its edges.
 *
 * Run with `npm run icons` after changing the crest or the background colour.
 *
 * Other clubs (and the demo site) override the source and background:
 *   RONDO_ICON_SOURCE=public/icons/rondo-logo.png RONDO_ICON_BG=#001b60 npm run icons
 */

import sharp from 'sharp';
import { mkdir } from 'fs/promises';
import { dirname, join, isAbsolute } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = join(__dirname, '..');
const iconsDir = join(projectRoot, 'public', 'icons');

/** Crest source. SVG preferred — it rasterises cleanly at every size. */
const SOURCE = process.env.RONDO_ICON_SOURCE
  ? (isAbsolute(process.env.RONDO_ICON_SOURCE)
    ? process.env.RONDO_ICON_SOURCE
    : join(projectRoot, process.env.RONDO_ICON_SOURCE))
  : join(iconsDir, 'club-crest.svg');

/**
 * Tile background. Pale mint: the AWC crest is dark green (#006935), so a
 * saturated green loses the shield silhouette at 48dp while a light tint of the
 * same hue keeps both the club colour and the contrast.
 */
const BACKGROUND = process.env.RONDO_ICON_BG || '#CCE1D7';

/** Fraction of the canvas the crest occupies, per icon role. */
const SCALE_ANY = 0.86;
const SCALE_MASKABLE = 0.7;

/** Rasterise the source high enough that downscaling stays sharp. */
const SOURCE_DENSITY = 1400;

const OUTPUTS = [
  { name: 'icon-192x192.png', size: 192, scale: SCALE_ANY },
  { name: 'icon-512x512.png', size: 512, scale: SCALE_ANY },
  { name: 'apple-touch-icon-180x180.png', size: 180, scale: SCALE_ANY },
  { name: 'icon-512x512-maskable.png', size: 512, scale: SCALE_MASKABLE },
];

/**
 * Parse a hex colour into the object shape sharp expects.
 *
 * @param {string} hex Hex colour, with or without leading #.
 * @returns {{r: number, g: number, b: number, alpha: number}}
 */
function parseHex(hex) {
  const value = hex.replace('#', '');
  const full = value.length === 3
    ? value.split('').map((char) => char + char).join('')
    : value;

  if (!/^[0-9a-fA-F]{6}$/.test(full)) {
    throw new Error(`RONDO_ICON_BG must be a hex colour, got "${hex}"`);
  }

  return {
    r: parseInt(full.slice(0, 2), 16),
    g: parseInt(full.slice(2, 4), 16),
    b: parseInt(full.slice(4, 6), 16),
    alpha: 1,
  };
}

/**
 * Render one icon: crest centred at `scale` of the canvas, on an opaque tile.
 *
 * @param {string} name Output filename.
 * @param {number} size Canvas edge in pixels.
 * @param {number} scale Fraction of the canvas the crest may occupy.
 * @param {object} background Parsed background colour.
 */
async function renderIcon(name, size, scale, background) {
  const inner = Math.round(size * scale);

  // fit: 'inside' preserves the crest's aspect ratio; the leftover space
  // becomes solid background rather than transparency.
  const crest = await sharp(SOURCE, { density: SOURCE_DENSITY })
    .resize(inner, inner, { fit: 'inside' })
    .png()
    .toBuffer();

  const { width, height } = await sharp(crest).metadata();

  await sharp({
    create: {
      width: size,
      height: size,
      channels: 4,
      background,
    },
  })
    .composite([
      {
        input: crest,
        top: Math.round((size - height) / 2),
        left: Math.round((size - width) / 2),
      },
    ])
    // flatten() composites onto the background, removeAlpha() drops the now-opaque
    // alpha channel from the file. Both are needed: flatten alone still writes
    // RGBA, and iOS treats any alpha channel as a reason to matte on black.
    .flatten({ background })
    .removeAlpha()
    .png()
    .toFile(join(iconsDir, name));

  console.log(`Generated ${name} (${size}x${size}, crest at ${Math.round(scale * 100)}%)`);
}

async function generateIcons() {
  const background = parseHex(BACKGROUND);

  await mkdir(iconsDir, { recursive: true });

  console.log(`Source:     ${SOURCE}`);
  console.log(`Background: ${BACKGROUND}\n`);

  for (const { name, size, scale } of OUTPUTS) {
    await renderIcon(name, size, scale, background);
  }

  // Opacity is the whole point — fail loudly rather than shipping a
  // black-cornered icon again.
  for (const { name } of OUTPUTS) {
    const { hasAlpha } = await sharp(join(iconsDir, name)).metadata();
    if (hasAlpha) {
      throw new Error(`${name} still has an alpha channel — iOS would composite it on black`);
    }
  }

  console.log('\nAll icons generated and verified opaque.');
}

generateIcons().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
