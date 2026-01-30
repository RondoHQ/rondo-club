---
phase: 121
plan: 02
type: summary
subsystem: vog-frontend
status: complete
completed: 2026-01-30

requires:
  - phase: 121
    plan: 01
    for: "Bulk VOG API endpoints (bulkSendVOGEmails, bulkMarkVOGRequested)"

provides:
  - capability: "Bulk checkbox selection in VOG list"
  - capability: "Bulk send VOG emails to multiple volunteers"
  - capability: "Bulk mark volunteers as VOG requested"
  - capability: "Selection toolbar with action dropdown"
  - capability: "Confirmation modals with results feedback"

affects:
  - phase: 122
    plan: "all"
    why: "Establishes UI patterns for bulk actions in other list views"

tech-stack:
  added: []
  patterns:
    - "Selection state with Set for tracking IDs"
    - "Sticky toolbar with conditional rendering"
    - "Bulk action dropdown with modal confirmations"
    - "Results feedback with sent/marked/failed counts"
    - "useEffect cleanup for outside click detection"

key-files:
  created: []
  modified:
    - path: "src/pages/VOG/VOGList.jsx"
      why: "Added checkbox column, selection state, toolbar, modals, and bulk action mutations"

decisions:
  - decision: "Use Set for selectedIds state"
    rationale: "Efficient O(1) lookups for has/add/delete operations, follows PeopleList pattern"
    alternatives: "Array with filter/includes (less efficient)"

  - decision: "Memoize people array with useMemo"
    rationale: "Prevents useEffect dependency warning and unnecessary re-renders"
    alternatives: "Ignore warning or use people.length (less clean)"

  - decision: "Clear selection when data changes"
    rationale: "Prevents stale selections after filters/refreshes, improves UX"
    alternatives: "Persist selection (could reference deleted/filtered items)"

  - decision: "Show results in modal before closing"
    rationale: "User gets immediate feedback on success/failure before modal closes"
    alternatives: "Toast notification (less detailed feedback)"

tags: [vog, bulk-actions, selection, ui, react, frontend]
---

# Phase 121 Plan 02: Bulk Selection & Actions UI Summary

**One-liner:** Checkbox selection and bulk operations (send emails, mark requested) for VOG list with confirmation modals and results feedback

## What Was Built

Implemented complete bulk selection and actions UI for the VOG list page, following established patterns from PeopleList.jsx:

**Selection System:**
- Checkbox column as first column in table with individual row selection
- Header checkbox with three states: unchecked, checked (all selected), indeterminate (some selected)
- Visual highlight for selected rows (accent background)
- Selection state tracked with Set for efficient O(1) operations
- Auto-clear selection when data changes (filters, refresh)

**Selection Toolbar:**
- Sticky toolbar appears when items selected showing "{N} vrijwilligers geselecteerd"
- Bulk actions dropdown with two options: "VOG email verzenden" and "Markeren als aangevraagd"
- "Selectie wissen" button to clear selection
- Outside click detection to close dropdown
- Dark mode styling throughout

**Confirmation Modals:**
- "Send VOG Email" modal explains auto-template selection (nieuw vs vernieuwing)
- "Mark Requested" modal explains date recording without email
- Both show loading state during operation
- Results feedback shows sent/marked/failed counts with error details
- Selection clears automatically after successful operation

**API Integration:**
- useMutation hooks for bulkSendVOGEmails and bulkMarkVOGRequested
- Query invalidation after successful operations
- Error handling with per-item results display

## Technical Implementation

**Selection State:**
```javascript
const [selectedIds, setSelectedIds] = useState(new Set());
const toggleSelection = (personId) => { /* Set manipulation */ };
const toggleSelectAll = () => { /* Select all vs clear */ };
const isAllSelected = people.length > 0 && selectedIds.size === people.length;
const isSomeSelected = selectedIds.size > 0 && selectedIds.size < people.length;
```

**Mutations:**
```javascript
const sendEmailsMutation = useMutation({
  mutationFn: ({ ids }) => prmApi.bulkSendVOGEmails(ids),
  onSuccess: (response) => {
    setBulkActionResult(response.data);
    queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
  },
});
```

