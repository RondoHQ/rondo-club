---
phase: 47-move-vog-settings-to-vog-page
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/VOG/VOG.jsx
  - src/pages/VOG/VOGSettings.jsx
  - src/pages/VOG/VOGList.jsx
  - src/pages/Settings/Settings.jsx
  - src/router.jsx
autonomous: true
must_haves:
  truths:
    - "VOG page at /vog shows tabbed layout with Overzicht as default tab"
    - "VOG page has an admin-only Instellingen tab with all VOG settings"
    - "Settings page no longer has a VOG subtab under Admin"
    - "Non-admin users only see Overzicht tab on /vog"
    - "Sidebar link to /vog still works and opens the overview"
  artifacts:
    - path: "src/pages/VOG/VOG.jsx"
      provides: "Parent tabbed container for VOG page"
      min_lines: 30
    - path: "src/pages/VOG/VOGSettings.jsx"
      provides: "Self-contained VOG settings component with own state and API calls"
      min_lines: 80
  key_links:
    - from: "src/pages/VOG/VOG.jsx"
      to: "src/pages/VOG/VOGList.jsx"
      via: "conditional render on activeTab === overzicht"
      pattern: "activeTab.*overzicht.*VOGList"
    - from: "src/pages/VOG/VOG.jsx"
      to: "src/pages/VOG/VOGSettings.jsx"
      via: "conditional render on activeTab === instellingen"
      pattern: "activeTab.*instellingen.*VOGSettings"
    - from: "src/router.jsx"
      to: "src/pages/VOG/VOG.jsx"
      via: "route for /vog and /vog/:tab"
      pattern: "path.*vog.*VOG"
---

<objective>
Move VOG settings from the Settings Admin tab into the VOG page itself, using a tabbed layout identical to the Contributie page pattern.

Purpose: VOG settings logically belong on the VOG page, not buried in Settings > Admin > VOG. This matches the pattern already established by Contributie.
Output: VOG page with tabs (Overzicht + admin-only Instellingen), Settings page cleaned of all VOG code.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/Contributie/Contributie.jsx (pattern to follow exactly)
@src/pages/VOG/VOGList.jsx (existing VOG overview component)
@src/pages/Settings/Settings.jsx (source of VOGTab component and state to extract)
@src/router.jsx (route configuration to update)
@src/components/TabButton.jsx (reusable tab component)
@src/api/client.js (prmApi.getVOGSettings, prmApi.updateVOGSettings, wpApi.getCommissies)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create VOG parent component and extract VOG settings into standalone component</name>
  <files>
    src/pages/VOG/VOG.jsx
    src/pages/VOG/VOGSettings.jsx
  </files>
  <action>
**Create `src/pages/VOG/VOG.jsx`** — Follow the Contributie.jsx pattern exactly:

```jsx
import { useParams, useNavigate, Navigate } from 'react-router-dom';
import TabButton from '@/components/TabButton';
import { VOGList } from './VOGList';  // Note: will need named export, see below
import VOGSettings from './VOGSettings';

const TABS = [
  { id: 'overzicht', label: 'Overzicht' },
  { id: 'instellingen', label: 'Instellingen', adminOnly: true },
];
```

- Use `useParams()` to read `:tab`, default to `'overzicht'`
- Read `isAdmin` from `window.rondoConfig`
- Redirect non-admin accessing `instellingen` to `/vog/overzicht`
- Filter visible tabs based on `adminOnly` and `isAdmin`
- Render tab bar with `<nav>` + `TabButton` components, navigating to `/vog/${t.id}`
- Conditional rendering: `activeTab === 'overzicht'` renders `<VOGList />`, `activeTab === 'instellingen' && isAdmin` renders `<VOGSettings />`
- Use `space-y-6` wrapper div like Contributie

**Update `src/pages/VOG/VOGList.jsx`** — Add a named export alongside the default export:
- Add `export { default as VOGList } from './VOGList'` or simply add `export { VOGList }` as a named export. The simplest approach: keep `export default function VOGList()` and in VOG.jsx import as `import VOGList from './VOGList'` (no named export change needed since default import works fine).
- Actually: just use default import in VOG.jsx — no changes needed to VOGList.jsx.

**Create `src/pages/VOG/VOGSettings.jsx`** — Self-contained settings component that manages its own state and API calls (not receiving props from parent). Extract from Settings.jsx:

- Import `useState, useEffect` from react, `Loader2` from lucide-react, `prmApi, wpApi` from `@/api/client`
- Declare all VOG state internally:
  ```
  const [vogSettings, setVogSettings] = useState({ from_email: '', from_name: '', template_new: '', template_renewal: '', exempt_commissies: [] })
  const [vogLoading, setVogLoading] = useState(true)
  const [vogSaving, setVogSaving] = useState(false)
  const [vogMessage, setVogMessage] = useState('')
  const [commissies, setCommissies] = useState([])
  ```
- Add `useEffect` to fetch VOG settings on mount (adapted from Settings.jsx lines 238-259, but WITHOUT the `isAdmin` check since this component is only rendered for admins):
  ```
  Promise.all([prmApi.getVOGSettings(), wpApi.getCommissies({ per_page: 100, _fields: 'id,title' })])
  ```
