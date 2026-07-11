import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    tailwindcss(),
    react(),
    VitePWA({
      registerType: 'prompt',
      injectRegister: null, // We'll inject meta tags via PHP in Plan 02
      manifest: {
        name: 'Rondo Club',
        short_name: 'Rondo Club',
        description: 'Club data management',
        theme_color: '#0891b2',
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
        // Keep the install burst small: only precache the app shell. Lazy page
        // chunks are cached when they are actually visited instead of making
        // every new user download the complete application up front.
        globPatterns: [
          '**/*.{html,ico,png,svg,woff2}',
          'assets/main-*.css',
          'assets/{main,vendor,utils,rolldown-runtime,createLucideIcon}-*.js',
        ],
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          {
            urlPattern: /\/wp-content\/themes\/rondo-club\/dist\/assets\/.*\.(?:js|css|woff2)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'rondo-assets',
              expiration: {
                maxEntries: 150,
                maxAgeSeconds: 60 * 60 * 24 * 365,
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
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
        navigateFallback: '/wp-content/themes/rondo-club/dist/offline.html',
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
  base: '/wp-content/themes/rondo-club/dist/',

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
        manualChunks(id) {
          const packageGroups = {
            vendor: ['react', 'react-dom', 'react-router', 'react-router-dom', '@tanstack/react-query'],
            utils: ['date-fns', 'clsx', 'axios', 'react-hook-form'],
          };

          for (const [chunk, packages] of Object.entries(packageGroups)) {
            if (packages.some((packageName) => id.includes(`/node_modules/${packageName}/`))) {
              return chunk;
            }
          }

          return undefined;
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
