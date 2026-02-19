---
phase: quick-109
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/layout/Layout.jsx
autonomous: true
must_haves:
  truths:
    - "The sidebar section header displays 'Financiën' with the correct diaeresis"
    - "The page title for /financien/instellingen reads 'Financiën Instellingen'"
  artifacts:
    - path: "src/components/layout/Layout.jsx"
      provides: "Corrected display strings for the Financiën nav section"
      contains: "Financiën"
  key_links:
    - from: "src/components/layout/Layout.jsx"
      to: "sidebar nav section header"
      via: "navItems array name property"
      pattern: "name: 'Financiën'"
---

<objective>
Replace the two occurrences of the misspelled display string `'Financien'` with the correctly accented `'Financiën'` in Layout.jsx.

Purpose: The sidebar section header and the page title helper both show "Financien" without the diaeresis, which is incorrect Dutch. URL paths (/financien/) are left unchanged — only the user-visible display strings are fixed.
Output: Layout.jsx with both strings corrected.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix Financiën display strings in Layout.jsx</name>
  <files>src/components/layout/Layout.jsx</files>
  <action>
    Make exactly two targeted string replacements in src/components/layout/Layout.jsx:

    1. Line 48 — navItems array entry:
       Change: `{ name: 'Financien', type: 'section', icon: Wallet, requiresFinancieel: true },`
       To:     `{ name: 'Financiën', type: 'section', icon: Wallet, requiresFinancieel: true },`

    2. Line 529 — page title helper function:
       Change: `if (path.startsWith('/financien/instellingen')) return 'Financien Instellingen';`
       To:     `if (path.startsWith('/financien/instellingen')) return 'Financiën Instellingen';`

    Do NOT change any URL path strings (e.g., '/financien/instellingen', '/financien/contributie') — those must remain ASCII for routing to work.
  </action>
  <verify>
    Run: grep -n "Financien" src/components/layout/Layout.jsx
    Expected: no output (zero remaining occurrences of the unaccented form).

    Run: grep -n "Financiën" src/components/layout/Layout.jsx
    Expected: two lines — one in the navItems array, one in the page title helper.

    Then build: npm run build
    Expected: build completes without errors.
  </verify>
  <done>
    Both 'Financiën' occurrences present, zero 'Financien' display strings remain, build passes.
  </done>
</task>

</tasks>

<verification>
grep -n "Financien" /Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx
# Must return no matches (only URL paths contain the ASCII form, not display strings)

grep -n "Financiën" /Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx
# Must return exactly 2 lines
</verification>

<success_criteria>
- `grep "Financien" src/components/layout/Layout.jsx` returns no display-string matches
- `grep "Financiën" src/components/layout/Layout.jsx` returns exactly 2 lines
- `npm run build` exits 0
- Commit pushed, production deployed
</success_criteria>

<output>
After completion, create `.planning/quick/109-replace-financien-with-financi-n-everywh/109-SUMMARY.md`
</output>
