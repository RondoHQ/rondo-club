import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, extname } from 'node:path';

// Local-only WordPress shell substitute; use with app-update-preview.config.mjs.
const root = resolve(process.argv[2]);
const prefix = '/wp-content/themes/rondo-club/dist/';
createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://127.0.0.1:5189');
    res.setHeader('Cache-Control', 'no-store');
    const release = (await readFile(`${root}/.release`, 'utf8')).trim();
    if (url.pathname === '/wp-json/rondo/v1/version') {
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify({ version: release, buildTime: release }));
    } else if (url.pathname === '/') {
      const manifest = JSON.parse(await readFile(`${root}/.vite/manifest.json`, 'utf8'));
      const entry = Object.values(manifest).find((item) => item.isEntry);
      res.setHeader('Content-Type', 'text/html');
      res.end(`<!doctype html><html lang="nl"><head>${(entry.css || []).map((css) => `<link rel="stylesheet" href="${prefix}${css}">`).join('')}</head><body><div id="root"></div><script>window.rondoConfig=${JSON.stringify({ buildTime: release, version: release })}</script><script type="module" src="${prefix}${entry.file}"></script></body></html>`);
    } else {
      const relative = url.pathname === '/sw.js' ? 'sw.js' : url.pathname.slice(prefix.length);
      const path = resolve(root, relative);
      if ((!url.pathname.startsWith(prefix) && url.pathname !== '/sw.js') || !path.startsWith(`${root}/`)) {
        res.writeHead(404).end();
        return;
      }
      const body = await readFile(path);
      res.setHeader('Content-Type', { '.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png', '.html': 'text/html' }[extname(path)] || 'application/octet-stream');
      res.end(body);
    }
  } catch {
    res.writeHead(404).end();
  }
}).listen(5189, '127.0.0.1', () => console.log('Update fixture: http://127.0.0.1:5189'));
