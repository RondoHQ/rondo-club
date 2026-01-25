---
created: 2026-01-13T21:48
title: Set up testing framework with PHPUnit and Playwright
area: testing
files:
  - tests/
  - phpunit.xml
  - playwright.config.js
---

## Problem

Stadion currently has no automated testing infrastructure. As the codebase grows with multi-user features, bulk operations, and API endpoints, we need a robust testing framework to:

1. Prevent regressions during development
2. Validate PHP backend functionality (REST API, access control, auto-titles)
3. Test React frontend interactions and user flows
4. Enable confident refactoring and feature additions

Without tests, changes to shared components (like access control or visibility systems) risk breaking existing functionality silently.

## Solution

Reference implementation: [ProgressPlanner/progress-planner](https://github.com/ProgressPlanner/progress-planner)

Set up dual testing infrastructure:

**PHPUnit for PHP backend:**
- Test REST API endpoints (authentication, authorization, CRUD)
- Test access control logic (user isolation, workspace permissions)
- Test auto-title generation
- Test reminder/notification systems
- WordPress integration tests with wp-env or similar

**Playwright for E2E frontend:**
- Test user authentication flow
- Test person/team CRUD operations
- Test bulk actions and selection
- Test visibility and sharing modals
- Test important date workflows

**Structure from ProgressPlanner:**
- Examine their test setup, CI configuration, and test patterns
- Adapt for WordPress theme context (vs plugin)
- Configure GitHub Actions for automated test runs
