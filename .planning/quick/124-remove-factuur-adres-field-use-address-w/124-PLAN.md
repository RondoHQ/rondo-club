---
phase: quick-124
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-pdf-generator.php
  - docs/prd/Invoice-Fields.md
autonomous: true
requirements: [QUICK-124]

must_haves:
  truths:
    - "Invoice PDF uses Factuur-labeled address from addresses repeater when present"
    - "Invoice PDF falls back to first address when no Factuur-labeled address exists"
    - "No factuur-adres ACF field references remain in active codebase"
  artifacts:
    - path: "includes/class-invoice-pdf-generator.php"
      provides: "Factuur address lookup logic in generate_pdf()"
      contains: "address_label"
  key_links:
    - from: "includes/class-invoice-pdf-generator.php"
      to: "ACF addresses repeater"
      via: "get_field('addresses', $person_id) with label filtering"
      pattern: "address_label.*Factuur"
---

<objective>
Update invoice PDF generation to prefer the "Factuur"-labeled address from the person's addresses repeater, falling back to the first address if none exists. Remove references to the old `factuur-adres` field from documentation.

Purpose: Rondo Sync will populate a "Factuur" address entry in the addresses repeater when a separate billing address exists. The invoice code must look for it there instead of the old standalone field.
Output: Updated invoice PDF generator with Factuur-address lookup logic.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-invoice-pdf-generator.php (lines 70-110 — address resolution logic)
@docs/prd/Invoice-Fields.md (references to factuur-adres field)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update invoice PDF address resolution to prefer Factuur-labeled address</name>
  <files>includes/class-invoice-pdf-generator.php</files>
  <action>
In `generate_pdf()`, update the address resolution block (currently lines 91-96) that reads the person's `addresses` repeater. Currently it blindly takes `$addresses[0]`. Change it to:

1. Loop through `$addresses` looking for an entry where `$address['address_label']` equals `'Factuur'` (case-insensitive comparison using `strcasecmp`).
2. If a Factuur-labeled address is found, use that entry for `$person_street` and `$person_city`.
3. If no Factuur-labeled address is found, fall back to the first address (`$addresses[0]`) as it does today.

The street formatting stays the same: `trim(street_name . ' ' . house_number . ' ' . house_number_addition)`.

Extract the address-to-street/city logic into a small helper to avoid duplicating the formatting code. A simple local closure or inline function is fine — no need for a separate method since it is only used here.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && grep -n "address_label" includes/class-invoice-pdf-generator.php && grep -n "Factuur" includes/class-invoice-pdf-generator.php</automated>
  </verify>
  <done>Invoice PDF generator checks for Factuur-labeled address first, falls back to first address. No duplicate formatting code.</done>
</task>

<task type="auto">
  <name>Task 2: Clean up factuur-adres references in documentation</name>
  <files>docs/prd/Invoice-Fields.md</files>
  <action>
Update `docs/prd/Invoice-Fields.md` to remove or update references to the `factuur-adres` ACF field:

1. Remove the `factuur-adres` row from the fields table (line ~29).
2. Remove the `factuur-adres` entry from the JSON example (line ~37).
3. Update the address section text to reflect that the billing address now comes from the `addresses` repeater with label "Factuur", with fallback to the first/primary address.
4. Remove the "Address" sync note about `factuur-adres` with `IsDefault = false` (line ~164) and replace with a note that Rondo Sync populates an address entry labeled "Factuur" in the addresses repeater when a separate billing address exists.

Note: The `factuur-adres` ACF field definition does NOT exist in `acf-json/group_person_fields.json` (already absent), and the field is not referenced in any PHP or React code. Only this documentation file needs updating.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && ! grep -i "factuur.adres" docs/prd/Invoice-Fields.md && echo "PASS: no factuur-adres references remain"</automated>
  </verify>
  <done>Invoice-Fields.md no longer references factuur-adres field. Documentation reflects the new addresses repeater approach with Factuur label.</done>
</task>

</tasks>

<verification>
- `npm run lint` passes (no new lint errors)
- `npm run build` succeeds
- `grep -ri "factuur.adres\|factuur_adres" includes/ src/` returns no matches (confirms no active code references)
- Invoice PDF generator contains `address_label` and `Factuur` in its address resolution logic
</verification>

<success_criteria>
- Invoice PDF generation prefers address with label "Factuur" from addresses repeater
- Falls back to first address when no Factuur label exists
- No references to old `factuur-adres` ACF field remain in active code or updated docs
- Build and lint pass
</success_criteria>

<output>
After completion, create `.planning/quick/124-remove-factuur-adres-field-use-address-w/124-SUMMARY.md`
</output>
