# S02 Roadmap Assessment

## Verdict: Roadmap is fine — no changes needed.

## What S02 Built

S02 delivered all boundary map outputs:

1. **`rondo_age_group_access` wp_option** — per-role arrays of permitted leeftijdsgroep values
2. **`AccessControl::get_permitted_age_groups($user_id)`** — static method with 6-capability management bypass
3. **Query filtering at all 3 points** — `filter_queries()`, `filter_rest_query()`, `get_filtered_people()` custom SQL
4. **Single-item REST access control** — 403 with `rest_forbidden_age_group` error code
5. **`/rondo/v1/user/me`** — includes `permitted_age_groups: string[] | null`
6. **Kaderlijst bypass flag** — `$suppress_age_group_filter` static flag exists, checked at all filtering points
7. **CapabilitiesTab "Ledendata" column** — multi-select dropdown per role, combined save
8. **REST endpoints** — `GET/POST /rondo/v1/settings/age-group-access`
9. **Frontend API client methods** — `getAgeGroupAccess()`, `updateAgeGroupAccess()`
10. **WPUnit tests** — 12 test cases for `get_permitted_age_groups()`
11. **Deployed** — v32.1.0 live on production

## Success Criteria Coverage

- ✅ Admin can view and edit a role×capability matrix → S01 (done)
- ✅ Saving the matrix updates WP role definitions → S01 (done)
- ✅ All 6 role-name checks replaced with manage_options → S01 (done)
- ✅ Each role can be configured with leeftijdsgroep values → S02 (done)
- Users with restricted roles see only matching members in People list/detail → **S03**
- Kaderlijst NOT affected by age-group restrictions → **S03**
- ✅ Existing Functies→Roles and Commissie→Roles sync unchanged → S01 (done)

All remaining criteria have S03 as their owning slice. Coverage check passes.

## S03 Readiness

S03 has clear work to do:
1. **People list** — backend already filters, but frontend needs to handle reduced results gracefully (counts, empty states)
2. **Person detail access denied** — backend returns 403 with `rest_forbidden_age_group`; frontend needs to catch this and show access denied message
3. **Kaderlijst bypass** — `$suppress_age_group_filter` flag exists but no code sets it to `true` yet. The Kaderlijst's `fetchAllPeople()` goes through `/wp/v2/person` which IS filtered. S03 must wire up the bypass (e.g., query parameter that sets the flag server-side for Kaderlijst context, or a dedicated unfiltered endpoint)

The boundary map from S02 → S03 is accurate. Risk level (medium) is appropriate — the main challenge is the Kaderlijst bypass mechanism, which is well-understood.

## Requirement Coverage

No requirements in `.gsd/REQUIREMENTS.md` are affected by this milestone. The BTN-* and ROLL-* requirements are from the button tier system (v32.0) and remain validated independently.
