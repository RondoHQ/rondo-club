# GSD State

**Active Milestone:** M002 — Mollie Payment Details
**Active Slice:** S01 — Webhook payment detail extraction + REST API + Invoice detail UI
**Phase:** planned
**Requirements Status:** 0 active · 12 validated · 0 deferred · 0 out of scope

## Milestone Registry
- ✅ **M001:** Button Tier System & Sitewide Rollout
- 🔄 **M002:** Mollie Payment Details
- ⬜ **M003:** Credit Invoice Improvements

## Recent Decisions
- [M002-S01] Two separate extraction methods for invoice-level and per-installment detail storage
- [M002-S01] handle_installment_paid receives $payment_link as 4th parameter
- [M002-S01] Betaalgegevens card placed after installment timeline section
- [M002-S01] Installment table adds Methode and Mollie columns
- [M002-S01] getMollieMethodLabel helper with fallback to capitalized raw string

## Blockers
- None

## Next Action
Execute T01: Extract and store Mollie payment details in webhook handler.
