# Feature Landscape: Infix/Tussenvoegsel Field

**Domain:** Dutch name handling in CRM systems
**Researched:** 2026-02-05
**Confidence:** HIGH (based on official Dutch naming conventions, vCard RFC 6350, and existing Stadion implementation analysis)

## Executive Summary

Adding an infix/tussenvoegsel field to person records requires coordinated changes across 8 integration points: storage, display, auto-title generation, sorting, search, duplicate detection, vCard import/export, and Google Contacts sync. The Dutch naming convention is well-established: infixes are lowercase when preceded by a first name ("Jan van Dijk"), uppercase when standalone ("Van Dijk"), and ignored during alphabetical sorting (van Dijk files under "D").

The vCard standard (RFC 6350) does not have a dedicated infix position in the N field, requiring a workaround using the "Additional Names" field (position 3). This impacts import/export compatibility with systems that follow strict vCard interpretation.

## Table Stakes

Features users expect when an infix field exists. Missing these = incomplete implementation.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Display format: "First Infix Last"** | Dutch convention for full names | Low | "Jan van Dijk" not "Jan Van Dijk" |
| **Lowercase infix in display** | Dutch grammar rule when preceded by first name | Low | Apply in all display contexts |
| **Sort by last name, ignore infix** | Dutch telephone directory convention | Medium | van Dijk under "D" not "V" |
| **Auto-title includes infix** | Title = full display name | Low | Update AutoTitle class to include infix |
| **vCard N field export with infix** | Standard contact exchange format | Medium | Use Additional Names field (position 3) |
| **vCard N field import with infix** | Parse incoming contacts correctly | Medium | Parse position 3 as infix |
| **Search finds "van dijk"** | Users type full name to search | Medium | Include infix in search index |
| **Search finds "dijk" alone** | Users may omit infix when searching | Medium | Search last_name with and without infix |
| **Empty infix handling** | Not all Dutch names have infixes | Low | Display and sort correctly when empty |
| **Google Contacts sync with infix** | Bidirectional sync must preserve infix | High | Map to middle name or structured name field |
| **Duplicate detection includes infix** | "Jan van Dijk" ≠ "Jan Dijk" | Medium | Match on all three name components |
| **API returns infix field** | REST API consumers need access | Low | Add to person endpoint response |
| **UI field for infix input** | Users must be able to set/edit | Low | Add between first_name and last_name |

## Differentiators

Features that enhance the infix implementation. Not expected, but valued.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Capitalization context awareness** | "Van Dijk is hier" vs "Jan van Dijk" | High | Uppercase when no first name precedes |
| **Infix validation list** | Prevent typos like "vam" or "dde" | Low | Validate against known list (van, de, van der, etc.) |
| **Infix auto-suggestion** | Speed up data entry | Low | Dropdown/autocomplete from common list |
| **Compound infix support** | Handle "van der", "van den", "van de" | Low | Store as single string, no special parsing |
| **Infix normalization** | Standardize "v.d." → "van der" | Medium | Apply on save, preserve user intent |
| **Formal address generation** | "Dhr. Van Dijk" (uppercase when formal) | Medium | Context-specific formatting rules |
| **Bulk import infix detection** | Parse existing "last_name" fields to extract infixes | High | One-time migration helper |
| **Infix statistics** | Show distribution of infixes in database | Low | Dashboard widget for data quality |
| **vCard NICKNAME fallback** | Export "Dijk" as nickname for systems without infix | Low | Helps with systems that can't parse N field correctly |

## Anti-Features

Features to explicitly NOT build. Common mistakes in this domain.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Uppercase infix by default** | Violates Dutch grammar rules | Always lowercase unless context requires uppercase |
| **Sort by infix** | Not Dutch convention | Ignore infix, sort by last_name only |
| **Separate "prefix" from "infix"** | Overcomplicates data model | Single "infix" field is sufficient |
| **Split compound infixes** | "van der" is one unit, not two | Store as single string |
| **Require infix for all persons** | Many names don't have infixes | Make field optional |
| **Auto-capitalize infix in search** | Search should be case-insensitive anyway | Use case-insensitive search matching |
| **Show infix in separate column in lists** | Takes up space, breaks name flow | Show as part of full name column |
| **Validate infix against strict list** | Rare/historical infixes exist | Warn but don't block unknown infixes |
| **Store infix with last_name** | Makes parsing and sorting complex | Keep as separate field |

## Feature Dependencies

```
Storage Schema Change (infix field)
  ↓
Auto-Title Generation (includes infix)
  ↓
Display in UI (PersonEditModal, PeopleList)
  ↓
├─ Search (full name with infix)
├─ Sorting (ignore infix, sort by last_name)
├─ Duplicate Detection (match first+infix+last)
└─ Export/Import
   ├─ vCard (N field position 3)
   └─ Google Contacts (map to middle name)
```

**Critical path:** Storage → Auto-Title → Display → Search
**High-risk:** vCard import/export (no standard position), Google Contacts sync (custom mapping)

## Integration Surface Analysis

