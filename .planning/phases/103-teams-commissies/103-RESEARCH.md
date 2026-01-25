# Phase 103: Teams & Commissies - Research

**Researched:** 2026-01-25
**Domain:** React UI translation (Dutch localization)
**Confidence:** HIGH

## Summary

This phase involves translating Teams and Commissies pages from English to Dutch, following the established translation patterns from Phase 102 (Leden). The research confirms this is a straightforward string replacement task with no library dependencies or architectural changes required.

The codebase already has Commissies as a separate entity (post type `commissie`) with its own list and detail pages. Teams use the `team` post type. Both follow nearly identical UI patterns with list views, detail pages, and edit modals. The translation approach should mirror Phase 102 exactly.

**Primary recommendation:** Apply direct string replacement in 4 page files (TeamsList.jsx, TeamDetail.jsx, CommissiesList.jsx, CommissieDetail.jsx), 2 modal files (TeamEditModal.jsx, CommissieEditModal.jsx), and 1 shared component (VisibilitySelector.jsx), following Phase 102 patterns and CONTEXT.md terminology.

## Standard Stack

This phase requires no new libraries - it uses the existing React application stack.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | UI framework | Already in use |
| React Router | 6.x | Navigation | Already in use |
| TanStack Query | 5.x | Data fetching | Already in use |

### Supporting
No additional libraries needed for translation work.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Hardcoded strings | i18n library (react-intl, i18next) | Overkill for single-language app; adds complexity |

**Installation:**
No installation required - pure string replacement.

## Architecture Patterns

### Translation Pattern (from Phase 102)

The established pattern is direct string replacement in JSX files:

```jsx
// Before (English)
<h2>Current employees</h2>

// After (Dutch)
<h2>Huidige leden</h2>
```

### Files to Modify

```
src/
├── pages/
│   ├── Teams/
│   │   ├── TeamsList.jsx      # List page, filters, bulk actions (~1272 lines)
│   │   └── TeamDetail.jsx     # Detail page, sections (~577 lines)
│   └── Commissies/
│       ├── CommissiesList.jsx # List page, filters, bulk actions (~1272 lines)
│       └── CommissieDetail.jsx # Detail page, sections (~577 lines)
└── components/
    ├── TeamEditModal.jsx      # Create/edit form (~617 lines)
    ├── CommissieEditModal.jsx # Create/edit form (~617 lines)
    └── VisibilitySelector.jsx # Shared visibility component (~159 lines)
```

### Singular/Plural Pattern

Dutch requires different singular/plural forms:
- Team: "team" / "teams"
- Commissie: "commissie" / "commissies"
- Organization: "organisatie" / "organisaties" (generic fallback)

Example pattern from Phase 102:
```jsx
{selectedIds.size === 1 ? 'team' : 'teams'}
```

### Anti-Patterns to Avoid
- **Partial translation:** Don't leave some strings in English - complete each component
- **Inconsistent terminology:** Always use terminology from CONTEXT.md (e.g., "Sponsoren" not "Investeerders")
- **Breaking existing functionality:** Only change string literals, not logic

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Translation system | i18n framework | Direct string replacement | Single-language app, simple scope |
| Visibility component | Duplicate code | Existing VisibilitySelector.jsx | Already shared across entities |

**Key insight:** The VisibilitySelector component is shared and should be translated once, affecting all entities. This was NOT translated in Phase 102 (verified: still contains English strings).

## Common Pitfalls

### Pitfall 1: Forgetting Bulk Action Modals
**What goes wrong:** Inline modal components in list files get overlooked
**Why it happens:** BulkVisibilityModal, BulkWorkspaceModal, BulkLabelsModal are defined inline
**How to avoid:** Systematically search for all English strings in each file
**Warning signs:** Modal text still appears in English during testing

### Pitfall 2: Inconsistent Terminology with CONTEXT.md
**What goes wrong:** Using different Dutch terms than those agreed upon
**Why it happens:** Not referencing CONTEXT.md during translation
**How to avoid:** Cross-reference every translation with CONTEXT.md decisions
**Warning signs:** Terms like "Investeerders" instead of "Sponsoren"

### Pitfall 3: Missing Empty State Messages
**What goes wrong:** "No organizations found" still appears in English
**Why it happens:** Empty states are only visible when data is empty
**How to avoid:** Grep for common empty state patterns: "No .* found", "No .* yet"
**Warning signs:** Users see English when they have no data

### Pitfall 4: Parent/Child Terminology for Teams
**What goes wrong:** "Subsidiary" and "Parent organization" left in English
**Why it happens:** These are less prominent UI elements
**How to avoid:** Per CONTEXT.md: remove/hide parent organization for Teams (not needed)
**Warning signs:** References to parent organizations still visible in Teams

