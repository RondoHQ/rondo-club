---
phase: 76-add-email-to-existing-person
plan: 76-FIX
type: fix
wave: 1
depends_on: []
files_modified:
  - src/hooks/usePeople.js
  - src/components/AddAttendeePopup.jsx
autonomous: true

must_haves:
  truths:
    - "Selecting person in search adds email to their contact_info without API error"
    - "Popup height sufficient for search mode without scrolling"
  artifacts:
    - path: "src/hooks/usePeople.js"
      provides: "Fixed useAddEmailToPerson hook"
      contains: "first_name"
    - path: "src/components/AddAttendeePopup.jsx"
      provides: "Taller popup in search mode"
      contains: "max-h-"
---

<objective>
Fix 2 UAT issues from phase 76.

Source: 76-UAT.md
Diagnosed: yes (root causes identified)
Priority: 1 blocker, 0 major, 1 minor, 0 cosmetic
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issues being fixed:**
@.planning/phases/76-add-email-to-existing-person/76-UAT.md

**Original plan for reference:**
@.planning/phases/76-add-email-to-existing-person/76-01-PLAN.md

**Key files:**
@src/hooks/usePeople.js
@src/components/AddAttendeePopup.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix useAddEmailToPerson to preserve existing ACF fields</name>
  <files>src/hooks/usePeople.js</files>
  <action>
**Root Cause:** useAddEmailToPerson sends only contact_info in ACF update, but API requires first_name. The PATCH request only includes `{ acf: { contact_info: [...] } }` which fails validation.

**Issue:** User reported: "Selecting person gives error: rest_invalid_param - first_name is a required property of acf."

**Expected:** Click a person in search results. Popup closes. Email is added to that person's contact_info.

**Fix:** The useAddEmailToPerson hook already fetches the fresh person data. Instead of sending only contact_info in the update, include all required ACF fields from the fetched person data. The minimal fix is to include first_name and last_name (required fields) along with contact_info.

Update the mutationFn to:
```javascript
await wpApi.updatePerson(personId, {
  acf: {
    first_name: person.acf?.first_name || '',
    last_name: person.acf?.last_name || '',
    contact_info: [...currentContacts, newContact],
  },
});
```

This preserves the required fields while updating contact_info.
  </action>
  <verify>
- `npm run build` succeeds
- Test manually: Click Add on unknown attendee → Add to existing → Search → Select person → No error, email added
  </verify>
  <done>useAddEmailToPerson includes required ACF fields (first_name, last_name) in update payload</done>
</task>

<task type="auto">
  <name>Task 2: Increase popup height in search mode</name>
  <files>src/components/AddAttendeePopup.jsx</files>
  <action>
**Root Cause:** Popup height too constrained for search mode with results - max-height class is too small.

**Issue:** User reported: "popup is too small - search person interface requires scrolling"

**Expected:** Popup renders correctly with sufficient height to show search results without excessive scrolling.

**Fix:** Increase max-height for the popup container in search mode. The popup should be tall enough to show:
- Search input
- At least 4-5 person results without scrolling
- Back button

Change max-height from current value to a larger value (e.g., `max-h-80` or `max-h-96` for 320px or 384px) in search mode.

If the popup uses a single max-height, consider using a conditional class based on mode:
- Choice mode: current compact height is fine
- Search mode: taller height for results
  </action>
  <verify>
- `npm run build` succeeds
- Test manually: Open Add popup → Click "Add to existing" → Search mode shows with more vertical space
- Can see at least 4-5 results without scrolling
  </verify>
  <done>Popup height increased in search mode to accommodate results without scrolling</done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] Blocker fixed: Selecting person adds email without API error
- [ ] Minor fixed: Popup height sufficient in search mode
- [ ] Both fixes verified against original reported issues
- [ ] Build passes
- [ ] Deploy to production
</verification>

<success_criteria>
- All UAT issues from 76-UAT.md addressed
- Tests pass
- Ready for re-verification with /gsd:verify-work 76
</success_criteria>

<output>
After completion, create `.planning/phases/76-add-email-to-existing-person/76-FIX-SUMMARY.md`
</output>
