# M001: Button Tier System & Sitewide Rollout

**Vision:** Establish a clear four-tier button hierarchy (primary → secondary → tertiary → danger) and apply it consistently across all pages, eliminating ad-hoc inline color overrides.

## Success Criteria

- Four-tier CSS button system defined in src/index.css
- All pages and modals use only btn-primary/secondary/tertiary/danger classes
- No inline color overrides on buttons anywhere in the codebase


## Slices

- [x] **S01: Button Css System** `risk:medium` `depends:[]`
  > After this: Define the four-tier button CSS system in src/index.
- [x] **S02: Sitewide Rollout** `risk:medium` `depends:[S01]`
  > After this: Apply correct button tier hierarchy to all Finance-related pages and components.
