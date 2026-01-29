---
phase: 112-birthdate-denormalization
verified: 2026-01-29T15:45:00Z
status: passed
score: 7/7 must-haves verified
---

# Phase 112: Birthdate Denormalization Verification Report

**Phase Goal:** Birthdate is fast to filter without ACF repeater queries.
**Verified:** 2026-01-29T15:45:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Birthday important_date save syncs date to _birthdate meta on related persons | ✓ VERIFIED | `sync_birthdate_to_person` method exists, hooks to `save_post_important_date` at priority 20, updates `_birthdate` meta via `update_post_meta()` |
| 2 | Birthday important_date delete clears _birthdate meta from related persons | ✓ VERIFIED | `clear_birthdate_on_delete` method exists, hooks to `before_delete_post`, deletes `_birthdate` meta via `delete_post_meta()` |
| 3 | WP-CLI migration backfills all existing birthdays to _birthdate meta | ✓ VERIFIED | `migrate_birthdates` command exists with `--dry-run` flag, queries all birthday dates, updates/clears `_birthdate` meta. Summary reports 1068 records migrated. |
| 4 | Persons with year_unknown birthdays have no _birthdate meta | ✓ VERIFIED | Both sync method and migration check `year_unknown` field and call `delete_post_meta()` when true |
| 5 | User can filter people by birth year range via filtered endpoint | ✓ VERIFIED | Endpoint accepts `birth_year_from` and `birth_year_to` parameters with validation (1900-2100) |
| 6 | Birth year filter uses YEAR() function on _birthdate meta | ✓ VERIFIED | Query uses `LEFT JOIN` on `_birthdate` meta with `YEAR(bd.meta_value) BETWEEN %d AND %d` for range filtering |
| 7 | Birth year filter combines with other filters using AND logic | ✓ VERIFIED | Birth year WHERE clause added to same `$where_clauses` array as other filters, combined with AND |

