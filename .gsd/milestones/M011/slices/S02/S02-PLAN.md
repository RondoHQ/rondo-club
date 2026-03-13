# S02: UI for custom role management & mapping table usability

**Goal:** Add "Rol toevoegen" and delete buttons on CapabilitiesTab, fix the Ledendata hint text, add sticky first columns and horizontal scroll to all mapping tables (Capabilities, Functies, Commissie), and create three coordinator roles on production.
**Demo:** Admin can create a custom role from the UI, it appears in all three mapping tables. Tables scroll horizontally with the first column sticky. Admin can delete custom roles. Three coordinator roles exist on production with age-group config.

## Must-Haves

- "Rol toevoegen" input + button on CapabilitiesTab
- Delete button (trash icon) per custom role row in CapabilitiesTab (base roles not deletable)
- Ledendata hint text updated from "Geen selectie = alle leden" to "Geen selectie = geen leden"
- "Alle leden" display text in formatSelectedGroups changed to "Geen leden" when empty
- Sticky first column + horizontal overflow on Capabilities matrix table
- Sticky first column + horizontal overflow on Functies mapping table
- Sticky first column + horizontal overflow on Commissie mapping table
- Three coordinator roles created on production: Coördinator Pupillen, Coördinator Junioren, Coördinator Senioren
- Age-group access configured for each coordinator role

## Proof Level

- This slice proves: operational
- Real runtime required: yes (production deploy and setup)
- Human/UAT required: yes (admin verifies role creation and member visibility)

## Verification

- `npm run build` — zero errors
- `npm run lint` — zero warnings
- Production: 3 coordinator roles visible in capability matrix
- Production: Functies mapping shows all roles with horizontal scroll
- Production: "Geen leden" shown for roles without age-group config

## Tasks

- [ ] **T01: CapabilitiesTab — add role creation, deletion, and fix hint text** `est:20m`
  - Why: Core UI for managing custom roles. Hint text is now wrong after default inversion.
  - Files: `src/pages/Settings/Settings.jsx`
  - Do:
    1. Import `Plus` and `Trash2` from lucide-react
    2. Add state: `newRoleName`, `creatingRole` in CapabilitiesTab
    3. Add "Rol toevoegen" section above the table: text input + button, calls `prmApi.createCustomRole({ label })`, then refetches matrix
    4. In each role row, add a delete button (Trash2 icon) after the role label — only for custom roles (not in BASE_ROLES). On click: confirm dialog, call `prmApi.deleteCustomRole(slug)`, refetch matrix.
    5. To distinguish custom from base roles: add `is_custom` flag to the capability matrix API response, or pass custom role slugs as a prop. Simpler: check if the slug exists in a known base role list in JS.
    6. Change "Geen selectie = alle leden" to "Geen selectie = geen leden" in the dropdown footer
    7. Change `formatSelectedGroups` return from 'Alle leden' to 'Geen leden' when empty (non-management roles)
    8. Pass a `refetchMatrix` callback prop to CapabilitiesTab so it can trigger a re-fetch after create/delete
  - Verify: `npm run lint` + `npm run build` pass; "Geen leden" text visible in source
  - Done when: role creation input, delete button, and corrected hint text all in place

- [ ] **T02: Sticky first columns and horizontal scroll on all mapping tables** `est:15m`
  - Why: With 10+ role columns the tables will overflow. Sticky first column keeps the functie/commissie/role name visible while scrolling.
  - Files: `src/pages/Settings/Settings.jsx`
  - Do:
    1. CapabilitiesTab table: wrap table container in `overflow-x-auto`. Add `sticky left-0 bg-gray-50 dark:bg-gray-800 z-10` to the "Rol" th. Add `sticky left-0 bg-white dark:bg-gray-900 z-10` to each role name td. Ensure border shows on sticky column edge.
    2. FunctiesTab Functie mapping table: same treatment — sticky first column for Functie names.
    3. FunctiesTab Commissie mapping table: same treatment — sticky first column for Commissie names.
    4. Test that tables scroll horizontally and first column stays fixed.
  - Verify: `npm run lint` + `npm run build` pass; all three tables have `overflow-x-auto` and `sticky left-0`
  - Done when: first column stays visible when scrolling horizontally in all mapping tables

- [ ] **T03: Version bump, deploy, create coordinator roles on production** `est:15m`
  - Why: Ship the UI and set up the three coordinator roles with age-group config.
  - Files: `style.css`, `package.json`, `CHANGELOG.md`
  - Do:
    1. Bump to 32.4.0
    2. Add CHANGELOG entry
    3. Build, lint, deploy
    4. Create three roles via WP-CLI or browser UI: Coördinator Pupillen, Coördinator Junioren, Coördinator Senioren
    5. Configure age-group access for each via the capability matrix
    6. Verify in browser: roles appear in all three tables, sticky columns work, hint text correct
  - Verify: production shows 3 coordinator roles with age-group config
  - Done when: deployed, coordinator roles created, end-to-end verified

## Files Likely Touched

- `src/pages/Settings/Settings.jsx` — CapabilitiesTab (role CRUD, hint fix), FunctiesTab (sticky columns), both tables
- `style.css` — Version bump
- `package.json` — Version bump
- `CHANGELOG.md` — Release notes
