# Plan 20-01 Summary: Vendor Chunking

**Completed:** 2026-01-13
**Status:** Success

## Objective

Implement Vite manual chunks to split vendor libraries into separate cacheable bundles.

## Tasks Completed

### Task 1: Configure Vite manual chunks for vendor splitting
- Added `manualChunks` configuration to `vite.config.js`
- Created `vendor` chunk: react, react-dom, react-router-dom, @tanstack/react-query
- Created `utils` chunk: date-fns, clsx, zustand, axios, react-hook-form

### Task 2: Verify chunk splitting and measure sizes
- Confirmed build produces multiple chunks
- Verified manifest.json shows correct dependency graph
- Preview server starts successfully

## Build Results

| Chunk | Raw Size | Gzipped |
|-------|----------|---------|
| vendor-*.js | 210.29 KB | 66.76 KB |
| utils-*.js | 95.67 KB | 33.82 KB |
| main-*.js | 1,336.47 KB | 377.69 KB |
| main-*.css | 42.35 KB | 7.03 KB |

**Total JavaScript:** 1,642 KB raw / 478 KB gzipped

## Comparison to Baseline

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Total JS (raw) | 1,646 KB | 1,642 KB | -4 KB |
| Total JS (gzip) | 480 KB | 478 KB | -2 KB |
| Vendor chunk | 0 | 210 KB | +210 KB (separated) |
| Utils chunk | 0 | 96 KB | +96 KB (separated) |

**Key improvement:** While total size is similar, stable dependencies (React ecosystem + utilities) are now in separate chunks that can be cached long-term. App code updates will only invalidate the main chunk (~1,336 KB), not the vendor/utils chunks (~306 KB total).

## Files Modified

- `vite.config.js` - Added manualChunks configuration
- `package.json` - Version bump to 1.72.0
- `style.css` - Version bump to 1.72.0
- `CHANGELOG.md` - Added 1.72.0 entry
- `dist/` - Rebuilt with chunked output

## Next Steps

The main chunk is still 1,336 KB, well above the 500 KB target. This is because heavy optional libraries (vis-network, @tiptap, @icons-pack, lucide-react) are not yet lazy loaded.

Plans 20-02 and 20-03 will address this through:
- Route-based lazy loading (React.lazy for page components)
- Dynamic imports for heavy optional libraries (network graph, rich text editor)

## Commits

1. `c14d027` - perf(20-01): configure vendor and utils chunks in Vite
2. `eb3f85f` - chore(20-01): bump version to 1.72.0 with changelog
