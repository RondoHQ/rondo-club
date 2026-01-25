# Phase 103: Teams & Commissies - Research

**Researched:** 2026-01-25
**Domain:** React UI translation (Dutch localization)
**Confidence:** HIGH

## Summary

This phase involves translating Teams and Commissies pages from English to Dutch, following the established translation patterns from Phase 102 (Leden). The research confirms this is a straightforward string replacement task with no library dependencies or architectural changes required.

The codebase already has Commissies as a separate entity (post type `commissie`) with its own list and detail pages. Teams use the `team` post type. Both follow nearly identical UI patterns with list views, detail pages, and edit modals. The translation approach should mirror Phase 102 exactly.

**Primary recommendation:** Apply direct string replacement in 4 files (TeamsList.jsx, TeamDetail.jsx, CommissiesList.jsx, CommissieDetail.jsx) and 2 modals (TeamEditModal.jsx, CommissieEditModal.jsx), following Phase 102 patterns and CONTEXT.md terminology.

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
│   │   ├── TeamsList.jsx      # List page, filters, bulk actions
│   │   └── TeamDetail.jsx     # Detail page, sections
│   └── Commissies/
│       ├── CommissiesList.jsx # List page, filters, bulk actions
│       └── CommissieDetail.jsx # Detail page, sections
└── components/
    ├── TeamEditModal.jsx      # Create/edit form
    ├── CommissieEditModal.jsx # Create/edit form
    └── VisibilitySelector.jsx # Shared visibility component
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

**Key insight:** The VisibilitySelector component is shared and should be translated once, affecting all entities.

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

### Pitfall 4: Parent/Child Terminology
**What goes wrong:** "Subsidiary" and "Parent organization" left in English
**Why it happens:** These are less prominent UI elements
**How to avoid:** Per CONTEXT.md: remove/hide parent organization for Teams
**Warning signs:** References to parent organizations still visible

### Pitfall 5: VisibilitySelector Shared Component
**What goes wrong:** Translating visibility in TeamEditModal but not the shared component
**Why it happens:** VisibilitySelector.jsx is a separate shared component
**How to avoid:** Translate VisibilitySelector.jsx once, affects all uses
**Warning signs:** Visibility options show different languages in different places

## Code Examples

### Translation Mapping - Teams List

```jsx
// Source: CONTEXT.md decisions + Phase 102 patterns

// Page elements
"Search organizations..." -> "Teams zoeken..."
"Add organization" -> "Nieuw team"
"organizations" -> "teams" (in selection toolbar)

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
"Subsidiaries" -> Skip/hide per CONTEXT.md

// Empty states
"No current employees." -> "Geen huidige leden."
"No former employees." -> "Geen voormalige leden."

// Confirmation dialogs
"Are you sure you want to delete this organization?" -> "Weet je zeker dat je dit team wilt verwijderen?"
"Failed to delete organization. Please try again." -> "Team kon niet worden verwijderd. Probeer het opnieuw."
```

### Translation Mapping - Teams Edit Modal

```jsx
// Modal headers
"Edit organization" -> "Team bewerken"
"Add organization" -> "Nieuw team"

// Form labels
"Organization name *" -> "Naam *"
"Website" -> "Website"
"Parent organization" -> Remove/hide per CONTEXT.md
"Investors" -> "Sponsoren"

// Placeholders
"Acme Inc." -> "Voorbeeld BV"
"Search organizations..." -> "Teams zoeken..."
"Search people and organizations..." -> "Leden en teams zoeken..."
"Add investor..." -> "Sponsor toevoegen..."

// Buttons
"Cancel" -> "Annuleren"
"Saving..." -> "Opslaan..."
"Save changes" -> "Wijzigingen opslaan"
"Create organization" -> "Team aanmaken"

// Helper text
"Select if this organization is a subsidiary or division of another" -> Remove/hide
"Select people or organizations that have invested in this team" -> "Selecteer leden of teams die dit team sponsoren"
```

### Translation Mapping - Commissies List

```jsx
// Page elements
"Search organizations..." -> "Commissies zoeken..."
"Add organization" -> "Nieuwe commissie"

// Empty states
"No organizations found" -> "Geen commissies gevonden"
"Failed to load organizations." -> "Commissies konden niet worden geladen."
"Add your first organization." -> "Voeg je eerste commissie toe."
```

### Translation Mapping - Commissies Detail

