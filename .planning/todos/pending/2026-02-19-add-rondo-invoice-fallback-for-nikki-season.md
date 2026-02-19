---
created: 2026-02-19T14:10:58.111Z
title: Add Rondo invoice fallback for Nikki season
area: general
files:
  - src/pages/Contributie/
  - includes/class-membership-fees.php
  - includes/class-rest-invoices.php
  - includes/class-bulk-invoice-creator.php
---

## Problem

When a season's billing method is set to Nikki, there is no way to create Rondo invoices for people who do not have a Nikki saldo. This blocks mid-season switching: if the club starts with Nikki but some members never got a Nikki saldo (or their saldo ran out), those members cannot be invoiced at all through the current system.

The "Geen Nikki" filter currently shows these people but offers no action path to invoice them.

## Solution

1. Move the "Geen Nikki" filter from inline filter to a **separate tab** on the Contributie page
2. Rename the tab to **"Nog te factureren"** (still to be invoiced)
3. Add a **Rondo balance column** to this tab showing each person's current Rondo invoice/payment status
4. Add ability to **create Rondo invoices** for people in this tab, even when the season billing method is Nikki
5. This enables mid-season hybrid billing: Nikki for most members, Rondo invoices for those without Nikki saldo
