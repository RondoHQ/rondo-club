import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],

  // Base path for production builds - WordPress theme location
  base: '/wp-content/themes/stadion/dist/',

  // Inject build timestamp for version checking
  define: {
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },

  // Build configuration
  build: {
    // Output to dist folder
    outDir: 'dist',
    
    // Generate manifest for WordPress to read
    manifest: true,
    
    // Entry point
    rollupOptions: {
      input: resolve(__dirname, 'src/main.jsx'),
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom', 'react-router-dom', '@tanstack/react-query'],
          utils: ['date-fns', 'clsx', 'zustand', 'axios', 'react-hook-form'],
        },
      },
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
