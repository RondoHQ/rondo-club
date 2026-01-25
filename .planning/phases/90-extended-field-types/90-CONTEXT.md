# Phase 90: Extended Field Types - Context

**Gathered:** 2026-01-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Support 5 advanced field types for media, links, colors, and relationships: Image, File, Link, Color, Relationship. This phase adds backend support (Manager class, REST API) and Settings UI configuration options for these types. Detail view rendering is Phase 91.

</domain>

<decisions>
## Implementation Decisions

### Media Upload (Image & File fields)
- Direct upload button (not WordPress Media Library modal)
- Both Image and File fields allow any file type (no restrictions)
- File fields display: icon + filename + download button
- Image preview style: Claude's discretion

### Link Field
- Captures URL + display text (two inputs)
- Always opens in new tab (target=_blank)
- No URL validation (allow any text)
- If display text empty, show the URL itself as link text

### Color Picker
- Hex only format (#FF5733), no alpha/transparency
- No preset swatches, just the picker
- Simple square picker style (saturation/brightness square with hue slider)
- Display in detail view: swatch + hex code
- Allow clearing to empty/no color
- Use @uiw/react-color library
- Manual hex text input: Claude's discretion

### Relationship Field
- Can link to People and Teams only (not configurable per field)
- Cardinality configurable per field (single or multiple)
- Search-as-you-type dropdown for selection
- Display as chip/badge with entity name (clickable to navigate)

### Claude's Discretion
- Image field preview style (thumbnail size, details shown)
- Color picker manual hex input (whether to include)
- Exact styling of chips/badges for relationships

</decisions>

<specifics>
## Specific Ideas

- File display should show file type icon (PDF icon, doc icon, etc.) alongside filename
- Color swatch should be a visible square showing the actual color next to the hex text
- Relationship chips should be clickable to navigate to the linked entity

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope

</deferred>

---

*Phase: 90-extended-field-types*
*Context gathered: 2026-01-18*
