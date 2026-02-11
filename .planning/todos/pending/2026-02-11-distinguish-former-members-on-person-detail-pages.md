---
created: 2026-02-11T10:00:56.467Z
title: Distinguish former members on person detail pages
area: ui
files:
  - src/pages/PersonDetail.jsx
---

## Problem

Former members (people with `former_member: true`) look identical to active members on their individual detail pages. There's no visual distinction — a user viewing a former member's page has no immediate indication that this person is no longer an active member.

The v23.0 milestone added former member filtering and visibility controls at the list level, but the individual person detail pages don't clearly communicate former-member status.

## Solution

Add a visual indicator on the person detail page when viewing a former member. Options to consider:
- A banner/badge at the top of the page (e.g., "Former Member" with a muted/warning style)
- Greyed-out or visually distinct header treatment
- Show `lid-tot` date prominently if set
- Consider using the existing design system tokens (e.g., muted text colors, warning badges)
