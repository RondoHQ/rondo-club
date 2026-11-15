import { mergeConfig } from 'vite';
import base from './photo-crop-preview.config.mjs';

export default mergeConfig(base, {
  server: { host: '127.0.0.1', port: 5195, strictPort: true },
  plugins: [{
    name: 'survey-analysis-preview',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        if (req.headers.accept?.includes('text/html')) req.url = '/survey-analysis-preview.html';
        next();
      });
    },
  }],
});
