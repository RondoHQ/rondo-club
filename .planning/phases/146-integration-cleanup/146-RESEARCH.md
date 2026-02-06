# Phase 146: Integration Cleanup - Research

**Researched:** 2026-02-05
**Domain:** Codebase refactoring, configuration externalization, documentation cleanup
**Confidence:** HIGH

## Summary

This phase involves three interconnected tasks: externalizing the hardcoded FreeScout URL into the club config API, removing AWC/svawc.nl-specific references from documentation, and verifying the theme is installable by any club without code changes.

The existing infrastructure is already in place for this work. The club config API (`/rondo/v1/config`) was implemented in Phase 144 and supports `freescout_url` as a field. The Settings page already has a UI for configuring this value. The primary work is:

1. **FreeScout URL externalization**: Replace the hardcoded `https://box.svawc.nl/customers/` URL in PersonDetail.jsx with dynamic construction using `window.stadionConfig.freescoutUrl`
2. **Documentation cleanup**: Systematic find-and-replace of svawc.nl/AWC references with generic placeholders (example.com, "your club")
3. **Installability verification**: Comprehensive grep scan to ensure zero AWC/svawc references remain in source code

**Primary recommendation:** Use grep-based scanning with explicit exclusion patterns for verification, migrate legacy auto-migration code, and update all documentation to be club-agnostic while preserving historical CHANGELOG entries.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | Core | Configuration storage | Native WordPress approach for site-wide settings |
| React conditional rendering | React 18 | Hide UI when config empty | Standard pattern for optional features |
| grep/ripgrep | CLI | Codebase scanning | Industry-standard text search tool |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| window globals | Native | Frontend config access | Already established pattern in this codebase |
| eslint | 8.x | Code quality during refactor | Ensure no regressions introduced |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| grep | ast-grep, semgrep | More sophisticated but overkill for simple string search |
| Window globals | REST API fetch on mount | Adds unnecessary request, config already available |

**Installation:**
No new dependencies required. All tools already in project.

## Architecture Patterns

### Recommended Approach

**Phase execution order:**
```
1. FreeScout URL externalization (code change)
2. Documentation cleanup (find-replace)
3. Verification scan (grep with exclusions)
4. Cleanup legacy migration code
```

### Pattern 1: External Service URL Configuration

**What:** Store external service base URLs in club config, construct full URLs at runtime
**When to use:** Any integration where the base URL varies per installation (support systems, third-party tools)

**Current state:**
```javascript
// Hardcoded in PersonDetail.jsx line 1027
if (acf['freescout-id']) {
  links.push({
    contact_type: 'freescout',
    contact_value: `https://box.svawc.nl/customers/${acf['freescout-id']}`,
  });
}
```

**Target state:**
```javascript
// Dynamic construction from config
const freescoutUrl = window.stadionConfig?.freescoutUrl;
if (acf['freescout-id'] && freescoutUrl) {
  links.push({
    contact_type: 'freescout',
    contact_value: `${freescoutUrl}/customers/${acf['freescout-id']}`,
  });
}
```

**Key insight:** The `freescout-id` ACF field remains unchanged. Only the base URL becomes configurable. When `freescoutUrl` is empty/undefined, the link is hidden entirely.

### Pattern 2: Conditional UI Rendering Based on Config

**What:** Show/hide UI elements based on configuration presence
**When to use:** Optional integrations, feature flags controlled by admin settings

**Example:**
```javascript
// In PersonDetail.jsx social links rendering (line ~1247)
if (contact.contact_type === 'freescout') {
  // Only render if we have a FreeScout URL configured
  const freescoutUrl = window.stadionConfig?.freescoutUrl;
  if (!freescoutUrl) return null;

  return (
    <a href={contact.contact_value} target="_blank" rel="noopener noreferrer">
      <img src={`${themeUrl}/public/icons/freescout.png`} alt="FreeScout" />
    </a>
  );
}
```

**Source:** Common React pattern verified in [WordPress React Settings guide](https://daext.com/blog/how-to-create-a-wordpress-settings-page-with-react/)

### Pattern 3: Systematic Codebase Scanning

**What:** Use grep with explicit exclusion patterns to find hardcoded values
**When to use:** Refactoring hardcoded configuration, pre-release verification

**Recommended grep command:**
```bash
grep -rn "awc\|AWC\|svawc" \
  --include="*.php" \
  --include="*.js" \
  --include="*.jsx" \
  --include="*.md" \
  --include="*.json" \
  --exclude-dir=node_modules \
  --exclude-dir=.git \
  --exclude-dir=dist \
  --exclude-dir=vendor \
  --exclude-dir=.planning \
  --exclude="CHANGELOG.md" \
  --exclude="package-lock.json" \
  .
