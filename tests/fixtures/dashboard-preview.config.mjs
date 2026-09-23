import { mergeConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import base from './photo-crop-preview.config.mjs';

export default mergeConfig(base, {
  build: {
    outDir: fileURLToPath(new URL('../../.cache/dashboard-preview', import.meta.url)),
    rollupOptions: { input: fileURLToPath(new URL('./dashboard-preview.html', import.meta.url)) },
  },
});
