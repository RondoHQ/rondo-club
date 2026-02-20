---
created: 2026-02-20T09:39:58.159Z
title: Update demo export for newly added features
area: general
files:
  - bin/generate-demo-data.php
  - includes/class-demo-protection.php
---

## Problem

The demo export data has not been updated to reflect features added in recent milestones (v25.0 through v28.0). This means the demo site cannot properly showcase:

- **v28.0** Membership fee invoicing (invoices, installments, payment links)
- **v27.0** Mollie payment integration
- **v26.0** Discipline case invoicing
- **v25.0** Feedback system with agent workflow
- Any other features added since v24.0 Demo Data was shipped

Without updated demo data, new features appear empty or non-functional on the demo site, making it harder to demonstrate the platform's capabilities.

## Solution

1. Review all features added since v24.0 to identify what demo data is needed
2. Update the demo data generation script to create realistic sample data for each new feature
3. Regenerate and deploy the demo data to demo.rondo.club
4. Verify all feature areas show meaningful content on the demo site
