---
status: resolved
trigger: "doorbelast-shows-nee-on-invoiced-discipline-cases"
created: 2026-02-19T00:00:00Z
updated: 2026-02-19T21:30:00Z
---

## Current Focus

hypothesis: RESOLVED
test: n/a
expecting: n/a
next_action: archived

## Symptoms

expected: Discipline cases linked to invoices should show "Doorbelast: Ja, Rondo" (or similar) indicating the fine has been charged/invoiced
actual: They show "Doorbelast: Nee" even though invoices exist and some are paid
errors: No error messages — just wrong display value
reproduction: Look at invoice https://rondo.svawc.nl/financien/facturen/6181 — both discipline cases show "Nee" on Doorbelast. Same for all other Tuchtzaken invoices.
started: Likely since rondo-sync runs after invoices are sent

## Eliminated

- hypothesis: is_charged never set during send_invoice()
  evidence: send_invoice() at line 951-959 in class-rest-invoices.php correctly iterates line_items and calls update_field('is_charged', 'rondo', case_id). Tested manually on production — update_field works correctly.
  timestamp: 2026-02-19

- hypothesis: ACF field issue with get_field/update_field
  evidence: get_field('line_items', 6181) returns correct data with discipline_case as integer IDs. update_field works. ACF field key field_discipline_is_charged exists and is correct.
  timestamp: 2026-02-19

- hypothesis: Invoices were sent before is_charged code was added
  evidence: Commit dd59a577 added is_charged code on Feb 16. All invoices were created Feb 18-19. Code was in place.
  timestamp: 2026-02-19

## Evidence

- timestamp: 2026-02-19
  checked: Production meta for invoice 6181 (rondo_paid)
  found: line_items_0_discipline_case=2209, line_items_1_discipline_case=2204. Both discipline cases have is_charged='' (empty)
  implication: is_charged was never set or was reset after being set

- timestamp: 2026-02-19
  checked: Production discipline cases 2209 and 2204
  found: is_charged is empty string on both, yet invoice is sent and paid
  implication: send_invoice() either failed or its is_charged update was overwritten

- timestamp: 2026-02-19
  checked: rondo-sync/steps/submit-rondo-club-discipline.js line 178
  found: 'is_charged': caseData.is_charged === 1 ? 'sportlink' : ''
  implication: rondo-sync ALWAYS sets is_charged to '' unless Sportlink says it's charged (value=1). It never produces 'rondo'. So any time rondo-sync runs and a discipline case has changed data (different hash), it overwrites is_charged back to '' even if Rondo set it to 'rondo'.

- timestamp: 2026-02-19
  checked: rondo-sync syncCase() hash check
  found: Only skips update if source_hash === last_synced_hash AND not force mode. If anything about the case changes in Sportlink data, the full payload including is_charged='' is sent.
  implication: This is the race condition: Rondo sends an invoice setting is_charged='rondo', then next time rondo-sync runs with a changed case, it resets is_charged back to ''.

- timestamp: 2026-02-19
  checked: Local vendor/composer/autoload_classmap.php
  found: BulkInvoiceCreator was NOT in the local classmap. Running composer dump-autoload fixed it.
  implication: Previous deployments deployed an outdated autoloader, causing PHP fatal errors on some requests (BulkInvoiceCreator not found). This could also cause send_invoice() to fail mid-execution in some cases.

- timestamp: 2026-02-19
  checked: Production after data fix
  found: All 5 invoiced discipline cases (2207, 2208, 2203, 2209, 2204) now have is_charged='rondo'
  implication: Data fix successful

## Resolution

root_cause: Two-part root cause:
1. PRIMARY: rondo-sync/steps/submit-rondo-club-discipline.js line 178 always sets is_charged to either 'sportlink' or '' (empty). When updating existing cases, if Sportlink doesn't charge the case, it sends is_charged='' which overwrites the 'rondo' value set by Rondo Club's send_invoice().
2. SECONDARY: Local vendor/composer/autoload_classmap.php was outdated (missing BulkInvoiceCreator and other recent classes). Deploying this stale autoloader caused PHP fatal errors on some requests, potentially interrupting send_invoice() before it could set is_charged.

fix:
1. rondo-sync: Modified syncCase() to omit is_charged from update payload when Sportlink doesn't charge the case (sportlinkIsCharged=false). This preserves any 'rondo' value set by Rondo Club. For creates, is_charged is still set to '' or 'sportlink' based on Sportlink data.
2. rondo-club: Ran composer dump-autoload to regenerate classmap with all new classes including BulkInvoiceCreator. Deployed to production.
3. data: Fixed all 5 existing invoiced discipline cases (2207, 2208, 2203, 2209, 2204) by setting is_charged='rondo' via WP-CLI eval-file.

verification: All 5 discipline cases confirmed as is_charged='rondo' on production. wp eval-file fix script reports Fixed:0 OK:5.

files_changed:
  - rondo-sync/steps/submit-rondo-club-discipline.js (commit e6e9c31)
  - rondo-club/vendor/composer/autoload_classmap.php (regenerated, deployed)
  - Production data: discipline cases 2207, 2208, 2203, 2209, 2204 set to is_charged='rondo'
