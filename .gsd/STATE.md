# GSD State

**Active Milestone:** M011 — Roles & Capability Expansion
**Active Slice:** S02 (next)
**Phase:** executing
**Requirements Status:** 0 active · 12 validated · 0 deferred · 0 out of scope

## Milestone Registry
- ✅ **M001:** Button Tier System & Sitewide Rollout
- ✅ **M002:** Mollie Payment Details
- ✅ **M003:** Credit Invoice Improvements
- ✅ **M004:** Contributie Exclusion Improvements
- ✅ **M005:** Spelactiviteit Field
- ✅ **M006:** Markeer als betaald
- ✅ **M007:** Remove iCal Feed
- ✅ **M008:** Credit Invoice Type Badge
- ✅ **M009:** Person Detail Page Improvements
- ✅ **M010:** Role-Capability Matrix & Age-Group Access
- 🔄 **M011:** Roles & Capability Expansion (S01 ✅, S02 next)

## Recent Decisions
- ROLES → BASE_ROLES, dynamic get_all_roles() merges base + custom from wp_option
- Ledendata default inverted: no config = see nobody (not see everyone)
- Custom roles stored in rondo_custom_roles option as slug→label
- Empty age-group arrays use `1 = 0` or impossible match for safe SQL

## Blockers
- None

## Next Action
Plan and execute S02 (UI for custom role management & mapping table usability).
