# M003: Credit Invoice Improvements

**Vision:** Credit invoices get their own email template and no longer auto-mark as paid on send.

## Success Criteria

- Sending a credit invoice uses a dedicated email template without payment link/QR code references
- Credit email template is configurable in Finance Settings
- Sent credit invoices remain in "Verstuurd" status until manually marked paid
- Existing invoice flows (discipline, membership, manual) are unaffected

## Key Risks / Unknowns

- None — both changes are small, well-understood modifications to existing patterns

## Verification Classes

- Contract verification: build + lint pass, manual send flow test on production
- Integration verification: send a credit invoice on production and verify email content + status
- Operational verification: none
- UAT / human verification: verify email content looks correct, verify status flow works

## Milestone Definition of Done

This milestone is complete only when all are true:

- Credit email template added to FinanceConfig with Dutch default
- Template selection in send_invoice() routes credit invoices to new template
- Auto-paid transition removed for credit invoices
- Finance Settings UI has credit email template editor
- Deployed to production and verified

## Slices

- [x] **S01: Credit Invoice Email Template & Status Fix** `risk:low` `depends:[]`
  > After this: Credit invoices use their own email, stay in "Verstuurd" status, and the template is configurable in Settings
