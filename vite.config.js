import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  
  // Build configuration
  build: {
    // Output to dist folder
    outDir: 'dist',
    
    // Generate manifest for WordPress to read
    manifest: true,
    
    // Entry point
    rollupOptions: {
      input: resolve(__dirname, 'src/main.jsx'),
    },
    
    // Don't empty outDir (preserves other files)
    emptyOutDir: true,
  },
  
  // Development server
  server: {
    port: 5173,
    strictPort: true,
    
    // Allow WordPress to access dev server
    cors: true,
    
    // HMR configuration
    hmr: {
      host: 'localhost',
    },
  },
  
  // Resolve aliases
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
});
