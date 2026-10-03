import { mergeConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import { readFile } from 'node:fs/promises';
import base from './photo-crop-preview.config.mjs';

export default mergeConfig(base, {
  server: { host: '127.0.0.1', port: 5188, strictPort: true },
  plugins: [{
    name: 'dashboard-preview-shell',
    configureServer(server) {
      server.middlewares.use(async (req, res, next) => {
        if (req.url?.split('?')[0] === '/__dashboard-preview-data') {
          if (req.method !== 'GET' || !['127.0.0.1:5188', 'localhost:5188'].includes(req.headers.host) || (req.headers.origin && !['http://127.0.0.1:5188', 'http://localhost:5188'].includes(req.headers.origin))) { res.writeHead(403); res.end(); return; }
          res.setHeader('Cache-Control', 'no-store');
          res.setHeader('Content-Type', 'application/json');
          try { res.end(await readFile(new URL('../../.cache/dashboard-preview-live.json', import.meta.url), 'utf8')); }
          catch { res.writeHead(404); res.end(JSON.stringify({ error: 'Geen lokale snapshot beschikbaar.' })); }
          return;
        }
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