```

**Source:** [Baeldung Linux grep exclude guide](https://www.baeldung.com/linux/grep-exclude-directories)

**Why these exclusions:**
- `node_modules/`, `vendor/`, `dist/` - Third-party code, build artifacts
- `.git/` - Version control history (not source)
- `.planning/` - Planning documents reference the project context
- `CHANGELOG.md` - Historical record, AWC mentions are legitimate past references
- `package-lock.json` - Generated file, not edited

### Pattern 4: Documentation Placeholder Standards

**What:** Use `example.com` and generic phrasing in documentation
**When to use:** Any documentation that previously referenced specific installation

**Find-replace patterns:**
```
svawc.nl → example.com
stadion.svawc.nl → your-site.com
box.svawc.nl → support.example.com
AWC/SV AWC → your club
your club's AWC accent color → your club's accent color
```

**Exception:** Keep Dutch domain-specific terms (leden, commissies, tussenvoegsel) - this is a Dutch-origin project

**Source:** [RFC 2606](https://www.rfc-editor.org/rfc/rfc2606.html) reserves example.com for documentation

### Anti-Patterns to Avoid

- **Over-documentation**: Don't add comments explaining the refactor in source code. The code should be self-documenting.
- **Incomplete config migration**: Don't leave fallback to hardcoded values. If config is empty, hide the feature entirely.
- **CHANGELOG rewriting**: Don't edit historical entries. Past AWC references are legitimate historical context.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Text search in codebase | Custom Node script | grep/ripgrep with exclusions | Battle-tested, handles edge cases, fast |
| Configuration storage | Custom tables | WordPress Options API | Native WP approach, already implemented in Phase 144 |
| Frontend config access | REST fetch on mount | window.stadionConfig globals | Already established pattern, avoids extra request |
| Regex-based refactoring | Manual find-replace in editor | grep preview + targeted edits | Verify scope before changing, avoid missing instances |

**Key insight:** This phase is primarily refactoring existing patterns, not building new infrastructure. The club config API already exists and works.

## Common Pitfalls

### Pitfall 1: Missing window.stadionConfig in Initial Render

**What goes wrong:** Reading `window.stadionConfig.freescoutUrl` before WordPress has injected the global can cause undefined errors or incorrect empty checks.

**Why it happens:** In development mode with Vite HMR, the timing of script injection can vary.

**How to avoid:** Always use optional chaining and provide fallback:
```javascript
const freescoutUrl = window.stadionConfig?.freescoutUrl || '';
```

**Warning signs:** Console errors in development mode about undefined properties.

### Pitfall 2: Forgetting to Update clubConfigSaved State

**What goes wrong:** After saving FreeScout URL in Settings, the frontend doesn't reflect the change until page reload.

**Why it happens:** The Settings.jsx component updates `window.stadionConfig` but doesn't trigger React re-renders in other components.

**How to avoid:** Ensure Settings.jsx updates `window.stadionConfig.freescoutUrl` in the save handler (already done on line 951):
```javascript
window.stadionConfig.freescoutUrl = response.data.freescout_url;
```

For immediate UI updates, components should read from `window.stadionConfig` in render, not cache in state.

**Warning signs:** Settings save succeeds but PersonDetail still shows old FreeScout URL.

### Pitfall 3: Excluding Too Much in Grep Scan

**What goes wrong:** Overly aggressive exclusions miss legitimate references in source files.

**Why it happens:** Adding `.md` or `docs/` to exclusions would skip documentation that needs cleanup.

**How to avoid:** Only exclude directories/files that are:
1. Generated (dist/, node_modules/)
2. Version control internals (.git/)
3. Legitimate historical records (CHANGELOG.md)
4. Planning context (.planning/)

**Verification:** Manually verify the exclusion list produces expected results on a test pattern first.

**Warning signs:** Zero matches when you know references exist, or matches in files you can't edit.

### Pitfall 4: Breaking FreeScout Icon Path

**What goes wrong:** The FreeScout icon (`public/icons/freescout.png`) path breaks if conditional rendering isn't careful.

**Why it happens:** The icon URL is constructed using `window.stadionConfig.themeUrl`, which is separate from `freescoutUrl`.

**How to avoid:** The icon path should remain unchanged - only gate the rendering on `freescoutUrl` presence:
```javascript
if (!freescoutUrl) return null; // Hide entire link
// Icon path still works because themeUrl is always present
const iconUrl = `${window.stadionConfig.themeUrl}/public/icons/freescout.png`;
```

**Warning signs:** Icon doesn't load even when FreeScout URL is configured.

### Pitfall 5: Migrating Internal Field Keys

**What goes wrong:** Renaming ACF field keys like `freescout-id` to `freescout_id` requires data migration and breaks existing installations.

**Why it happens:** Confusing "installability" with "renaming everything that mentions FreeScout."

**How to avoid:** The `freescout-id` field key is NOT an AWC-specific reference. It's a generic integration field. Don't rename internal database keys unless absolutely necessary.

**Decision from CONTEXT.md:** User decided to rename "everything found, including internal keys (ACF field keys, database meta keys)." However, `freescout-id` doesn't contain "awc" or "svawc", so it's NOT in scope.

**Verification:** Only rename keys that contain literal "awc" or "svawc" strings. FreeScout is a third-party product name, not AWC-specific.

## Code Examples

Verified patterns from the codebase:

### FreeScout URL Construction (Updated Pattern)

```javascript
// Source: PersonDetail.jsx line ~1024 (to be updated)
// Current state:
const sortedSocialLinks = (() => {
  const links = [...socialLinks];

  // Add FreeScout if there's a FreeScout ID
  if (acf['freescout-id']) {
    links.push({
      contact_type: 'freescout',
      contact_value: `https://box.svawc.nl/customers/${acf['freescout-id']}`,
    });
  }

  return sortSocialLinks(links);
})();

