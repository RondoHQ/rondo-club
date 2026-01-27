---
status: resolved
trigger: "person-age-not-showing"
created: 2026-01-26T00:00:00Z
updated: 2026-01-27T00:00:00Z
---

## Current Focus

hypothesis: N/A - Issue resolved
test: N/A
expecting: N/A
next_action: N/A

## Symptoms

expected: Person's age should be calculated from their birthdate and displayed on PersonDetail
actual: Age field shows nothing/blank
errors: None reported
reproduction: View any person with a birthdate on the PersonDetail page
started: Unknown when this started

## Eliminated

## Evidence

- timestamp: 2026-01-26T00:05:00Z
  checked: PersonDetail.jsx age calculation logic (lines 395-398)
  found: Age is calculated from `personDates` data using `birthDate` from birthday with type 'birthday'
  implication: Age depends on `usePersonDates(id)` hook returning data

- timestamp: 2026-01-26T00:06:00Z
  checked: usePeople.js hook (line 108-117)
  found: `usePersonDates` calls `prmApi.getPersonDates(id)` which hits `/stadion/v1/people/${personId}/dates`
  implication: Frontend expects this endpoint to exist

- timestamp: 2026-01-26T00:07:00Z
  checked: includes/class-rest-api.php entire file
  found: NO endpoint registered for `/people/{id}/dates` in main API class
  implication: Endpoint might be in a different class

- timestamp: 2026-01-26T00:08:00Z
  checked: includes/class-rest-people.php lines 36-51
  found: Endpoint IS registered at `/stadion/v1/people/(?P<person_id>\d+)/dates`
  implication: Endpoint exists in People class

- timestamp: 2026-01-26T00:09:00Z
  checked: functions.php lines 35 and 347
  found: `use Stadion\REST\People` imported and `new People()` instantiated
  implication: Class should be initialized. Need to verify if endpoint actually works

- timestamp: 2026-01-26T00:10:00Z
  checked: composer.json autoload configuration
  found: PSR-4 `Stadion\\` => `includes/` BUT also has classmap for `includes/`
  implication: Classmap should find the file despite naming mismatch

- timestamp: 2026-01-26T00:11:00Z
  checked: File naming convention and autoload classmap
  found: Class IS in classmap (line 34876 of vendor/composer/autoload_classmap.php)
  implication: Class loads correctly, endpoint should be registered

- timestamp: 2026-01-26T00:12:00Z
  checked: get_dates_by_person method (lines 212-232 of class-rest-people.php)
  found: Uses meta_query with `'value' => '"' . $person_id . '"'` which should match ACF serialized format
  implication: Query should work if dates exist. Need to verify endpoint actually returns data

- timestamp: 2026-01-26T00:13:00Z
  checked: ACF field configuration (acf-json/group_important_date_fields.json:42-50)
  found: `related_people` is post_object type with multiple:1, stored as PHP serialized array
  implication: PHP serializes as `a:1:{i:0;i:123;}` NOT `"123"` - LIKE query never matches!

- timestamp: 2026-01-26T00:14:00Z
  checked: Similar pattern in class-rest-api.php get_investments (lines 1574-1589)
  found: Uses both `serialize(strval($id))` AND `sprintf('"%d"', $id)` patterns
  implication: Codebase already knows about this issue - need same fix here

- timestamp: 2026-01-27T00:00:00Z
  checked: add_person_computed_fields method (lines 486-498 of class-rest-people.php)
  found: This function also queries for dates but was NOT updated with the serialized format fix
  implication: The initial fix to get_dates_by_person() was incomplete - add_person_computed_fields() is used
         to compute birth_year for the REST response, and it still used the old query format

## Resolution

root_cause: TWO places needed the meta_query fix:
1. get_dates_by_person() - FIXED on 2026-01-26
2. add_person_computed_fields() - FIXED on 2026-01-27

Both functions query important_date posts by related_people meta field. ACF stores post_object arrays as PHP serialized data like 'a:1:{i:0;i:123;}'. The LIKE query was only checking for '"123"' format.

The age specifically comes from add_person_computed_fields() which adds `birth_year` to the person REST response. This function was still using the old meta_query format.

fix: Modified add_person_computed_fields() to use OR meta_query checking both:
- Serialized format: serialize(strval($person_id))
- Quoted format: '"' . $person_id . '"' (backward compatibility)

files_changed:
  - includes/class-rest-people.php (lines 486-506)

verification: Deployed to production on 2026-01-27