### 1. Storage (ACF Field)
**What changes:** Add `infix` text field to person CPT
**Complexity:** Low
**Risk:** Low (additive change)

### 2. Auto-Title Generation (class-auto-title.php)
**Current:** `$full_name = trim( $first_name . ' ' . $last_name );`
**New:** `$full_name = trim( $first_name . ' ' . $infix . ' ' . $last_name );`
**Complexity:** Low
**Risk:** Low (affects display everywhere)

### 3. Display Formatting
**Surfaces:**
- PersonEditModal: Input field between first and last name
- PeopleList: Name column shows full name with infix
- PersonDetail: Header shows full name with infix
- Timeline entries: Author name includes infix
- Search results: Highlighted name includes infix

**Complexity:** Low (template changes)
**Risk:** Low (visual only)

### 4. Sorting (REST API + Frontend)
**Current:** Sort by `first_name` or `last_name` meta query
**New:** Continue sorting by `last_name` (ignore infix)
**Complexity:** Low (no change needed if infix stored separately)
**Risk:** Medium (must verify sorting doesn't break)

### 5. Search (Global Search)
**Current:** Searches `post_title` (auto-generated from first+last)
**New:** Title includes infix, so search already works
**Complexity:** Low (automatic via title)
**Risk:** Low (inherits from auto-title)

### 6. Duplicate Detection (vCard import, manual checks)
**Current:** Match on `first_name` + `last_name` (exact)
**New:** Match on `first_name` + `infix` + `last_name`
**Complexity:** Medium
**Risk:** High (may miss existing duplicates if infix added later)

### 7. vCard Export (class-vcard-export.php)
**Current:** `N:last_name;first_name;;;`
**New:** `N:last_name;first_name;infix;;`
**Complexity:** Medium
**Risk:** High (position 3 may not be interpreted as infix by all systems)

### 8. vCard Import (class-vcard-import.php)
**Current:** Parses `N` field parts[0]=last, parts[1]=first
**New:** Parse parts[2]=infix (if present)
**Complexity:** Medium
**Risk:** High (incoming vCards may use position 3 for middle name, not infix)

### 9. Google Contacts Sync (if exists)
**Status:** Unknown (not found in code review)
**Expected mapping:** Use "middle name" field
**Complexity:** High (if bidirectional sync)
**Risk:** High (data loss if mapping incorrect)

## Edge Cases

### Empty Infix
**Scenario:** Person with no infix (e.g., "Jan Jansen")
**Handling:**
- Display: "Jan Jansen" (no extra space)
- Sorting: By "Jansen"
- vCard: `N:Jansen;Jan;;;` (position 3 empty)
**Risk:** Low (trim whitespace in auto-title)

### Compound Infix
**Scenario:** "van der", "van den", "van de"
**Handling:** Store as single string "van der"
**Display:** "Jan van der Berg"
**Sorting:** By "Berg" (ignore entire "van der")
**Risk:** Low (no parsing needed)

### Capitalization
**Scenario:** User enters "Van" instead of "van"
**Handling:**
- Option A: Auto-lowercase on save (opinionated)
- Option B: Store as entered, lowercase in display (preserves intent)
**Recommendation:** Option B (less data loss)
**Risk:** Medium (inconsistent data entry)

### Unknown Infix
**Scenario:** Historical or rare infix like "des", "d'"
**Handling:** Allow any value, no strict validation
**Risk:** Low (edge case)

### vCard Import Ambiguity
**Scenario:** Import vCard with position 3 = "Paul" (middle name, not infix)
**Handling:**
- Cannot distinguish middle name from infix programmatically
- Require manual review if position 3 looks like middle name
**Risk:** High (incorrect infix assignment)

### Duplicate with/without Infix
**Scenario:** Existing "Jan Dijk" vs new "Jan van Dijk"
**Handling:**
- Current: No match (different last_name)
- New: Should these be flagged as potential duplicates?
**Recommendation:** Fuzzy match warning, not hard block
**Risk:** High (false negatives in duplicate detection)

### Sportlink Sync Read-Only
**Scenario:** Name fields are read-only in UI (Sportlink-synced)
**Handling:** Infix field also read-only in UI, editable via API
**Risk:** Low (consistent with existing fields)

## Display Context Matrix

| Context | Format | Capitalization | Example |
|---------|--------|----------------|---------|
| Full name in list | First Infix Last | Lowercase infix | Jan van Dijk |
| Full name in detail | First Infix Last | Lowercase infix | Jan van Dijk |
| Formal address (if implemented) | Title Last | Uppercase infix | Dhr. Van Dijk |
| Search result highlight | First Infix Last | Lowercase infix | Jan **van Dijk** |
| Timeline byline | First Infix Last | Lowercase infix | Door Jan van Dijk |
| Sorting key (invisible) | Last | Ignore infix | Dijk |
| vCard FN field | First Infix Last | Lowercase infix | Jan van Dijk |
| vCard N field | Last;First;Infix | Lowercase infix | Dijk;Jan;van |

## MVP Recommendation

For MVP (subsequent milestone), prioritize:

### Phase 1: Core Implementation
1. **Storage:** Add `infix` ACF field (text, optional)
2. **Auto-title:** Include infix in title generation
3. **Display:** Show infix in PersonEditModal, PeopleList, PersonDetail
4. **UI field:** Add input between first_name and last_name (read-only if Sportlink-synced)

**Rationale:** These four give immediate user value and are low-risk.

### Phase 2: Search & Sort
5. **Search:** Verify auto-title change makes infix searchable
6. **Sorting:** Verify sorting by last_name ignores infix correctly

**Rationale:** Search inherits from auto-title (free). Sorting needs verification.

### Phase 3: Import/Export
7. **vCard export:** Map infix to N field position 3
8. **vCard import:** Parse N field position 3 as infix
9. **Duplicate detection:** Include infix in match logic

**Rationale:** Essential for data interchange, but highest risk (vCard ambiguity).

### Defer to Post-MVP:
- **Capitalization context awareness:** Complexity high, edge case
- **Infix validation list:** Nice-to-have, not critical
- **Bulk import infix detection:** One-time need, manual acceptable
- **Google Contacts sync:** Requires investigation (not found in code)
- **Formal address generation:** No use case identified yet

## Implementation Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| **vCard position 3 ambiguity** | High | Document expectation, test with major clients (Apple, Google, Outlook) |
| **Existing data without infix** | Medium | Leave empty, do not attempt auto-parsing |
| **Duplicate detection false negatives** | Medium | Add fuzzy matching for "van Dijk" vs "Dijk" |
| **Sportlink sync override** | Medium | Confirm infix field included in Sportlink API |
| **Google Contacts sync unmapped** | High | Investigate if sync exists, update mapping |
| **Capitalization inconsistency** | Low | Store lowercase, document convention |
| **Sorting regression** | Medium | Add test cases for "van Dijk" under "D" |

## Data Quality Considerations

**Current state:**
- 0 person records have `infix` field (field doesn't exist yet)
- Unknown how many existing last_name fields contain infixes

**Post-implementation:**
- New records: Infix entered during creation
- Existing records: Remain empty unless manually updated
- Import from vCard: Infix parsed from position 3 (if present)

**Migration path:**
- Do NOT attempt automatic parsing of existing last_name fields
- Reason: Ambiguous (is "van Dijk" last_name or infix+last_name?)
- Let infix field populate organically via:
  1. Manual edits
  2. vCard imports
  3. Sportlink sync (if API includes infix)

## Testing Checklist

**Before deployment, verify:**

- [ ] Person with infix displays correctly: "Jan van Dijk"
- [ ] Person without infix displays correctly: "Jan Jansen"
- [ ] Compound infix displays correctly: "Jan van der Berg"
- [ ] Sorting places "van Dijk" under "D" not "V"
- [ ] Search finds "van dijk" (full query)
- [ ] Search finds "dijk" (last name only)
- [ ] vCard export includes infix in N field position 3
- [ ] vCard import parses infix from N field position 3
- [ ] vCard import handles empty position 3 gracefully
- [ ] Duplicate detection catches "Jan van Dijk" vs "Jan van Dijk"
- [ ] Duplicate detection distinguishes "Jan van Dijk" vs "Jan Dijk"
- [ ] Auto-title handles empty infix (no double space)
- [ ] UI field is read-only when Sportlink-synced
- [ ] API returns infix in person response

## Sources

### HIGH Confidence Sources:
- [Tussenvoegsel - Wikipedia](https://en.wikipedia.org/wiki/Tussenvoegsel) - Comprehensive overview of Dutch name prefixes
- [Dutch Genealogy: How to capitalize Dutch names with prefixes](https://www.dutchgenealogy.nl/how-to-capitalize-dutch-names-with-prefixes/) - Authoritative capitalization rules
- [RFC 6350: vCard Format Specification](https://www.rfc-editor.org/rfc/rfc6350) - Official vCard standard
- [Dutch name - Wikipedia](https://en.wikipedia.org/wiki/Dutch_name) - Dutch naming conventions

### MEDIUM Confidence Sources:
- [HubSpot Community: Dutch surnames](https://community.hubspot.com/t5/CRM/Searching-for-a-solution-for-Dutch-surnames/m-p/352499) - CRM implementation patterns
- [Salesforce Ideas: Name Fields to support Dutch conventions](https://ideas.salesforce.com/s/idea/a0B8W00000GdhfWUAR/name-fields-to-support-dutch-conventions-tussenvoegsel) - Commercial CRM approaches
- [Fuzzy Matching 101: Complete Guide for 2026](https://matchdatapro.com/fuzzy-matching-101-a-complete-guide-for-2026/) - Duplicate detection algorithms

### Code Analysis:
- `/Users/joostdevalk/Code/stadion/includes/class-auto-title.php` - Current title generation
- `/Users/joostdevalk/Code/stadion/includes/class-vcard-export.php` - Current vCard export (lines 254: N field)
- `/Users/joostdevalk/Code/stadion/includes/class-vcard-import.php` - Current vCard import (lines 326: N field parsing)
- `/Users/joostdevalk/Code/stadion/src/components/PersonEditModal.jsx` - Current UI form
