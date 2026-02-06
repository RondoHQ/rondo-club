# Domain Pitfalls: Adding Infix/Tussenvoegsel Field

**Domain:** Adding infix field to person names in WordPress/React CRM
**Researched:** 2026-02-05
**Context:** Subsequent milestone - adding read-only infix field synced from Sportlink API to ~1400 existing person records

## Critical Pitfalls

Mistakes that cause rewrites or major issues.

### Pitfall 1: Sorting by Infix Instead of Last Name
**What goes wrong:** Records get sorted under "D", "V", "T" instead of actual last names. "de Vries" appears under D, "van Dijk" under V.
**Why it happens:** Developers treat infix+last_name as a single sortable string, or sort by SQL column order (first_name, infix, last_name).
**Consequences:**
- List views become unusable - massive clusters under common prefixes (van, de, der)
- User reports "Everyone's under V now!"
- Search for "Vries" fails because query matches "de Vries" under D
**Prevention:**
- **ALWAYS sort by last_name alone**, ignoring infix for alphabetical order
- Current code: `ORDER BY fn.meta_value, ln.meta_value` in `class-rest-people.php:1237`
- After infix: Keep last_name as primary sort key: `ORDER BY ln.meta_value $order, fn.meta_value $order`
- Document: "Dutch convention ignores tussenvoegsel in alphabetization"
**Detection:**
- Run query: `SELECT first_name, infix, last_name FROM people ORDER BY last_name LIMIT 100`
- Check if people with infix appear scattered (CORRECT) or clustered (WRONG)
**Phase impact:** Phase 3 (Backend sorting) - This MUST be addressed before any UI displays sorted names

