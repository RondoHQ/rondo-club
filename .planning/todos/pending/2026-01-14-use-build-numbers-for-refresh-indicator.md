---
created: 2026-01-14T20:19
title: Use build numbers for refresh indicator
area: ui
files:
  - src/App.jsx
  - vite.config.js
---

## Problem

The refresh indicator that detects new deployments currently uses version numbers. This can result in users seeing stale versions between deployments if the version number hasn't changed (e.g., during development or hotfixes within the same version).

## Solution

Replace version number detection with build numbers (e.g., timestamps or Git commit hashes) so every deployment triggers the refresh indicator, regardless of whether the semantic version has changed.

TBD: Determine build number format (Unix timestamp, Git SHA, auto-incrementing number) and where to inject it during the build process.
