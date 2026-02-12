---
phase: 62-make-vrijwilliger-badge-more-prominent
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PersonDetail.jsx
autonomous: false

must_haves:
  truths:
    - "Vrijwilliger badge is visually prominent on PersonDetail"
    - "Badge uses brand color (electric-cyan) effectively"
    - "Badge is more noticeable than Oud-lid badge"
  artifacts:
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Prominent Vrijwilliger badge styling"
      contains: "bg-electric-cyan"
  key_links:
    - from: "PersonDetail badge"
      to: "acf['huidig-vrijwilliger']"
      via: "conditional rendering"
      pattern: "acf\\['huidig-vrijwilliger'\\]"
---

<objective>
Make the Vrijwilliger badge more prominent on PersonDetail page.

Purpose: The volunteer badge should be eye-catching to immediately identify volunteers, while the Oud-lid badge remains subdued.
Output: Updated badge styling with solid brand-colored background.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/People/PersonDetail.jsx
</context>

<tasks>

<task type="auto">
  <name>Update Vrijwilliger badge styling</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
Update the Vrijwilliger badge at lines 1042-1046 to use a solid brand-colored background with white text for prominence.

Current styling:
```jsx
bg-electric-cyan/10 text-electric-cyan dark:bg-electric-cyan/20 dark:text-electric-cyan-light
```

New styling:
```jsx
bg-electric-cyan text-white dark:bg-electric-cyan dark:text-white
```

This creates a solid, eye-catching badge that contrasts with the subdued gray Oud-lid badge and makes volunteers immediately identifiable.

Keep all other badge properties (inline-flex, items-center, px-1.5, py-0.5, rounded, text-xs, font-medium).
  </action>
  <verify>
```bash
npm run build
grep -A2 "huidig-vrijwilliger" src/pages/People/PersonDetail.jsx | grep "bg-electric-cyan text-white"
```
  </verify>
  <done>Badge uses solid electric-cyan background with white text in both light and dark mode</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Verify badge prominence on production</name>
  <what-built>Updated Vrijwilliger badge with solid brand-colored background</what-built>
  <action>Human verifies the visual prominence of the updated badge on production</action>
  <how-to-verify>
1. Deploy to production: `bin/deploy.sh`
2. Visit production site and navigate to a volunteer's PersonDetail page (someone with huidig-vrijwilliger = true)
3. Verify the Vrijwilliger badge has a solid electric-cyan background (#0891b2) with white text
4. Compare to Oud-lid badge (if present) — Vrijwilliger should be significantly more prominent
5. Test dark mode toggle — badge should remain solid cyan with white text
6. Badge should be eye-catching but not garish
  </how-to-verify>
  <verify>Visual inspection of badge prominence</verify>
  <done>Badge is visually prominent and uses brand color effectively</done>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
- Badge uses solid `bg-electric-cyan` with `text-white`
- Build completes without errors
- Visual prominence significantly improved vs previous `/10` opacity
</verification>

<success_criteria>
- Vrijwilliger badge is immediately noticeable on PersonDetail
- Badge uses brand color effectively (solid cyan background, white text)
- Badge is more prominent than the subdued Oud-lid badge
- Works correctly in both light and dark modes
</success_criteria>

<output>
After completion, create `.planning/quick/62-make-vrijwilliger-badge-more-prominent/62-SUMMARY.md`
</output>
