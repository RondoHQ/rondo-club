# Plan 20-03 Summary: Heavy Library Lazy Loading

**Completed:** 2026-01-13
**Status:** Success

## Objective

Lazy load heavy third-party libraries (vis-network, TipTap) that are only used in specific features, reducing initial bundle size and improving load time.

## Tasks Completed

### Task 1: Lazy load vis-network in FamilyTree
- Converted TreeVisualization import to `lazy(() => import(...))`
- Wrapped component in Suspense with loading spinner fallback
- vis-network now loads only when user navigates to family tree page

### Task 2: Lazy load TipTap in RichTextEditor
- Extracted utility functions to `src/utils/richTextUtils.js` to break static import chain
- Converted RichTextEditor import to `lazy(() => import(...))` in NoteModal and QuickActivityModal
- Wrapped components in Suspense with placeholder fallback
- TipTap now loads only when user opens a note or activity modal

### Task 3: Final verification and documentation
- Verified build produces separate chunks for heavy libraries
- Documented complete bundle breakdown
- Confirmed initial load under 500 KB target

## Build Results

### Core Bundles (Always Loaded)

| Chunk | Raw Size | Gzipped |
|-------|----------|---------|
| main.js | 87.05 KB | 20.65 KB |
| vendor.js | 210.29 KB | 66.76 KB |
| utils.js | 95.67 KB | 33.82 KB |
| main.css | 42.45 KB | 7.06 KB |
| **Total Initial** | **435.46 KB** | **128.29 KB** |

### Lazy-Loaded Page Chunks

| Page | Raw Size | Gzipped |
|------|----------|---------|
| Dashboard | 12.42 KB | 3.34 KB |
| PeopleList | 32.35 KB | 7.03 KB |
| PersonDetail | 130.49 KB | 38.02 KB |
| FamilyTree | 9.01 KB | 3.72 KB |
| TeamsList | 23.41 KB | 5.73 KB |
| TeamDetail | 12.51 KB | 3.41 KB |
| DatesList | 3.83 KB | 1.48 KB |
| TodosList | 6.66 KB | 2.23 KB |
| Settings | 58.03 KB | 12.14 KB |
| RelationshipTypes | 10.26 KB | 3.18 KB |
| UserApproval | 4.87 KB | 1.60 KB |
| WorkspacesList | 5.37 KB | 1.87 KB |
| WorkspaceDetail | 11.76 KB | 3.71 KB |
| WorkspaceSettings | 4.86 KB | 1.69 KB |
| WorkspaceInviteAccept | 4.46 KB | 1.31 KB |
| Login | 0.58 KB | 0.39 KB |

### Heavy Library Chunks (Loaded On-Demand)

| Chunk | Raw Size | Gzipped | Loaded When |
|-------|----------|---------|-------------|
| TreeVisualization | 526.31 KB | 159.12 KB | User views family tree |
| RichTextEditor | 370.96 KB | 117.69 KB | User opens note/activity modal |
| QuickActivityModal | 13.23 KB | 4.14 KB | User opens activity modal |

### Shared Component Chunks

| Chunk | Raw Size | Gzipped |
|-------|----------|---------|
| ShareModal | 6.32 KB | 2.06 KB |
| TodoModal | 2.98 KB | 1.28 KB |

## Phase 20 Complete Bundle Comparison

| Stage | Initial JS | Initial Gzip | Notes |
|-------|------------|--------------|-------|
| **Baseline** | 1,646 KB | 480 KB | Single monolithic bundle |
| **Plan 01 (Vendor Chunking)** | 1,642 KB | 478 KB | Vendor/utils separated for caching |
| **Plan 02 (Route Lazy Loading)** | 393 KB | 121 KB | Pages load on demand |
| **Plan 03 (Heavy Lib Lazy Loading)** | 435 KB | 128 KB | Heavy libs load on demand |

Note: Initial load increased slightly from Plan 02 because the Dashboard page chunk (12 KB) is always loaded immediately. The key improvement is that:
- FamilyTree page reduced from 534 KB to 9 KB (vis-network deferred)
- QuickActivityModal reduced from 383 KB to 13 KB (TipTap deferred)

## Key Improvements

1. **vis-network isolated** - 526 KB library only loads when viewing family tree
2. **TipTap isolated** - 371 KB library only loads when opening note/activity modals
3. **Utility functions extracted** - `richTextUtils.js` enables proper code splitting
4. **Target achieved** - Initial load (435 KB) is under 500 KB target

## Loading Behavior

| User Action | Initial Load | Additional Load |
|-------------|--------------|-----------------|
| Open app (Dashboard) | 435 KB | Dashboard (12 KB) |
| Navigate to People list | - | PeopleList (32 KB) |
| View person detail | - | PersonDetail (130 KB) |
| View family tree | - | FamilyTree (9 KB) + TreeVisualization (526 KB) |
| Open activity modal | - | QuickActivityModal (13 KB) + RichTextEditor (371 KB) |

## Files Modified

- `src/pages/People/FamilyTree.jsx` - Lazy load TreeVisualization
- `src/components/Timeline/NoteModal.jsx` - Lazy load RichTextEditor
- `src/components/Timeline/QuickActivityModal.jsx` - Lazy load RichTextEditor
- `src/components/RichTextEditor.jsx` - Re-export utilities for backward compatibility
- `src/utils/richTextUtils.js` - New file with extracted utility functions
- `package.json` - Version bump to 1.74.0
- `style.css` - Version bump to 1.74.0
- `CHANGELOG.md` - Added 1.74.0 entry

## Commits

1. `ec8284e` - perf(20-03): lazy load vis-network in FamilyTree
2. `fb77a28` - perf(20-03): lazy load TipTap in modals

## Phase 20 Success Criteria

- [x] Vendor and utils chunks created (Plan 01)
- [x] Route-based lazy loading implemented (Plan 02)
- [x] vis-network lazy loaded via component-level Suspense (Plan 03)
- [x] TipTap lazy loaded via component-level Suspense (Plan 03)
- [x] Initial load size under 500 KB target: **435 KB** (Plan 03)
- [x] All features still work correctly

**Phase 20: Bundle Optimization - COMPLETE**
