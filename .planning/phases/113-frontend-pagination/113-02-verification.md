# 113-02 Verification Checklist

Deployed: 2026-01-29 15:22 UTC
Production URL: https://stadion.svawc.nl/people

## Pagination Basics

- [ ] Page 1 shows first 100 people
- [ ] Pagination controls visible at bottom of list
- [ ] Page info shows "Tonen 1-100 van X leden" (or appropriate range)
- [ ] Click page 2 shows next 100 people (different from page 1)
- [ ] Previous button disabled on page 1
- [ ] Next button disabled on last page
- [ ] Can click specific page numbers to jump to that page

## Filter Integration

- [ ] Applying a label filter resets to page 1
- [ ] Applying birth year filter resets to page 1
- [ ] Applying "Laatst gewijzigd" filter resets to page 1
- [ ] Applying ownership filter resets to page 1
- [ ] Filter changes show updated results from backend
- [ ] Filter chips display correctly (label names, not IDs)
- [ ] Clear filters button works

## Loading States

- [ ] Initial load shows loading spinner (centered)
- [ ] Page navigation shows subtle loading indicator (bottom-right toast)
- [ ] Previous data stays visible during page navigation (no flash)
- [ ] Loading toast disappears after page loads

## Empty States

- [ ] Empty database shows "Geen leden gevonden" with "Lid toevoegen" button
- [ ] Restrictive filters with 0 results show "Geen leden vinden die aan je filters voldoen"
- [ ] Empty state with filters shows "Filters wissen" button
- [ ] Clearing filters returns to full list

## Sort Integration

- [ ] Change sort field to "Achternaam" - results re-sort
- [ ] Change sort direction to descending - order reverses
- [ ] Custom field sorting works (e.g., "KNVB ID")
- [ ] Sort changes maintain current page (or reset to page 1 - both acceptable)
- [ ] Sort by "Team" falls back to first_name sorting
- [ ] Sort by "Labels" falls back to first_name sorting

## Performance

- [ ] Page load time < 500ms (check Network tab)
- [ ] Page navigation < 200ms (check Network tab)
- [ ] No console errors
- [ ] No excessive API calls (check Network tab)
- [ ] Pagination works smoothly with 1400+ records

## No Regressions

- [ ] Add person button works
- [ ] Bulk selection works (within current page)
- [ ] Bulk organization assignment works
- [ ] Bulk labels management works
- [ ] Person links navigate to detail page
- [ ] Pull to refresh works
- [ ] Dark mode toggle works
- [ ] Mobile responsive layout works

## Browser Console Tests

```javascript
// Test pagination API call
fetch('https://stadion.svawc.nl/wp-json/rondo/v1/people/filtered?page=2&per_page=100', {
  headers: { 'X-WP-Nonce': wpApiSettings.nonce }
}).then(r => r.json()).then(console.log)

// Test with filters
fetch('https://stadion.svawc.nl/wp-json/rondo/v1/people/filtered?page=1&per_page=100&labels[]=14', {
  headers: { 'X-WP-Nonce': wpApiSettings.nonce }
}).then(r => r.json()).then(console.log)

// Test birth year filter
fetch('https://stadion.svawc.nl/wp-json/rondo/v1/people/filtered?page=1&per_page=100&birth_year_from=1990&birth_year_to=1990', {
  headers: { 'X-WP-Nonce': wpApiSettings.nonce }
}).then(r => r.json()).then(console.log)
```

## Expected Results

1. **Page 1 should show 100 people** (unless total < 100)
2. **Pagination controls appear when totalPages > 1** (with 1400+ records, should have ~14 pages)
3. **Filter changes reset to page 1** (verify page state updates)
4. **Loading indicator shows during transitions** (subtle toast, not full-page spinner)
5. **Empty states distinguish "no people" vs "no results"**
6. **Sort by custom fields works** (backend handles via custom_ prefix)

## Known Limitations

1. **Team column shows "-" for all rows** - Backend doesn't return team data in filtered endpoint (requires Phase 113-03)
2. **Sort by "Team" and "Labels" falls back to first_name** - Backend doesn't support these sort fields yet
3. **Selection limited to current page** - Not cross-page (intentional design)
