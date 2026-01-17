---
phase: 77-fixed-height-dashboard-widgets
plan: 77-FIX
type: fix
wave: 1
depends_on: []
autonomous: true

must_haves:
  truths:
    - "Events widget layout remains stable when navigating between days"
    - "Previous meetings data shown while new date's data loads"
  artifacts:
    - path: "src/hooks/useMeetings.js"
      provides: "placeholderData option for useDateMeetings hook"
      contains: "placeholderData"
---

<objective>
Fix 1 UAT issue from phase 77.

Source: 77-UAT.md
Diagnosed: yes - root cause identified
Priority: 0 blocker, 1 major, 0 minor, 0 cosmetic
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issue being fixed:**
@.planning/phases/77-fixed-height-dashboard-widgets/77-UAT.md

**Original plan for reference:**
@.planning/phases/77-fixed-height-dashboard-widgets/77-01-PLAN.md

**File to modify:**
@src/hooks/useMeetings.js
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix meetings widget layout jump during date navigation</name>
  <files>src/hooks/useMeetings.js</files>
  <action>
**Root Cause:** useDateMeetings hook doesn't preserve previous data during fetch. When date changes, query key changes and data becomes undefined briefly, causing 'No meetings' empty state to flash and resize widget.

**Issue:** "when i click back and forth between days on the Events widget, it jumps"

**Expected:** Layout remains stable during data loading and refresh

**Fix:** Add `placeholderData: (previousData) => previousData` to the useDateMeetings hook's useQuery options. This keeps the previous date's data visible while the new date's data is being fetched, preventing the empty state flash.

In TanStack Query v5, this is the replacement for the deprecated `keepPreviousData: true` option.

**Implementation:**
Find the `useDateMeetings` function (around line 40) and add `placeholderData` to the useQuery options:

```javascript
export function useDateMeetings(date) {
  const dateStr = format(date, 'yyyy-MM-dd');
  return useQuery({
    queryKey: meetingsKeys.forDate(dateStr),
    queryFn: async () => {
      const response = await prmApi.getMeetingsForDate(dateStr);
      return response.data;
    },
    enabled: !!date,
    staleTime: 5 * 60 * 1000,
    refetchInterval: 15 * 60 * 1000,
    placeholderData: (previousData) => previousData,  // Keep previous data while fetching
  });
}
```
  </action>
  <verify>
1. Run `npm run build` - should complete without errors
2. On production: navigate between days in Events widget - should not jump or flash empty state
  </verify>
  <done>useDateMeetings hook now preserves previous data during fetch, preventing layout jump</done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] useDateMeetings has placeholderData option
- [ ] Build passes
- [ ] Deploy to production
- [ ] Events widget does not jump when navigating between days
</verification>

<success_criteria>
- UAT issue from 77-UAT.md addressed
- Build passes
- Ready for re-verification with /gsd:verify-work 77
</success_criteria>

<output>
After completion, create `.planning/phases/77-fixed-height-dashboard-widgets/77-FIX-SUMMARY.md`
</output>
