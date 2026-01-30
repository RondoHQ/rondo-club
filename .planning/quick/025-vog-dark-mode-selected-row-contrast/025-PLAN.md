---
phase: quick-025
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/VOG/VOGList.jsx
autonomous: true

must_haves:
  truths:
    - "Selected rows in dark mode have readable text"
    - "Text contrast meets WCAG AA standards"
    - "Normal unselected rows are unchanged"
  artifacts:
    - path: "src/pages/VOG/VOGList.jsx"
      provides: "Fixed dark mode selected row contrast"
      contains: "isSelected.*dark:text"
  key_links: []
---

<objective>
Fix dark mode contrast issue on VOG page where selected row text becomes unreadable.

Purpose: Selected rows use `bg-accent-900/30` background in dark mode, but text remains
gray (`dark:text-gray-50` and `dark:text-gray-400`), creating poor contrast. When multiple
rows are selected, users cannot read the row content.

Output: VOGList.jsx with proper text colors for selected state in dark mode.
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
  <name>Task 1: Fix selected row text contrast in dark mode</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
Update the VOGRow component (around line 115-206) to apply proper text colors when selected in dark mode.

**Current issue location (lines 120-124):**
```jsx
<tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
  isSelected
    ? 'bg-accent-50 dark:bg-accent-900/30'
    : isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
}`}>
```

The `bg-accent-900/30` (any accent color at 30% opacity) does not contrast well with:
- `dark:text-gray-50` (name link at line 141)
- `dark:text-gray-400` (KNVB ID, email, phone, dates at lines 150, 162, 171, 180, 192, 199)

**Solution approach:**
Rather than updating each text element individually (error-prone), apply conditional text classes to the `<tr>` element that child elements will inherit where appropriate, OR update each text element to be conditional on `isSelected`.

**Recommended fix:**
Update text color classes in each cell to be selection-aware:

1. Name cell (line 141): Change from `text-gray-900 dark:text-gray-50` to include selected state handling:
   ```jsx
   className={`text-sm font-medium ${
     isSelected
       ? 'text-gray-900 dark:text-white'
       : 'text-gray-900 dark:text-gray-50'
   }`}
   ```
   Note: For the name, `dark:text-white` provides strong contrast on the accent background.

2. Secondary text cells (KNVB ID, Email, Phone, Dates at lines 150, 162, 171, 180, 192, 199):
   Change from `text-gray-500 dark:text-gray-400` to include selected state:
   ```jsx
   className={`px-4 py-3 text-sm ${
     isSelected
       ? 'text-gray-700 dark:text-gray-100'
       : 'text-gray-500 dark:text-gray-400'
   }`}
   ```

**Important:** Pass `isSelected` prop through to any child styling that needs it. The VOGRow component already receives `isSelected` as a prop.

**Also update:**
- The link hover state for email/phone should remain `hover:text-accent-600 dark:hover:text-accent-400`
- VOGBadge and VOGEmailIndicator do not need changes (they have their own distinct colors)
  </action>
  <verify>
1. Run `npm run build` - should complete without errors
2. Deploy to production with `bin/deploy.sh`
3. Open VOG page in production
4. Switch to dark mode
5. Select one or more rows
6. Verify: Name text is clearly readable (white on accent background)
7. Verify: Secondary text (KNVB ID, email, phone, dates) is readable (light gray on accent background)
8. Verify: Unselected rows still look normal (gray tones)
  </verify>
  <done>
- Selected rows in dark mode have white/light gray text that contrasts with accent-900/30 background
- WCAG AA contrast ratio achieved for all text in selected state
- Unselected rows unchanged
- Build passes
  </done>
</task>

</tasks>

<verification>
- npm run build completes successfully
- Dark mode selected rows have readable text
- Light mode selected rows remain unchanged
- Unselected rows in both modes unchanged
</verification>

<success_criteria>
- Text in selected rows is readable in dark mode
- No visual regression in light mode
- No visual regression for unselected rows
</success_criteria>

<output>
After completion, create `.planning/quick/025-vog-dark-mode-selected-row-contrast/025-SUMMARY.md`
</output>
