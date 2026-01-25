# Phase 106: Global UI - Context

**Gathered:** 2026-01-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Translate all remaining English text scattered across shared components, utilities, and common UI patterns. This is the final phase of Dutch localization (v7.0). Focus areas: button labels, loading states, placeholders, validation messages, confirmation dialogs, and shared component text.

</domain>

<decisions>
## Implementation Decisions

### Activity Type Labels
- Mix approach: keep international terms, translate others
- Email → Email (keep)
- Chat → Chat (keep)
- Phone → Telefoon
- Video → Videogesprek
- Meeting → Vergadering
- Coffee → Koffie
- Lunch → Lunch (same in Dutch)
- Dinner → Diner
- Other → Overig

### Contact Type Labels
- Mix approach: keep brand names and technical terms
- Email → Email (keep)
- Phone → Telefoon
- Mobile → Mobiel
- Website → Website (keep)
- LinkedIn, Twitter, Bluesky, Threads, Instagram, Facebook, Slack → Keep as-is (brand names)
- Calendar link → Agenda link
- Other → Overig

### Custom Field Type Labels
- Translate common terms, keep technical ones
- Text → Tekst
- Textarea → Tekstveld
- Number → Nummer
- Date → Datum
- Select → Selectie
- Checkbox → Selectievakje
- True/False → Ja/Nee
- Image → Afbeelding
- File → Bestand
- Link → Link (keep)
- Color → Kleur
- Relationship → Relatie
- Email → Email (keep)
- URL → URL (keep)

### Boolean Display Values
- Use Ja / Nee for boolean field display

### Button Labels (from established patterns)
- Save → Opslaan
- Cancel → Annuleren
- Delete → Verwijderen
- Edit → Bewerken
- Add → Toevoegen
- Save changes → Wijzigingen opslaan
- Saving... → Opslaan...
- Adding... → Toevoegen...
- Uploading... → Uploaden...
- Replace → Vervangen
- Upload → Uploaden

### Placeholder Patterns (from established patterns)
- Search... → Zoeken...
- Search people... → Personen zoeken...
- Search country... → Land zoeken...
- No results → Geen resultaten
- e.g., → bijv.

### Status Labels
- New → Nieuw
- In Progress → In behandeling
- Resolved → Opgelost
- Declined → Afgewezen
- Approved → Goedgekeurd
- Low/Medium/High/Critical → Laag/Gemiddeld/Hoog/Kritiek

### Claude's Discretion
- Exact phrasing of help text and descriptions
- Tooltip text for rich text editor (Bold, Italic, etc.)
- Day names in date format options (Sunday, Monday)
- Table control tooltips (Save (Enter), Cancel (Esc))

</decisions>

<specifics>
## Specific Ideas

- Keep technical terms that Dutch users commonly use in English (Email, URL, Website, Chat)
- Brand names always stay as-is (LinkedIn, Twitter, Slack, etc.)
- Follow the informal "je" tone established in previous phases
- Maintain consistency with translations done in phases 99-105

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 106-global-ui*
*Context gathered: 2026-01-25*
