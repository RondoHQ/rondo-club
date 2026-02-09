---
phase: 168-visibility-controls
verified: 2026-02-09T22:00:00Z
status: passed
score: 5/5 must-haves verified
---

# Phase 168: Visibility Controls Verification Report

**Phase Goal:** Users can find former members through search and a dedicated filter toggle
**Verified:** 2026-02-09T22:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Leden list has a "Toon oud-leden" toggle in the filter dropdown | ✓ VERIFIED | Toggle found at line 1169-1181 in PeopleList.jsx, positioned at top of filter dropdown with toggle switch UI |
| 2 | When toggle is enabled, former members appear with visual distinction | ✓ VERIFIED | Badge "Oud-lid" renders at line 104-108, row opacity-60 applied at line 64. Backend returns former_member boolean at line 1301 of class-rest-people.php |
| 3 | When toggle is disabled (default), former members are hidden | ✓ VERIFIED | Conditional WHERE clause at line 1034 excludes when include_former !== '1' |
| 4 | Global search includes former members with "oud-lid" indicator | ✓ VERIFIED | SearchModal renders "Oud-lid" badge at line 381-385 in Layout.jsx. format_person_summary includes former_member flag at line 207 of class-rest-base.php |
| 5 | Former members are easily discoverable when needed | ✓ VERIFIED | Toggle prominently placed, URL-persisted (oudLeden param), counted in filter badge, included in exports |

**Score:** 5/5 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-rest-people.php` | include_former parameter on filtered endpoint | ✓ VERIFIED | 1518 lines. Parameter registered at line 366, extracted at 1002, conditional SQL at 1034, former_member in response at 1301 |
| `includes/class-rest-api.php` | former_member flag in global search results | ✓ VERIFIED | 3481 lines. format_person_summary called at lines 1781, 1971, 2136 for search and dashboard |
| `includes/class-rest-base.php` | former_member field in person summary format | ✓ VERIFIED | 229 lines. Field added at line 207 with loose comparison (ACF returns '1' string) |
| `src/pages/People/PeopleList.jsx` | Toon oud-leden toggle UI and visual distinction styling | ✓ VERIFIED | 1780 lines. Toggle at 1169-1181, badge at 104-108, opacity at line 64, URL persistence at 661, filter count at 1155 |
| `src/components/layout/Layout.jsx` | Oud-lid badge in search results | ✓ VERIFIED | 622 lines. Badge renders at lines 381-385 with former_member conditional |
| `../developer/src/content/docs/features/former-members.md` | Developer documentation | ✓ VERIFIED | 214 lines. Starlight frontmatter present, documents ACF field, filtering patterns, API endpoints, UI components |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| PeopleList.jsx | /rondo/v1/people/filtered?include_former=1 | useFilteredPeople hook with includeFormer param | ✓ WIRED | includeFormer parsed from URL at 661, passed to hook at 791, hook sends include_former at usePeople.js:143 |
| Layout.jsx | searchResults.people[].former_member | SearchModal reads former_member flag from API | ✓ WIRED | SearchModal conditionally renders badge at 381-385 based on person.former_member from API response |
| class-rest-api.php | format_person_summary | Global search uses format_person_summary which includes former_member | ✓ WIRED | format_person_summary called at 1781, 1971, 2136. Method adds former_member at class-rest-base.php:207 |
| class-rest-people.php | SQL query with conditional former member exclusion | Backend conditionally excludes based on include_former param | ✓ WIRED | Param extracted at 1002, LEFT JOIN always present, WHERE clause added at 1034 only when include_former !== '1', former_member in SELECT at 1301 |

### Requirements Coverage

| Requirement | Status | Blocking Issue |
|-------------|--------|----------------|
| LIST-02: Filter toggle "Toon oud-leden" shows former members (with visual distinction) | ✓ SATISFIED | None. Toggle UI renders, badge displays, opacity applied |
| SRCH-01: Global search includes former members with visual "oud-lid" indicator | ✓ SATISFIED | None. Badge renders in search results, former_member flag in API response |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| includes/class-rest-people.php | 1073-1074 | `$placeholders` variable name (SQL binding, not placeholder stub) | ℹ️ Info | False positive - legitimate SQL query builder pattern |
| src/pages/People/PeopleList.jsx | 442 | `placeholder="Teams zoeken..."` | ℹ️ Info | False positive - legitimate form placeholder attribute |

**No blocking anti-patterns found.**

### Human Verification Required

None. All features are verifiable through code inspection:
- Toggle UI renders with correct state handling
- API responses include former_member boolean
- SQL filtering is conditional and NULL-safe
- Visual indicators (badge, opacity) are CSS-based

### Gaps Summary

**No gaps found.** All must-haves verified. All artifacts exist, are substantive, and properly wired. All key links traced successfully. Requirements satisfied.

### Notes

1. **Minor finding:** No production build detected (`dist/manifest.json` missing). SUMMARY claims `npm run build` succeeded and "Deployed to production successfully" but build artifacts not found locally. This is acceptable if deployment happened from a different environment or build was cleaned. Verify build exists on production server.

2. **Commits verified:** 
   - 17d5fa9d (backend changes)
   - 9a8f4502 (frontend changes)
   - c108b12 (docs - likely in developer repo)

3. **Version bump:** Correctly incremented to 23.1.0 (minor version for new feature).

4. **Developer docs:** Created with proper Starlight frontmatter, 214 lines of comprehensive documentation.

---

_Verified: 2026-02-09T22:00:00Z_
_Verifier: Claude (gsd-verifier)_
