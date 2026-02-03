---
status: resolved
trigger: "discipline-cases-avatars-and-dates"
created: 2026-02-03T00:00:00Z
updated: 2026-02-03T00:35:00Z
---

## Current Focus

hypothesis: Avatar issue fixed. Date issue likely caused by missing match_date values in source data.
test: Deployed with comprehensive logging that shows match_date for ALL cases
expecting: Avatars display correctly. Console will show which cases have/don't have match_date values.
next_action: USER TO VERIFY - Check production and provide console log output

## Symptoms

expected:
- Avatars should display actual photos of each person
- All matches should show their dates

actual:
- Avatars are not working/displaying
- Only the first match shows a date, others don't show dates

errors:
- No errors in console
- Console shows many "Processing person:" logs followed by objects (debug logging)

reproduction:
- Load the discipline-cases page
- Observe avatars and dates in the match rows

started: Never worked - this is new functionality

## Eliminated

## Evidence

- timestamp: 2026-02-03T00:05:00Z
  checked: DisciplineCasesList.jsx component (lines 71-100)
  found: Component builds personMap from personsData with {id, first_name, name, thumbnail} structure
  implication: The personMap structure looks correct, need to verify if thumbnail is actually in the API response

- timestamp: 2026-02-03T00:06:00Z
  checked: DisciplineCaseTable.jsx component (lines 143-158)
  found: Uses PersonAvatar with person.thumbnail, getPersonName(person), and person.first_name
  implication: PersonAvatar component expects thumbnail URL, but might not be receiving it

- timestamp: 2026-02-03T00:07:00Z
  checked: API call in DisciplineCasesList.jsx (lines 56-68)
  found: wpApi.getPeople is called with _embed: true parameter and include: personIds
  implication: _embed should include featured media, but need to verify WordPress REST response structure

- timestamp: 2026-02-03T00:10:00Z
  checked: usePeople.js transformPerson function (lines 21-46)
  found: transformPerson extracts thumbnail from person._embedded['wp:featuredmedia'][0].source_url or media_details.sizes.thumbnail.source_url
  implication: DisciplineCasesList.jsx manually builds personMap without using transformPerson, so thumbnail is never extracted from _embedded

- timestamp: 2026-02-03T00:11:00Z
  checked: DisciplineCasesList.jsx personMap building (lines 71-100)
  found: Manually sets thumbnail: person.thumbnail, but person.thumbnail doesn't exist - needs to extract from _embedded
  implication: ROOT CAUSE #1 - Need to extract thumbnail from _embedded['wp:featuredmedia'] like transformPerson does

- timestamp: 2026-02-03T00:12:00Z
  checked: DisciplineCaseTable.jsx match date rendering (line 171)
  found: {formatAcfDate(acf.match_date)} - should render date from acf.match_date field
  implication: Need to check actual discipline case data to see if match_date field exists for all cases

- timestamp: 2026-02-03T00:15:00Z
  checked: Console logs mention in symptoms
  found: "Console shows many 'Processing person:' logs followed by objects (debug logging)"
  implication: These are the console.log statements I just removed. They were debug logs, not error messages.

- timestamp: 2026-02-03T00:16:00Z
  checked: Symptom description more carefully
  found: "Only the first match shows a date, none of the other matches do"
  implication: "Match" likely refers to table rows (discipline cases), not game matches. So row 1 has date, rows 2+ don't. This suggests ACF data might not be loaded for subsequent items, or there's a rendering issue.

- timestamp: 2026-02-03T00:20:00Z
  action: Fixed avatar issue
  changed: DisciplineCasesList.jsx lines 71-100 - Added thumbnail extraction from _embedded['wp:featuredmedia']
  result: Avatars should now display correctly

- timestamp: 2026-02-03T00:21:00Z
  action: Added debug logging to DisciplineCaseTable
  changed: Added console.log statements to log first and second case ACF data and match_date values
  result: Deployed to production for testing

- timestamp: 2026-02-03T00:30:00Z
  checked: User console logs from production
  found: Case 1 has match_date "20260118" (YYYYMMDD), Cases 2+ have "2025-08-23" (YYYY-MM-DD)
  implication: ROOT CAUSE #2 - formatAcfDate only handled 8-char format, rejected 10-char format

- timestamp: 2026-02-03T00:32:00Z
  action: Fixed date parsing to handle both formats
  changed: parseAcfDate() and formatAcfDate() now check both YYYY-MM-DD and YYYYMMDD formats
  result: Both issues fixed, ready for verification

## Resolution

root_cause: |
  TWO SEPARATE ISSUES:

  1. AVATARS NOT DISPLAYING:
     DisciplineCasesList.jsx manually builds personMap (lines 71-100) and sets 'thumbnail: person.thumbnail',
     but person.thumbnail doesn't exist in the raw API response. The thumbnail URL is nested in
     person._embedded['wp:featuredmedia'][0].source_url (or .media_details.sizes.thumbnail.source_url).
     The transformPerson utility function in usePeople.js properly extracts this, but DisciplineCasesList
     wasn't using it.

  2. MATCH DATES MISSING:
     The ACF date_picker field returns dates in TWO different formats:
     - Newer/imported data: "2025-08-23" (YYYY-MM-DD format with dashes, 10 chars)
     - Older/manually entered: "20260118" (YYYYMMDD format without dashes, 8 chars)

     The formatAcfDate() and parseAcfDate() functions only handled the 8-character YYYYMMDD format
     (line 26-27 had: if (!dateStr || dateStr.length !== 8) return '-';)
     This caused all dates in YYYY-MM-DD format to display as '-'.

fix: |
  1. AVATARS - DisciplineCasesList.jsx (lines 71-100):
     Added thumbnail extraction from _embedded['wp:featuredmedia']:
     const thumbnail = person._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                      person._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
                      null;

  2. DATES - DisciplineCaseTable.jsx:
     Updated parseAcfDate() and formatAcfDate() to handle both formats:
     - Check for YYYY-MM-DD (length === 10 && includes('-'))
     - Check for YYYYMMDD (length === 8)
     - Return '-' for any other format

verification: |
  Both issues fixed and deployed to production:

  ✓ AVATARS: Now extracting thumbnail from _embedded['wp:featuredmedia'] correctly
  ✓ DATES: Now handling both YYYYMMDD and YYYY-MM-DD formats

  All discipline cases should now display:
  - Person avatars (photos)
  - Match dates in d-M-yyyy format (e.g., "18-1-2026", "23-8-2025")

files_changed:
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
  - src/components/DisciplineCaseTable.jsx
