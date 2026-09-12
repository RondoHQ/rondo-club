import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

const path = (relative) => fileURLToPath(new URL(relative, import.meta.url));

// Standalone test artifact: the real cropper, without WordPress, API calls or a service worker.
export default defineConfig({
  root: path('./'),
  base: './',
  publicDir: false,
  plugins: [react(), tailwindcss()],
  resolve: { alias: { '@': path('../../src') } },
  build: {
    outDir: path('../../.cache/photo-crop-preview'),
    emptyOutDir: true,
    rollupOptions: { input: path('./photo-crop-preview.html') },
  },
});