**Score:** 7/7 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-auto-title.php` | Birthdate sync hooks | ✓ VERIFIED | Lines 53-54: hooks registered. Lines 539-577: `sync_birthdate_to_person` method (38 lines). Lines 587-607: `clear_birthdate_on_delete` method (20 lines). |
| `includes/class-wp-cli.php` | WP-CLI migrate-birthdates command | ✓ VERIFIED | Lines 439-559: `migrate_birthdates` method (120 lines). Line 2447: command registered as `stadion migrate-birthdates`. |
| `includes/class-rest-people.php` | Birth year filter parameters | ✓ VERIFIED | Lines 275-290: `birth_year_from` and `birth_year_to` parameters with validation. Lines 992-1009: LEFT JOIN and YEAR() filter implementation. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| save_post_important_date hook | update_post_meta(_birthdate) | sync_birthdate_to_person method | ✓ WIRED | Line 53: Hook registered at priority 20. Line 575: `update_post_meta($person_id, '_birthdate', $date_value)` |
| save_post_important_date hook | delete_post_meta(_birthdate) | sync_birthdate_to_person when year_unknown=true | ✓ WIRED | Line 566-570: Check `year_unknown` OR empty `date_value`, then `delete_post_meta($person_id, '_birthdate')` |
| before_delete_post hook | delete_post_meta(_birthdate) | clear_birthdate_on_delete method | ✓ WIRED | Line 54: Hook registered. Lines 593-606: Check birthday type, then `delete_post_meta($person_id, '_birthdate')` |
| birth_year_from/birth_year_to params | YEAR(bd.meta_value) BETWEEN %d AND %d | get_filtered_people WHERE clause | ✓ WIRED | Lines 992-1009: Parameters extracted, LEFT JOIN added, YEAR() comparison in WHERE clause with prepared statement |

### Requirements Coverage

| Requirement | Status | Blocking Issue |
|-------------|--------|----------------|
| DATA-05: Server filters people by birth year | ✓ SATISFIED | Parameters validated (1900-2100), exact year and range filtering implemented |
| DATA-11: Birthdate denormalized to post_meta | ✓ SATISFIED | `_birthdate` meta key used (underscore prefix hides from UI), synced on save/delete |
| DATA-12: Birthdate syncs with important_date | ✓ SATISFIED | Hooks on `save_post_important_date` (priority 20) and `before_delete_post` implemented |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| includes/class-auto-title.php | 541-542 | DOING_AUTOSAVE check | ℹ️ Info | Standard WordPress pattern to prevent infinite loops |
| includes/class-wp-cli.php | 462 | posts_per_page: -1 | ℹ️ Info | Acceptable for migration command (one-time operation, not user-facing query) |
| includes/class-rest-people.php | 993 | LEFT JOIN on _birthdate | ℹ️ Info | Correct pattern - WHERE clause makes it effectively INNER, keeps query structure consistent |

**No blockers found.** All patterns are appropriate for their use cases.

### Human Verification Required

#### 1. Sync Hook Verification (Manual Test)

**Test:** Edit a birthday important_date in WordPress admin
**Expected:** Related person's `_birthdate` meta is updated immediately
**Why human:** Requires interacting with WordPress admin UI and verifying database state

**Steps:**
1. Log into WordPress admin on production
2. Edit an existing birthday important_date
3. Change the `date_value` field
4. Save the post
5. Verify via WP-CLI: `wp post meta get <person_id> _birthdate`
6. Value should match the new `date_value` in YYYY-MM-DD format

#### 2. Year Unknown Edge Case

**Test:** Set `year_unknown` to true on a birthday
**Expected:** `_birthdate` meta is deleted from related person
**Why human:** Requires toggling ACF field and verifying deletion

**Steps:**
1. Edit a birthday important_date with a known date
2. Check the "Year unknown" checkbox
3. Save the post
4. Verify via WP-CLI: `wp post meta get <person_id> _birthdate`
5. Should return empty/error (meta key deleted)

#### 3. Delete Hook Verification

**Test:** Permanently delete a birthday important_date
**Expected:** `_birthdate` meta is cleared from related person
**Why human:** Requires trash + permanent delete workflow

**Steps:**
1. Identify a birthday important_date with related person
2. Note the person ID
3. Trash the birthday post
4. Permanently delete from trash
5. Verify via WP-CLI: `wp post meta get <person_id> _birthdate`
6. Should return empty/error (meta key deleted)

#### 4. Birth Year Filter Accuracy

**Test:** Filter people by birth year range and verify results
**Expected:** All returned people have birthdays in the specified year range
**Why human:** Requires cross-referencing filtered results with actual birthday data

**Steps:**
1. Access production: `https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?birth_year_from=1985&birth_year_to=1990`
2. For a sample of returned people, verify their actual birthdate in admin
3. Confirm all birthdates fall within 1985-1990 range
4. Confirm people with birthdays outside range are excluded

#### 5. Filter Combination Test

**Test:** Combine birth year filter with label filter
**Expected:** Results match BOTH filters (AND logic)
**Why human:** Requires understanding the dataset to verify logical intersection

**Steps:**
1. Filter by birth year and a specific label
2. Verify returned people have BOTH the selected label AND birthdate in range
3. Confirm people with label but wrong birth year are excluded
4. Confirm people with correct birth year but no label are excluded

---

## Implementation Analysis

### Level 1: Existence Check

All artifacts verified to exist:

```bash
# AutoTitle sync hooks
grep -n "sync_birthdate_to_person" includes/class-auto-title.php
# Lines 53, 539 (method definition)

grep -n "clear_birthdate_on_delete" includes/class-auto-title.php  
# Lines 54, 587 (method definition)

# WP-CLI migration command
grep -n "migrate_birthdates" includes/class-wp-cli.php
# Lines 439 (method), 2447 (registration)