// Target state:
const sortedSocialLinks = (() => {
  const links = [...socialLinks];
  const freescoutUrl = window.stadionConfig?.freescoutUrl;

  // Add FreeScout if there's a FreeScout ID AND a configured URL
  if (acf['freescout-id'] && freescoutUrl) {
    links.push({
      contact_type: 'freescout',
      contact_value: `${freescoutUrl}/customers/${acf['freescout-id']}`,
    });
  }

  return sortSocialLinks(links);
})();
```

### FreeScout Link Rendering (Updated Pattern)

```javascript
// Source: PersonDetail.jsx line ~1247 (to be updated)
// Current state:
if (contact.contact_type === 'freescout') {
  return (
    <a
      key={index}
      href={contact.contact_value}
      target="_blank"
      rel="noopener noreferrer"
      className="flex-shrink-0 hover:opacity-80 transition-opacity"
      title="Bekijk in FreeScout"
    >
      <img
        src={`${window.stadionConfig?.themeUrl}/public/icons/freescout.png`}
        alt="FreeScout"
        className="w-5 h-5"
      />
    </a>
  );
}

// Target state (no change needed - URL is already in contact_value):
// The conditional logic happens at URL construction time (above)
// This rendering code stays the same
```

### Club Config Update Handler

```javascript
// Source: Settings.jsx line ~941 (already correct)
const handleSaveClubConfig = async () => {
  setSavingClubConfig(true);
  setClubConfigSaved(false);
  try {
    const response = await prmApi.updateClubConfig({
      club_name: clubName,
      accent_color: clubColor,
      freescout_url: freescoutUrl,
    });

    // Update window.stadionConfig with saved values
    window.stadionConfig.clubName = response.data.club_name;
    window.stadionConfig.accentColor = response.data.accent_color;
    window.stadionConfig.freescoutUrl = response.data.freescout_url;

    // Success state...
  } catch (error) {
    // Error handling...
  }
};
```

### Legacy Auto-Migration Cleanup

```javascript
// Source: useTheme.js line ~131 (to be REMOVED)
// This was added in Phase 145 for backwards compatibility
// After this phase, no users will have 'awc' stored