**Sources:**
- [Dutch spelling and alphabetic ordering](https://www.van-diemen-de-jel.nl/Genea/Spelling.html)
- [Prefixes in surnames](https://www.dutchgenealogy.nl/prefixes-in-surnames/)

---

### Pitfall 2: Auto-Title Regeneration Creates Double Spaces
**What goes wrong:** Existing auto-title logic: `trim(first_name + ' ' + last_name)`. Adding infix without conditionals: `first_name + ' ' + infix + ' ' + last_name`. When infix is empty (most existing records), titles become "John  Doe" (double space).
**Why it happens:** String concatenation doesn't check for empty infix field before adding spaces.
**Consequences:**
- Visual: Double spaces in UI, list views, search results
- Functional: String comparison breaks (`"John  Doe" !== "John Doe"`)
- Search: Queries for "John Doe" don't match "John  Doe"
- vCard: Double spaces in FN field violate expectations
**Prevention:**
```php
// WRONG (creates double space when infix empty)
$full_name = trim( $first_name . ' ' . $infix . ' ' . $last_name );

// CORRECT (conditional spacing)
$parts = array_filter( [ $first_name, $infix, $last_name ], function( $v ) { return $v !== null && $v !== ''; } );
$full_name = implode( ' ', $parts );
```
**Detection:**
- Search post_title for double spaces: `SELECT ID, post_title FROM wp_posts WHERE post_type='person' AND post_title LIKE '%  %'`
- Unit test: `test_auto_title_with_empty_infix()`, `test_auto_title_with_infix()`
**Phase impact:** Phase 2 (Auto-title logic) - Must fix BEFORE bulk regeneration in Phase 5

**Current code location:** `includes/class-auto-title.php:213`

---

### Pitfall 3: Search Fragmentation Across Three Fields
**What goes wrong:** Users search "van Dijk" but query only checks `last_name='van Dijk'`. Actual data: `infix='van'`, `last_name='Dijk'`. Zero results returned.
**Why it happens:** Search implementation doesn't concatenate fields for matching.
**Consequences:**
- Users report "Person not found" when person exists
- Workarounds: Users search "Dijk" (ambiguous) or guess field structure
- Support tickets: "Why can't I find van Dijk?"
**Prevention:**
- Concatenate for search: `WHERE CONCAT_WS(' ', first_name, infix, last_name) LIKE '%search_term%'`
- **OR** maintain denormalized `full_name_search` field (recommended for performance)
- Frontend: Search across all permutations ("van Dijk", "Dijk, van", "Dijk")
**Detection:**
- Create test: Person with infix="van", last_name="Dijk"
- Search "van Dijk" - must return result
- Search "Dijk" - must return result
**Phase impact:** Phase 4 (Search updates) - Critical for REST API `/rondo/v1/search` endpoint

**Current search code:** Likely in `includes/class-rest-api.php` (search endpoint) - needs investigation

---

### Pitfall 4: vCard N Field Mapping Breaks Standards
**What goes wrong:** vCard N field: `N:FamilyName;GivenName;AdditionalNames;Prefix;Suffix`. Mapping infix to AdditionalNames: `N:Dijk;John;van;;` is semantically wrong - AdditionalNames is for middle names, not surname prefixes.
**Why it happens:** No standard vCard field for tussenvoegsel, so developers misuse closest field.
**Consequences:**
- CardDAV import/export: "van" appears as middle name in iOS Contacts
- Google Contacts: Tussenvoegsel lost or corrupted on sync roundtrip
- User confusion: "Why is 'van' my middle name?"
**Prevention:**
- **Accept the limitation:** vCard 3.0 has no tussenvoegsel field
- **Recommended mapping:** Include infix in FamilyName field for vCard: `N:van Dijk;John;;;`
- Document: "vCard export combines infix + last_name for compatibility"
- **Alternative:** Use X-INFIX extension for internal CardDAV, but expect it to be ignored by most clients
**Detection:**
- Export vCard for person with infix
- Import to iOS Contacts / Google Contacts
- Check if name displays correctly
**Phase impact:** Phase 6 (vCard/CardDAV update) - Low priority unless users report sync issues

**Current vCard code:** `includes/class-vcard-export.php:254` (N field generation)

**Sources:**
- [Notes on the vCard format](https://www.w3.org/2002/12/cal/vcard-notes.html)
- [vCard middleName vs additionalNames discussion](https://discussions.apple.com/thread/2743303?tstart=0)

---

### Pitfall 5: Google Contacts Sync Loses Infix on Roundtrip
**What goes wrong:** Export: `infix='van'`, `last_name='Dijk'` → Google Contacts: `familyName='van Dijk'`. Import: Google returns `familyName='van Dijk'` → Stadion tries to parse back to infix+last_name → Fails, creates `infix=null`, `last_name='van Dijk'`.
**Why it happens:** Google People API has no infix/tussenvoegsel field. Data structure is lossy.
**Consequences:**
- Sync corruption: Infix disappears after export→edit→import cycle
- Data integrity: Sportlink source of truth overwritten with corrupted data
- User report: "My 'van' disappeared!"
**Prevention:**
- **Make infix read-only in UI** (already planned) ✓
- Store `_google_original_family_name` meta on export for comparison
- On import, if `_google_original_family_name` matches Google's `familyName`, skip update (no changes)
- If different, log warning: "Google Contacts name changed, but infix is read-only"
- Document: "Infix field syncs from Sportlink only, not editable via Google"
**Detection:**
- Export person with infix to Google
- Edit person in Google Contacts (change first name only)
- Trigger import
- Verify infix unchanged in Stadion
**Phase impact:** Phase 7 (Google Contacts sync) - Critical if Google sync is active

**Current Google export code:** `includes/class-google-contacts-export.php:655-673` (build_name method)

---

## Moderate Pitfalls

Mistakes that cause delays or technical debt.

### Pitfall 6: Display Inconsistency - Sometimes Shown, Sometimes Hidden
**What goes wrong:** Frontend shows full name in some components (`PersonDetail.jsx`: "John van Dijk"), but only first+last in others (`PeopleList.jsx`: "John Dijk"). User confusion: "Where did 'van' go?"
**Why it happens:** Developers update some components to use new `getFullName()` utility, but forget others. No centralized display logic.
**Prevention:**
- Audit ALL components rendering person names (grep: `person.first_name`, `person.acf.first_name`, `getPersonName`)
- Create centralized utility: `formatPersonFullName(person, includeInfix=true)`
- Update all 7+ components identified in grep results
- Test checklist: PeopleList, PersonDetail, ImportantDateModal, MeetingDetailModal, VOGList, Timeline, Search results
**Detection:**
- Visual regression test: Screenshot all person name displays
- Verify infix appears consistently (or document intentional differences)
**Phase impact:** Phase 8 (Frontend display) - Must complete before UAT

**Components to audit:**
- `src/pages/People/PeopleList.jsx`
- `src/pages/People/PersonDetail.jsx`
- `src/components/ImportantDateModal.jsx`
- `src/components/MeetingDetailModal.jsx`
- `src/pages/VOG/VOGList.jsx`

---

### Pitfall 7: Capitalization Inconsistency - "Van" vs "van"
**What goes wrong:** Dutch rules: "Jan van Dijk" (van lowercase in full name), but "Van Dijk" (Van capitalized when last name standalone). System stores `infix='van'` (lowercase), displays "van Dijk" everywhere, violates Dutch convention for standalone contexts.
**Why it happens:** Developers store one canonical form, don't apply context-sensitive capitalization.
**Consequences:**
- Looks wrong to Dutch users in formal contexts (letters, reports)
- Not technically broken, but unprofessional
**Prevention:**
- Store lowercase in database: `infix='van'`
- Create display utility with context parameter:
  ```javascript
  formatInfix(infix, context='full') {
    if (context === 'standalone' && infix) {
      return infix.charAt(0).toUpperCase() + infix.slice(1); // "Van"
    }
    return infix; // "van"
  }
  ```
- Use `standalone` context in: List views (last name column), formal letters, certificates
- Use `full` context in: Full name displays, conversation UI
**Detection:**
- Check list view: Should show "Van Dijk" (capitalized) when only last name shown
- Check detail page: Should show "Jan van Dijk" (lowercase) when full name shown
**Phase impact:** Phase 8 (Frontend display) - Polish step, optional for MVP

**Sources:**
- [How to capitalize Dutch names with prefixes](https://www.dutchgenealogy.nl/how-to-capitalize-dutch-names-with-prefixes/)

---

### Pitfall 8: Compound Infixes Not Handled - "van de", "van der"
**What goes wrong:** Sportlink sends `infix='van de'`, database field stores it correctly, but UI assumes single-word infix. Display logic splits on space: Shows "van" in infix field, "de Boer" as last name.
**Why it happens:** Developers assume infix is single word (common in examples: "van", "de", "het").
**Consequences:**
- Data corruption in display layer (not storage)
- "van de Boer" becomes "van" + "de Boer"
**Prevention:**
- **DO NOT parse infix** - treat as opaque string from Sportlink
- Database: Store as `TEXT` or `VARCHAR(50)`, not `ENUM` of common prefixes
- Validation: Accept any string containing letters, spaces, apostrophes: `^[a-zA-Z\s']+$`
- Examples to test: "van de", "van der", "van den", "de l'", "van 't"
**Detection:**
- Create test person: `infix='van de'`, `last_name='Boer'`
- Verify UI shows full "van de Boer" correctly
**Phase impact:** Phase 2 (Database schema) - Ensure field type accommodates multi-word infixes

**Common compound infixes (from research):**
- van de, van der, van den, van het
- de la, de l', de le
- van 't, van d'

**Sources:**
- [Tussenvoegsel Wikipedia](https://en.wikipedia.org/wiki/Tussenvoegsel)

---

### Pitfall 9: REST API Backward Compatibility - Existing Consumers Break
**What goes wrong:** External system (e.g., Sportlink sync script) expects response: `{first_name, last_name}`. After update, response includes `infix` field. Consumer's parser breaks: "Unexpected field 'infix'".
**Why it happens:** Adding fields to existing API responses without versioning.
**Consequences:**
- Sportlink sync stops working
- Other integrations fail silently
- Rollback pressure from production issues
**Prevention:**
- Check if REST API is consumed externally (ask: "Does Sportlink API use our REST endpoints?")
- If YES: Add `infix` field but maintain backward compatibility
  - Existing `/wp/v2/people` endpoint: Add `infix` to `acf` object (ACF already handles this automatically)
  - New field appears in `.acf.infix`, doesn't break top-level structure
- Document: API changelog noting new field
- Notify: Email Sportlink team about new field availability
**Detection:**
- Review API logs: Are there external consumers? (`wp-json/wp/v2/people` requests from non-WordPress IPs)
- Test: Call endpoint before/after change, verify response structure compatibility
**Phase impact:** Phase 9 (API documentation) - Must complete before deployment if external consumers exist

**Current API structure:** WordPress REST API + ACF auto-includes custom fields in `.acf` object

---

### Pitfall 10: Bulk Migration Regenerates All 1400 Titles - Performance/Locking
**What goes wrong:** Migration script loops through 1400 people, calls `wp_update_post()` for each. Takes 10+ minutes, locks database, users see errors.
**Why it happens:** Synchronous bulk updates without batching or background processing.
**Consequences:**
- Production downtime during migration
- Users see "503 Service Unavailable"
- Database locks cause failed requests
**Prevention:**
- Use WP-Cron for async processing (like existing `stadion_async_calendar_rematch`)
- Batch: Process 50 people per cron run
- Store progress: `update_option('infix_migration_progress', $completed_count)`
- UI indicator: "Migration in progress: 347/1400 complete"
- Run migration in maintenance window OR non-blocking background
**Detection:**
- Before migration: `SELECT COUNT(*) FROM wp_posts WHERE post_type='person'` - verify count
- Monitor: WP-Cron logs for completion
**Phase impact:** Phase 5 (Data migration) - Critical for production deployment

**Existing async pattern:** `includes/class-auto-title.php:498-518` (schedule_calendar_rematch)

---

## Minor Pitfalls

Mistakes that cause annoyance but are fixable.

### Pitfall 11: Empty Infix Displays as "null" String in UI
**What goes wrong:** Database: `infix=NULL`. React renders: "John null Dijk". Reason: JavaScript null coercion to string.
**Why it happens:** Template literal without null check: ``${first_name} ${infix} ${last_name}``
**Consequences:**
- Ugly display: "John null Dijk"
- Users report: "Why does my name say 'null'?"
**Prevention:**
```javascript
// WRONG
const fullName = `${first_name} ${infix} ${last_name}`;

// CORRECT
const fullName = [first_name, infix, last_name]
  .filter(Boolean)
  .join(' ');
```
**Detection:**
- Create person without infix
- Check UI: Should show "John Dijk", not "John null Dijk"
**Phase impact:** Phase 8 (Frontend display) - Easy fix, low priority

---

### Pitfall 12: Search Highlighting Breaks on Infix Match
**What goes wrong:** User searches "van", 100 results highlight "van" in infix field. Search result shows "John **van** Dijk" (bold), but also matches "E**van** Smith" (middle of first name).
**Why it happens:** Simple substring highlighting without field-aware matching.
**Consequences:**
- False positive highlights confuse users
- "Why is 'Evan' highlighted when I searched for 'van'?"
**Prevention:**
- Field-aware search: Match "van" specifically in infix field OR as word boundary
- Regex: `\bvan\b` (word boundary)
- Display: Show field label in results: "John **van** Dijk (infix match)"
**Detection:**
- Search "van", verify only full-word matches highlighted
**Phase impact:** Phase 4 (Search updates) - Polish step, optional

---

### Pitfall 13: ACF JSON Sync Conflict - Local vs Production Drift
**What goes wrong:** Developer adds `infix` field to ACF locally, generates `acf-json/group_person_fields.json`. Production has old version. Git conflict on deploy.
**Why it happens:** ACF JSON files change on both local and production when admins edit fields.
**Consequences:**
- Git merge conflict
- Production field group out of sync with codebase
- "I deployed but infix field doesn't show up"
**Prevention:**
- **Before migration:** Disable field editing in production (`define('ACF_DISABLE_LOCAL_SYNC', true)` for non-dev environments)
- Document: "ACF field changes must happen in dev, never prod"
- Deploy process: Sync ACF JSON first, then run migration
**Detection:**
- Compare ACF JSON checksums: `md5sum acf-json/group_person_fields.json` on local vs prod
**Phase impact:** Phase 1 (ACF field addition) - Must handle before any coding

**Current ACF JSON location:** `acf-json/group_person_fields.json`

---

### Pitfall 14: Infix Field Not Visible in WordPress Admin UI
**What goes wrong:** Field added to ACF schema, visible in REST API, but doesn't appear in WP Admin person edit screen.
**Why it happens:** ACF field group has conditional logic hiding it, or `show_in_rest=0` but no admin display config.
**Consequences:**
- Developers see it in API, but clients can't verify data in admin
- Support: "Where do I see the infix?"
**Prevention:**
- Verify ACF field group settings:
  - Location: `post_type == person` ✓
  - Field placement: Within existing "Basic Information" tab
  - `show_in_rest: 1` ✓
  - `readonly: 1` (read-only, synced from Sportlink)
- Test: Edit person in WP Admin, verify infix field visible but grayed out
**Detection:**
- Log into WP Admin, edit any person, verify field appears
**Phase impact:** Phase 1 (ACF field addition) - Must verify immediately after field creation

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Phase 1: ACF Field Addition | Field not visible in admin UI (Pitfall 14) | Verify ACF field group settings, test immediately |
| Phase 2: Auto-title Logic | Double spaces when infix empty (Pitfall 2) | Use array_filter + implode, never raw concatenation |
| Phase 3: Backend Sorting | Sorting by infix+last_name (Pitfall 1) | Keep last_name as primary sort key, document Dutch convention |
| Phase 4: Search Logic | Search fragmentation (Pitfall 3) | Concatenate fields in WHERE clause or use denormalized search field |
| Phase 5: Data Migration | Performance/locking during bulk update (Pitfall 10) | Use WP-Cron async processing, batch 50 at a time |
| Phase 6: vCard Export | Incorrect N field mapping (Pitfall 4) | Combine infix+last_name in FamilyName, document limitation |
| Phase 7: Google Sync | Infix lost on roundtrip (Pitfall 5) | Store original Google name, prevent overwrites from Google |
| Phase 8: Frontend Display | Display inconsistency across components (Pitfall 6) | Audit all 7+ components, centralize name formatting utility |
| Phase 9: API Documentation | Backward compatibility break (Pitfall 9) | Verify no external consumers, document new field in changelog |

---

## Sources

Dutch naming conventions and technical implementation references:

- [Tussenvoegsel - Wikipedia](https://en.wikipedia.org/wiki/Tussenvoegsel)
- [Prefixes in surnames](https://www.dutchgenealogy.nl/prefixes-in-surnames/)
- [Dutch spelling and alphabetic ordering](https://www.van-diemen-de-jel.nl/Genea/Spelling.html)
- [How to capitalize Dutch names with prefixes](https://www.dutchgenealogy.nl/how-to-capitalize-dutch-names-with-prefixes/)
- [HubSpot Community - Dutch surnames issue](https://community.hubspot.com/t5/CRM/Searching-for-a-solution-for-Dutch-surnames/m-p/352499)
- [Salesforce Ideas - Tussenvoegsel support](https://ideas.salesforce.com/s/idea/a0B8W00000GdhfWUAR/name-fields-to-support-dutch-conventions-tussenvoegsel)
- [Apple Community - Infix Address Book](https://discussions.apple.com/thread/2743303?tstart=0)
- [Notes on the vCard format](https://www.w3.org/2002/12/cal/vcard-notes.html)
- [OpenEMR Issue - Tussenvoegsel support](https://github.com/openemr/openemr/issues/2595)

---

## Confidence Assessment

**Overall confidence:** HIGH

| Area | Confidence | Reason |
|------|------------|--------|
| Sorting | HIGH | Multiple sources confirm Dutch convention, clear code location in class-rest-people.php |
| Auto-title | HIGH | Existing code reviewed, pattern clear (line 213), common string concatenation mistake |
| Search | MEDIUM | Logic location inferred, needs code verification in class-rest-api.php |
| vCard mapping | HIGH | RFC 2426 reviewed, standard confirmed, existing code location identified |
| Google Contacts | HIGH | Existing export code reviewed (lines 655-673), API limitation confirmed |
| Display logic | HIGH | All React components identified via grep, centralization need clear |
| Capitalization | MEDIUM | Dutch convention confirmed by sources, implementation detail optional |
| Compound infixes | HIGH | Wikipedia examples documented, validation pattern clear |
| API compatibility | MEDIUM | WordPress REST + ACF pattern understood, external consumer status unknown |
| Migration performance | HIGH | Existing async pattern identified (class-auto-title.php:498-518) |

---

## Test Plan Recommendations

Based on pitfall analysis, critical tests before UAT:

**Unit Tests (Backend):**
1. `test_auto_title_with_infix()` - Verify "John van Dijk" format
2. `test_auto_title_without_infix()` - Verify no double spaces
3. `test_auto_title_with_compound_infix()` - Verify "van de Boer" works
4. `test_sorting_ignores_infix()` - Verify "van Dijk" sorts under D
5. `test_search_with_infix()` - Verify "van Dijk" finds person

**Integration Tests (API):**
1. POST `/wp/v2/people` with infix - verify stored correctly
2. GET `/wp/v2/people` - verify infix appears in `.acf.infix`
3. GET `/wp/v2/people?orderby=last_name` - verify sorting correct
4. GET `/rondo/v1/search?q=van+Dijk` - verify search works

**Frontend Tests (E2E):**
1. PeopleList displays "John van Dijk" consistently
2. PersonDetail shows full name with infix
3. Search "van Dijk" returns correct results
4. No "null" strings in UI for persons without infix
5. No double spaces in any name displays

**vCard/Google Tests:**
1. Export person with infix to vCard - verify N field format
2. Import vCard to iOS Contacts - verify name displays correctly
3. Export to Google Contacts - verify familyName includes infix
4. Edit in Google, re-import - verify infix unchanged (read-only)

---

## Open Questions for Validation

1. **Does Sportlink API consume our REST endpoints?** (Affects Pitfall 9 - API backward compatibility)
2. **What percentage of existing records have tussenvoegsel?** (Affects migration priority)
3. **Is Google Contacts sync actively used?** (Affects Pitfall 5 priority)
4. **Are there other external API consumers?** (Check logs for `/wp-json/wp/v2/people` requests)
5. **Should infix be editable in future, or always read-only from Sportlink?** (Affects long-term architecture)
