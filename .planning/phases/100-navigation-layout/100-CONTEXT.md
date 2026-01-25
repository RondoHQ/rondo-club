# Phase 100: Navigation & Layout - Context

**Gathered:** 2026-01-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Translate all navigation and layout UI elements from English to Dutch. This includes sidebar labels, quick actions menu, user menu, and search modal. No structural changes — direct string replacement only.

</domain>

<decisions>
## Implementation Decisions

### Formality & Tone
- Informal (je/jij) tone throughout the UI
- Imperative verbs for buttons: "Sla op", "Verwijder", "Annuleer" (not infinitive "Opslaan")
- Nouns only for navigation labels: "Leden", "Teams", "Instellingen" (not "Bekijk leden")
- Imperative style for placeholders: "Zoek...", "Typ hier..."

### Text Length Handling
- Allow overflow — UI adapts to content, no abbreviations
- Sidebar shows full Dutch labels, width can grow if needed
- Dropdown menus accommodate longer text, no concern about length

### Special Terms & Exceptions
- Keep "Dashboard" (common loan word)
- Keep "Feedback" (standard loan word)
- Use "Workspaces" instead of "Werkruimtes" (keep English)
- Translate all other technical/UI terms to Dutch (Beheerder, Instellingen, Profiel, etc.)

### Navigation Labels (from roadmap)
- Dashboard (keep English)
- Leden
- Teams
- Commissies
- Datums
- Taken
- Workspaces (keep English)
- Feedback (keep English)
- Instellingen

### Quick Actions Labels
- Nieuw lid
- Nieuw team
- Nieuwe taak
- Nieuwe datum

### User Menu Labels
- Profiel bewerken
- WordPress admin → WordPress beheer
- Uitloggen

### Claude's Discretion
- Exact phrasing for edge cases not covered above
- How to handle any compound terms that arise

</decisions>

<specifics>
## Specific Ideas

- Consistent use of informal "je" when addressing the user
- Button style follows Dutch imperative convention (command form)
- English loan words only for Dashboard, Feedback, Workspaces — everything else Dutch

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 100-navigation-layout*
*Context gathered: 2026-01-25*
