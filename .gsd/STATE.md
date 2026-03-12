# GSD State

**Active Milestone:** M003 — Credit Invoice Improvements
**Active Slice:** S01 — Credit Invoice Email Template & Status Fix
**Phase:** executing
**Requirements Status:** 0 active · 12 validated · 0 deferred · 0 out of scope

## Milestone Registry
- ✅ **M001:** Button Tier System & Sitewide Rollout
- ✅ **M002:** Mollie Payment Details
- 🔄 **M003:** Credit Invoice Improvements
- ⬜ **M004:** Contributie Exclusion Improvements
- ⬜ **M005:** Spelactiviteit Field
- ⬜ **M006:** Markeer als betaald
- ⬜ **M007:** Remove iCal Feed

## Recent Decisions
- [M003-S01] Credit template follows existing 8-template pattern exactly
- [M003-S01] Credit template variables exclude {betaallink}/{qr_code}/{betaalknop}
- [M003-S01] invoice_kind checked BEFORE invoice_type in template routing
- [M003-S01] Auto-paid transition fully removed for credit invoices
- [M003-S01] InvoiceEmailSender reads _invoice_kind from post meta inside send()

## Blockers
- None

## Next Action
Execute T01: Add credit email template to FinanceConfig and wire REST API.
