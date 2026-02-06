# Phase 111: Server-Side Foundation - Execution Plan

## Overview

This plan creates a new `/rondo/v1/people/filtered` REST endpoint that handles pagination, filtering, and sorting at the database layer using optimized `$wpdb` queries with JOINs. This replaces the current client-side approach of fetching all 1400+ people in a loop.

## Plan Structure

### Plan 1: Backend Filtered Endpoint (Wave 1)
**File:** `111-plan-1-filtered-endpoint.md`

Creates the new REST endpoint with:
- Parameter validation and whitelisting
- Access control enforcement
- Optimized SQL query with JOINs
- Pagination support

### Plan 2: Frontend Hook Integration (Wave 2)
**File:** `111-plan-2-frontend-hook.md`

Updates the frontend to use the new endpoint:
- Add `getFilteredPeople` to API client
- Create new `useFilteredPeople` hook
- Update query key structure for filters

---

## Wave Execution Order

| Wave | Plans | Parallelizable | Dependencies |
|------|-------|---------------|--------------|
| 1 | Plan 1 (Backend) | Single | None |
| 2 | Plan 2 (Frontend) | Single | Plan 1 |

Wave 1 must complete before Wave 2 because the frontend requires the endpoint to exist.

---

## Success Criteria

After all plans execute:

1. **Endpoint exists:** `GET /rondo/v1/people/filtered` returns paginated results
2. **Pagination works:** `page=1&per_page=100` returns first 100, `page=2` returns next 100
3. **Label filtering works:** `labels[]=5&labels[]=7` returns people with ANY of those labels (OR logic)
4. **Ownership filtering works:** `ownership=mine` returns only current user's people
5. **Date filtering works:** `modified_days=30` returns people modified in last 30 days
6. **Sorting works:** `orderby=first_name&order=asc` sorts alphabetically
7. **Access control enforced:** Unapproved users get empty results
8. **SQL injection prevented:** Malicious inputs are rejected or escaped
9. **Frontend uses new endpoint:** `useFilteredPeople` hook calls new endpoint
10. **No N+1 queries:** Single SQL query fetches posts with meta JOINs

---

## Requirements Mapping

| Requirement | Plan | Implementation |
|-------------|------|----------------|
| DATA-01 | Plan 1 | `per_page` and `page` params with LIMIT/OFFSET |
| DATA-02 | Plan 1 | `labels` param with taxonomy JOIN |
| DATA-03 | Plan 1 | `ownership` param with post_author WHERE |
| DATA-04 | Plan 1 | `modified_days` param with date WHERE |
| DATA-06 | Plan 1 | `orderby=first_name` with meta JOIN |
| DATA-07 | Plan 1 | `orderby=last_name` with meta JOIN |
| DATA-08 | Plan 1 | `orderby=modified` with post_modified |
| DATA-10 | Plan 1 | $wpdb query with LEFT JOINs for meta |
| DATA-13 | Plan 1 | `is_user_approved()` check at start |
| DATA-14 | Plan 1 | Whitelist validation + $wpdb->prepare() |

---

## Risk Mitigation

| Risk | Mitigation | Plan |
|------|------------|------|
| Access control bypass | Check `is_user_approved()` before any query | Plan 1 |
| SQL injection | Whitelist column names, `$wpdb->prepare()` for values | Plan 1 |
| N+1 queries | JOIN meta in main query, not post-loop | Plan 1 |
| Stale cache after mutations | Document `resetQueries()` pattern | Plan 2 |

---

## Files Modified

| Plan | Files |
|------|-------|
| Plan 1 | `includes/class-rest-people.php` |
| Plan 2 | `src/api/client.js`, `src/hooks/usePeople.js` |

---

## Testing Strategy

### Manual Testing (Plan 1)

1. **Access Control Test**
   - Log in as unapproved user, call endpoint, expect empty results
   - Log in as approved user, call endpoint, expect data

2. **Pagination Test**
   - Call with `page=1&per_page=10`, count results
   - Call with `page=2&per_page=10`, verify different results
   - Verify `total` and `total_pages` in response

3. **Label Filter Test**
   - Create people with different labels
   - Filter by single label, verify correct people returned
   - Filter by multiple labels, verify OR logic

4. **Ownership Filter Test**
   - Call with `ownership=mine`, verify only author's people
   - Call with `ownership=shared`, verify only others' people
   - Call with `ownership=all`, verify all people

5. **Date Filter Test**
   - Call with `modified_days=7`, verify recent people only

6. **Sort Test**
   - Call with `orderby=first_name&order=asc`, verify alphabetical
   - Call with `orderby=last_name&order=desc`, verify reverse

7. **SQL Injection Test**
   - Try `orderby=first_name; DROP TABLE wp_posts`
   - Try `labels[]=1 OR 1=1`
   - Verify both are rejected

### Manual Testing (Plan 2)

1. **Hook Integration Test**
   - Open PeopleList page, verify data loads
   - Check Network tab, verify single request to `/rondo/v1/people/filtered`

---

## Rollback Plan

If issues arise:
1. Frontend hook has `useFilteredPeople` separate from `usePeople`
2. Original `usePeople` remains unchanged as fallback
3. Endpoint can be disabled by removing route registration
