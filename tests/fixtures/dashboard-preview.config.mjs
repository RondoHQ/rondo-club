import { mergeConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import base from './photo-crop-preview.config.mjs';

export default mergeConfig(base, {
  server: { host: '127.0.0.1', port: 5188, strictPort: true },
  plugins: [{
    name: 'dashboard-preview-shell',
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        // BrowserRouter keeps real app paths; every document uses this isolated fixture.
        if (req.headers.accept?.includes('text/html')) req.url = '/dashboard-preview.html';
        next();
      });
    },
  }],
  build: {
    outDir: fileURLToPath(new URL('../../.cache/dashboard-preview', import.meta.url)),
    rollupOptions: { input: fileURLToPath(new URL('./dashboard-preview.html', import.meta.url)) },
  },
});
