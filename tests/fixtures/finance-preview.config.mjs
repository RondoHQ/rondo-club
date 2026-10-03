import { mergeConfig } from 'vite';
import base from './photo-crop-preview.config.mjs';
export default mergeConfig(base, {
  server: { host: '127.0.0.1', port: 5191, strictPort: true },
  plugins: [{ name: 'finance-preview', configureServer(server) {
    server.middlewares.use((req, res, next) => {
      if (req.headers.accept?.includes('text/html')) req.url = '/finance-preview.html';
      next();
    });
  }}],
});
