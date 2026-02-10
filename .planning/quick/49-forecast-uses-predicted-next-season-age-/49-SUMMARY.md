# Quick Task 49: Forecast uses predicted next-season age class

## Changes

### Backend: `includes/class-membership-fees.php`
- Added `predict_next_season_age_class()` method: "Onder N" → "Onder N+1", "Onder 19" → "Senioren", preserves gender suffixes (Meiden→Vrouwen at senior level)

### Backend: `includes/class-rest-api.php`
- Modified forecast branch of `get_fee_summary()`:
  - Only reclassifies **youth category** members (minis, pupil, junior) based on predicted age class
  - Non-youth categories (senior, recreant, donateur) stay as-is (matched by team/werkfunctie)
  - Uses next season's fee amounts for reclassified members
  - Recalculates family discount using same rate against new base fee
  - No additional DB queries — reuses current season cache

## Verification

Tested on production — forecast correctly shows:
- Minis: 45 → 19 (aging out to Pupil)
- Pupil: 165 → 134 (aging out to Junior)
- Junior: 303 → 339 (gaining from Pupil)
- Senior: 214 → 234 (gaining from Junior via Onder 19 → Senioren)
- Recreanten: 71 → 71 (unchanged, non-youth)

## Commit
`dfcb7641`
