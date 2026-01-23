# Resolved: Investor Display on Company Detail Page

## Issue
Investors added to a company (e.g., Deeploy) were being saved correctly to the database but not displaying on the company detail page or in the edit modal.

## Root Cause
Multiple issues combined:

1. **Closure issue in React Query**: The queryFn was capturing `company` from the outer scope. When React Query cached or refetched, the closure had stale data.

2. **Nested embeddings not supported**: WordPress REST API doesn't do nested embeddings. The company response includes investor data in `_embedded['acf:post']`, but those embedded posts don't include their own `_embedded['wp:featuredmedia']` for thumbnails.

## Solution

1. **Use embedded data directly**: Changed from making separate API calls to using `useMemo` to extract investor details from `company._embedded['acf:post']`. This is faster and avoids closure issues.

2. **Separate media query for thumbnails**: Added a query that fetches media items based on `featured_media` IDs from the embedded posts.

## Files Changed
- `src/pages/Companies/CompanyDetail.jsx` - Rewrote investor details logic
- `src/components/CompanyEditModal.jsx` - Updated for consistency
- `src/api/client.js` - Added `getMedia` API helper

## Commit
`0c11f11` - fix: display investors correctly on company detail page