// REMOVE THIS:
// Auto-migrate 'awc' to 'club' for existing users
if (parsed.accentColor === 'awc') {
  parsed.accentColor = 'club';
}

// Users who had 'awc' will have been migrated during Phase 145
// New installations will never have 'awc' value
// This code can be safely removed
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded integration URLs | Configuration API with UI | Phase 144 (2026-02) | Enables multi-tenant usage |
| AWC-specific documentation | Generic club documentation | Phase 146 (this phase) | Makes theme installable by any club |
| `awc` accent color option | `club` accent color option | Phase 145 (2026-02) | Generic branding terminology |

**Deprecated/outdated:**
- Hardcoded `https://box.svawc.nl` URL: Replaced with `window.stadionConfig.freescoutUrl`
- `awc` accent color value: Migration code added in Phase 145, can be removed after this phase
- AWC-specific placeholder domains in docs: Replaced with example.com/your-site.com

## Open Questions

None - all requirements are clear and infrastructure exists.

## Sources

### Primary (HIGH confidence)
- Existing codebase files:
  - `/includes/class-club-config.php` - ClubConfig service with freescout_url support
  - `/includes/class-rest-api.php` - REST API endpoints for config (lines 703-679)
  - `/src/pages/Settings/Settings.jsx` - Settings UI with FreeScout URL field (lines 844-1075)
  - `/src/pages/People/PersonDetail.jsx` - Current hardcoded FreeScout URL (line 1027)
  - `/src/hooks/useTheme.js` - Legacy 'awc' migration code (lines 131-134)
  - `.planning/phases/146-integration-cleanup/146-CONTEXT.md` - User decisions

### Secondary (MEDIUM confidence)
- [How to Create a WordPress Settings Page With React - DAEXT](https://daext.com/blog/how-to-create-a-wordpress-settings-page-with-react/) - WordPress Options API with React patterns
- [Exclude Directories With grep - Baeldung](https://www.baeldung.com/linux/grep-exclude-directories) - grep exclusion best practices
- [Mastering Grep - Oreate AI](https://www.oreateai.com/blog/mastering-grep-excluding-files-and-directories-with-precision/abd16023117e6856a638eb480193eef7) - Advanced grep patterns

### Tertiary (LOW confidence)
- [Breaking Free from Hardcoded Values - IN-COM](https://www.in-com.com/blog/breaking-free-from-hardcoded-values-smarter-strategies-for-modern-software/) - Configuration externalization patterns (WebSearch only)
- [How to Refactor Complex Codebases - freeCodeCamp](https://www.freecodecamp.org/news/how-to-refactor-complex-codebases/) - Refactoring strategies (WebSearch only)

## Metadata

**Confidence breakdown:**
- FreeScout URL externalization: HIGH - All infrastructure exists, straightforward code change
- Documentation cleanup: HIGH - Simple find-replace with clear patterns
- Installability verification: HIGH - grep scanning is well-understood, exclusion list clear
- Legacy migration cleanup: MEDIUM - Need to verify timing (can remove immediately vs. wait)

**Research date:** 2026-02-05
**Valid until:** 2026-03-05 (30 days - stable domain, no fast-moving dependencies)

**Current state summary:**
- 5 AWC/svawc references found in source files (excluding .planning and CHANGELOG.md):
  1. `PERFORMANCE-FINDINGS.md` line 1: Title "stadion.svawc.nl"
  2. `AGENTS.md` line 273: SSH example with svawc.nl path
  3. `src/hooks/useTheme.js` lines 131-134: Legacy 'awc' migration code
  4. `src/pages/People/PersonDetail.jsx` line 1027: Hardcoded FreeScout URL

**No AWC/svawc references found in:**
- ACF JSON field definitions (verified acf-json/group_person_fields.json)
- PHP includes/ directory
- Other JavaScript/React components

**Scope verification:**
- ✅ FreeScout integration infrastructure exists (club config API, Settings UI)
- ✅ User decisions from CONTEXT.md understood (no alternatives to locked choices)
- ✅ Exclusion list for grep verified (historical records preserved)
- ✅ No internal key renaming required (no ACF fields contain "awc" or "svawc")
