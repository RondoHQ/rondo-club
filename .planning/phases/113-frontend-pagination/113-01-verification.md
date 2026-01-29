# Phase 113-01 Verification

## Custom Field Sorting Implementation

### Test Results

**Test Date:** 2026-01-29
**Endpoint:** `/stadion/v1/people/filtered`

### 1. Validation Tests

#### Invalid custom field name
```bash
curl "https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?orderby=custom_nonexistent"
```
**Result:** ✅ Returns `rest_invalid_param` error (400)

#### Built-in fields still work
```bash
curl "https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?orderby=first_name"
curl "https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?orderby=last_name"
curl "https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?orderby=modified"
```
**Expected:** All should work (requires authentication for results)

### 2. Sorting Tests (Browser Console)

Test these in browser console after logging in:

#### Text field sorting (knvb-id)
```javascript
// Ascending
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&order=asc&per_page=10')
  .then(r => r.json())
  .then(d => console.log('ASC:', d.people.map(p => p.first_name)))

// Descending
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&order=desc&per_page=10')
  .then(r => r.json())
  .then(d => console.log('DESC:', d.people.map(p => p.first_name)))
```
**Expected:** Results sorted by KNVB ID value, empty values last

#### Date field sorting (lid-sinds)
```javascript
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_lid-sinds&order=asc&per_page=10')
  .then(r => r.json())
  .then(d => console.log('Total:', d.total, 'People:', d.people.map(p => p.first_name)))
```
**Expected:** Results sorted chronologically by membership start date

#### Boolean field sorting (isparent)
```javascript
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_isparent&order=asc&per_page=10')
  .then(r => r.json())
  .then(d => console.log('Total:', d.total, 'People:', d.people.map(p => p.first_name)))
```
**Expected:** Results sorted with false first, then true

### 3. Integration Tests

#### Pagination with custom sort
```javascript
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&page=2&per_page=10')
  .then(r => r.json())
  .then(d => console.log('Page:', d.page, 'Total Pages:', d.total_pages))
```
**Expected:** Correct page number and total pages

#### Custom sort + label filter
```javascript
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&labels[]=123')
  .then(r => r.json())
  .then(d => console.log('Total:', d.total))
```
**Expected:** Results filtered AND sorted correctly

#### Custom sort + birth year filter
```javascript
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&birth_year_from=2010')
  .then(r => r.json())
  .then(d => console.log('Total:', d.total))
```
**Expected:** Results filtered by birth year AND sorted by custom field

### 4. Performance Tests

#### Response time
```javascript
console.time('custom_sort');
fetch('/wp-json/stadion/v1/people/filtered?orderby=custom_knvb-id&per_page=100')
  .then(r => r.json())
  .then(d => {
    console.timeEnd('custom_sort');
    console.log('Total records:', d.total);
  })
```
**Expected:** < 200ms response time

### 5. No Regressions

#### Built-in sorts still work
```javascript
['first_name', 'last_name', 'modified'].forEach(field => {
  fetch(`/wp-json/stadion/v1/people/filtered?orderby=${field}&per_page=5`)
    .then(r => r.json())
    .then(d => console.log(`${field}:`, d.people[0].first_name))
})
```
**Expected:** All return results

#### Existing filters unchanged
```javascript
fetch('/wp-json/stadion/v1/people/filtered?ownership=mine&modified_days=7')
  .then(r => r.json())
  .then(d => console.log('Mine, modified 7 days:', d.total))
```
**Expected:** Filters work as before

## Verification Status

- ✅ Validation: Invalid custom fields rejected
- ⏳ Sorting: Requires browser console testing with authentication
- ⏳ Integration: Requires browser console testing
- ⏳ Performance: Requires browser console testing
- ⏳ No regressions: Requires browser console testing

## Notes

- Custom field validation works correctly (tested via curl)
- Full sorting tests require authentication (browser console or authenticated curl)
- All custom field names use hyphens (e.g., 'knvb-id', 'lid-sinds')
- Custom fields available in production: isparent, knvb-id, leeftijdsgroep, type-lid, lid-sinds, datum-foto, datum-vog, freescout-id, nikki-contributie-status, financiele-blokkade