# Birth year filter parameters
grep -n "birth_year_from" includes/class-rest-people.php
# Lines 275, 932, 992, 998, 1000, 1003
```

### Level 2: Substantive Check

**AutoTitle sync hooks:**
- `sync_birthdate_to_person`: 38 lines (lines 539-577)
- Checks: DOING_AUTOSAVE, post_status === 'publish', birthday taxonomy
- Logic: Gets ACF fields, handles year_unknown, updates/deletes _birthdate meta
- No stub patterns (TODO, placeholder, console.log)
- ✓ SUBSTANTIVE

**AutoTitle delete hook:**
- `clear_birthdate_on_delete`: 20 lines (lines 587-607)
- Checks: post_type, birthday taxonomy
- Logic: Gets related_people, deletes _birthdate meta
- No stub patterns
- ✓ SUBSTANTIVE

**WP-CLI migration:**
- `migrate_birthdates`: 120 lines (lines 439-559)
- Features: Banner, WP_Query, dry-run support, progress logging, summary table
- Logic: Queries birthdays, checks year_unknown, updates/deletes meta
- No stub patterns
- ✓ SUBSTANTIVE

**Birth year filter:**
- Parameter definitions: 16 lines (lines 275-290)
- Validation: 1900-2100 range check
- Implementation: 18 lines (lines 992-1009)
- Logic: LEFT JOIN, YEAR() function, range/exact filtering
- No stub patterns
- ✓ SUBSTANTIVE

### Level 3: Wiring Check

**save_post_important_date → sync_birthdate_to_person:**
```php
// Line 53: Hook registration
add_action( 'save_post_important_date', [ $this, 'sync_birthdate_to_person' ], 20, 3 );

// Line 575: update_post_meta call
update_post_meta( $person_id, '_birthdate', $date_value );
```
✓ WIRED

**before_delete_post → clear_birthdate_on_delete:**
```php
// Line 54: Hook registration  
add_action( 'before_delete_post', [ $this, 'clear_birthdate_on_delete' ], 10, 2 );

// Line 605: delete_post_meta call
delete_post_meta( $person_id, '_birthdate' );
```
✓ WIRED

**birth_year_from/to → SQL YEAR() filter:**
```php
// Lines 932-933: Extract parameters
$birth_year_from = $request->get_param( 'birth_year_from' );
$birth_year_to   = $request->get_param( 'birth_year_to' );

// Line 993: LEFT JOIN on _birthdate meta
$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";

// Line 997: YEAR() comparison
$where_clauses[]  = "YEAR(bd.meta_value) BETWEEN %d AND %d";
```
✓ WIRED

### Phase-Specific Verification

**Priority 20 hook strategy:**
- ACF saves fields at priority 10
- Sync hook runs at priority 20 (after ACF)
- Ensures `get_field()` reads just-saved values
- ✓ Correct implementation

**Underscore prefix on _birthdate:**
- WordPress convention: underscore = hidden from custom fields UI
- Prevents accidental manual editing by users
- ✓ Correct implementation

**Migration idempotency:**
- Uses `update_post_meta()` which overwrites existing values
- Safe to re-run if interrupted or new birthdays added
- ✓ Correct implementation

**SQL injection prevention:**
- Parameters validated against 1900-2100 range
- Values passed through `$wpdb->prepare()` with %d placeholders
- YEAR() is MySQL function, not user input
- ✓ Secure implementation

**Access control preservation:**
- Migration uses `suppress_filters => true` (runs in WP-CLI, no user context)
- Filtered endpoint uses `check_user_approved()` permission callback
- Custom query explicitly checks `is_user_approved()` before execution
- ✓ Access control maintained

---

## Production Verification Evidence

**From 112-01-SUMMARY.md:**
- Dry-run: 1068 birthdays identified
- Migration: 1068 birthdays successfully migrated
- Database verification: 1068 `_birthdate` meta values exist

**From 112-02-SUMMARY.md:**
- Exact year filter (2010): 57 people
- Range filter (2010-2015): 276 people
- Invalid year (1800): Validation error returned
- Combined filters (year + ownership): AND logic confirmed

**Performance:**
- Phase 112-01: 12 minutes (migration)
- Phase 112-02: 5 minutes (filter implementation)
- Filter query: Expected <0.1s (no measurement in summary, flagged for human verification)

---

## Gaps Summary

**No gaps found.** All must-haves verified. Phase goal achieved.

The birthdate denormalization infrastructure is complete and functional:
1. Sync hooks automatically maintain `_birthdate` meta on person posts
2. Migration successfully backfilled 1068 existing birthdays
3. Birth year filter endpoint accepts range and exact year parameters
4. Filter uses optimized YEAR() SQL function on denormalized meta
5. People without `_birthdate` are excluded from filtered results
6. Filter combines with other filters (labels, ownership, modified) using AND logic

**Ready to proceed to Phase 113 (Frontend Pagination).**

---

_Verified: 2026-01-29T15:45:00Z_
_Verifier: Claude (gsd-verifier)_