- Add `handleVogSave` function (copied from Settings.jsx lines 287-305)
- Render the same UI as the `VOGTab` function in Settings.jsx (lines 3079-3240): heading, loading spinner, form fields (from_email, from_name, template_new, template_renewal, exempt_commissies checkboxes), save button with loading state, message display
- Export as `export default function VOGSettings()`
  </action>
  <verify>
    - `npm run lint` passes with no errors on new files
    - VOG.jsx follows same structure as Contributie.jsx (tabs array, useParams, navigate, conditional rendering)
    - VOGSettings.jsx is fully self-contained (no props needed from parent)
  </verify>
  <done>
    - `src/pages/VOG/VOG.jsx` exists with tabbed layout matching Contributie pattern
    - `src/pages/VOG/VOGSettings.jsx` exists as self-contained settings component
  </done>
</task>

<task type="auto">
  <name>Task 2: Update router and clean up Settings.jsx</name>
  <files>
    src/router.jsx
    src/pages/Settings/Settings.jsx
  </files>
  <action>
**Update `src/router.jsx`:**

1. Change the lazy import from `VOGList` to `VOG`:
   ```jsx
   const VOG = lazy(() => import('@/pages/VOG/VOG'));
   ```
   Remove the `VOGList` import (line 24).

2. Replace the single VOG route (lines 181-188) with two routes matching the Contributie pattern:
   ```jsx
   // VOG routes - requires VOG capability
   {
     path: 'vog',
     element: <VOGRoute><VOG /></VOGRoute>,
   },
   {
     path: 'vog/:tab',
     element: <VOGRoute><VOG /></VOGRoute>,
   },
   ```

**Clean up `src/pages/Settings/Settings.jsx`:**

1. Remove `ADMIN_SUBTABS` entry for VOG (line 37: `{ id: 'vog', label: 'VOG' }`). The array should become:
   ```jsx
   const ADMIN_SUBTABS = [
     { id: 'users', label: 'Gebruikers', icon: Users },
     { id: 'rollen', label: 'Rollen' },
   ];
   ```

2. Remove all VOG state declarations (lines 116-127):
   - `vogSettings`, `vogLoading`, `vogSaving`, `vogMessage`, `vogCommissies` — all 5 useState calls

3. Remove the VOG settings `useEffect` fetch (lines 238-259 — the entire `fetchVogSettings` useEffect block)

4. Remove the `handleVogSave` function (lines 287-305)

5. Remove VOG props from the `AdminTabWithSubtabs` call in the `case 'admin'` switch (lines 661-667):
   - Remove: `vogSettings`, `setVogSettings`, `vogLoading`, `vogSaving`, `vogMessage`, `handleVogSave`, `vogCommissies`

6. Remove VOG props from the `AdminTabWithSubtabs` function signature (lines 2934-2940)

7. Remove the VOG subtab rendering in `AdminTabWithSubtabs` (lines 2976-2985 — the `activeSubtab === 'vog'` ternary branch). The subtab content should go from `users` directly to `rollen`.

8. Delete the entire `VOGTab` function component (lines 3078-3240)

9. Since ADMIN_SUBTABS now has only 2 items, if the default subtab logic references 'vog', update it. The default is `'users'` (line 2967: `activeSubtab === 'users' || !activeSubtab`), so no change needed there.
  </action>
  <verify>
    - `npm run lint` passes with no errors
    - `npm run build` succeeds
    - `grep -r "VOGTab\|vogSettings\|vogLoading\|vogSaving\|vogMessage\|vogCommissies" src/pages/Settings/Settings.jsx` returns nothing
    - `grep "vog" src/pages/Settings/Settings.jsx` returns nothing VOG-settings related (only the ADMIN_SUBTABS should have no vog entry)
    - Router has both `/vog` and `/vog/:tab` routes
  </verify>
  <done>
    - Router updated with `/vog` and `/vog/:tab` routes pointing to VOG parent component
    - Settings.jsx has zero VOG-related code (state, effects, handlers, components, subtab entries all removed)
    - Build succeeds cleanly
  </done>
</task>

</tasks>

<verification>
1. `npm run lint` — no warnings or errors
2. `npm run build` — production build succeeds
3. Navigate to `/vog` — shows tabbed layout with Overzicht tab active, VOG list visible
4. Navigate to `/vog/instellingen` as admin — shows VOG email settings form
5. Navigate to `/vog/instellingen` as non-admin — redirects to `/vog/overzicht`
6. Navigate to `/settings/admin` — no VOG subtab visible
7. Sidebar VOG link still works (points to `/vog`)
</verification>

<success_criteria>
- VOG page has working tabbed layout with Overzicht (default) and admin-only Instellingen tabs
- VOG settings are fully functional on the new Instellingen tab (load, edit, save)
- Settings page has no remaining VOG code
- No lint errors, build succeeds
</success_criteria>

<output>
After completion, create `.planning/quick/47-move-vog-settings-to-vog-page-with-tabbe/47-SUMMARY.md`
</output>
