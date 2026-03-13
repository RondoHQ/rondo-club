---
status: complete
outcome: success
started: 2026-03-12T22:19:00+01:00
completed: 2026-03-12T22:50:00+01:00
version: 32.4.0
---

# S02: UI for custom role management & mapping table usability

## What was delivered

- **"Rol toevoegen" input** on CapabilitiesTab — text field + button creates custom roles via REST API, refetches matrix on success
- **Delete button** (Trash2 icon) per custom role row with confirmation dialog — base roles protected (no trash icon)
- **`is_custom` flag** added to capability matrix API response to distinguish base vs custom roles
- **Ledendata hint text** corrected from "Geen selectie = alle leden" to "Geen selectie = geen leden"
- **"Geen leden" display** in `formatSelectedGroups` for non-management roles without age-group config
- **Sticky first columns** with horizontal scroll on all three mapping tables (Capabilities, Functies, Commissie)
- **Three coordinator roles** created on production: Coördinator Pupillen, Junioren, Senioren
- **Age-group access** configured for each coordinator role (Pupillen: O6–O11, Junioren: O12–O19, Senioren: Senioren + Vrouwen)

## Key decisions

- Used `refetchData` callback prop pattern — extracted fetch logic from useEffect into a standalone async function, passed down through AdminTabWithSubtabs → CapabilitiesTab so role creation/deletion triggers a full matrix refetch
- Sticky column uses `sticky left-0 z-10/z-20` with explicit bg-color on both th and td to prevent transparent overlap; border-r on sticky column edge for visual separation
- Table containers changed from `overflow-y-auto` to `overflow-auto` + `min-w-max` on table to enable both horizontal and vertical scrolling
- Increased `max-h` on capabilities table from `max-h-96` to `max-h-[32rem]` since it now has more rows with 10 roles

## Files changed

- `includes/class-rest-api.php` — added `is_custom` flag and `get_custom_roles()` call to capability matrix response
- `src/pages/Settings/Settings.jsx` — CapabilitiesTab rewrite (role CRUD, hint fix), FunctiesTab sticky columns, Commissie table sticky columns, `fetchCapabilityData` extraction and prop threading
- `style.css`, `package.json` — version 32.4.0
- `CHANGELOG.md` — release notes

## Production state

- 10 roles total (7 base + 3 custom coordinators + administrator)
- Age-group access configured for all 3 coordinator roles
- All mapping tables (Capabilities, Functies, Commissie) support horizontal scroll with sticky first column
