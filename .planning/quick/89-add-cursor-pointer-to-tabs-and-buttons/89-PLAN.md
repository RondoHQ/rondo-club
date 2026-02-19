---
phase: quick-89
plan: 01
type: execute
---

## Objective

Add `cursor: pointer` to all buttons and tab elements globally so interactive elements show the pointer cursor on hover.

## Tasks

### Task 1: Add global cursor-pointer rule

**File:** `src/index.css`

Add a CSS rule targeting `button`, `[role="tab"]`, `[role="button"]`, and `summary` elements with `cursor: pointer`. Placed before the `.btn` class definitions so it applies globally without modifying each component individually.
