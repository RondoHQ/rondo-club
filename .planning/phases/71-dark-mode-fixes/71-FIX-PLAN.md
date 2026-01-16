---
phase: 71-dark-mode-fixes
plan: FIX
type: fix
wave: 1
depends_on: []
autonomous: true

must_haves:
  truths:
    - "Settings Connections subtab active button readable in dark mode"
    - "QuickActivityModal selected type button readable in dark mode"
    - "ImportantDateModal people badges have proper contrast in dark mode"
  artifacts:
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Improved active subtab text contrast"
      contains: "dark:text-accent-200"
    - path: "src/components/Timeline/QuickActivityModal.jsx"
      provides: "Improved selected type text contrast"
      contains: "dark:text-accent-200"
    - path: "src/components/ImportantDateModal.jsx"
      provides: "Darker people badge background"
      contains: "dark:bg-accent-800"
---

<objective>
Fix 3 UAT issues from phase 71.

Source: 71-UAT.md
Diagnosed: yes - root causes identified
Priority: 0 blocker, 3 major, 0 minor, 0 cosmetic

All issues share a common pattern: accent-colored text on semi-transparent accent backgrounds (accent-900/30) has insufficient contrast. Fixes involve either using lighter text (accent-200) or darker backgrounds (accent-800).
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issues being fixed:**
@.planning/phases/71-dark-mode-fixes/71-UAT.md

**Original plans for reference:**
@.planning/phases/71-dark-mode-fixes/71-01-PLAN.md
@.planning/phases/71-dark-mode-fixes/71-02-PLAN.md
</context>

<tasks>

<task type="auto">
  <name>Fix Settings active subtab button contrast</name>
  <files>src/pages/Settings/Settings.jsx</files>
  <action>
**Root Cause:** Active subtab button uses dark:text-accent-400 on dark:bg-accent-900/30 background - accent-400 is too dark for this semi-transparent background

**Issue:** "No, the active button is still hard to read"
**Expected:** Active subtab button should be readable with proper contrast

**Fix:** In the CONNECTION_SUBTABS rendering (around line 1566), change the active state from:
`dark:text-accent-400`
to:
`dark:text-accent-200`

This makes the text lighter and more readable against the semi-transparent dark background.
  </action>
  <verify>
- Build succeeds: `npm run build`
- Settings > Connections tab shows readable active button in dark mode
  </verify>
  <done>Settings active subtab button has proper contrast with dark:text-accent-200</done>
</task>

<task type="auto">
  <name>Fix QuickActivityModal selected type contrast</name>
  <files>src/components/Timeline/QuickActivityModal.jsx</files>
  <action>
**Root Cause:** QuickActivityModal selected type button uses dark:text-accent-300 on dark:bg-accent-900/30 background - accent-300 is too dark for this semi-transparent background

**Issue:** "No, the active activity type is still hard to read."
**Expected:** Selected activity type button should be readable with proper contrast

**Fix:** In the activity type button styling (around line 166), change the selected state from:
`dark:text-accent-300`
to:
`dark:text-accent-200`

This makes the text lighter and more readable against the semi-transparent dark background.
  </action>
  <verify>
- Build succeeds: `npm run build`
- Log Activity modal shows readable selected activity type in dark mode
  </verify>
  <done>QuickActivityModal selected type has proper contrast with dark:text-accent-200</done>
</task>

<task type="auto">
  <name>Fix ImportantDateModal people badge background</name>
  <files>src/components/ImportantDateModal.jsx</files>
  <action>
**Root Cause:** People badges use dark:bg-accent-900/30 which is too transparent (light) in dark mode - the 30% opacity doesn't create enough background contrast for text readability

**Issue:** "No, the contrast is way too low as the background is too light"
**Expected:** People badges should have proper text contrast on the accent-colored background

**Fix:** In the selected people badge styling (around line 43), change the background from:
`dark:bg-accent-900/30`
to:
`dark:bg-accent-800`

This creates a solid darker background that provides better contrast for the text.
  </action>
  <verify>
- Build succeeds: `npm run build`
- ImportantDateModal shows readable people badges in dark mode
  </verify>
  <done>ImportantDateModal people badges have proper contrast with dark:bg-accent-800</done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] All 3 major issues fixed
- [ ] Each fix verified against original reported issue
- [ ] Build succeeds
</verification>

<success_criteria>
- All UAT issues from 71-UAT.md addressed
- Build passes
- Ready for re-verification with /gsd:verify-work 71
</success_criteria>

<output>
After completion, create `.planning/phases/71-dark-mode-fixes/71-FIX-SUMMARY.md`
</output>
