---
created: 2026-02-19T17:34:11.479Z
title: Make installment offers sensible based on remaining months
area: finance
files:
  - includes/class-public-payment-page.php
  - includes/class-bulk-invoice-creator.php
  - includes/class-installment-scheduler.php
---

## Problem

Installment plan options (quarterly_3, monthly_8) are currently offered regardless of when the invoice is actually sent. If an invoice is sent on February 19th, there are only a few payment dates left before the season ends in April, so offering 8 monthly installments makes no sense.

The system should dynamically calculate which installment plans are sensible based on:
- The date the invoice is sent
- Installment payment emails are always sent on the **23rd** of each month
- All contributie payments must be **fully completed by the last payment email in April**
- Only offer plans where the number of remaining payment dates (23rd of each month through April) can accommodate the plan

### Examples

- Invoice sent **October 1**: remaining dates are Oct 23, Nov 23, Dec 23, Jan 23, Feb 23, Mar 23, Apr 23 = 7 dates → can offer up to 7 installments
- Invoice sent **February 19**: remaining dates are Mar 23, Apr 23 = 2 dates → can only offer 2 or 3 installments (if Feb 23 is still upcoming, 3)
- Invoice sent **December 1**: remaining dates are Dec 23, Jan 23, Feb 23, Mar 23, Apr 23 = 5 dates → can offer up to 5 installments

## Solution

1. Calculate remaining payment dates (23rd of each month) between invoice send date and April 23rd
2. Only show installment plan options where the number of installments fits within available dates
3. Due dates should be the 23rd of each month (not 25th as currently hardcoded)
4. Update the public payment page to dynamically filter available plans
5. Update the installment scheduler to use 23rd instead of 25th for due dates