**Checkbox Column:**
- Added as first `<td>` in VOGRow with width constraint (`w-10`)
- Uses CheckSquare (selected) and Square (unselected) icons
- Accent color for selected state
- Click handler prevents default and toggles selection

**Header Checkbox:**
- Shows CheckSquare when all selected
- Shows MinusSquare (indeterminate) when some selected
- Shows Square when none selected
- Title attribute for tooltip explaining action

## Files Changed

**Modified:**
- `src/pages/VOG/VOGList.jsx` (+309 lines)
  - Added imports: useState, useRef, useEffect, useMemo, useMutation
  - Added icons: Square, CheckSquare, MinusSquare, ChevronDown, X
  - Updated VOGRow signature to accept isSelected and onToggleSelection
  - Added selection state and modal state
  - Added bulk action mutations and handlers
  - Added checkbox header column
  - Added checkbox row column
  - Added sticky selection toolbar
  - Added send email modal
  - Added mark requested modal
  - Memoized people array to fix lint warning
  - Fixed escaped quotes in modal text

## Deviations from Plan

None - plan executed exactly as written.

## Challenges & Solutions

**Challenge 1: ESLint dependency warning**
- Issue: `people` reference in useEffect caused exhaustive-deps warning
- Solution: Wrapped `people` in useMemo to stabilize reference
- Why it works: Array identity is stable, only changes when data.people changes

**Challenge 2: ESLint unescaped quotes**
- Issue: Double quotes in JSX string "VOG aangevraagd" flagged by linter
- Solution: Used HTML entity `&quot;` instead of literal quotes
- Why it works: React renders entities correctly, satisfies linter

**Challenge 3: Pre-existing lint errors**
- Issue: 149 lint errors exist across codebase (not from this work)
- Solution: Fixed only new errors introduced by bulk actions feature
- Why it works: Keeps PR clean, doesn't mix concerns with tech debt

## Testing Notes

**Verification checklist:**
1. ✅ Checkbox column appears as first column
2. ✅ Header checkbox toggles all/none selection
3. ✅ Individual checkboxes work for each row
4. ✅ Selected rows have visual highlight (accent background)
5. ✅ Selection toolbar appears when items selected
6. ✅ Toolbar shows correct count and pluralization
7. ✅ "VOG email verzenden" opens confirmation modal
8. ✅ "Markeren als aangevraagd" opens confirmation modal
9. ✅ Modals show loading state during operations
10. ✅ Results feedback shows success/failure counts
11. ✅ Selection clears after successful operation
12. ✅ Dark mode styling works throughout
13. ✅ Outside click closes dropdown
14. ✅ Selection clears when data changes (refresh)

**Production deployed:** https://stadion.svawc.nl/leden/vog

## Lessons Learned

1. **Follow established patterns**: Using PeopleList.jsx as reference ensured consistency and completeness
2. **Set for selection state**: More efficient than Array for frequent add/delete/has operations
3. **Memoization prevents warnings**: Wrapping derived data in useMemo stabilizes references for useEffect deps
4. **User feedback is critical**: Showing results in modal before closing provides better UX than toast
5. **Auto-clear on data change**: Prevents stale selections and confusing UI state after operations

## Next Phase Readiness

**Dependencies satisfied for Phase 122 (Bulk VOG Email Reminders):**
- ✅ Bulk selection UI pattern established
- ✅ Confirmation modal pattern established
- ✅ Results feedback pattern established
- ✅ Dark mode styling consistent
- ✅ Selection toolbar pattern reusable

**No blockers or concerns for next phase.**

## Performance Impact

- Minimal: Selection state uses Set (O(1) operations)
- Checkbox column adds ~40px width to table
- Modals are conditionally rendered (not in DOM when closed)
- Mutations invalidate queries (triggers refetch as expected)
- Memoized people array prevents unnecessary re-renders

## Links & References

- Plan: [121-02-PLAN.md](./121-02-PLAN.md)
- Phase Context: [121-CONTEXT.md](./121-CONTEXT.md)
- Related Phase: [121-01-SUMMARY.md](./121-01-SUMMARY.md)
- Production: https://stadion.svawc.nl/leden/vog
