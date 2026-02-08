# Phase 154: Sync Cleanup - Research

**Researched:** 2026-02-08
**Domain:** Node.js codebase cleanup, SQLite schema migration, dead code removal
**Confidence:** HIGH

## Summary

This phase removes hardcoded role fallback values ("Speler", "Staflid", "Lid") from the rondo-sync codebase. Investigation confirms that Sportlink consistently provides role descriptions in its API responses, making these fallback values dead code. The cleanup involves:

1. **Four implementation files** with hardcoded fallbacks
2. **One utility function** (`determineJobTitleFallback()`) to be completely removed
3. **One database column** (`member_type`) to be dropped from `sportlink_team_members` table
4. **Simplified logic** in `getTeamMemberRole()` to return role_description or null

The user has confirmed through operational experience that Sportlink's API always includes role descriptions for both team members (players/staff) and commissie members. The fallback logic was defensive programming that has never been needed in production.

**Primary recommendation:** Remove all hardcoded fallbacks, simplify role lookup to return Sportlink-provided values only, skip entries with missing role_description and log warnings.

## Standard Stack

This phase works entirely within the existing rondo-sync technology stack:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| better-sqlite3 | latest | SQLite database access | Project standard for sync state tracking |
| Node.js | 18+ | Runtime environment | Required for rondo-sync execution |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| playwright | latest | Web scraping Sportlink | Already used in download-teams-from-sportlink.js |

## Architecture Patterns

### Current Code Structure

The rondo-sync codebase follows a module/CLI hybrid pattern:

```javascript
// Pattern used in all sync scripts
async function runSync(options) {
  /* implementation */
}

module.exports = { runSync };

if (require.main === module) {
  runSync({ verbose: true });
}
```

### Files Requiring Changes

**Location mapping:**

```
/Users/joostdevalk/Code/rondo/rondo-sync/
├── lib/rondo-club-db.js                                    # Database functions
│   ├── Lines 115-131: CREATE TABLE sportlink_team_members
│   ├── Lines 1407-1494: getTeamMemberRole() + fallbacks
│   └── Lines 1526-1539: getTeamMemberCounts() (uses member_type)
│
├── steps/download-teams-from-sportlink.js                  # Team data download
│   ├── Line 175: member_type: 'player'
│   ├── Line 176: || 'Speler' fallback
│   ├── Line 201: member_type: 'staff'
│   └── Line 202: || 'Staflid' fallback
│
├── steps/submit-rondo-club-work-history.js                 # Team work history sync
│   ├── Lines 82-84: determineJobTitleFallback() function
│   ├── Lines 95-103: getJobTitleForTeam() calls fallback
│   └── Line 112: Default parameter jobTitle = 'Speler'
│
└── steps/submit-rondo-club-commissie-work-history.js       # Commissie work history
    ├── Line 55: || 'Lid' fallback
    ├── Line 169: || 'Lid' fallback
    ├── Line 207: || 'Lid' fallback
    └── Line 214: || 'Lid' in log message
```

### Database Schema Change Pattern

The project uses conditional column addition in `initDb()`:

```javascript
// Existing pattern from lib/rondo-club-db.js (lines 242-255)
const memberColumns = db.prepare('PRAGMA table_info(stadion_members)').all();

if (!memberColumns.some(col => col.name === 'person_image_date')) {
  db.exec('ALTER TABLE stadion_members ADD COLUMN person_image_date TEXT');
}
```

**For column removal** (not currently used in codebase):

```javascript
// Pattern to adopt for dropping member_type column
const columns = db.prepare('PRAGMA table_info(sportlink_team_members)').all();

if (columns.some(col => col.name === 'member_type')) {
  // SQLite 3.35.0+ supports DROP COLUMN directly
  db.exec('ALTER TABLE sportlink_team_members DROP COLUMN member_type');
}
```

### Error Handling Pattern for Missing Data

**Current approach** (return fallback):
```javascript
// lib/rondo-club-db.js lines 1484-1494
if (row && row.role_description) {
  return row.role_description;
}
// Fallback to member_type
if (row && row.member_type === 'player') {
  return 'Speler';
}
return null;
```

**New approach** (return null, caller handles):
```javascript
// Simplified: just return role_description or null
if (row && row.role_description) {
  return row.role_description;
}
return null;
```