### Pitfall 5: VisibilitySelector Shared Component
**What goes wrong:** Translating visibility in modals but not the shared component
**Why it happens:** VisibilitySelector.jsx is a separate shared component, easy to miss
**How to avoid:** Translate VisibilitySelector.jsx FIRST, then all uses get translated
**Warning signs:** Visibility options show English in forms but Dutch elsewhere

### Pitfall 6: Person/Organization Type Labels in Investor Lists
**What goes wrong:** "Person" and "Organization" type labels remain in English
**Why it happens:** These small type indicators in investor selection are easy to miss
**How to avoid:** Search for "Person" and "Organization" strings in modal files
**Warning signs:** Investor dropdown shows "Person" or "Organization" type in English

## Code Examples

### Translation Mapping - Teams List

```jsx
// Source: CONTEXT.md decisions + Phase 102 patterns

// Page elements
"Search organizations..." -> "Teams zoeken..."
"Add organization" -> "Nieuw team"
"organizations" -> "teams" (in selection toolbar)
"organization" -> "team" (singular)

// Sort options
"Name" -> "Naam"
"Website" -> "Website" (keep English)
"Workspace" -> "Workspace" (keep English per CONTEXT.md)

// Filter labels
"Ownership" -> "Eigenaar"
"All Organizations" -> "Alle teams"
"My Organizations" -> "Mijn teams"
"Shared with Me" -> "Gedeeld met mij"
"All Workspaces" -> "Alle workspaces"
"Clear all filters" -> "Alle filters wissen"

// Empty states
"No organizations found" -> "Geen teams gevonden"
"Failed to load organizations." -> "Teams konden niet worden geladen."
"Add your first organization." -> "Voeg je eerste team toe."
"No organizations match your filters" -> "Geen teams voldoen aan je filters"
"Try adjusting your filters to see more results." -> "Pas je filters aan om meer resultaten te zien."
"Try a different search." -> "Probeer een andere zoekopdracht."

// Bulk selection
"{n} organization(s) selected" -> "{n} team(s) geselecteerd"
"Clear selection" -> "Selectie wissen"
"Actions" -> "Acties"
"Change visibility..." -> "Zichtbaarheid wijzigen..."
"Assign to workspace..." -> "Toewijzen aan workspace..."
```

### Translation Mapping - Teams Detail

```jsx
// Navigation
"Back to organizations" -> "Terug naar teams"
"Edit" -> "Bewerken"
"Delete" -> "Verwijderen"
"Share" -> "Delen"

// Section headers
"Current employees" -> "Huidige leden"
"Former employees" -> "Voormalige leden"
"Investors" -> "Sponsoren" (per CONTEXT.md)
"Invested in" -> "Investeert in" (or hide if not applicable)
"Contact information" -> "Contactgegevens"
"Subsidiaries" -> Hide/remove per CONTEXT.md (parent org not relevant)

// Empty states
"No current employees." -> "Geen huidige leden."
"No former employees." -> "Geen voormalige leden."
"Failed to load organization." -> "Team kon niet worden geladen."

// Confirmation dialogs
"Are you sure you want to delete this organization?" -> "Weet je zeker dat je dit team wilt verwijderen?"
"Failed to delete organization. Please try again." -> "Team kon niet worden verwijderd. Probeer het opnieuw."
"Failed to save organization. Please try again." -> "Team kon niet worden opgeslagen. Probeer het opnieuw."
```

### Translation Mapping - Teams Edit Modal

```jsx
// Modal headers
"Edit organization" -> "Team bewerken"
"Add organization" -> "Nieuw team"

// Form labels
"Organization name *" -> "Naam *"
"Website" -> "Website"
"Parent organization" -> REMOVE/HIDE per CONTEXT.md
"Investors" -> "Sponsoren"

// Placeholders
"Acme Inc." -> "Voorbeeld BV"
"Search organizations..." -> "Teams zoeken..."
"Search people and organizations..." -> "Leden en teams zoeken..."
"Add investor..." -> "Sponsor toevoegen..."

// Type labels in investor dropdown
"Person" -> "Lid"
"Organization" -> "Team"

// Validation messages
"Organization name is required" -> "Naam is verplicht"

// Helper text
"Select if this organization is a subsidiary or division of another" -> REMOVE with parent field
"Select people or organizations that have invested in this team" -> "Selecteer leden of teams die dit team sponsoren"

// No results states
"No organizations found" -> "Geen teams gevonden"
"No results found" -> "Geen resultaten gevonden"
"No people or organizations available" -> "Geen leden of teams beschikbaar"
"Loading..." -> "Laden..."
"Searching..." -> "Zoeken..."

// Buttons
"Cancel" -> "Annuleren"
"Saving..." -> "Opslaan..."
"Save changes" -> "Wijzigingen opslaan"
"Create organization" -> "Team aanmaken"
```

