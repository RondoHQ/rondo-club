---
phase: 035-doorbelast-card-columns
plan: 01
subsystem: ui
tags: [discipline-cases, table, ui, react]

# Dependency graph
requires: [134-02] # Discipline Cases List Page UI
provides:
  - Card column in discipline cases table
  - Doorbelast column in discipline cases table
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns: []

# File tracking
key-files:
  created: []
  modified:
    - src/components/DisciplineCaseTable.jsx

# Decisions
decisions: []

# Metrics
duration: 70s
completed: 2026-02-03
---

# Quick Task 035: Doorbelast and Card Columns

**One-liner:** Added Kaart (yellow/red card) and Doorbelast (Ja/Nee) columns to discipline cases table

## Objective

Add two new columns to the discipline cases table to allow users to quickly see if a case has been charged back and what type of card was given.

## Tasks Completed

### Task 1: Add Kaart and Doorbelast columns to DisciplineCaseTable ✓

**Implementation:**

1. **Kaart Column** (after Sanctie):
   - Shows yellow card emoji (🟨) when `charge_codes` ends with `-1`
   - Shows red card emoji (🟥) when `charge_codes` does not end with `-1`
   - Shows `-` when no charge_codes
   - Center-aligned

2. **Doorbelast Column** (after Kaart):
   - Shows "Ja" when `is_charged` is truthy
   - Shows "Nee" when `is_charged` is falsy
   - Center-aligned

3. **Expanded Row Fix:**
   - Updated colspan from `5/4` to `7/6` (accounting for 2 new columns)
   - Ensures expanded details span full table width

**Column Order:**
Persoon (optional) | Wedstrijd | Sanctie | Kaart | Doorbelast | Boete | Expand

**Files Modified:**
- `src/components/DisciplineCaseTable.jsx` - Added two new columns with proper logic and styling

**Commit:** `5c9981b4` - feat(035): add Kaart and Doorbelast columns to discipline cases table

## Verification

- [x] Lint passes (no new errors introduced)
- [x] Build completes successfully
- [x] Card column logic implemented correctly (yellow for -1 suffix, red otherwise)
- [x] Doorbelast column shows Ja/Nee based on is_charged field
- [x] Expanded row colspan updated to span all columns
- [x] Changes deployed to production (https://stadion.svawc.nl/)

## Deviations from Plan

None - plan executed exactly as written.

## Next Steps

Users can now:
1. Quickly identify card types in the discipline cases table
2. See at a glance which cases have been charged back to individuals
3. Sort and filter discipline cases with improved visibility of key attributes

## Production URL

https://stadion.svawc.nl/people (navigate to person → Tuchtzaken tab to see table)
