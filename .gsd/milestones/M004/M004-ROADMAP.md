# M004: Contributie Exclusion Improvements

**Vision:** The exclusion toggle asks for confirmation, immediately updates the UI, and notifies the Secretaris and Penningmeester by email.

## Success Criteria

- "Uitsluiten van contributie" shows a confirmation prompt before proceeding
- "Opnemen" shows a confirmation prompt before proceeding
- FinancesCard immediately reflects the new state after toggling (no page reload)
- Secretaris and Penningmeester receive an email with person name (linked), actor name, and timestamp

## Key Risks / Unknowns

- None — small, well-understood changes using existing patterns

## Verification Classes

- Contract verification: build + lint pass
- Integration verification: toggle exclusion on production, verify UI refresh and email delivery
- Operational verification: none
- UAT / human verification: verify email content and link correctness

## Milestone Definition of Done

This milestone is complete only when all are true:

- Confirmation dialogs work on both exclude and re-include actions
- FinancesCard updates immediately after toggle
- Email sent to Secretaris and Penningmeester with correct content
- Deployed to production and verified

## Slices

- [ ] **S01: Confirmation, Refresh & Email Notification** `risk:low` `depends:[]`
  > After this: Exclusion toggle confirms, refreshes immediately, and sends notification emails to Secretaris and Penningmeester