### Translation Mapping - Commissies List

```jsx
// Page elements (similar to Teams but with commissie-specific terms)
"Search organizations..." -> "Commissies zoeken..."
"Add organization" -> "Nieuwe commissie"
"organizations" -> "commissies"
"organization" -> "commissie"

// Filter labels
"All Organizations" -> "Alle commissies"
"My Organizations" -> "Mijn commissies"

// Empty states
"No organizations found" -> "Geen commissies gevonden"
"Failed to load organizations." -> "Commissies konden niet worden geladen."
"Add your first organization." -> "Voeg je eerste commissie toe."
"No organizations match your filters" -> "Geen commissies voldoen aan je filters"

// Bulk selection
"{n} organization(s) selected" -> "{n} commissie(s) geselecteerd"
```

### Translation Mapping - Commissies Detail

```jsx
// Navigation
"Back to organizations" -> "Terug naar commissies"

// Section headers
"Current employees" -> "Leden" (commissie members)
"Former employees" -> "Voormalige leden"
"Investors" -> "Sponsoren"
"Subsidiaries" -> "Subcommissies" (if applicable, or hide)
"Subsidiary of {name}" -> "Subcommissie van {name}"

// Confirmation
"Are you sure you want to delete this organization?" -> "Weet je zeker dat je deze commissie wilt verwijderen?"
"Failed to delete organization. Please try again." -> "Commissie kon niet worden verwijderd. Probeer het opnieuw."
"Failed to save organization. Please try again." -> "Commissie kon niet worden opgeslagen. Probeer het opnieuw."
"Failed to load organization." -> "Commissie kon niet worden geladen."
```

### Translation Mapping - Commissies Edit Modal

```jsx
// Modal headers
"Edit commissie" -> "Commissie bewerken"
"Add commissie" -> "Nieuwe commissie"

// Form labels
"Commissie name *" -> "Naam *"
"Parent commissie" -> "Hoofdcommissie"
"Investors" -> "Sponsoren"

// Placeholders
"Commissie name" -> "Commissienaam"
"Search commissies..." -> "Commissies zoeken..."
"Search people and commissies..." -> "Leden en commissies zoeken..."
"Add investor..." -> "Sponsor toevoegen..."

// Type labels
"Person" -> "Lid"
"Commissie" -> "Commissie"

// Validation
"Commissie name is required" -> "Naam is verplicht"

// Helper text
"Select if this commissie is a sub-commissie of another" -> "Selecteer als dit een subcommissie is"
"Select people or commissies that have invested in this commissie" -> "Selecteer leden of commissies die deze commissie sponsoren"

// No results states
"No commissies found" -> "Geen commissies gevonden"
"No parent commissie" -> "Geen hoofdcommissie"
"No people or commissies available" -> "Geen leden of commissies beschikbaar"

// Buttons
"Cancel" -> "Annuleren"
"Saving..." -> "Opslaan..."
"Save changes" -> "Wijzigingen opslaan"
"Create commissie" -> "Commissie aanmaken"
```

### Translation Mapping - VisibilitySelector (SHARED)

```jsx
// Visibility label
"Visibility" -> "Zichtbaarheid"

// Visibility options (per CONTEXT.md)
"Private" -> "Prive"
"Only you can see this" -> "Alleen jij kunt dit zien"
"Workspace" -> "Workspace" (keep English per CONTEXT.md)
"Share with workspace members" -> "Deel met workspace-leden"

// Workspace selection
"Select Workspaces" -> "Selecteer workspaces"
"Loading workspaces..." -> "Workspaces laden..."
"No workspaces available. Create one first." -> "Geen workspaces beschikbaar. Maak er eerst een aan."
"members" -> "leden"

// Summary
"Shared with {n} workspace(s)" -> "Gedeeld met {n} workspace(s)"

// Button
"Done" -> "Klaar"
```

### Translation Mapping - Bulk Action Modals (INLINE IN LIST FILES)

