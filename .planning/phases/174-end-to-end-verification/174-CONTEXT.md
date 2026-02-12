# Phase 174: End-to-End Verification - Context

**Gathered:** 2026-02-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Validate the full export/anonymize/import pipeline produces data that works throughout the Rondo Club application. Export from production, import to demo site, verify all pages render correctly, then ship v24.0.

</domain>

<decisions>
## Implementation Decisions

### Target environment
- **Export source:** Always from production (rondo.svawc.nl) — this is where real data lives
- **Import target:** Demo site only (demo.rondo.club) — production stays untouched with real member data
- **Demo site purpose:** Permanently runs demo data — always anonymized, never real data
- **Deploy:** Create a new `bin/deploy-demo.sh` script that deploys to demo.rondo.club using demo SSH credentials (u26-b0fnaayuzqqg)
- **Pipeline flow:** Export on production → pull fixture locally → deploy code to demo → import on demo with --clean

### Demo site banner
- Sitewide top banner on the demo site, approximately 20-30px high
- Bright warning style: yellow/orange background, text like "DEMO OMGEVING — Dit is geen echte data"
- Must be obnoxiously obvious — impossible to mistake demo for production
- Only shows on the demo site, not on production

### Claude's Discretion
- Verification page order and thoroughness
- Bug fix approach when verification reveals issues
- Exact banner implementation (PHP header vs React component)
- Shipping criteria details (changelog format, version bump)

</decisions>

<specifics>
## Specific Ideas

- The existing plans (174-01, 174-02) reference stadion.svawc.nl URLs — these must be updated to use demo.rondo.club
- The existing plans run import on production with --clean — this must be changed to run on demo site only
- User wants to verify on demo.rondo.club, not production

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 174-end-to-end-verification*
*Context gathered: 2026-02-12*
