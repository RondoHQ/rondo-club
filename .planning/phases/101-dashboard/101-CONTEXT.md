# Phase 101: Dashboard - Context

**Gathered:** 2026-01-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Translate all dashboard UI elements from English to Dutch. This includes stat cards, widget titles, empty states, welcome messages, error messages, and update banners. The dashboard structure and functionality remain unchanged — only the text content is translated.

</domain>

<decisions>
## Implementation Decisions

### Terminology choices
- Use "Totaal leden" for the people count stat (not "personen" or "contacten")
- Use "Taken" for tasks (not "To-dos" or "Actiepunten")
- Use "Openstaand" for pending/waiting items (not "Wachtend" or "In afwachting")
- Informal tone throughout: use "jij/je" forms, never "u"

### Empty state messages
- Warm & helpful style for all empty states
- Include action suggestions with CTA buttons (e.g., "Geen taken — Maak je eerste taak" with button)
- Consistent warm tone across dashboard and widget empty states
- Always use "je" pronoun: "Je hebt nog geen taken", "Voeg je eerste lid toe"

### Claude's Discretion
- Error message phrasing (keep friendly and actionable)
- Widget title translations (follow established patterns from Phase 100)
- Update banner wording

</decisions>

<specifics>
## Specific Ideas

- Stat cards from success criteria: "Totaal leden", "Teams", "Evenementen", "Open taken", "Openstaand"
- Widget titles from success criteria: "Komende herinneringen", "Open taken", "Openstaand" (was "Wachtend op reactie"), "Afspraken vandaag"

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 101-dashboard*
*Context gathered: 2026-01-25*
