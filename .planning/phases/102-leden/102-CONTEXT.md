# Phase 102: Leden (People) - Context

**Gathered:** 2026-01-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Translate all people/leden UI elements from English to Dutch: list view headers, person form fields, gender options, and import messages. Functionality stays the same — only language changes.

</domain>

<decisions>
## Implementation Decisions

### List view terminology
- Page title: "Leden" (not Personen or Contacten)
- Name columns: "Voornaam" / "Achternaam" (separate columns)
- Labels column: "Labels" (keep as-is — commonly used in Dutch)
- Workspace column: "Workspace" (keep English — tech term)
- Team column: "Team" (same in Dutch)

### Form field labels
- Use compact style: Voornaam, Achternaam, E-mail, Telefoon
- Nickname field: "Bijnaam"
- Section headers: Use full Dutch words (e.g., "Contactgegevens" for Contact Information)
- Related people: "Gerelateerde personen"

### Gender options
- Field label: "Geslacht"
- Options use compact style:
  - M (Man/Mannelijk)
  - V (Vrouw/Vrouwelijk)
  - X (Non-binair)
  - Anders
  - Geen antwoord (for prefer not to say)

### Claude's Discretion
- Import/error message phrasing — maintain consistent tone with other translated areas
- Placeholder text in form fields
- Button labels (Opslaan, Annuleren, etc.)
- Empty state messages

</decisions>

<specifics>
## Specific Ideas

- Keep "Workspace" and "Labels" in English — they're understood tech terms
- Gender options are compact (M/V/X) to match minimalist UI style
- Use "Leden" consistently — implies club/association membership context

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 102-leden*
*Context gathered: 2026-01-25*