**Caller handles null** (skip and warn pattern):
```javascript
const jobTitle = getJobTitleForTeam(db, knvb_id, teamName);
if (!jobTitle) {
  logVerbose(`Warning: No role description for ${knvb_id} in team ${teamName}, skipping`);
  continue;
}
```

## Don't Hand-Roll

This phase is about removing code, not adding it. No custom solutions needed.

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| SQLite schema migration | Custom backup/restore logic | SQLite's built-in DROP COLUMN (3.35.0+) | Safe, atomic, handles indexes automatically |
| Missing data handling | Complex fallback chains | Skip + log pattern | Makes data gaps visible, prevents silent corruption |

**Key insight:** Dead code removal is straightforward. The risk is in identifying what's truly dead. User confirmation that Sportlink always provides role_description makes this safe.

## Common Pitfalls

### Pitfall 1: Assuming Defensive Code Is Necessary
**What goes wrong:** Keeping "just in case" fallbacks that never execute clutters the codebase and creates false confidence.
**Why it happens:** Fear of edge cases without validating they exist in production.
**How to avoid:** Trust operational data. User confirms Sportlink always provides role_description.
**Warning signs:** Fallback code that has never logged a warning in production.

### Pitfall 2: Incomplete Dead Code Removal
**What goes wrong:** Removing some hardcoded values but leaving others creates inconsistency.
**Why it happens:** Search terms miss variations (e.g., searching "Speler" misses "'Lid'").
**How to avoid:** Search for ALL three hardcoded values: "Speler", "Staflid", "Lid". Check both code and comments/JSDoc.
**Warning signs:** Mixed behavior where some roles fall back and others don't.

### Pitfall 3: Breaking Caller Assumptions
**What goes wrong:** Changing `getTeamMemberRole()` to return null more often breaks code that assumed it always returns a string.
**Why it happens:** Not checking all call sites when modifying return contract.
**How to avoid:** Grep for all calls to `getTeamMemberRole()`, verify each handles null appropriately.
**Warning signs:** Runtime errors like "Cannot read property 'length' of null".

### Pitfall 4: SQLite Version Assumptions
**What goes wrong:** `DROP COLUMN` fails on older SQLite versions (pre-3.35.0).
**Why it happens:** Assuming production server has latest SQLite.
**How to avoid:** Check column exists before dropping (pattern above). SQLite silently ignores IF EXISTS-style checks.
**Warning signs:** ALTER TABLE errors in production logs.

### Pitfall 5: Losing getTeamMemberCounts() Functionality
**What goes wrong:** Function at lines 1526-1539 queries `member_type` column for player/staff counts.
**Why it happens:** Forgetting to check which code uses the column being dropped.
**How to avoid:** Grep for all uses of `member_type` before dropping. Decide if counts still needed (probably not, since Rondo Club now classifies).
**Warning signs:** Breaking existing reporting or logging that relies on counts.

## Code Examples

### Example 1: Simplified getTeamMemberRole()

**Before** (lines 1475-1495 in lib/rondo-club-db.js):
```javascript
function getTeamMemberRole(db, knvbId, teamName) {
  const stmt = db.prepare(`
    SELECT tm.role_description, tm.member_type
    FROM sportlink_team_members tm
    JOIN stadion_teams t ON tm.sportlink_team_id = t.sportlink_id
    WHERE tm.sportlink_person_id = ?
      AND t.team_name = ? COLLATE NOCASE
  `);
  const row = stmt.get(knvbId, teamName);
  if (row && row.role_description) {
    return row.role_description;
  }
  // Fallback to member_type if no role_description
  if (row && row.member_type === 'player') {
    return 'Speler';
  }
  if (row && row.member_type === 'staff') {
    return 'Staflid';
  }
  return null;
}
```

**After**:
```javascript
function getTeamMemberRole(db, knvbId, teamName) {
  const stmt = db.prepare(`
    SELECT tm.role_description
    FROM sportlink_team_members tm
    JOIN stadion_teams t ON tm.sportlink_team_id = t.sportlink_id
    WHERE tm.sportlink_person_id = ?
      AND t.team_name = ? COLLATE NOCASE
  `);
  const row = stmt.get(knvbId, teamName);
  return row?.role_description || null;
}
```

### Example 2: Remove determineJobTitleFallback()

**Before** (lines 77-84 in steps/submit-rondo-club-work-history.js):
```javascript
/**
 * Determine job title based on KernelGameActivities (fallback only).
 * @param {string} kernelGameActivities - Value from Sportlink
 * @returns {string} - 'Speler' or 'Staflid'
 */
function determineJobTitleFallback(kernelGameActivities) {
  return kernelGameActivities === 'Veld -  Algemeen' ? 'Speler' : 'Staflid';
}
```