```jsx
// BulkVisibilityModal (in TeamsList.jsx and CommissiesList.jsx)
"Change Visibility" -> "Zichtbaarheid wijzigen"
"Select visibility for {count} organization(s):" -> "Kies zichtbaarheid voor {count} team(s)/commissie(s):"
"Only you can see these organizations" -> "Alleen jij kunt deze teams/commissies zien"
"Share with workspace members" -> "Deel met workspace-leden"
"Cancel" -> "Annuleren"
"Applying..." -> "Toepassen..."
"Apply to {count} organization(s)" -> "Toepassen op {count} team(s)/commissie(s)"

// BulkWorkspaceModal (in TeamsList.jsx and CommissiesList.jsx)
"Assign to Workspace" -> "Toewijzen aan workspace"
"Select workspaces for {count} organization(s):" -> "Selecteer workspaces voor {count} team(s)/commissie(s):"
"No workspaces available." -> "Geen workspaces beschikbaar."
"Create a workspace first to use this feature." -> "Maak eerst een workspace aan om deze functie te gebruiken."
"Assigning..." -> "Toewijzen..."
"Assign to {count} organization(s)" -> "Toewijzen aan {count} team(s)/commissie(s)"
"members" -> "leden"

// BulkLabelsModal (in TeamsList.jsx and CommissiesList.jsx)
"Manage Labels" -> "Labels beheren"
"Add" / "Remove" labels for {count} organization(s):" -> "Labels toevoegen aan" / "Labels verwijderen van"
"Add Labels" -> "Labels toevoegen"
"Remove Labels" -> "Labels verwijderen"
"No labels available." -> "Geen labels beschikbaar."
"Create labels first to use this feature." -> "Maak eerst labels aan om deze functie te gebruiken."
"Adding..." -> "Toevoegen..."
"Removing..." -> "Verwijderen..."
"Add/Remove {n} label(s)" -> "{n} label(s) toevoegen/verwijderen"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| English UI | Dutch UI | Phase 101-103 | Full Dutch experience |
| "Investors" | "Sponsoren" | CONTEXT.md decision | Sports club context |
| "Parent Organization" | Hidden/removed | CONTEXT.md decision | Simplified Teams UI |
| "Industry" field | Hidden | CONTEXT.md decision | Not needed for sports club |

**Deprecated/outdated:**
- "Industry" field: Hidden for Teams per CONTEXT.md (not needed)
- "Parent Organization" field: Hidden for Teams per CONTEXT.md (no longer relevant)

## Open Questions

1. **VisibilitySelector Translation Scope**
   - What we know: Component is shared across Teams, Commissies, People
   - Confirmed: NOT yet translated (checked source - still English)
   - Recommendation: Translate in this phase; affects all entity edit forms

2. **Commissie Member Roles**
   - What we know: CONTEXT.md mentions "Voorzitter" and "Lid" as predefined roles
   - What's unclear: These may be in the Person work_history, not in Commissie UI
   - Recommendation: Roles appear to be on the Person side (work history), not Commissie forms. Skip for this phase; address in separate person/work-history phase if needed.

3. **"Invested in" Section for Teams**
   - What we know: Teams can show companies they've invested in
   - What's unclear: Is this relevant for sports club context?
   - Recommendation: Translate to "Investeert in" but consider hiding in future if not applicable

## Sources

### Primary (HIGH confidence)
- `.planning/phases/103-teams-commissies/103-CONTEXT.md` - User decisions and terminology
- `src/pages/Teams/TeamsList.jsx` - Current implementation (verified English strings)
- `src/pages/Teams/TeamDetail.jsx` - Current implementation (verified English strings)
- `src/pages/Commissies/CommissiesList.jsx` - Current implementation (verified English strings)
- `src/pages/Commissies/CommissieDetail.jsx` - Current implementation (verified English strings)
- `src/components/TeamEditModal.jsx` - Current implementation (verified English strings)
- `src/components/CommissieEditModal.jsx` - Current implementation (verified English strings)
- `src/components/VisibilitySelector.jsx` - Shared component (verified English strings)
- `src/pages/People/PeopleList.jsx` - Reference for Dutch translation patterns (already translated)

### Secondary (MEDIUM confidence)
- Phase 102 completed summaries - Verified translation approach works

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No changes needed, existing stack
- Architecture: HIGH - Direct string replacement, established pattern
- Pitfalls: HIGH - Based on actual Phase 102 experience and code review
- Code examples: HIGH - Derived from actual codebase + CONTEXT.md

**Files to translate (7 total):**
1. `src/pages/Teams/TeamsList.jsx` (~1272 lines)
2. `src/pages/Teams/TeamDetail.jsx` (~577 lines)
3. `src/pages/Commissies/CommissiesList.jsx` (~1272 lines)
4. `src/pages/Commissies/CommissieDetail.jsx` (~577 lines)
5. `src/components/TeamEditModal.jsx` (~617 lines)
6. `src/components/CommissieEditModal.jsx` (~617 lines)
7. `src/components/VisibilitySelector.jsx` (~159 lines) - SHARED

**Research date:** 2026-01-25
**Valid until:** 2026-02-25 (stable - translation patterns don't change)
