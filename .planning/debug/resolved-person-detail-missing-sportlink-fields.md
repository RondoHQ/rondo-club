---
status: resolved
trigger: "person-detail-missing-sportlink-fields"
created: 2026-02-12T00:00:00Z
updated: 2026-02-12T00:45:00Z
---

## Current Focus

hypothesis: CONFIRMED - Root cause identified: CustomFieldsSection only shows Manager fields, not native ACF fields
test: Complete - analyzed entire data flow from ACF → REST API → React components
expecting: Full understanding of which fields exist, which are displayed, and why others aren't
next_action: Return diagnosis to orchestrator

## Symptoms

expected: Sportlink sync fields (lid-sinds, lid-tot, leeftijdsklasse, geboortedatum, etc.) should be visible somewhere on the PersonDetail page
actual: These fields are not displayed on PersonDetail, even though they are stored as post meta on person posts
errors: None — the fields are just not rendered in the UI
reproduction: Go to any person's detail page, e.g. https://rondo.svawc.nl/people/998 — Sportlink fields are not shown
started: User is unsure if this ever worked. May have been visible on the old ACF-based site. The current React SPA may never have rendered these fields.

## Eliminated

## Evidence

- timestamp: 2026-02-12T00:10:00Z
  checked: ACF field definitions in acf-json/group_person_fields.json
  found: Sportlink tab (field_person_sportlink) contains these fields:
    - knvb-id (text, readonly)
    - isparent (true_false)
    - type-lid (text, readonly)
    - leeftijdsgroep (text, readonly)
    - lid-sinds (date_picker, readonly, display: "d F Y")
    - datum-foto (date_picker, readonly)
    - datum-vog (date_picker)
    - huidig-vrijwilliger (true_false)
    - financiele-blokkade (true_false)
    - freescout-id (number)
  implication: These fields exist in the data model and are available via ACF

- timestamp: 2026-02-12T00:12:00Z
  checked: PersonDetail.jsx component code (lines 1-1815)
  found: Component only uses the following Sportlink fields:
    - acf['knvb-id'] (line 908) - to generate Sportlink link in social icons
    - acf['freescout-id'] (line 917) - to generate Freescout link in social icons
    - acf['financiele-blokkade'] (lines 945, 1026, 1034) - displayed in header card background and profile info
  implication: Most Sportlink sync fields (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, datum-vog, huidig-vrijwilliger) are NOT rendered anywhere in PersonDetail

- timestamp: 2026-02-12T00:15:00Z
  checked: REST API person endpoint (includes/class-rest-people.php)
  found: ACF fields are returned via standard WordPress REST API with ACF integration. The expand_person_relationships filter adds relationship data, add_person_computed_fields adds is_deceased and birth_year, but no filtering of ACF fields.
  implication: All ACF fields (including Sportlink fields) ARE returned by the REST API in the acf object

- timestamp: 2026-02-12T00:18:00Z
  checked: How birthdate and VOG are displayed
  found:
    - birthdate (acf.birthdate) is displayed in header (line 134-145) as formatted date with age
    - datum-vog is NOT directly displayed, but VOG status is computed and shown as badge (line 947-958) via getVogStatus helper
    - VOGCard component exists (line 1601) which likely shows VOG info
  implication: Some fields are displayed indirectly (VOG via badge/card), but many Sportlink fields have no UI representation at all

- timestamp: 2026-02-12T00:22:00Z
  checked: VOGCard component (src/components/VOGCard.jsx)
  found: VOGCard shows:
    - datum-vog (with status: valid/expired/missing)
    - vog_email_sent_date (email verzonden)
    - vog_justis_submitted_date (Justis aanvraag)
    - huidig-vrijwilliger (used to show/hide card)
  implication: VOG-related fields ARE displayed, but only for volunteers in a sidebar card

- timestamp: 2026-02-12T00:25:00Z
  checked: CustomFieldsSection usage in PersonDetail (line 1421-1435)
  found: CustomFieldsSection is invoked with excludeLabelPrefixes={['Nikki', 'Financiële', 'Datum VOG', 'VOG', 'Freescout']}
  implication: This excludes fields with labels starting with these prefixes, which would include "Datum VOG" and "VOG" related fields, but NOT other Sportlink fields

- timestamp: 2026-02-12T00:28:00Z
  checked: ACF field labels for Sportlink tab
  found: Field labels in Sportlink tab:
    - KNVB ID (excluded: shown as social link)
    - isParent (NOT excluded)
    - Type lid (NOT excluded)
    - Leeftijdsgroep (NOT excluded)
    - Lid sinds (NOT excluded)
    - Datum foto (NOT excluded)
    - Datum VOG (EXCLUDED by prefix "Datum VOG")
    - Huidig vrijwilliger (NOT excluded, but shown indirectly via VOGCard)
    - Financiële blokkade (EXCLUDED by prefix "Financiële", but shown in header)
    - Freescout ID (EXCLUDED by prefix "Freescout", but shown as social link)
  implication: Most Sportlink sync fields (isParent, type-lid, leeftijdsgroep, lid-sinds, datum-foto) SHOULD be visible in CustomFieldsSection but apparently are not being rendered

- timestamp: 2026-02-12T00:35:00Z
  checked: CustomFields REST API endpoint (includes/class-rest-custom-fields.php line 244-305)
  found: get_field_metadata() returns fields from CustomFields\Manager->get_fields()
  implication: CustomFields Manager only returns dynamically-created custom fields, NOT native ACF fields defined in acf-json

- timestamp: 2026-02-12T00:38:00Z
  checked: CustomFields Manager behavior
  found: The Manager was introduced in Phase 91 (custom field system) to allow users to create their own fields dynamically. It stores field definitions in wp_options (custom_fields_{post_type}), separate from ACF's native field system.
  implication: ACF fields defined in acf-json/group_person_fields.json (including all Sportlink fields) are NOT managed by the CustomFields Manager and therefore NOT returned by the /custom-fields/person/metadata endpoint

## Resolution

root_cause: Sportlink sync fields (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, isparent) are ACF native fields defined in acf-json but NOT displayed on PersonDetail page because:
  1. CustomFieldsSection component only displays fields from the CustomFields Manager (dynamically created fields), not native ACF fields
  2. Native ACF fields like Sportlink fields are NOT included in the /custom-fields/person/metadata endpoint
  3. These fields ARE in the acf object from the REST API, but PersonDetail doesn't render them anywhere
  4. Some Sportlink fields ARE displayed:
     - knvb-id: shown as Sportlink social link
     - freescout-id: shown as Freescout social link
     - financiele-blokkade: shown in header card background and profile info
     - datum-vog + huidig-vrijwilliger: shown in VOGCard (sidebar, volunteers only)
  5. Fields NOT displayed anywhere:
     - lid-sinds (membership start date)
     - lid-tot (membership end date)
     - leeftijdsgroep (age group)
     - type-lid (member type)
     - datum-foto (photo date)
     - isparent (is parent flag)

fix: This is a UI gap, not a bug. Two possible solutions:
  A. Add a "Sportlink Info" card to PersonDetail that renders these specific ACF fields
  B. Extend CustomFieldsSection to also render ACF native fields (not just Manager fields)

verification: Once implemented, check person profile and confirm all Sportlink fields are visible
files_changed: []
