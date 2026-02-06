---
phase: quick
plan: 041
type: execute
wave: 1
depends_on: []
files_modified:
  - src/index.css
  - src/components/layout/Layout.jsx
autonomous: true

must_haves:
  truths:
    - "Primary buttons are readable in dark mode regardless of club accent color"
    - "Active menu items are clearly visible in dark mode"
    - "Search result selections have sufficient contrast in dark mode"
  artifacts:
    - path: "src/index.css"
      provides: "Dark mode button styles with gray-based backgrounds"
    - path: "src/components/layout/Layout.jsx"
      provides: "Dark mode nav and search styles with accent borders instead of backgrounds"
  key_links:
    - from: "btn-primary dark mode"
      to: "readable text"
      via: "gray background with accent border or text"
---

<objective>
Fix dark mode contrast issues when club accent color is dark (e.g., dark green #006935).

Purpose: Ensure UI elements remain readable in dark mode regardless of the configured club accent color. The current approach uses accent-colored backgrounds in dark mode, which fails when the accent color itself is dark.

Output: Updated CSS and JSX with dark mode styles that use neutral gray backgrounds with accent-colored borders or text for visual distinction, rather than accent-colored backgrounds with white text.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/index.css
@src/components/layout/Layout.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix dark mode button and accent background styles</name>
  <files>src/index.css</files>
  <action>
Update the `.btn-primary` class to use a gray background with accent border in dark mode instead of accent background with white text:

Current (line 363-365):
```css
.btn-primary {
  @apply btn bg-accent-600 text-white hover:bg-accent-700 focus:ring-accent-500 dark:bg-accent-500 dark:hover:bg-accent-400;
}
```

Change to:
```css
.btn-primary {
  @apply btn bg-accent-600 text-white hover:bg-accent-700 focus:ring-accent-500 dark:bg-gray-700 dark:text-accent-400 dark:border dark:border-accent-500 dark:hover:bg-gray-600 dark:hover:text-accent-300;
}
```

This approach:
- Uses gray-700 background (always readable)
- Uses accent-400/300 text (lighter accent shades that work in dark mode)
- Adds accent-500 border for visual accent tie-in
- Maintains hover states with appropriate color shifts
  </action>
  <verify>Run `npm run build` successfully. Visually inspect btn-primary in dark mode.</verify>
  <done>Primary buttons display with gray background and accent text/border in dark mode, ensuring readability.</done>
</task>

<task type="auto">
  <name>Task 2: Fix dark mode navigation and search selection styles</name>
  <files>src/components/layout/Layout.jsx</files>
  <action>
Update two locations in Layout.jsx:

1. NavLink active state (around line 132-134):
Current:
```jsx
isActive
  ? 'bg-accent-50 text-accent-700 dark:bg-gray-700 dark:text-accent-300'
  : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700'
```
Change to:
```jsx
isActive
  ? 'bg-accent-50 text-accent-700 dark:bg-gray-700 dark:text-accent-400 dark:border-l-2 dark:border-accent-400'
  : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700'
```
Uses accent-400 text (brighter) with left border accent indicator.

2. Search result selected state - people (around line 379-380):
Current:
```jsx
isSelected ? 'bg-accent-50 text-accent-900 dark:bg-accent-700 dark:text-white' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
```
Change to:
```jsx
isSelected ? 'bg-accent-50 text-accent-900 dark:bg-gray-700 dark:text-accent-400 dark:ring-1 dark:ring-accent-500' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
```

3. Search result selected state - teams (around line 419-420):
Same pattern as people - change from dark:bg-accent-700 dark:text-white to dark:bg-gray-700 dark:text-accent-400 dark:ring-1 dark:ring-accent-500.

The pattern: In dark mode, use gray backgrounds with accent text and subtle accent ring/border for selection indicator instead of accent backgrounds.
  </action>
  <verify>Run `npm run build` successfully. Test in browser with dark mode enabled - navigate sidebar, use Cmd+K search and arrow through results.</verify>
  <done>Active nav items show accent text with left border indicator. Search selections use gray background with accent text and ring.</done>
</task>

<task type="auto">
  <name>Task 3: Build, deploy, and verify</name>
  <files>dist/</files>
  <action>
1. Run `npm run build` to compile changes
2. Deploy to production using `bin/deploy.sh`
3. Clear caches
4. Test on production in dark mode:
   - Check primary buttons (e.g., Save button in settings)
   - Check active menu item in sidebar
   - Check search modal selection highlighting (Cmd+K)
  </action>
  <verify>Production site shows readable dark mode UI elements.</verify>
  <done>All three contrast issues fixed: buttons, nav active state, and search selections are readable in dark mode.</done>
</task>

</tasks>

<verification>
- npm run build succeeds without errors
- In dark mode with dark accent color:
  - Primary buttons have gray background with visible accent text
  - Active nav items have accent text with border indicator
  - Search result selections have gray background with accent ring
- Light mode behavior unchanged
</verification>

<success_criteria>
- All UI elements readable in dark mode regardless of club accent color
- Visual accent tie-in maintained through text color and borders
- No regression in light mode appearance
- Production deployed and verified
</success_criteria>

<output>
After completion, create `.planning/quick/041-dark-mode-contrast-fixes/041-SUMMARY.md`
</output>
