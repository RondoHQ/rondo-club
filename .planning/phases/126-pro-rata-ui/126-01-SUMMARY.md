---
phase: 126
plan: 01
subsystem: fees
requires:
  - 125-02
provides:
  - pro-rata-calculation
  - quarterly-fee-reduction
affects:
  - 126-02
  - 126-03
tech-stack:
  added: []
  patterns:
    - quarterly-percentage-tiers
    - fee-calculation-pipeline
key-files:
  created: []
  modified:
    - includes/class-membership-fees.php
decisions:
  - pro-rata-quarters
  - registration-date-parameter
tags:
  - php
  - fees
  - pro-rata
  - sportlink
duration: 3 min
completed: 2026-01-31
---

# Phase 126 Plan 01: Pro-rata Calculation Backend Summary

**One-liner:** Quarterly pro-rata calculation (100%/75%/50%/25%) based on Sportlink registration date

## What Was Built

Added two new methods to the MembershipFees class to handle pro-rata fee calculation for mid-season registrations:

### 1. `get_prorata_percentage()`

Calculates the pro-rata percentage based on registration month using quarterly tiers:
- **Q1 (July-September):** 100% - full season fee
- **Q2 (October-December):** 75% - three-quarter season fee
- **Q3 (January-March):** 50% - half season fee
- **Q4 (April-June):** 25% - quarter season fee

Edge cases handled:
- Null dates default to 100% (full fee)
- Empty strings default to 100%
- Invalid dates default to 100%

### 2. `calculate_full_fee()`

Integrates the complete fee calculation pipeline:
1. Calculate base fee (from age group/category)
2. Apply family discount (if eligible)
3. Apply pro-rata percentage (based on registration date)
4. Return final fee

**Key design decision:** The method takes `registration_date` as a parameter rather than reading it from ACF directly. This keeps the method pure and testable, while the REST API endpoint (Plan 02) will read from the 'registratiedatum' ACF field and pass it in.

## Technical Implementation

### Calculation Order

```
base_fee (€230)
  ↓
family_discount (-€58 if position 2)
  ↓
fee_after_discount (€172)
  ↓
pro-rata percentage (75% if Oct-Dec)
  ↓
final_fee (€129)
```

### Return Structure

The `calculate_full_fee()` method returns:

```php
[
    'category' => 'junior',
    'base_fee' => 230,
    'family_discount_rate' => 0.25,
    'family_discount_amount' => 58,
    'fee_after_discount' => 172,    // Added by calculate_full_fee
    'registration_date' => '2025-11-15',
    'prorata_percentage' => 0.75,
    'final_fee' => 129,             // Overridden with pro-rata amount
    'family_position' => 2,
    'family_key' => '1234AB-12',
    'family_size' => 2,
    // ... other fields
]
```

## Files Modified

| File | Changes | LOC |
|------|---------|-----|
| includes/class-membership-fees.php | Added get_prorata_percentage() and calculate_full_fee() | +80 |

## Decisions Made

### Decision: Quarterly Pro-rata Tiers

**Choice:** Use 100%/75%/50%/25% quarterly tiers based on registration month

**Rationale:**
- Aligns with sports season structure (July start)
- Simple, fair calculation that's easy to communicate
- Matches real-world membership patterns
- Avoids complex daily/weekly pro-rata calculations

**Alternatives considered:**
- Monthly pro-rata (12 different percentages) - too granular
- Semester-based (2 tiers) - not granular enough
- Daily pro-rata - too complex, creates decimal issues

### Decision: Registration Date as Parameter

**Choice:** Pass registration_date as parameter to calculate_full_fee() instead of reading from ACF internally

**Rationale:**
- **Testability:** Can test with any date without database setup
- **Purity:** Method has no side effects, easier to reason about
- **Flexibility:** Can use different date sources in future (manual override, import data)
- **Separation of concerns:** Business logic separate from data access

**Implementation:**
- REST endpoint (Plan 02) reads 'registratiedatum' ACF field
- Passes it to calculate_full_fee()
- If field is empty, passes null → defaults to 100%

## Test Results

### Pro-rata Percentage Verification

All quarter boundaries tested via WP-CLI on production:

| Registration Date | Expected | Actual | Status |
|-------------------|----------|--------|--------|
| 2025-07-01 (Q1 start) | 100% | 1.0 | ✓ |
| 2025-09-30 (Q1 end) | 100% | 1.0 | ✓ |
| 2025-10-01 (Q2 start) | 75% | 0.75 | ✓ |
| 2025-12-31 (Q2 end) | 75% | 0.75 | ✓ |
| 2026-01-01 (Q3 start) | 50% | 0.5 | ✓ |
| 2026-03-31 (Q3 end) | 50% | 0.5 | ✓ |
| 2026-04-01 (Q4 start) | 25% | 0.25 | ✓ |
| 2026-06-30 (Q4 end) | 25% | 0.25 | ✓ |
| null | 100% | 1.0 | ✓ |
| empty string | 100% | 1.0 | ✓ |
| invalid date | 100% | 1.0 | ✓ |

### Method Existence Verification

- ✓ get_prorata_percentage() exists with correct signature
- ✓ calculate_full_fee() exists with parameters: person_id, registration_date, season
- ✓ Methods deployed to production successfully

## Integration Points

### Upstream Dependencies

- **125-02:** Family discount calculation provides the `fee_after_discount` value that pro-rata is applied to

### Downstream Usage

- **126-02:** REST API endpoint will read 'registratiedatum' from ACF and pass to calculate_full_fee()
- **126-03:** UI will display pro-rata percentage and breakdown

## Next Phase Readiness

**Status:** Ready for 126-02

**What's ready:**
- Pro-rata calculation logic complete and tested
- All quarter boundaries verified
- Edge cases handled (null, empty, invalid dates)
- Return structure includes all needed fields for UI display

**What 126-02 needs to do:**
- Read 'registratiedatum' ACF field from person
- Call calculate_full_fee() with that date
- Return fee breakdown to REST API

**No blockers.**

## Deviations from Plan

None - plan executed exactly as written.

## Performance Notes

- Calculation is instant (simple month-based logic)
- No database queries added (pure calculation)
- Pro-rata applied after family grouping (family logic unchanged)

---

**Commits:** 72d6a5de
**Duration:** 3 minutes
**Deployed:** 2026-01-31 21:48 UTC
