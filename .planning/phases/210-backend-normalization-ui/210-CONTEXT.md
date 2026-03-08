# Phase 210: Backend Normalization & UI - Context

**Gathered:** 2026-03-08
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can view and edit all 6 contact fields on the person detail page with E.164 phone normalization on save and email change warnings. The ContactEditModal and display UI already exist from Phase 209 — this phase adds normalization, formatting, and the email warning.

</domain>

<decisions>
## Implementation Decisions

### Email change warning
- Inline hint text below email_1 field only (not email_2)
- Always visible in the ContactEditModal, regardless of whether email_1 has a value
- Text: "⚠️ Wijzigingen beïnvloeden de voetbal.nl login"
- Informational only — no confirmation dialog, does not block saving

### Phone display formatting
- Dutch mobile numbers: display as 06-12345678 (local format)
- Dutch landlines: display as 020-1234567 (area code-subscriber number, with correct 3/4-digit area code handling)
- Non-NL numbers: display in international format with spaces (+49 123 456789)
- Readable format shown in edit modal inputs too — user sees 06-12345678, not +31612345678
- Backend normalizes to E.164 on save regardless of input format

### Claude's Discretion
- E.164 normalization implementation (PHP, on REST API save) — handling edge cases, invalid input, already-international numbers
- Dutch area code lookup approach (hardcoded list vs pattern matching)
- Where to place the formatting utility (new PHP helper or extend existing formatters.js)
- Edit trigger and save flow — ContactEditModal already exists and works, keep current approach
- Error handling for invalid phone numbers

</decisions>

<specifics>
## Specific Ideas

- Phone normalization is NL-only (+31) per requirements — no need for international normalization
- The `formatPhoneForTel()` utility already handles 06→+316 conversion client-side for tel: links — the new display formatter is a separate concern (E.164→readable)

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ContactEditModal` (src/components/ContactEditModal.jsx): Fully functional 6-field form with react-hook-form, just needs email warning hint and readable phone formatting in inputs
- `formatPhoneForTel()` (src/utils/formatters.js): Client-side phone→tel format, handles 06→+316 and Unicode cleanup
- `handleSaveContacts()` (PersonDetail.jsx): Save flow through updatePerson.mutateAsync — already wired up
- `sanitizePersonAcf()` (src/utils/formatters.js): Sanitizes ACF data before save

### Established Patterns
- Contact fields use fixed ACF fields: email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2
- REST API save goes through WordPress standard person update endpoint
- Phone formatting for tel: links already strips non-digits and converts 06→+316

### Integration Points
- Backend normalization hooks into the REST API person update flow (PHP side)
- Frontend display formatter needed in PersonDetail.jsx (contactItems display) and ContactEditModal (input default values)
- Same display formatter useful in PeopleList.jsx, VOGList.jsx, Kaderlijst.jsx where phone numbers are shown

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 210-backend-normalization-ui*
*Context gathered: 2026-03-08*
