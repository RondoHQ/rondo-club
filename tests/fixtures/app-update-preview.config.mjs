import { resolve } from 'node:path';
import base from '../../vite.config.js';

// Exercise the production worker and update UI with two isolated local builds.
export default {
  ...base,
  define: { ...base.define, __UPDATE_FIXTURE_RELEASE__: JSON.stringify(process.env.UPDATE_FIXTURE_RELEASE || 'A') },
  build: {
    ...base.build,
    outDir: process.env.UPDATE_FIXTURE_OUTPUT || '.cache/app-update-preview',
    rollupOptions: { input: { main: resolve('tests/fixtures/app-update-preview.jsx') } },
  },
};
