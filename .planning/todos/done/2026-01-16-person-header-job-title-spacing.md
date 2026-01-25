---
created: 2026-01-16T19:23
title: Fix missing space in person header job title
area: ui
files:
  - src/pages/People/PersonDetail.jsx
---

## Problem

In the person header, the job title and team display is missing a space. It currently shows:

"<Job title> at<Team>"

Instead of:

"<Job title> at <Team>"

## Solution

Find the job title / team display in PersonDetail.jsx header section and add the missing space after "at".
