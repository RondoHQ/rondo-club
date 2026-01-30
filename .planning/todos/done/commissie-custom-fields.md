# Todo: Custom Fields for Commissies

## Description

Add support for custom fields on Commissies, similar to how we have "Ledenvelden" for persons and "Teamvelden" for teams.

## Context

Currently the Custom Fields settings page has two tabs:
- Ledenvelden (person custom fields)
- Teamvelden (team custom fields)

Commissies should also support custom fields to allow admins to add metadata specific to committees.

## Implementation Notes

- Add a new tab "Commissievelden" to the Custom Fields settings page
- The commissie post type is `commissie`
- Follow the same pattern as person/team custom fields
- Backend: Extend custom fields API to support `commissie` post type
- Frontend: Add tab to CustomFields.jsx

## Created

2026-01-29
