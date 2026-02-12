---
phase: 60-distinguish-former-members-on-person-det
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PersonDetail.jsx
autonomous: true

must_haves:
  truths:
    - "Former members are visually distinguished on person detail pages"
    - "Users can see when a person's membership ended (lid-tot date)"
    - "Former member status is clearly indicated in the profile header"
  artifacts:
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Former member badge and lid-tot display"
      min_lines: 1830
  key_links:
    - from: "PersonDetail profile header"
      to: "acf.former_member"
      via: "conditional badge rendering"
      pattern: "acf.former_member.*Oud-lid"
    - from: "PersonDetail metadata line"
      to: "acf['lid-tot']"
      via: "date formatting"
      pattern: "acf\\['lid-tot'\\].*format"
---

<objective>
Add visual distinction for former members on person detail pages, matching the "Oud-lid" badge pattern already used in PeopleList.

Purpose: Help users quickly identify former members when viewing person details
Output: Former member badge next to name, lid-tot date in metadata line
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/People/PersonDetail.jsx
@src/pages/People/PeopleList.jsx
@src/utils/dateFormat.js
</context>

<tasks>

<task type="auto">
  <name>Add former member visual indicators to PersonDetail</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
Add visual distinction for former members in the PersonDetail profile header (starting around line 946):

1. **Add "Oud-lid" badge next to the person's name** (around line 998-1001):
   - After the person name and deceased indicator (†), add a conditional badge
   - Use the same styling as PeopleList: `<span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">Oud-lid</span>`
   - Condition: `{acf.former_member && ...}`
   - The `acf` object is already available (line 885)

2. **Add "Lid tot: {date}" to the metadata line** (around line 1027-1041):
   - In the metadata paragraph that shows gender, pronouns, age, and financiële blokkade
   - After the existing information, add: `{acf['lid-tot'] && <><span>&nbsp;—&nbsp;</span><span>Lid tot: {format(parseISO(acf['lid-tot']), 'd MMMM yyyy')}</span></>}`
   - Only show if `acf['lid-tot']` is set
   - Use the `format` and `parseISO` functions already imported from `@/utils/dateFormat`
   - Add separator (`&nbsp;—&nbsp;`) before if there's other content

3. **Optional: Subtle background for former members**:
   - The header card (line 946) already has conditional background for `financiele-blokkade`: `bg-red-50 dark:bg-red-950/30`
   - Add similar treatment for former members: if `acf.former_member && !acf['financiele-blokkade']`, apply `bg-gray-50 dark:bg-gray-900/30`
   - This gives a subtle visual distinction without being too prominent
   - Use ternary: `className={`card p-6 relative ${acf['financiele-blokkade'] ? 'bg-red-50 dark:bg-red-950/30' : acf.former_member ? 'bg-gray-50 dark:bg-gray-900/30' : ''}`}`

**Why these patterns:**
- Match PeopleList "Oud-lid" badge for consistency
- Use existing metadata line format (separator style, font sizing)
- Subtle background differentiates without alarming (red is for financial block)
- Parse ISO date with Dutch locale formatting (d MMMM yyyy → "15 januari 2024")
  </action>
  <verify>
1. Build production assets: `npm run build`
2. Check PersonDetail renders without errors
3. Visually verify former member indicators appear correctly (test with a person who has `acf.former_member = true` and `acf['lid-tot']` set)
  </verify>
  <done>
- Former members show "Oud-lid" badge next to their name
- If lid-tot date is set, it appears in metadata line formatted as "Lid tot: 15 januari 2024"
- Former member profile cards have subtle gray background (unless financiële blokkade overrides)
- Changes are visible on production after deployment
  </done>
</task>

</tasks>

<verification>
## Visual Checks

Test with:
1. A former member with lid-tot date → should show badge AND "Lid tot: {date}"
2. A former member without lid-tot date → should show badge only
3. A current member → should show neither badge nor lid-tot
4. A former member with financiële blokkade → red background should take precedence

## Code Verification

```bash
# Verify PersonDetail compiles
npm run build

# Check for "Oud-lid" pattern
grep -n "Oud-lid" src/pages/People/PersonDetail.jsx

# Check for lid-tot formatting
grep -n "lid-tot" src/pages/People/PersonDetail.jsx | grep format
```
</verification>

<success_criteria>
- [x] Former members are visually distinguished with "Oud-lid" badge
- [x] Lid-tot date displays in Dutch format when available
- [x] Styling matches PeopleList pattern for consistency
- [x] Former member cards have subtle gray background
- [x] Changes deployed to production
- [x] No build errors or console warnings
</success_criteria>

<output>
After completion, create `.planning/quick/60-distinguish-former-members-on-person-det/60-SUMMARY.md`
</output>
