---
phase: quick-89
status: complete
---

## Summary

Added a global CSS rule in `src/index.css` that sets `cursor: pointer` on all `button`, `[role="tab"]`, `[role="button"]`, and `summary` elements. This ensures all interactive elements throughout the app show the pointer cursor on hover, which browsers no longer do by default for `<button>` elements.

## Files Changed

- `src/index.css` — Added 4-line global rule before `.btn` class definitions

## Commit

- `31c5043f`: fix(quick-89): add cursor pointer to buttons and tabs globally
