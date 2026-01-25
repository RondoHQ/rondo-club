---
created: 2026-01-13T21:49
title: Improve React bundle chunking
area: tooling
files:
  - vite.config.js
  - dist/assets/
---

## Problem

The production build shows a warning about chunk size:

```
dist/assets/main-B3BZu-fm.js   1,646.73 kB │ gzip: 480.09 kB

(!) Some chunks are larger than 500 kB after minification. Consider:
- Using dynamic import() to code-split the application
- Use build.rollupOptions.output.manualChunks to improve chunking
```

A single 1.6MB JavaScript bundle means:
- Slower initial page load (must download entire app)
- No caching benefits when only parts change
- Poor performance on slower connections

## Solution

Implement code splitting in Vite configuration:

**1. Vendor chunking:**
```javascript
// vite.config.js
build: {
  rollupOptions: {
    output: {
      manualChunks: {
        'vendor-react': ['react', 'react-dom', 'react-router-dom'],
        'vendor-query': ['@tanstack/react-query'],
        'vendor-ui': ['lucide-react'],
      }
    }
  }
}
```

**2. Route-based code splitting:**
```javascript
// Lazy load page components
const PeopleList = lazy(() => import('./pages/People/PeopleList'));
const TeamDetail = lazy(() => import('./pages/Teams/TeamDetail'));
```

**3. Component-level splitting:**
- Large modals (ShareModal, BulkModals) could be lazy loaded
- Heavy components loaded on demand

**Target:** Main chunk under 500KB, vendor chunks cached separately