**After**: Delete entire function. No replacement needed.

### Example 3: Simplify getJobTitleForTeam()

**Before** (lines 86-103 in steps/submit-rondo-club-work-history.js):
```javascript
function getJobTitleForTeam(db, knvbId, teamName, fallbackKernelGameActivities) {
  // Try to get role from team members table
  const roleFromTeam = getTeamMemberRole(db, knvbId, teamName);
  if (roleFromTeam) {
    return roleFromTeam;
  }
  // Fallback for members not in team data
  return determineJobTitleFallback(fallbackKernelGameActivities);
}
```

**After**:
```javascript
function getJobTitleForTeam(db, knvbId, teamName) {
  // Return role from team members table, or null if not found
  return getTeamMemberRole(db, knvbId, teamName);
}
```

Note: Remove `fallbackKernelGameActivities` parameter from function signature and all call sites.

### Example 4: Download Teams Without member_type

**Before** (lines 172-177 in steps/download-teams-from-sportlink.js):
```javascript
allMembers.push({
  sportlink_team_id: team.sportlink_id,
  sportlink_person_id: personId,
  member_type: 'player',
  role_description: player.RoleFunctionDescription || 'Speler'
});
```

**After**:
```javascript
// Skip members without role description
if (!player.RoleFunctionDescription) {
  logVerbose(`Warning: Player ${personId} in team ${team.team_name} has no role description, skipping`);
  continue;
}

allMembers.push({
  sportlink_team_id: team.sportlink_id,
  sportlink_person_id: personId,
  role_description: player.RoleFunctionDescription
});
```

### Example 5: Commissie Work History Without Fallback

**Before** (line 55 in steps/submit-rondo-club-commissie-work-history.js):
```javascript
function buildWorkHistoryEntry(commissieStadionId, jobTitle, isActive, startDate, endDate) {
  return {
    job_title: jobTitle || 'Lid',
    is_current: isActive,
    start_date: convertDateForACF(startDate),
    end_date: isActive ? '' : convertDateForACF(endDate),
    team: commissieStadionId
  };
}
```

**After**:
```javascript
function buildWorkHistoryEntry(commissieStadionId, jobTitle, isActive, startDate, endDate) {
  // Caller must ensure jobTitle is provided
  if (!jobTitle) {
    throw new Error('jobTitle is required for commissie work history entry');
  }

  return {
    job_title: jobTitle,
    is_current: isActive,
    start_date: convertDateForACF(startDate),
    end_date: isActive ? '' : convertDateForACF(endDate),
    team: commissieStadionId
  };
}
```

Or simpler approach (skip at call site):
```javascript
// At call sites (lines 169, 207)
if (!commissie.role_name) {
  logVerbose(`Warning: Commissie ${commissie.commissie_name} has no role_name, skipping`);
  continue;
}

const entry = buildWorkHistoryEntry(
  commissieStadionId,
  commissie.role_name,  // No fallback
  commissie.is_active !== false,
  commissie.relation_start,
  commissie.relation_end
);
```

### Example 6: Database Migration for member_type

**Pattern** (add to lib/rondo-club-db.js after line 240, in initDb function):
```javascript
// Remove deprecated member_type column (Phase 154)
const teamMemberColumns = db.prepare('PRAGMA table_info(sportlink_team_members)').all();
if (teamMemberColumns.some(col => col.name === 'member_type')) {
  db.exec('ALTER TABLE sportlink_team_members DROP COLUMN member_type');
}
```

**Note:** SQLite 3.35.0+ (released 2021-03-12) supports DROP COLUMN directly. Since better-sqlite3 is set to "latest" in package.json, production server will have sufficient version.

### Example 7: Handling getTeamMemberCounts() After member_type Removal

**Current implementation** (lines 1526-1539):
```javascript
function getTeamMemberCounts(db, sportlinkTeamId) {
  const playerStmt = db.prepare(`
    SELECT COUNT(*) as count FROM sportlink_team_members
    WHERE sportlink_team_id = ? AND member_type = 'player'
  `);
  const staffStmt = db.prepare(`
    SELECT COUNT(*) as count FROM sportlink_team_members
    WHERE sportlink_team_id = ? AND member_type = 'staff'
  `);
  return {
    players: playerStmt.get(sportlinkTeamId)?.count || 0,
    staff: staffStmt.get(sportlinkTeamId)?.count || 0
  };
}
```

