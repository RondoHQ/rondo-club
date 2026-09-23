---
name: Rondo Club dashboard
description: Existing Rondo styles observed in the role dashboard.
colors:
  electric-cyan: "oklch(0.69 0.14 196)"
  electric-cyan-light: "oklch(0.79 0.14 196)"
  bright-cobalt: "oklch(0.55 0.19 264)"
  cyan-50: "oklch(0.96 0.02 196)"
  cyan-200: "oklch(0.85 0.08 196)"
  cyan-800: "oklch(45% 0.085 224.283)"
  white: "#fff"
  gray-50: "oklch(98.5% 0.002 247.839)"
  gray-100: "oklch(96.7% 0.003 264.542)"
  gray-200: "oklch(92.8% 0.006 264.531)"
  gray-400: "oklch(70.7% 0.022 261.325)"
  gray-500: "oklch(55.1% 0.027 264.364)"
  gray-700: "oklch(37.3% 0.034 259.733)"
  gray-800: "oklch(27.8% 0.033 256.848)"
  gray-900: "oklch(21% 0.034 264.665)"
  red-700: "oklch(50.5% 0.213 27.518)"
  red-300: "oklch(80.8% 0.114 19.571)"
  amber-800: "oklch(47.3% 0.137 46.201)"
  amber-200: "oklch(92.4% 0.12 95.746)"
typography:
  headline:
    fontFamily: "Montserrat, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: "2rem"
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Montserrat, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: "1.75rem"
  body:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.25rem"
  label:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: "1rem"
rounded:
  lg: "0.5rem"
  xl: "0.75rem"
  full: "9999px"
spacing:
  "1": "0.25rem"
  "2": "0.5rem"
  "3": "0.75rem"
  "4": "1rem"
  "6": "1.5rem"
components:
  dashboard-action:
    backgroundColor: "transparent"
    textColor: "{colors.cyan-800}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  dashboard-action-dark:
    backgroundColor: "transparent"
    textColor: "{colors.cyan-200}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  dashboard-panel:
    backgroundColor: "{colors.white}"
    textColor: "{colors.gray-900}"
    rounded: "{rounded.xl}"
    padding: "1rem"
  dashboard-panel-dark:
    backgroundColor: "{colors.gray-800}"
    textColor: "{colors.gray-100}"
    rounded: "{rounded.xl}"
    padding: "1rem"
  input:
    rounded: "{rounded.lg}"
    padding: "0.5rem 0.75rem"
  scope-navigation:
    textColor: "{colors.cyan-800}"
    typography: "{typography.body}"
  birthday-today:
    backgroundColor: "{colors.cyan-50}"
    padding: "1rem"
---

# Design System: Rondo Club dashboard

## Overview

This records the existing Rondo visual system as used by the role dashboard. It is a compact Dutch operational interface with cyan accents, Montserrat headings, neutral panels and paired light/dark treatments; it does not establish a new brand identity.

**Key Characteristics:**
- Compact text hierarchy with generous separation between sections.
- Thin borders and flat panels that keep operational rows legible.
- Cyan selection cues and explicit written status feedback.

Scope: observed in `src/components/dashboard/RoleDashboard.jsx`, `src/pages/Dashboard.jsx`, `src/components/PersonAvatar.jsx` and `src/index.css`; built-in scales come from Tailwind's theme. This is a source-derived extension record, not a replacement for unrelated screens.

## Colors

Primary electric cyan marks icons, borders and selection; cobalt participates in the incumbent primary-button gradient. Small selected labels and dashboard actions use cyan-800 on light backgrounds, with lighter cyan in dark mode. Today's birthdays receive a pale cyan surface.

Neutral white and gray surfaces separate panels from the page. Secondary text uses gray-500 in light mode and gray-400 in dark mode. Errors and cancellations use red; uncertain or stale data uses amber, always with explanatory text.

**The Paired Theme Rule.** Every dashboard surface and status label has a legible light and dark treatment.

## Typography

Montserrat supplies the heading hierarchy. The incumbent system sans-serif supplies rows, controls and metadata. Page headlines, section titles, body rows and compact labels follow the frontmatter scale; row names often increase to medium or semibold weight without increasing size. Match times use tabular numerals. Plain text headings are the observed dashboard treatment.

## Layout

Sections use a single column with a six-step gap. At the large breakpoint, the dashboard uses twelve columns: fixtures occupy eight and the team panel four when both are visible. Full-width sections span twelve. Birthday entries become two columns at the small breakpoint and three at extra-large. Headers and controls wrap; panels and list entries generally use four-step padding, with three-step vertical row spacing. These are dashboard compositions, not mandatory layouts for other pages.

## Elevation & Depth

Dashboard panels are flat, with thin neutral borders and row dividers. Shared inputs retain their subtle shadow. The incumbent primary and secondary buttons lift slightly on hover; primary buttons also add a cyan-tinted shadow. Shared controls transition over 200ms, with reduced-motion support in the base stylesheet.

## Shapes

Panels use the extra-large radius; shared buttons and inputs use the large radius. Avatars are circular, with the dashboard using a compact 32px size. Match rows use horizontal separators and native disclosure controls.

## Components

- **Dashboard actions:** outlined cyan controls, darker text on light surfaces, compact labels, rounded corners and visible focus treatment.
- **Primary save action:** reuses the existing cyan-to-cobalt gradient button and pending/disabled behavior; its gradient belongs to the sidecar because frontmatter cannot encode background images.
- **Inputs:** rounded bordered fields with a small shadow and cyan focus treatment. The team selector uses intrinsic width capped at its container.
- **Scope navigation:** text buttons above a shared baseline; the selected scope gains a cyan bottom border, stronger weight and theme-appropriate cyan text.
- **Panels:** flat white or dark gray surfaces. Today’s birthday entry uses a tonal fill inside the shared panel.
- **Match disclosures:** time, team names and compact assignment metadata remain visible; expansion reveals additional fields. Unknown values stay written explicitly.
- **State feedback:** loading, empty, setup, failed and stale states have distinct text. Missing team assignments include administrator setup guidance; cancellation-feed failures remain visible independently of the selected match scope.

## Do's and Don'ts

- Do preserve paired light and dark colors for panels, labels and status messages.
- Do use darker cyan for small action text on light backgrounds.
- Do distinguish unavailable or stale information from a verified empty result.

- Don't promote the dashboard's block order into a rule for every Rondo page.
- Don't replace explanatory error or setup text with color alone.
- Don't import the legacy decorative card treatment into the dashboard's flat panels by default.
