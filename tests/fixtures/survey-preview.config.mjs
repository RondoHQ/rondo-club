import { mergeConfig } from 'vite';
import base from './photo-crop-preview.config.mjs';
export default mergeConfig(base, { server: { host: '127.0.0.1', port: 5193, strictPort: true }, plugins: [{ name: 'survey-preview', configureServer(server) { server.middlewares.use((req, res, next) => { if (req.headers.accept?.includes('text/html')) req.url = '/survey-preview.html'; next(); }); } }] });
