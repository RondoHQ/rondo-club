---
phase: 103-teams-commissies
plan: 02
subsystem: ui-translation
tags: [dutch-localization, teams, ui, i18n]
completed: 2026-01-25
duration: 415s

requires:
  - 103-01  # VisibilitySelector translated
  - 102-03  # Leden translation patterns established

provides:
  - dutch-teams-list-page
  - dutch-teams-detail-page
  - dutch-teams-edit-modal

affects:
  - 103-03  # Commissies pages (same patterns)

tech-stack:
  added: []
  patterns:
    - Dutch singular/plural handling (team/teams)
    - Sports club terminology (Sponsoren instead of Investeerders)
    - Conditional field hiding (parent organization)

key-files:
  created: []
  modified:
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Teams/TeamDetail.jsx
    - src/components/TeamEditModal.jsx

decisions:
  - id: hide-parent-organization-field
    what: Hide parent organization field in TeamEditModal
    why: Not relevant for sports club context per CONTEXT.md
    impact: Cleaner UI for Teams, matches domain requirements

  - id: sponsors-not-investors
    what: Use "Sponsoren" instead of "Investeerders"
    why: Sports club terminology more appropriate than business terminology
    impact: Consistent with domain language throughout Teams section

  - id: leden-not-employees
    what: Use "Huidige leden" and "Voormalige leden" instead of employees
    why: Sports club teams have members, not employees
    impact: Correct terminology for team membership

metrics:
  tasks: 3
  commits: 3
  files-changed: 3
  lines-changed: 113
---

# Phase 103 Plan 02: Teams Pages Translation Summary

**One-liner:** Dutch translation of all Teams pages, forms, and modals with sports club terminology (Sponsoren, Leden)

## What Was Done

Translated the complete Teams section (list, detail, edit modal) to Dutch following established Phase 102 patterns. Applied sports club-specific terminology decisions from CONTEXT.md.

### Task 1: Translate TeamsList.jsx (commit a5b28b5)

**Translated elements:**
- Page title and search placeholder: "Teams zoeken..."
- Add button: "Nieuw team"
- Sort options: "Naam", "Website", "Workspace"
- Filter labels: "Eigenaar", "Alle teams", "Mijn teams", "Gedeeld met mij"
- Bulk action modals (visibility, workspace, labels)
- Empty states and error messages
- Selection toolbar

**Key changes:**
- Replaced organization/organizations with team/teams throughout
- Updated bulk modal headers and descriptions
- Translated "leden" for workspace member counts

### Task 2: Translate TeamDetail.jsx (commit d059c04)

**Translated elements:**
- Navigation: "Terug naar teams"
- Action buttons: "Delen", "Bewerken", "Verwijderen"
- Section headers: "Huidige leden", "Voormalige leden", "Sponsoren"
- Subsidiary reference: "Onderdeel van {name}"
- Contact information: "Contactgegevens"
- Empty states: "Geen huidige leden", "Geen voormalige leden"
- Type labels in sponsor cards: "Lid" / "Team"

**Key changes:**
- Replaced "employees" with "leden" (members)
- Replaced "Investors" with "Sponsoren"
- Updated "Invested in" to "Investeert in"
- Translated all confirmation dialogs and error alerts

### Task 3: Translate TeamEditModal.jsx (commit 21beed8)

**Translated elements:**
- Modal headers: "Team bewerken" / "Nieuw team"
- Form labels: "Naam *", "Website", "Sponsoren"
- Search placeholders: "Teams zoeken...", "Leden en teams zoeken..."
- Type labels in dropdowns: "Lid" / "Team"
- Validation messages: "Naam is verplicht"
- Helper text for sponsor field
- No results states
- Buttons: "Annuleren", "Opslaan...", "Wijzigingen opslaan", "Team aanmaken"

**Key changes:**
- Hidden parent organization field (wrapped in `{false && ...}`) per CONTEXT.md
- Updated all investor references to sponsor/Sponsoren
- Replaced "Person" / "Organization" with "Lid" / "Team"
- Translated all placeholder text to Dutch

## Deviations from Plan

None - plan executed exactly as written.

## Technical Implementation

### Translation Pattern Applied

Followed Phase 102 established pattern of direct string replacement in JSX with proper singular/plural handling:

```jsx
// Singular/plural pattern
{selectedIds.size} {selectedIds.size === 1 ? 'team' : 'teams'} geselecteerd

// Conditional text
mode === 'add' ? 'Labels toevoegen aan' : 'Labels verwijderen van'
```

### Terminology Decisions

Applied sports club context per CONTEXT.md:

| English | Generic Dutch | Sports Club Dutch | Why |
|---------|---------------|-------------------|-----|
| Investors | Investeerders | **Sponsoren** | Sports clubs have sponsors |
| Employees | Werknemers | **Leden** | Team members, not employees |
| Parent Organization | Hoofdorganisatie | *Hidden* | Not relevant for sports clubs |

### Field Hiding Implementation

Parent organization field hidden using conditional rendering:

```jsx
{false && (
  <div>
    {/* Parent team selection - HIDDEN per CONTEXT.md */}
    ...
  </div>
)}
```

This preserves the code for potential future use while cleanly removing it from UI.

## Testing Performed

1. **Build validation:** `npm run build` - passed (2.59s)
2. **Lint validation:** `npm run lint` - passed (pre-existing warnings unrelated to changes)
3. **String verification:** Grep searches confirmed no English organization/employee strings remain

## Next Phase Readiness

**Blockers:** None

**Concerns:** None

**Ready for:** Phase 103-03 (Commissies pages) - same translation patterns apply

## Files Changed

```
src/pages/Teams/TeamsList.jsx       (51 insertions, 51 deletions)
src/pages/Teams/TeamDetail.jsx      (35 insertions, 35 deletions)
src/components/TeamEditModal.jsx    (27 insertions, 28 deletions)
```

**Total:** 113 lines changed across 3 files

## Commits

- `a5b28b5` - feat(103-02): translate TeamsList.jsx to Dutch
- `d059c04` - feat(103-02): translate TeamDetail.jsx to Dutch
- `21beed8` - feat(103-02): translate TeamEditModal.jsx to Dutch

## Dependencies Used

No new dependencies - pure string translation using existing React/JSX.

## Knowledge for Future

### Reusable Patterns

**Bulk action modal translation:**
All three bulk modals (visibility, workspace, labels) follow same pattern:
- Modal title
- Description with singular/plural team(s)
- Option labels
- Empty states
- Action buttons with loading states

**Type label translation:**
When showing entity types in dropdowns/cards:
- `'Person'` → `'Lid'`
- `'Organization'` → `'Team'`

**Workspace member counts:**
Always translate "members" to "leden": `{count} leden`

### Sports Club Terminology

For future sports club-related features:
- Team members are "leden" (not werknemers/employees)
- Financial supporters are "sponsoren" (not investeerders)
- Parent/child relationships may not be relevant (evaluate case-by-case)

### Conditional Field Hiding

When hiding fields per domain requirements:
- Use `{false && ...}` wrapper for clean hiding
- Preserves code for potential future reuse
- More maintainable than deleting code
- Add comment explaining why field is hidden

## Risk Assessment

**Risk Level:** LOW

- Simple string replacement, no logic changes
- Build and lint pass
- Follows established Phase 102 patterns
- No breaking changes to functionality

**Rollback Plan:**
Revert commits `a5b28b5`, `d059c04`, `21beed8` to restore English strings.
