---
phase: quick-023
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/VOG/VOGList.jsx
autonomous: true

must_haves:
  truths:
    - "Verzonden column displays dates in yyyy-MM-dd format"
    - "Justis column displays dates in yyyy-MM-dd format"
  artifacts:
    - path: "src/pages/VOG/VOGList.jsx"
      provides: "VOG date formatting"
      contains: "yyyy-MM-dd"
  key_links: []
---

<objective>
Change the date display format in the Verzonden and Justis columns on the VOG page from `d MMM yyyy` (e.g., "30 jan 2026") to `yyyy-MM-dd` (e.g., "2026-01-30").

Purpose: User preference for ISO date format for easier sorting/comparison at a glance.
Output: Updated VOGList.jsx with new date format.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/VOG/VOGList.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update date format in VOGList.jsx</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
In the VOGRow component, update the date format strings for the Verzonden and Justis columns:

1. Line 194: Change `format(new Date(person.acf['vog_email_sent_date']), 'd MMM yyyy')` to `format(new Date(person.acf['vog_email_sent_date']), 'yyyy-MM-dd')`

2. Line 201: Change `format(new Date(person.acf['vog_justis_submitted_date']), 'd MMM yyyy')` to `format(new Date(person.acf['vog_justis_submitted_date']), 'yyyy-MM-dd')`

The format utility from `@/utils/dateFormat` already supports this format string.
  </action>
  <verify>
- `npm run lint` passes
- `npm run build` completes without errors
  </verify>
  <done>
Both Verzonden and Justis columns display dates in yyyy-MM-dd format (e.g., "2026-01-30").
  </done>
</task>

</tasks>

<verification>
- Build completes: `npm run build`
- Lint passes: `npm run lint`
- Deploy and verify on production VOG page that dates display as yyyy-MM-dd
</verification>

<success_criteria>
- Verzonden column shows dates like "2026-01-30" instead of "30 jan 2026"
- Justis column shows dates like "2026-01-30" instead of "30 jan 2026"
- No build or lint errors
</success_criteria>

<output>
After completion, create `.planning/quick/023-vog-date-format-yyyy-mm-dd/023-SUMMARY.md`
</output>
