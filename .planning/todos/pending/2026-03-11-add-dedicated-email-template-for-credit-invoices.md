---
created: "2026-03-11T12:43:36.812Z"
title: Add dedicated email template for credit invoices
area: general
files:
  - includes/class-invoice-email-sender.php
  - includes/class-finance-config.php
---

## Problem

Credit invoices (creditfacturen) currently use the same email template as regular invoices. This is confusing for recipients because the email references payment, includes a QR code, and contains a payment link — none of which apply to a credit invoice where money is being returned, not collected.

## Solution

Add a separate email template for credit invoices in FinanceConfig (similar to how membership vs discipline invoices have different templates). The credit invoice email should:
- Explain the credit/refund clearly
- Omit QR code and payment link
- Reference the original invoice being credited
- Use appropriate subject line (e.g., "Creditfactuur" instead of "Factuur")

InvoiceEmailSender should detect the invoice type and select the correct template.
