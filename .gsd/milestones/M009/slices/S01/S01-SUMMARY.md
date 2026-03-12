# S01: Person Detail Page Cleanup — Summary

**Completed:** 2026-03-12
**Commit:** 2fc3b974

## What Was Done

All four UI improvements implemented in a single commit:

1. **VOG status pill removed** from person header — VOG info remains in VOGCard on profile tab
2. **Relaties card hidden** when `sortedRelationships?.length > 0` is false — no empty card shown
3. **Account card conditioned** on `person?.linked_user_id` existence — no longer shown for all volunteers
4. **Tab counts added** via optional `count` prop on TabButton component — Tijdlijn, Rollen, Kleding, and Tuchtzaken tabs show item counts in parentheses when > 0

## Files Modified

- `src/components/TabButton.jsx` — added optional `count` prop with `({count})` display when > 0
- `src/pages/People/PersonDetail.jsx` — all four changes applied
- `style.css` — version bump to 31.15.0
- `package.json` — version bump to 31.15.0
- `CHANGELOG.md` — added [31.15.0] entry

## Verification

- `npm run build` passes
- `npm run lint` passes (0 errors, 0 warnings)
- Deployed to production
