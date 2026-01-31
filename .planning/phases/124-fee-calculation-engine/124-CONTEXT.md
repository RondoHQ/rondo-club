# Phase 124: Fee Calculation Engine - Context

**Gathered:** 2026-01-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Calculate correct base fees based on member type and age group. This phase implements the fee calculation logic — determining which fee category applies to each member based on their leeftijdsgroep, team membership, and function. Family discounts and pro-rata are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Age group parsing
- JO6-JO7 → Mini fee
- JO8-JO11 → Pupil fee
- JO12-JO19 → Junior fee
- JO23 or "Senioren" → Senior fee (both values exist, treat as equivalent)
- JO20-JO22 do not exist in the system — no handling needed
- Strip " Meiden" suffix before matching (space-separated)
- Strip " Vrouwen" suffix — only appears as "Senioren Vrouwen" which maps to Senioren

### Member type detection
- Priority order: Youth > Recreant > Donateur
- Youth: Members with valid leeftijdsgroep (JO6-JO19) get age-based fee
- Recreant: Senioren who play in teams with "Recreant" or "Walking Football" in name (case-insensitive)
- Donateur: Members with Function = "Donateur"
- If member has both age group AND Donateur function → playing fee wins

### Team-based fee determination
- Recreant fee requires: leeftijdsgroep is Senioren/JO23 AND team name contains "recreant" or "walking football" (case-insensitive)
- Regular Senioren (not in recreant/walking football team) get Senior fee
- Multiple teams: highest applicable fee wins (regular senior > recreant)

### Edge case handling
- No leeftijdsgroep + no team + not Donateur → exclude completely from calculations
- Unrecognized leeftijdsgroep value → flag for review (show but marked as unknown category)
- No current team assignment → exclude (considered inactive)
- Multiple teams with different fee implications → highest fee wins

### Calculation triggers
- Calculations run on-demand when viewing fee list or member page
- Show calculated fee on individual member detail page
- Season snapshot model: fees locked at season start (July 1)
- Season runs July 1 - June 30
- Override option to clear a season and recalculate all fees

### Claude's Discretion
- Internal calculation service architecture
- How to efficiently query members with teams
- Season snapshot storage mechanism
- Performance optimization for large member lists

</decisions>

<specifics>
## Specific Ideas

- Season snapshot allows locking fees but with admin ability to recalculate if settings were wrong
- Fee shown on individual member pages, not just list view

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 124-fee-calculation-engine*
*Context gathered: 2026-01-31*
