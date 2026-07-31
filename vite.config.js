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
      // Registration happens in src/components/ReloadPrompt.jsx; the meta tags
      // and the manifest link are injected by PHP (functions.php) because
      // WordPress renders the shell, not an index.html the plugin can transform.
      injectRegister: null,

      // The manifest is served by WordPress at /manifest.webmanifest so its name
      // can follow the site title per deployment. See rondo_render_manifest().
      manifest: false,

      // The worker lives in the theme's dist/ directory, so its default scope
      // would be that directory and it could never control the app at /. The
      // theme's .htaccess sends `Service-Worker-Allowed: /` to permit this.
      scope: '/',

      workbox: {
        // vite-plugin-pwa defaults navigateFallback to 'index.html'. That default
        // is wrong twice over here: WordPress renders the shell (there is no
        // index.html in the build, so createHandlerBoundToURL would throw and
        // kill the worker on startup), and a NavigationRoute would answer every
        // navigation from cache. The navigation route is defined under
        // runtimeCaching below instead.
        navigateFallback: undefined,
        // Keep the install burst small: only precache the app shell. Lazy page
        // chunks are cached when they are actually visited instead of making
        // every new user download the complete application up front.
        globPatterns: [
          'assets/montserrat-latin-{600,700}-normal-*.woff2',
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
          {
            // WordPress renders the app shell per request — nonce, window.rondoConfig,
            // dashboard preload — so navigations MUST hit the network. Do not use
            // workbox's navigateFallback here: it binds a NavigationRoute to the
            // precached offline page and answers every navigation from cache,
            // online or not, which replaces the entire app with "je bent offline".
            // precacheFallback only kicks in when the network request actually fails.
            urlPattern: ({ request, url }) => request.mode === 'navigate'
              && !url.pathname.startsWith('/wp-admin')
              && !url.pathname.startsWith('/wp-login')
              && !url.pathname.startsWith('/wp-json'),
            handler: 'NetworkOnly',
            options: {
              precacheFallback: {
                fallbackURL: '/wp-content/themes/rondo-club/dist/offline.html',
              },
            },
          },
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
