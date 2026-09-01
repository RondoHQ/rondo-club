# AWC FreeScout appearance color evidence

**Date:** 2026-09-01<br>
**Source:** [AWC logo and house style](https://www.svawc.nl/logo/)<br>
**Method:** read-only inspection of the published page and WCAG relative-luminance calculation

## Published palette

AWC publishes these house-style values on its logo page:

| Role on source page | Value |
|---|---|
| Dark AWC green | `#006935` |
| Medium green | `#4E8A63` |
| Light green | `#CCE1D7` |
| Very light green | `#EBF3EF` |
| Warm neutral | `#EEE9E7` |
| Dark neutral | `#333333` |

The page says the dark green is used for headings and buttons, with white text on dark green and
black text on the lighter colors.

## FreeScout selection

| Module setting | AWC value |
|---|---|
| Interface accent | `#006935` |
| Interface accent surface | `#CCE1D7` |

This pair keeps the active navigation and toolbar clearly green while preserving normal-text and
icon contrast:

| Foreground | Background | Contrast |
|---|---|---:|
| `#006935` | `#FFFFFF` | `6.84:1` |
| `#006935` | `#CCE1D7` | `4.99:1` |
| `#333333` | `#CCE1D7` | `9.22:1` |
| `#FFFFFF` | `#006935` | `6.84:1` |

The medium `#4E8A63` reaches only `4.09:1` against white and is therefore not selected for the
module's normal-size link/icon role.

## Configuration boundary

These are AWC installation values, not compiled defaults. The module remains club-neutral and
falls back to FreeScout's native colors until another club configures its own validated pair.
