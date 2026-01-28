---
phase: quick
plan: 010
subsystem: frontend
tags: [person-detail, compliance, vog, sportlink, knvb]
requires: []
provides:
  - VOG status indicator in person header
  - Sportlink external link for KNVB members
affects: []
tech-stack:
  added: []
  patterns:
    - Conditional badge rendering based on ACF field values
    - Custom image icons in social links
    - Date-based status calculation
key-files:
  created:
    - public/icons/sportlink.png
  modified:
    - src/pages/People/PersonDetail.jsx
decisions: []
metrics:
  duration: "2m 47s"
  completed: 2026-01-28
---

# Quick Task 010: VOG Status Indicator and Sportlink Link Summary

**One-liner:** Added VOG compliance badge with 3-year validation and Sportlink icon link for KNVB members in person header

## What Was Built

### VOG Status Indicator
- Added `getVogStatus` helper function to calculate VOG (Certificate of Good Conduct) status
- Logic checks:
  1. Person must have werkfuncties (work functions) other than "Donateur"
  2. If no vog_datum field: Shows "Geen VOG" (red badge)
  3. If vog_datum < 3 years old: Shows "VOG OK" (green badge)
  4. If vog_datum > 3 years old: Shows "VOG verlopen" (orange badge)
- Badge positioned absolutely in top-right corner of profile card
- Dark mode support with appropriate color variants
- Only displays for members with non-Donateur work functions

### Sportlink Integration
- Added Sportlink icon link to social links section
- Downloaded Sportlink favicon (60x60 PNG) from club.sportlink.com
- Link appears when person has `acf.knvb_id` field populated
- URL format: `https://club.sportlink.com/member/{knvb_id}`
- Opens in new tab with security attributes (rel="noopener noreferrer")
- Custom image rendering instead of icon component
- Title attribute: "Bekijk in Sportlink Club"

## Tasks Completed

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Download Sportlink favicon | 34f6811 | public/icons/sportlink.png |
| 2 | Add VOG indicator and Sportlink link | 0857a5f | src/pages/People/PersonDetail.jsx |

## Technical Implementation

### VOG Status Calculation
```javascript
function getVogStatus(acf) {
  // Only show for non-Donateur members
  const werkfuncties = acf?.werkfuncties || [];
  const hasNonDonateurFunction = werkfuncties.some(fn => fn !== 'Donateur');
  if (!hasNonDonateurFunction) return null;

  // Check VOG date and calculate age
  const vogDate = acf?.vog_datum;
  if (!vogDate) return { status: 'missing', label: 'Geen VOG', color: 'red' };

  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  return new Date(vogDate) >= threeYearsAgo
    ? { status: 'valid', label: 'VOG OK', color: 'green' }
    : { status: 'expired', label: 'VOG verlopen', color: 'orange' };
}
```

### Social Links Extension
- Extended `sortedSocialLinks` logic to include Sportlink when `acf.knvb_id` exists
- Special rendering path for Sportlink using `<img>` tag instead of icon component
- Maintains consistent hover effects and accessibility

## Color Scheme
- **Green (Valid):** `bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`
- **Orange (Expired):** `bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`
- **Red (Missing):** `bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`

## Dependencies
- Requires ACF fields on person post type:
  - `vog_datum` (date field)
  - `knvb_id` (text field)
  - `werkfuncties` (array/repeater field)

## Deviations from Plan

None - plan executed exactly as written.

## Deployment

- Built production assets: `npm run build` (2.59s)
- Deployed to production: `bin/deploy.sh`
- Production URL: https://stadion.svawc.nl/
- Caches cleared automatically

## Verification Checklist

- [x] Sportlink icon downloaded and displays correctly (60x60 PNG)
- [x] VOG indicator logic handles all 4 states (null, missing, valid, expired)
- [x] VOG indicator only shows for non-Donateur members
- [x] Sportlink link opens correct URL in new tab
- [x] Dark mode styling works for VOG badge
- [x] Mobile responsive layout maintained (absolute positioning on card)
- [x] npm run build succeeds
- [x] Deploy to production complete

## Next Steps

None - feature complete. Ready for user acceptance testing on production.

## User Testing Notes

To verify the implementation, check persons with different configurations:
1. Person with KNVB ID → should see Sportlink icon in social links
2. Person with non-Donateur werkfunctie + recent vog_datum → green "VOG OK" badge
3. Person with non-Donateur werkfunctie + old vog_datum → orange "VOG verlopen" badge
4. Person with non-Donateur werkfunctie + no vog_datum → red "Geen VOG" badge
5. Person with only "Donateur" werkfunctie → no VOG badge

Production URL: https://stadion.svawc.nl/people/{person-id}
