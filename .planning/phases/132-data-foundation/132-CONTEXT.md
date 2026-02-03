# Phase 132: Data Foundation - Context

**Gathered:** 2026-02-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Backend infrastructure for discipline case data storage and API access. Includes `discipline_case` CPT, ACF fields for all case data, `seizoen` taxonomy, and REST API exposure. This phase builds the data layer — UI display is Phase 134.

</domain>

<decisions>
## Implementation Decisions

### Field Semantics
- `is-charged` (Is doorbelast): Boolean true/false — indicates whether costs were charged to the person, NOT about formal charges
- `administrative-fee`: Decimal number stored in euros (e.g., 25.00)
- `charge-codes`: Single text field — one charge code per case
- `dossier-id`: Primary Sportlink sync key — must be unique, used for matching/updating cases during sync

### Person Linking
- Use ACF Relationship field pointing to person CPT
- Single person per case (not multi-select)
- Person field is optional — cases can exist without a linked person
- Unlinked import behavior: If a discipline case comes from Sportlink but the person doesn't exist, import the case with empty person field for manual linking later

### Seizoen Taxonomy
- Format: Full years (2024-2025)
- Non-hierarchical, REST-enabled
- Shared taxonomy — register for discipline_case but designed for future use with other CPTs
- Auto-create terms during Sportlink sync when new seasons appear
- Support "current season" concept — one term marked as active (via term meta) for default filtering in UI

### Claude's Discretion
- REST API response structure and field naming
- ACF field group internal organization
- Validation rules for fields (beyond what's specified)
- Error handling for sync edge cases

</decisions>

<specifics>
## Specific Ideas

- Label translations: "Tuchtzaak" (singular) / "Tuchtzaken" (plural) for CPT
- "Is doorbelast" as the Dutch label for is-charged field
- Season format should be consistent: 2024-2025 (not 24/25 or 2024/2025)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 132-data-foundation*
*Context gathered: 2026-02-03*
