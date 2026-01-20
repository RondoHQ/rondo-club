# Phase 94: Polish - Context

**Gathered:** 2026-01-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete field management UX with drag-and-drop ordering, required/unique validation options, and placeholder text. This phase polishes existing field management — no new field types or capabilities.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

User delegated all implementation decisions to Claude. The following areas are open for Claude to decide during planning:

**Drag-and-drop behavior:**
- Visual feedback style (ghost element, drop indicators)
- Save behavior (auto-save on drop vs explicit save)
- Scope (reorder affects display order in both detail view and list view)
- Library choice (react-beautiful-dnd, dnd-kit, or native HTML5)

**Validation UX:**
- When validation runs (on blur, on save, or both)
- Error display style (inline under field vs toast notification)
- Error message format and wording
- Required field indicator style (asterisk, text, color)
- Unique validation timing (immediate API check vs on-save)

**Placeholder styling:**
- Which field types support placeholders (text, textarea, email, url, number)
- Character limit for placeholder text
- How placeholders display in admin preview
- Whether placeholders show in list view empty states

**Settings panel layout:**
- Grouping of new options (validation section, display section)
- Progressive disclosure approach (expandable sections vs flat layout)
- Field order in FieldFormPanel
- Help text and tooltips for new options

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches that match existing Caelis patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 94-polish*
*Context gathered: 2026-01-20*
