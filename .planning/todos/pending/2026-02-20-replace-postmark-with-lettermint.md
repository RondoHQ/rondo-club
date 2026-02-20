---
created: 2026-02-20T07:41:44.897Z
title: Replace Postmark with Lettermint
area: general
files:
  - includes/class-invoice-email-sender.php
  - includes/class-installment-email-sender.php
  - includes/class-email-channel.php
  - includes/class-vog-email.php
  - includes/class-mention-notifications.php
  - includes/class-installment-scheduler.php
  - includes/class-rest-invoices.php
---

## Problem

The system currently uses WordPress's default `wp_mail()` for all transactional emails (invoice sending, installment notifications, overdue reminders, VOG emails, mention notifications). Postmark was considered during v28.0 research but not implemented. The email infrastructure should be migrated to Lettermint for better deliverability and transactional email management.

## Solution

Replace the email sending layer with Lettermint. Key areas to update:
- `class-email-channel.php` — likely the central email abstraction
- `class-invoice-email-sender.php` — discipline case invoice emails
- `class-installment-email-sender.php` — installment and reminder emails
- `class-vog-email.php` — VOG notification emails
- `class-mention-notifications.php` — @mention notification emails
- Evaluate whether Lettermint provides a WordPress plugin/SDK or requires direct API integration
- Consider whether to replace `wp_mail()` globally or wrap it in a Lettermint transport