**Options:**

**Option A:** Remove function entirely if not used (check grep results):
- Simplest approach
- No replacement needed if function isn't called

**Option B:** Return total count only:
```javascript
function getTeamMemberCount(db, sportlinkTeamId) {
  const stmt = db.prepare(`
    SELECT COUNT(*) as count FROM sportlink_team_members
    WHERE sportlink_team_id = ?
  `);
  return stmt.get(sportlinkTeamId)?.count || 0;
}
```

**Option C:** Classify by role patterns (if counts are truly needed):
```javascript
function getTeamMemberCounts(db, sportlinkTeamId) {
  // Role patterns that indicate players vs staff
  const playerRoles = ['Speler', 'Keeper', 'Doelman'];

  const stmt = db.prepare(`
    SELECT role_description, COUNT(*) as count
    FROM sportlink_team_members
    WHERE sportlink_team_id = ?
    GROUP BY role_description
  `);

  const rows = stmt.all(sportlinkTeamId);
  let players = 0, staff = 0;

  for (const row of rows) {
    if (playerRoles.includes(row.role_description)) {
      players += row.count;
    } else {
      staff += row.count;
    }
  }

  return { players, staff };
}
```

**Recommendation:** Check if function is used. If not, remove it (Option A). The player/staff distinction is now handled by Rondo Club's configured role settings, not rondo-sync.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Rondo-sync classifies roles as player/staff | Rondo Club classifies via configured settings | Phase 152-153 (v19.x) | Sync can pass through raw role descriptions |
| Hardcoded fallbacks for missing roles | Skip entries with missing data + log warning | Phase 154 | Makes data quality issues visible |
| member_type column in sync database | No classification in sync layer | Phase 154 | Simplifies schema, reduces redundancy |

**Deprecated/outdated:**
- `determineJobTitleFallback()` function: Never needed in production, Sportlink always provides role descriptions
- `member_type` column: Classification now happens in Rondo Club, not sync layer
- Default parameter `jobTitle = 'Speler'`: Makes missing data invisible, should fail explicitly instead

## Open Questions

1. **Is getTeamMemberCounts() actually used?**
   - What we know: Function exists at lines 1526-1539, queries member_type column
   - What's unclear: Whether any code actually calls this function
   - Recommendation: Grep for `getTeamMemberCounts` across codebase. If unused, remove function entirely. If used, implement Option B or C above.

2. **Should we validate Sportlink always provides role_description?**
   - What we know: User confirms from operational experience it's always present
   - What's unclear: Whether to add monitoring/alerting if assumption breaks
   - Recommendation: The "skip and log warning" approach provides sufficient visibility. If role_description is missing, logs will show it and sync continues for other members.

3. **Does production use SQLite 3.35.0+?**
   - What we know: better-sqlite3 "latest" in package.json, 3.35.0 released 2021-03-12
   - What's unclear: Actual SQLite version on production server
   - Recommendation: Migration code checks column existence before dropping. If DROP COLUMN fails on old SQLite, error will be clear in logs. Risk is LOW given package.json uses latest.

## Sources

### Primary (HIGH confidence)
- Direct code inspection: /Users/joostdevalk/Code/rondo/rondo-sync/lib/rondo-club-db.js
- Direct code inspection: /Users/joostdevalk/Code/rondo/rondo-sync/steps/download-teams-from-sportlink.js
- Direct code inspection: /Users/joostdevalk/Code/rondo/rondo-sync/steps/submit-rondo-club-work-history.js
- Direct code inspection: /Users/joostdevalk/Code/rondo/rondo-sync/steps/submit-rondo-club-commissie-work-history.js
- User context: CONTEXT.md confirming Sportlink always provides role descriptions

### Secondary (MEDIUM confidence)
- [SQLite ALTER TABLE documentation](https://www.sqlite.org/lang_altertable.html) - DROP COLUMN restrictions and behavior

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing project dependencies (better-sqlite3, Node.js)
- Architecture: HIGH - All code locations identified through grep and direct inspection
- Pitfalls: HIGH - Based on common refactoring risks and code inspection of dependencies

**Research date:** 2026-02-08
**Valid until:** 2026-03-08 (30 days - stable cleanup work, Node.js and SQLite patterns are mature)
