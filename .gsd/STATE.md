# GSD State

**Active Milestone:** M006 — Markeer als betaald
**Active Slice:** S01 — Manual paid audit trail + display
**Phase:** planned
**Requirements Status:** 0 active · 12 validated · 0 deferred · 0 out of scope

## Milestone Registry
- ✅ **M001:** Button Tier System & Sitewide Rollout
- ✅ **M002:** Mollie Payment Details
- ✅ **M003:** Credit Invoice Improvements
- ✅ **M004:** Contributie Exclusion Improvements
- ✅ **M005:** Spelactiviteit Field
- 🔄 **M006:** Markeer als betaald
- ⬜ **M007:** Remove iCal Feed
- ⬜ **M008:** M008

## Recent Decisions
- [M006-S01] Manual-paid meta stored BEFORE artifact cleanup in update_invoice_status()
- [M006-S01] Betaalgegevens card prioritizes Mollie data over manual-paid display
- [M006-S01] Use post meta (not ACF) for manual-paid tracking — matches _invoice_sent_by_user_id pattern

## Blockers
- None

## Next Action
Execute T01 of slice S01 (store manual-paid audit meta and return in REST response).
