---
phase: quick-026
plan: 01
subsystem: vog-management
completed: 2026-01-30
duration: 3 minutes

tags: [vog, filtering, ui, rest-api]

requires:
  - quick-022  # VOG filter dropdown pattern established

provides:
  - vog_type REST parameter for filtering by VOG status
  - UI filter dropdown for Nieuw vs Vernieuwing
  - Accurate counts for each VOG type
  - Filter chip display for active VOG type

affects:
  - future-vog-features  # New filter available for any VOG-related features

tech-stack:
  patterns:
    - Multi-filter coordination (email status + VOG type)
    - Count calculation from unfiltered dataset
    - Filter chip management

decisions:
  - filter-behavior: "VOG type filter modifies existing vog_missing/vog_older_than_years logic rather than replacing it"
  - count-calculation: "Calculate VOG type counts from allData (unfiltered) to show accurate totals"
  - filter-priority: "vog_type filter takes precedence over default OR behavior when specified"

key-files:
  created: []
  modified:
    - includes/class-rest-people.php  # Added vog_type parameter and filter logic
    - src/hooks/usePeople.js          # Added vogType parameter
    - src/pages/VOG/VOGList.jsx       # Added filter UI and state management
---

# Quick Task 026: VOG Nieuw/Vernieuwing Filter Summary

**One-liner:** Added filter dropdown to VOG page for separating "Nieuw" (no VOG) from "Vernieuwing" (expired VOG) with accurate counts

## What Was Built

### Backend Changes
- Added `vog_type` REST parameter to `/stadion/v1/people/filtered` endpoint
- Implemented filter logic with three states:
  - `nieuw`: Only people without datum-vog (no VOG on file)
  - `vernieuwing`: Only people with expired datum-vog (older than 3 years)
  - empty/default: Both groups (existing OR behavior)
- Filter takes precedence over default `vog_missing` + `vog_older_than_years` logic

### Frontend Changes
- Added "VOG type" section to filter dropdown (above Email status)
- Three radio-style options: Alle, Nieuw, Vernieuwing
- Real-time count display for each option
- Filter chip shows active VOG type selection
- Badge count reflects both email status and VOG type filters
- Google Sheets export respects VOG type filter

## Technical Implementation

### Filter Logic Priority
When `vog_type` is specified, it overrides the default OR behavior:
```php
if ( $vog_type === 'nieuw' ) {
    // Only NULL/empty datum-vog
} elseif ( $vog_type === 'vernieuwing' && $vog_older_than_years !== null ) {
    // Only expired datum-vog
} else {
    // Default: OR both conditions
}
```

### Count Calculation Strategy
- Main query: Filtered by vogType for display
- Separate allData query: Unfiltered (for accurate counts)
- Counts derived from allData to avoid circular dependency

### Multi-Filter Coordination
- Filter badge shows sum: `(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0)`
- Active filter chips display independently
- "Clear all filters" resets both filters simultaneously

## User Experience

### Filter Workflow
1. Click "Filter" button (shows badge if filters active)
2. Select VOG type: Alle (default) / Nieuw / Vernieuwing
3. Optionally combine with Email status filter
4. Filter chips appear below toolbar showing active selections
5. Click X on chip or "Alle filters wissen" to clear

### Visual Feedback
- Filter button highlights when any filter active
- Badge shows count of active filters (1-2)
- Chips show human-readable filter values
- Counts update in real-time as data changes

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Add vog_type backend filter | befcd73 |
| 2 | Add vog_type frontend filter UI | 3d427d0 |

## Decisions Made

**Why vog_type parameter instead of separate endpoints?**
- Reuses existing filtered people infrastructure
- Allows combination with other filters (email status, labels, etc.)
- Maintains consistency with other filter parameters

**Why calculate counts from allData?**
- Main data query is filtered by vogType
- Counts need totals across all types
- Prevents circular dependency in query logic
- Pattern matches existing emailCounts calculation

**Why place VOG type above Email status in dropdown?**
- VOG type is primary categorization (Nieuw vs Vernieuwing)
- Email status is secondary action-tracking
- Most users filter by type first, then email status

## Files Modified

### Backend
- `includes/class-rest-people.php`
  - Added `vog_type` parameter registration with validation
  - Added `$vog_type` parameter extraction
  - Modified VOG filter logic to handle type-specific filtering

### Frontend
- `src/hooks/usePeople.js`
  - Added `vogType` to JSDoc
  - Added `vog_type` to params object

- `src/pages/VOG/VOGList.jsx`
  - Added `vogTypeFilter` state
  - Added `vogTypeCounts` memo for count calculation
  - Added VOG type filter section in dropdown
  - Updated filter badge count logic
  - Added VOG type filter chip display
  - Updated "Clear all filters" button
  - Added `vog_type` to Google Sheets export

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Ready for:**
- Any feature requiring VOG type filtering
- Analytics/reporting on Nieuw vs Vernieuwing VOG requests
- Bulk operations specific to VOG type

**No blockers or concerns.**

## Verification

- [x] Backend vog_type parameter accepted and filters correctly
- [x] Frontend filter dropdown shows VOG type section with counts
- [x] Nieuw filter shows only people without datum-vog
- [x] Vernieuwing filter shows only people with expired datum-vog
- [x] Filter badge shows correct count of active filters
- [x] Filter chips display and dismiss correctly
- [x] Google Sheets export respects VOG type filter
- [x] npm run build succeeds
- [x] Deployed to production

## Production URL

https://stadion.svawc.nl/leden/vog
