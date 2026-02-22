---
status: resolved
trigger: "contributie-columns-filters-incomplete"
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:05:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED
test: Built and deployed to production
expecting: Column picker now shows all 8 columns on per-lid page; leeftijdsgroep added to nog-te-factureren; actions column no longer appears in column picker
next_action: Done

## Symptoms

expected: Column picker should list all available columns, and filters should cover all filterable columns
actual: Only 3 columns available in column picker, limited filter options
errors: No errors — just missing configuration
reproduction: Go to /financien/contributie/per-lid or /financien/contributie/nog-te-factureren, click column cog or filter button
started: Since the DataTable refactoring on 2026-02-21

## Eliminated

- hypothesis: filterColumns array was incomplete
  evidence: filterColumns in ContributieList has 6 filters covering all useful columns
  timestamp: 2026-02-22T00:01:00Z

## Evidence

- timestamp: 2026-02-22T00:01:00Z
  checked: ContributieList.jsx colVisColumns
  found: Only 3 columns listed (leeftijdsgroep, family_discount_rate, prorata_percentage). Always-visible columns (voornaam, achternaam, categorie, basis, bedrag, nikki, saldo) not in picker and not toggle-checked via isColVisible
  implication: Column picker shows only 3 hideable columns; the rest are hardcoded always-visible in FeeRow

- timestamp: 2026-02-22T00:01:00Z
  checked: NogTeFactureren.jsx columns
  found: 9 columns defined (first_name, last_name, category, base_fee, family_discount_rate, prorata_percentage, final_fee, invoice_status, actions). Missing leeftijdsgroep column. Actions column not marked enableHiding:false so it appears in column picker.
  implication: Column picker shows actions column which should not be toggleable; leeftijdsgroep column is absent

- timestamp: 2026-02-22T00:01:00Z
  checked: DataTable.jsx ColumnSettingsPanel usage
  found: Uses table.getAllLeafColumns().filter(col => col.getCanHide()) — TanStack defaults to getCanHide()=true for all columns unless enableHiding:false set on column def
  implication: To exclude actions column from picker, need to add enableHiding:false

- timestamp: 2026-02-22T00:01:00Z
  checked: columnHelpers.js createColumn
  found: No enableHiding parameter exposed. Need to add it.
  implication: Need to add enableHiding support to createColumn, or set it directly on the column def after createColumn

## Resolution

root_cause: |
  ContributieList: colVisColumns only listed 3 optional columns; the 5 always-visible columns (voornaam, achternaam, categorie, basis, bedrag) were hardcoded without isColVisible checks and not listed in the column picker.
  NogTeFactureren: missing leeftijdsgroep column; actions column appeared in column picker because enableHiding was not set to false.

fix: |
  ContributieList: Added all 8 columns to colVisColumns (first_name, last_name, category, leeftijdsgroep, base_fee, family_discount_rate, prorata_percentage, final_fee). Added isColVisible checks to FeeRow and header for previously hardcoded columns. Updated footer colspan to be dynamic based on visible label columns.
  NogTeFactureren: Added leeftijdsgroep column (defaultHidden: true) with TEXT filter. Marked actions column with enableHiding: false.
  columnHelpers.js: Added enableHiding parameter to createColumn factory (defaults to true).
verification: Build passed, lint clean, deployed to https://rondo.svawc.nl/ at commit fac37780
files_changed:
  - src/pages/Contributie/ContributieList.jsx
  - src/pages/Contributie/NogTeFactureren.jsx
  - src/components/DataTable/columnHelpers.js
