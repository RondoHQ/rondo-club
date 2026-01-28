import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'prompt',
      injectRegister: null, // We'll inject meta tags via PHP in Plan 02
      manifest: {
        name: 'Stadion',
        short_name: 'Stadion',
        description: 'Club data management',
        theme_color: '#f97316',
        background_color: '#ffffff',
        display: 'standalone',
        orientation: 'any',
        start_url: '/dashboard',
        scope: '/',
        categories: ['sports'],
        icons: [
          {
            src: '../public/icons/icon-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: '../public/icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
          {
            src: '../public/icons/icon-512x512-maskable.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          {
            urlPattern: /\/wp-json\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 * 24, // 24 hours
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
        ],
        // Offline fallback page (must include base path)
        navigateFallback: '/wp-content/themes/stadion/dist/offline.html',
        navigateFallbackDenylist: [
          /^\/wp-json\//,   // Don't use offline page for API requests
          /^\/wp-admin\//,  // Don't use offline page for admin
          /^\/wp-login/,    // Don't use offline page for login
        ],
      },
      // Include offline.html in build
      includeAssets: ['offline.html', 'icons/**/*'],
    }),
  ],

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
