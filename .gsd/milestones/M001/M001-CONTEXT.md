# M001 Context

Button tier system milestone (Phases 212-213). Established a four-tier CSS button hierarchy and rolled it out across all pages and modals in the React SPA.

## Dependencies

- Tailwind CSS v4 with OKLCH brand tokens (shipped in v22.0)
- Existing btn-primary and btn-danger classes in src/index.css

## Key Context

- Button tiers: primary (gradient fill) > secondary (outlined) > tertiary (ghost) > danger (red fill)
- All variants extend a shared `.btn` base class via `@apply`
- Ghost and danger buttons suppress hover lift (reserved for primary/secondary)
- 57 files modified across Finance, Modals, People, Teams, Commissies, Settings, and utility pages
