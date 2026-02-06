---
phase: 111
plan: 01
subsystem: rest-api
tags: [php, rest-api, wpdb, optimization, pagination]
requires: [access-control, rest-base]
provides: [filtered-people-endpoint]
affects: [112-frontend-integration]
tech-stack:
  added: []
  patterns: [wpdb-joins, parameter-whitelisting, pagination-offset]
key-files:
  created: []
  modified: [includes/class-rest-people.php]
decisions:
  - id: 111-01-001
    title: Use wpdb with LEFT JOIN for meta fields
    rationale: Ensures people without first_name/last_name are still returned
    date: 2026-01-29
  - id: 111-01-002
    title: Use INNER JOIN for taxonomy filters
    rationale: Only want people WITH matching labels, DISTINCT prevents duplicates
    date: 2026-01-29
  - id: 111-01-003
    title: Post-query fetch for thumbnail and labels
    rationale: Avoids complex JOINs for gallery and taxonomy data
    date: 2026-01-29
metrics:
  duration: 156s
  completed: 2026-01-29
---

# Phase 111 Plan 01: Backend Filtered Endpoint Summary

**One-liner:** New `/rondo/v1/people/filtered` endpoint with wpdb JOINs for server-side pagination, filtering, and sorting

## What Was Built

Created a new REST endpoint that moves all people list operations to the database layer:

1. **Route registration** with comprehensive parameter validation
   - `page`, `per_page` for pagination (capped at 100 per page)
   - `labels` array for taxonomy filtering (OR logic)
   - `ownership` for filtering by post author (mine/shared/all)
   - `modified_days` for recent modification filtering
   - `orderby` and `order` for sorting (first_name, last_name, modified)

2. **Optimized SQL implementation** using `$wpdb` with JOINs
   - LEFT JOIN for meta fields (first_name, last_name)
   - INNER JOIN for taxonomy terms (person_label)
   - DISTINCT to prevent duplicates when filtering by labels
   - Single query fetches posts with meta data
   - Separate COUNT query for pagination info

3. **Security measures**
   - Access control check blocks unapproved users
   - Parameter whitelisting prevents SQL injection
   - `$wpdb->prepare()` for all dynamic values
   - Validation happens before query execution

## Deviations from Plan

None - plan executed exactly as written.

## Technical Implementation

### SQL Query Structure

```php
SELECT DISTINCT p.ID, p.post_modified, p.post_author, fn.meta_value AS first_name, ln.meta_value AS last_name
FROM wp_posts p
LEFT JOIN wp_postmeta fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'
LEFT JOIN wp_postmeta ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'
[INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id]
[INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'person_label']
WHERE p.post_type = 'person'
  AND p.post_status = 'publish'
  [AND p.post_author = %d]
  [AND p.post_modified >= %s]
  [AND tt.term_id IN (%d, %d, ...)]
ORDER BY [fn.meta_value|ln.meta_value|p.post_modified] [ASC|DESC]
LIMIT %d OFFSET %d
```

### Response Format

```json
{
  "people": [
    {
      "id": 123,
      "first_name": "John",
      "last_name": "Doe",
      "modified": "2026-01-29 12:34:56",
      "thumbnail": "https://...",
      "labels": ["Board Member", "Volunteer"]
    }
  ],
  "total": 1427,
  "page": 1,
  "total_pages": 15
}
```

## Testing Results

### Validation Tests (Passed)

✅ Invalid `orderby` rejected with 400 error
✅ `per_page > 100` rejected with 400 error
✅ Invalid `ownership` values rejected with 400 error
✅ Invalid `order` values rejected with 400 error
✅ Unauthenticated requests blocked with 401 error

### SQL Injection Prevention

Tested with malicious inputs:
- `orderby=invalid_column` → Rejected (not in whitelist)
- `per_page=200` → Rejected (exceeds max)
- `ownership=invalid` → Rejected (not in whitelist)
- `order=sideways` → Rejected (not in whitelist)

All malicious inputs were rejected **before** query execution.

### Endpoint Registration

✅ Endpoint appears in `/rondo/v1` route list
✅ Proper HTTP methods (GET only)
✅ Permission callback active (check_user_approved)

## Known Limitations

1. **Authenticated testing pending** - Full CRUD tests require authenticated browser session
2. **Performance testing pending** - Need to verify performance with 1400+ records and multiple filters
3. **N+1 for thumbnail/labels** - Post-query fetches create N queries for these fields (intentional tradeoff)

## Files Changed

| File | Lines Added | Lines Removed | Purpose |
|------|-------------|---------------|---------|
| `includes/class-rest-people.php` | 222 | 0 | Added filtered endpoint and implementation |

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 58f4e44 | feat(111-01): add filtered people endpoint with pagination and sorting | class-rest-people.php |

## Decisions Made

**D1: Use LEFT JOIN for meta fields**
- **Context:** People might not have first_name or last_name set
- **Decision:** LEFT JOIN ensures these people appear in results with NULL values
- **Alternative considered:** INNER JOIN would exclude people without names
- **Impact:** More inclusive query, handles incomplete data gracefully

**D2: Use INNER JOIN for taxonomy filters**
- **Context:** When filtering by labels, only want people WITH those labels
- **Decision:** INNER JOIN excludes people without matching labels, DISTINCT prevents duplicates
- **Alternative considered:** LEFT JOIN with WHERE clause
- **Impact:** Cleaner query, proper OR logic for multiple labels

**D3: Post-query fetch for thumbnail and labels**
- **Context:** Joining gallery attachments and taxonomy terms significantly complicates query
- **Decision:** Fetch these in the results loop using WordPress functions
- **Alternative considered:** Additional JOINs for complete single-query solution
- **Impact:** N queries for N people (acceptable for paginated results)

## Next Phase Readiness

### Phase 112 (Frontend Integration) Can Proceed When:

✅ Endpoint exists and is accessible
✅ Response structure documented
✅ Parameter validation working
⚠️ Full authenticated testing recommended before frontend integration

### Known Blockers: None

### Known Concerns:

1. **Performance validation needed** - Should test with full dataset (1400+ people) and complex filters
2. **Cache strategy undefined** - Consider adding transient caching for repeated queries
3. **Label filter performance** - INNER JOIN with multiple term IDs needs benchmarking

## Authentication Gates

None - no CLI/API authentication required during execution.

## Retrospective

### What Went Well

- Clean implementation following WordPress coding standards
- Comprehensive parameter validation prevents common security issues
- Query structure is maintainable and well-commented
- Deployment succeeded without errors

### What Could Be Improved

- Authenticated testing requires manual browser testing (could add WP-CLI test script)
- Performance testing deferred to after frontend integration
- No Query Monitor profiling in this phase (should add in next phase)

### Lessons Learned

1. **Whitelist validation is essential** - Can't use `$wpdb->prepare()` for column names, must whitelist
2. **DISTINCT is necessary with taxonomy JOINs** - People with multiple matching labels would appear multiple times
3. **Access control must be explicit with wpdb** - Custom queries bypass pre_get_posts hooks

## Documentation Updates Needed

- [ ] Add endpoint to API documentation
- [ ] Document response schema in OpenAPI format
- [ ] Add performance benchmarks after frontend testing
- [ ] Create authenticated test suite using WP-CLI

## Future Optimization Opportunities

1. **Caching layer** - Add transient cache for frequently-accessed filter combinations
2. **Eager loading** - Consider fetching thumbnails in a single query using IN clause
3. **Index optimization** - Verify database indexes on post_author, post_modified, meta_key
4. **Query caching** - Enable WordPress object cache for query results