```jsx
// Navigation
"Back to organizations" -> "Terug naar commissies"

// Section headers (per CONTEXT.md)
"Current employees" -> "Leden" (or "Commissieleden")
"Former employees" -> "Voormalige leden"
"Investors" -> "Sponsoren"
"Subsidiaries" -> "Subcommissies" (if applicable)

// Confirmation
"Are you sure you want to delete this organization?" -> "Weet je zeker dat je deze commissie wilt verwijderen?"
```

### Translation Mapping - Commissies Edit Modal

```jsx
// Modal headers
"Edit commissie" -> "Commissie bewerken"
"Add commissie" -> "Nieuwe commissie"

// Form labels
"Commissie name *" -> "Naam *"
"Parent commissie" -> "Hoofdcommissie"

// Helper text
"Select if this commissie is a sub-commissie of another" -> "Selecteer als deze commissie een subcommissie is"
```

### Translation Mapping - VisibilitySelector

```jsx
// Visibility label
"Visibility" -> "Zichtbaarheid"

// Visibility options (per CONTEXT.md)
"Private" -> "Prive"
"Only you can see this" -> "Alleen jij kunt dit zien"
"Workspace" -> "Workspace" (keep English)
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

### Translation Mapping - Bulk Action Modals

```jsx
// BulkVisibilityModal
"Change Visibility" -> "Zichtbaarheid wijzigen"
"Select visibility for {count} organization(s):" -> "Kies zichtbaarheid voor {count} team(s)/commissie(s):"
"Only you can see these organizations" -> "Alleen jij kunt deze teams/commissies zien"
"Cancel" -> "Annuleren"
"Applying..." -> "Toepassen..."
"Apply to {count} organization(s)" -> "Toepassen op {count} team(s)/commissie(s)"

// BulkWorkspaceModal
"Assign to Workspace" -> "Toewijzen aan workspace"
"Select workspaces for {count} organization(s):" -> "Selecteer workspaces voor {count} team(s)/commissie(s):"
"No workspaces available." -> "Geen workspaces beschikbaar."
"Create a workspace first to use this feature." -> "Maak eerst een workspace aan om deze functie te gebruiken."
"Assigning..." -> "Toewijzen..."
"Assign to {count} organization(s)" -> "Toewijzen aan {count} team(s)/commissie(s)"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| English UI | Dutch UI | Phase 101-103 | Full Dutch experience |
| "Investors" | "Sponsoren" | CONTEXT.md decision | Sports club context |
| "Parent Organization" | Hidden/removed | CONTEXT.md decision | Simplified Teams UI |

**Deprecated/outdated:**
- "Industry" field: Hidden for Teams per CONTEXT.md (not needed)
- "Parent Organization" field: Hidden for Teams per CONTEXT.md (no longer relevant)

## Open Questions

1. **VisibilitySelector Translation Scope**
   - What we know: Component is shared across Teams, Commissies, People
   - What's unclear: Should it be translated in this phase or was it already done in Phase 102?
   - Recommendation: Check if already translated; if not, translate in this phase

2. **Commissie Member Roles**
   - What we know: CONTEXT.md mentions "Voorzitter" and "Lid" as predefined roles
   - What's unclear: Where are these roles displayed/edited?
   - Recommendation: May be in a separate component not yet identified; implement if found

## Sources

### Primary (HIGH confidence)
- `.planning/phases/103-teams-commissies/103-CONTEXT.md` - User decisions and terminology
- `.planning/phases/102-leden/102-01-PLAN.md` - Established translation patterns
- `.planning/phases/102-leden/102-02-PLAN.md` - Form translation patterns
- `src/pages/Teams/TeamsList.jsx` - Current implementation (lines 1-1272)
- `src/pages/Teams/TeamDetail.jsx` - Current implementation (lines 1-577)
- `src/pages/Commissies/CommissiesList.jsx` - Current implementation (lines 1-1272)
- `src/pages/Commissies/CommissieDetail.jsx` - Current implementation (lines 1-577)
- `src/components/TeamEditModal.jsx` - Current implementation (lines 1-617)
- `src/components/CommissieEditModal.jsx` - Current implementation (lines 1-617)
- `src/components/VisibilitySelector.jsx` - Shared component (lines 1-159)

### Secondary (MEDIUM confidence)
- Phase 102 completed summaries - Verified translation approach works

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No changes needed, existing stack
- Architecture: HIGH - Direct string replacement, established pattern
- Pitfalls: HIGH - Based on actual Phase 102 experience
- Code examples: HIGH - Derived from actual codebase + CONTEXT.md

**Research date:** 2026-01-25
**Valid until:** 2026-02-25 (stable - translation patterns don't change)
