---
status: verifying
trigger: "vog-dark-mode-selected-rows"
created: 2026-01-30T00:00:00Z
updated: 2026-01-30T00:04:00Z
---

## Current Focus

hypothesis: CONFIRMED - CSS variables don't have dark mode variants
test: Implementing fix by adding .dark [data-accent] selectors with reversed color scales
expecting: Dark mode will use darker accent shades for backgrounds, lighter for text
next_action: Edit src/index.css to add dark mode CSS variable definitions

## Symptoms

expected: Selected rows in dark mode should have readable text (white/light text contrasting with selection background)
actual: Background is `rgb(236, 253, 245)` (light green) on the `tr`, text color is `rgb(243, 244, 246)` (very light gray) - both are light colors creating no contrast
errors: No console errors, visual issue only
reproduction: Go to VOG page, enable dark mode, select a row using checkbox
started: Just happened - quick task 025 was supposed to fix this but didn't work

## Eliminated

## Evidence

- timestamp: 2026-01-30T00:01:00Z
  checked: VOGRow component (lines 115-233)
  found: |
    Background: isSelected ? 'bg-accent-50 dark:bg-accent-900/30' (line 122)
    Text colors have conditional isSelected styling:
    - Name: isSelected ? 'text-gray-900 dark:text-white' : 'text-gray-900 dark:text-gray-50' (lines 141-144)
    - Other cells: isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' (lines 154-157, 170-173, 183-186, 196-199, 212-215, 223-226)

    User reported: Background is `rgb(236, 253, 245)` (light green), text is `rgb(243, 244, 246)` (very light gray)
  implication: The classes look correct for dark mode. Need to check Tailwind config for accent color definitions.

- timestamp: 2026-01-30T00:02:00Z
  checked: tailwind.config.js and src/index.css
  found: |
    Tailwind config uses CSS variables: accent-50 = var(--color-accent-50), etc.
    index.css defines these variables in @layer base with [data-accent="..."] selectors.

    PROBLEM: CSS variables are NOT defined separately for dark mode!
    The variables are set once globally, using light mode colors like:
    - emerald.50 = rgb(236, 253, 245) (very light green)
    - emerald.900 = rgb(6, 78, 59) (dark green)

    When the user has accent="emerald" and dark mode is active:
    - bg-accent-50 uses emerald.50 (light green) - wrong!
    - dark:bg-accent-900/30 ALSO uses emerald.900 at 30% opacity - but the CSS variable is the SAME in dark mode

    The dark: variants in Tailwind just add a .dark parent selector, but the CSS variable values themselves don't change.
  implication: Root cause found - CSS variables need dark mode variants that swap the color scale (50 becomes 900, etc.) or use different values.

## Resolution

root_cause: |
  CSS custom properties for accent colors (--color-accent-50 through --color-accent-900) are not defined with dark mode variants.

  In src/index.css, variables are set once per accent color (e.g., [data-accent="emerald"] sets --color-accent-50 to emerald.50).

  In dark mode, when bg-accent-50 is used, it still references the LIGHT color (emerald-50 = rgb(236,253,245) light green).
  The dark:bg-accent-900/30 class applies .dark parent selector but the CSS variable value remains emerald-900 from light mode.

  Result: Light background + light text = no contrast.

  Solution: Add .dark [data-accent="..."] selectors that reverse the color scale (50→900, 100→800, etc.) or use appropriately contrasting values.

fix: |
  Added dark mode variants for all accent color CSS variables in src/index.css.

  In the .dark selector, reversed the color scale:
  - accent-50 (light) → uses color.900 (dark)
  - accent-100 → uses color.800
  - accent-500 → uses color.400
  - accent-900 (dark) → uses color.50 (light)

  Added for all accent colors: orange (default), teal, indigo, emerald, violet, pink, fuchsia, rose.

  Now when dark mode is active:
  - bg-accent-50 → uses the dark shade (900) for background
  - dark:text-white → uses white text for maximum contrast

verification: |
  ✓ Build successful - no errors
  ✓ Deployed to production
  ✓ Caches cleared

  Manual testing required:
  1. Go to https://stadion.svawc.nl/vog
  2. Enable dark mode
  3. Select one or more rows using checkbox
  4. Verify: Selected row background is dark (using accent-900 color)
  5. Verify: Text is light/white and readable against dark background

  Expected behavior:
  - bg-accent-50 in dark mode now uses accent-900 (dark background)
  - dark:text-white provides white text on dark background
  - Result: High contrast, readable text
files_changed:
  - src/index.css
