---
phase: quick
plan: 010
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PersonDetail.jsx
  - public/icons/sportlink.png
autonomous: true

must_haves:
  truths:
    - "VOG status indicator shows in Person header for members with non-Donateur work functions"
    - "VOG OK (green) when VOG date exists and < 3 years old"
    - "VOG verlopen (orange) when VOG date exists but > 3 years old"
    - "Geen VOG (red) when no VOG date"
    - "Sportlink icon link shows for persons with KNVB ID"
  artifacts:
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "VOG indicator and Sportlink link in header"
    - path: "public/icons/sportlink.png"
      provides: "Sportlink favicon icon"
  key_links:
    - from: "PersonDetail.jsx"
      to: "acf.vog_datum"
      via: "date comparison logic"
    - from: "PersonDetail.jsx"
      to: "acf.knvb_id"
      via: "Sportlink URL construction"
---

<objective>
Add VOG status indicator and Sportlink link to the Person detail header.

Purpose: Display important member compliance info (VOG certificate status) and quick access to Sportlink for members with KNVB IDs.

Output: Updated PersonDetail.jsx with VOG badge and Sportlink icon link in the header area.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/pages/People/PersonDetail.jsx (lines 1500-1750 for header structure)
@acf-json/group_person_fields.json

Key findings:
- Header is in PersonDetail.jsx around line 1545 ("Profile header" section)
- Financial block indicator already exists as inline text (lines 1622-1635)
- ACF fields are accessed via `acf.fieldname` pattern
- Custom fields like VOG date (vog_datum), KNVB ID (knvb_id), and work functions (werkfuncties)
  are defined via WordPress admin and stored in acf-json
- The header currently shows: photo, name, positions, nickname, age, gender, financial block, labels, social links
</context>

<tasks>

<task type="auto">
  <name>Task 1: Download Sportlink favicon</name>
  <files>public/icons/sportlink.png</files>
  <action>
Download the Sportlink favicon from https://club.sportlink.com/assets/favicon/apple-icon-60x60.png
and save it to public/icons/sportlink.png

Use curl to fetch the image:
```bash
curl -o public/icons/sportlink.png "https://club.sportlink.com/assets/favicon/apple-icon-60x60.png"
```
  </action>
  <verify>File exists at public/icons/sportlink.png and is a valid PNG image</verify>
  <done>Sportlink icon saved to public/icons/sportlink.png</done>
</task>

<task type="auto">
  <name>Task 2: Add VOG indicator and Sportlink link to Person header</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
Add two elements to the Person header area (inside the profile card, near the name/labels):

**1. VOG Status Indicator:**
Create a helper function to determine VOG status based on:
- `acf.werkfuncties` - array of work function strings, check if person has any function other than "Donateur"
- `acf.vog_datum` - date string in Y-m-d format

Logic:
```javascript
// Helper to calculate VOG status
function getVogStatus(acf) {
  // Check if person has work functions other than "Donateur"
  const werkfuncties = acf?.werkfuncties || [];
  const hasNonDonateurFunction = werkfuncties.some(fn => fn !== 'Donateur');

  if (!hasNonDonateurFunction) {
    return null; // Don't show VOG indicator for Donateurs only
  }

  const vogDate = acf?.vog_datum;
  if (!vogDate) {
    return { status: 'missing', label: 'Geen VOG', color: 'red' };
  }

  const vogDateObj = new Date(vogDate);
  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  if (vogDateObj >= threeYearsAgo) {
    return { status: 'valid', label: 'VOG OK', color: 'green' };
  } else {
    return { status: 'expired', label: 'VOG verlopen', color: 'orange' };
  }
}
```

Display the VOG indicator as a badge in the header, positioned near the top-right of the card (similar placement to where financial block is shown, but as a badge). Use these colors:
- Green: `bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`
- Orange: `bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`
- Red: `bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`

**2. Sportlink Link:**
If `acf.knvb_id` exists (truthy value), show a small icon link:
- URL: `https://club.sportlink.com/member/{knvb_id}` (the knvb_id is the member ID in Sportlink)
- Icon: Use the downloaded sportlink.png from public/icons
- Title attribute: "Bekijk in Sportlink Club"
- No text label, just the icon
- Open in new tab with rel="noopener noreferrer"

Place the Sportlink icon in the social links area (line ~1701-1727) alongside other social icons, but use the Sportlink favicon image instead of a lucide icon.

**Implementation location:**
- Add getVogStatus helper function near other helper functions (around line 56-92)
- Add VOG badge in the profile header card, perhaps after the labels section or as absolute positioned element in top-right of the card
- Add Sportlink link in the social links section
  </action>
  <verify>
1. Visit a person detail page with KNVB ID - Sportlink icon should appear and link correctly
2. Visit a person with werkfunctie "Jeugdtrainer" and vog_datum within 3 years - shows "VOG OK" in green
3. Visit a person with werkfunctie other than Donateur and vog_datum > 3 years old - shows "VOG verlopen" in orange
4. Visit a person with non-Donateur werkfunctie but no vog_datum - shows "Geen VOG" in red
5. Visit a person with only "Donateur" werkfunctie - no VOG indicator shown
  </verify>
  <done>
- VOG status indicator displays correctly based on werkfuncties and vog_datum
- Sportlink icon link appears for persons with KNVB ID
- Both elements styled consistently with existing header design
  </done>
</task>

</tasks>

<verification>
- [ ] Sportlink icon downloaded and displays correctly
- [ ] VOG indicator logic correctly handles all 4 states
- [ ] VOG indicator only shows for non-Donateur members
- [ ] Sportlink link opens correct URL in new tab
- [ ] Dark mode styling works for VOG badge
- [ ] Mobile responsive layout maintained
- [ ] npm run build succeeds
- [ ] Deploy to production
</verification>

<success_criteria>
1. VOG status indicator shows in header for members with work functions other than Donateur
2. Three VOG states display correctly: VOG OK (green), VOG verlopen (orange), Geen VOG (red)
3. Sportlink icon appears for persons with KNVB ID and links to correct URL
4. Production deployment successful and testable at production URL
</success_criteria>

<output>
After completion, create `.planning/quick/010-vog-status-indicator-and-sportlink-link-/010-SUMMARY.md`
</output>
