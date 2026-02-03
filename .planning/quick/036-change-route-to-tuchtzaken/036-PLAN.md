---
phase: quick
plan: 036
type: execute
wave: 1
depends_on: []
files_modified:
  - src/App.jsx
  - src/components/layout/Layout.jsx
autonomous: true

must_haves:
  truths:
    - "Navigating to /tuchtzaken displays discipline cases list"
    - "Navigation menu links to /tuchtzaken"
    - "/discipline-cases redirects to /tuchtzaken or 404s"
  artifacts:
    - path: "src/App.jsx"
      provides: "Route definition"
      contains: "/tuchtzaken"
    - path: "src/components/layout/Layout.jsx"
      provides: "Navigation link"
      contains: "/tuchtzaken"
  key_links:
    - from: "src/components/layout/Layout.jsx"
      to: "src/App.jsx"
      via: "href matches route path"
      pattern: "/tuchtzaken"
---

<objective>
Change the frontend route for discipline cases from `/discipline-cases` to `/tuchtzaken`.

Purpose: Use Dutch URL for consistency with the Dutch UI ("Tuchtzaken" navigation label)
Output: Updated route and navigation link
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevolk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/App.jsx
@src/components/layout/Layout.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update route path and navigation link</name>
  <files>src/App.jsx, src/components/layout/Layout.jsx</files>
  <action>
Update the discipline cases route from `/discipline-cases` to `/tuchtzaken`:

1. In `src/App.jsx` line 258:
   - Change `<Route path="/discipline-cases"` to `<Route path="/tuchtzaken"`

2. In `src/components/layout/Layout.jsx` line 52:
   - Change `href: '/discipline-cases'` to `href: '/tuchtzaken'`

Note: Do NOT change the API endpoints (`/wp/v2/discipline-cases`) - those are backend REST routes and remain unchanged.
  </action>
  <verify>
    1. `npm run lint` passes
    2. `npm run build` succeeds
    3. grep -r "discipline-cases" src/ returns only API client references (api/client.js and hooks)
  </verify>
  <done>
    - Route path is `/tuchtzaken` in App.jsx
    - Navigation href is `/tuchtzaken` in Layout.jsx
    - No lint errors
    - Build succeeds
  </done>
</task>

</tasks>

<verification>
1. `npm run lint` passes
2. `npm run build` succeeds
3. Only API references to `discipline-cases` remain in src/ (client.js, hooks)
</verification>

<success_criteria>
- Clicking "Tuchtzaken" in navigation goes to /tuchtzaken
- /tuchtzaken displays discipline cases list page
- No hardcoded `/discipline-cases` references in frontend routes or navigation
</success_criteria>

<output>
After completion, create `.planning/quick/036-change-route-to-tuchtzaken/036-SUMMARY.md`
</output>
