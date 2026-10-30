import { mergeConfig } from 'vite';
import base from './photo-crop-preview.config.mjs';
export default mergeConfig(base, { server: { host: '127.0.0.1', port: 5192, strictPort: true } });
