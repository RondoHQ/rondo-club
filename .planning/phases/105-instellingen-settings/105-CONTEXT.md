# Phase 105: Instellingen (Settings) - Context

**Gathered:** 2026-01-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Translate all settings pages from English to Dutch. Covers settings navigation tabs, form fields, toggles, connection status indicators, and import/export functionality. This is a direct translation phase — no structural UI changes.

</domain>

<decisions>
## Implementation Decisions

### Tab & Navigation Labels
- Main tabs use user-friendly Dutch variants:
  - Appearance → Weergave
  - Connections → Koppelingen
  - Notifications → Meldingen
  - Data → Gegevens
  - Admin → Beheer (with visual indicator showing admin-only)
  - About → Info
- Connection subtabs keep service names in Dutch where applicable:
  - Google Agenda, Google Contacten, Slack (service names preserved)

### Form Field Terminology
- Theme options: Licht / Donker / Systeem
- Toggle switches use verb form: "Meldingen inschakelen", "Agenda synchroniseren"
- Import/Export uses standard Dutch: Importeren, Exporteren, Gegevens downloaden
- Connection status indicators: Actief / Inactief / Fout

### Claude's Discretion
- Help text and description translations (not discussed)
- Action feedback messages (success/error/loading states)
- Specific placeholder text in input fields
- Tooltip content translations

</decisions>

<specifics>
## Specific Ideas

- Admin tab should have a visual indicator (lock icon or badge) to show it's admin-only
- Keep external service names recognizable (Google Agenda, not "Google Kalender")

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 105-instellingen-settings*
*Context gathered: 2026-01-25*
