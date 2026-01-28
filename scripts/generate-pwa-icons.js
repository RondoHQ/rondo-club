/**
 * Generate PWA icons from favicon.svg
 *
 * This script generates all required PWA icons from the source SVG:
 * - icon-192x192.png (Android home screen)
 * - icon-512x512.png (Android splash)
 * - icon-512x512-maskable.png (Android adaptive icons)
 * - apple-touch-icon-180x180.png (iOS Add to Home Screen)
 */

import sharp from 'sharp';
import { mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = join(__dirname, '..');

async function generateIcons() {
  const svgPath = join(projectRoot, 'favicon.svg');
  const iconsDir = join(projectRoot, 'public', 'icons');

  // Create icons directory
  await mkdir(iconsDir, { recursive: true });

  // Read the SVG
  const svgBuffer = await sharp(svgPath)
    .resize(512, 512)
    .png()
    .toBuffer();

  // Generate standard icons
  const sizes = [
    { name: 'icon-192x192.png', size: 192 },
    { name: 'icon-512x512.png', size: 512 },
    { name: 'apple-touch-icon-180x180.png', size: 180 },
  ];

  for (const { name, size } of sizes) {
    await sharp(svgPath)
      .resize(size, size)
      .png()
      .toFile(join(iconsDir, name));
    console.log(`Generated ${name}`);
  }

  // Generate maskable icon (icon centered at 80% with background fill)
  // For maskable icons, the safe area is the center 80%, so we scale down the icon
  const maskableSize = 512;
  const iconSize = Math.floor(maskableSize * 0.7); // 70% of total size
  const padding = Math.floor((maskableSize - iconSize) / 2);

  // Create a white background
  const background = await sharp({
    create: {
      width: maskableSize,
      height: maskableSize,
      channels: 4,
      background: { r: 255, g: 255, b: 255, alpha: 1 },
    },
  })
    .png()
    .toBuffer();

  // Resize the icon to fit in safe area
  const iconBuffer = await sharp(svgPath)
    .resize(iconSize, iconSize)
    .png()
    .toBuffer();

  // Composite the icon on the background
  await sharp(background)
    .composite([
      {
        input: iconBuffer,
        top: padding,
        left: padding,
      },
    ])
    .png()
    .toFile(join(iconsDir, 'icon-512x512-maskable.png'));
  console.log('Generated icon-512x512-maskable.png');

  console.log('\nAll PWA icons generated successfully!');
  console.log(`Location: ${iconsDir}`);
}

generateIcons().catch(console.error);
