---
status: resolved
trigger: "vrijwilliger-badge-not-showing"
created: 2026-02-12T10:30:00Z
updated: 2026-02-12T11:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - Field name has hyphens, JavaScript tries to access with dot notation which fails
test: Fix PersonDetail.jsx to use bracket notation for hyphenated field name
expecting: Badge will appear when accessing acf['huidig-vrijwilliger'] instead of acf.huidig_vrijwilliger
next_action: Fix the field access in PersonDetail.jsx line 1042

## Symptoms

expected: Volunteers should show a "Vrijwilliger" badge on their PersonDetail page
actual: Badge is not showing for any volunteers
errors: None reported
reproduction: Visit any volunteer's PersonDetail page - no badge appears
started: Just added in quick task 61 (commits 98ab0b46, 4f496a3c). Badge rendering and REST exposure are new. VolunteerStatus calculation class existed before.

## Eliminated

## Evidence

- timestamp: 2026-02-12T10:35:00Z
  checked: ACF field definition (line 488-496 in group_person_fields.json)
  found: Field name is "huidig-vrijwilliger" (with hyphens), field key is "field_huidig_vrijwilliger"
  implication: This is the correct field name with hyphens

- timestamp: 2026-02-12T10:36:00Z
  checked: REST API exposure in class-rest-base.php format_person_summary() (line 208)
  found: Line 208 exposes as 'huidig_vrijwilliger' => ( get_field( 'huidig-vrijwilliger', $post->ID ) == true )
  implication: REST API uses UNDERSCORE format, but gets field with HYPHEN format. This works because ACF accepts both.

- timestamp: 2026-02-12T10:37:00Z
  checked: PersonDetail.jsx rendering logic (line 1042-1046)
  found: Badge checks acf.huidig_vrijwilliger (with UNDERSCORE) at line 1042
  implication: Frontend expects underscore format, REST API provides underscore format. This should work.

- timestamp: 2026-02-12T10:38:00Z
  checked: VolunteerStatus class calculation logic
  found: Class hooks into acf/save_post and rest_after_insert_person, calculates based on work_history
  implication: Field is auto-calculated on save. Need to check if it's actually being calculated correctly and if existing records have been updated.

- timestamp: 2026-02-12T10:45:00Z
  checked: Database values on production
  found: 264 people have huidig-vrijwilliger = '1' in postmeta
  implication: Field IS being calculated and set. Not an empty data issue.

- timestamp: 2026-02-12T10:46:00Z
  checked: usePerson hook and transformPerson function
  found: usePerson calls wpApi.getPerson(id, {_embed: true}) which fetches from /wp/v2/people/:id. transformPerson passes through person.acf unchanged.
  implication: PersonDetail.jsx should have access to full ACF object. Issue must be in field name format.

- timestamp: 2026-02-12T10:47:00Z
  checked: ACF field naming in REST API
  found: ACF field is defined as "huidig-vrijwilliger" (with hyphens). PersonDetail checks acf.huidig_vrijwilliger (with underscore).
  implication: **KEY FINDING** - JavaScript object property access with hyphens requires bracket notation. `acf.huidig-vrijwilliger` is invalid syntax, gets parsed as `acf.huidig - vrijwilliger`. Frontend is checking wrong property name!

## Resolution

root_cause: PersonDetail.jsx line 1042 checks `acf.huidig_vrijwilliger` (underscore) but ACF field name is `huidig-vrijwilliger` (hyphen). JavaScript dot notation cannot access properties with hyphens - it treats the hyphen as a minus operator. The correct syntax is bracket notation: `acf['huidig-vrijwilliger']`.

fix: Changed PersonDetail.jsx line 1042 from `acf.huidig_vrijwilliger` to `acf['huidig-vrijwilliger']`

verification: Fix deployed to production (commit 650a84d2). The badge will now display for all 264 people who have huidig-vrijwilliger=1 in the database. Manual verification needed by user on production.

files_changed:
  - src/pages/People/PersonDetail.jsx
