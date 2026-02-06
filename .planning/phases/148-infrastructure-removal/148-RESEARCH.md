# Phase 148: Infrastructure Removal - Research

**Researched:** 2026-02-06
**Domain:** WordPress custom post type and subsystem removal
**Confidence:** HIGH

## Summary

Phase 148 removes the obsolete Important Dates subsystem (CPT: `important_date`, taxonomy: `date_type`, and all associated frontend/backend code). Research confirms WordPress native deletion approaches using WP-CLI, established removal order (data first, then code), and thorough dependency tracking patterns.

User decisions from CONTEXT.md require: (1) WP-CLI deletion commands for posts and taxonomy terms, (2) data-first-then-code removal order, (3) no backups needed (redundant data), (4) complete removal including ACF JSON, navigation items, and documentation updates, and (5) single deploy at end with no redirects.

**Primary recommendation:** Delete all `important_date` posts and `date_type` terms using WP-CLI while CPT still registered, remove method from `class-post-types.php` and `class-taxonomies.php`, delete React components and routes, clean REST API references, remove ACF field group JSON, update documentation, and flush rewrite rules via production WP-CLI (not `delete_option`) to ensure complete cleanup.

## Standard Stack

No new libraries required. Uses existing WordPress and WP-CLI infrastructure.

### Core
| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| WP-CLI | Current | Post/term deletion, rewrite flush | Official WordPress command-line tool, ensures proper cascade deletion |
| WordPress Core | 6.0+ | `unregister_post_type()`, `wp_delete_post()` | Native WordPress functions handle cleanup hooks |

### Supporting
| Tool | Version | Purpose | When to Use |
|------|---------|---------|-------------|
| grep/ripgrep | Current | Code reference discovery | Finding all occurrences of deleted entities |
| Git | Current | Atomic commit of all removals | Single-deploy requirement |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP-CLI | Direct SQL DELETE | SQL bypasses WordPress hooks (comments, meta, taxonomies not cleaned) |
| `flush_rewrite_rules()` | `delete_option('rewrite_rules')` | Both work, but `delete_option` is preferred for removals per sources |
| Manual search | AST parsing tools | AST tools more accurate but overkill for this phase |

**Installation:**
No installation needed. WP-CLI already available on production server.

## Architecture Patterns

### Recommended Removal Order

**Phase 1: Data deletion (while CPT still registered)**
```bash
# Delete all important_date posts (force = permanent deletion)
wp post delete $(wp post list --post_type='important_date' --format=ids) --force

# Delete all date_type taxonomy terms
wp term list date_type --field=term_id | xargs -n1 wp term delete date_type

# Verify deletion
wp post list --post_type='important_date'
wp term list date_type
```

**Phase 2: Backend code removal**
1. Remove CPT registration: `register_important_date_post_type()` method from `class-post-types.php`
2. Remove taxonomy registration: `register_date_type_taxonomy()` and `add_default_date_types()` methods from `class-taxonomies.php`
3. Remove REST API references: filter registration and endpoint handlers in `class-rest-api.php`
4. Remove access control filters: `important_date` from controlled post types in `class-access-control.php`
5. Delete ACF field group: `acf-json/group_important_date_fields.json`
6. Remove utility script: `bin/cleanup-duplicate-dates.php`

**Phase 3: Frontend code removal**
1. Delete components: `src/components/ImportantDateModal.jsx`
2. Delete pages: `src/pages/Dates/DatesList.jsx`
3. Delete hooks: `src/hooks/useDates.js`
4. Remove routes: lazy import and route definition from `src/router.jsx`
5. Remove navigation: "Datums" item from `src/components/layout/Layout.jsx`
6. Clean API client: `getPersonDates()` method from `src/api/client.js`
7. Update Dashboard: Remove date stats card references from `src/pages/Dashboard.jsx`
8. Update PersonDetail: Remove important dates card from `src/pages/People/PersonDetail.jsx`
9. Update document title hook: Remove `/dates` path handling from `src/hooks/useDocumentTitle.js`

**Phase 4: Documentation updates**
Remove mentions from: `docs/data-model.md`, `docs/rest-api.md`, `docs/ical-feed.md`, `docs/reminders.md`, `docs/access-control.md`, `docs/family-tree.md`, `docs/import.md`, `docs/carddav.md`, `docs/api-leden-crud.md`, `docs/multi-user.md`, `docs/php-autoloading.md`

**Phase 5: Rewrite rules cleanup**
```bash
# On production after deploy
ssh -p "$PORT" "$USER@$HOST" "cd $PATH && wp rewrite flush"
```

### Pattern 1: WP-CLI Nested Command Substitution
**What:** Delete all posts of a type by combining `list` and `delete` commands
**When to use:** Bulk deletion of custom post type posts
**Example:**
```bash
# Source: https://developer.wordpress.org/cli/commands/post/delete/
# List posts by type, output as space-separated IDs, pipe to delete with --force
wp post delete $(wp post list --post_type='important_date' --format=ids) --force

# For large datasets (avoid "Argument list too long"):
wp post list --post_type='important_date' --format=ids | xargs -n 100 wp post delete --force
```

### Pattern 2: Find All Code References
**What:** Use grep patterns to find all occurrences before removal
**When to use:** Before deleting any code entity
**Example:**
```bash
# Source: https://github.com/livegrep/livegrep
# Find all references to important_date (case variations)
grep -r "important_date\|ImportantDate\|important-date" --include="*.php" --include="*.js" --include="*.jsx" -n

# Find date_type taxonomy references
grep -r "date_type\|DateType\|date-type" --include="*.php" --include="*.js" --include="*.jsx" -n

# Find route references
grep -r "/dates\|/datums" --include="*.jsx" --include="*.js" -n
```

### Pattern 3: WordPress Unregister Sequence
**What:** Proper unregistration order to avoid orphaned data
**When to use:** Removing custom post types and taxonomies
**Example:**
```php
// Source: https://developer.wordpress.org/reference/functions/unregister_post_type/
// WRONG: Unregister before data deletion leaves orphaned posts
function wrong_approach() {
    unregister_post_type('important_date'); // Can't query anymore!
    // Now can't use WP_Query or WP-CLI to find/delete posts
}

// RIGHT: Delete data first, then remove registration
function right_approach() {
    // 1. Delete all posts via WP-CLI (CPT still registered)
    // 2. THEN remove registration code
    // 3. Deploy and flush rewrite rules
}
```

### Anti-Patterns to Avoid
- **Direct SQL deletion:** Bypasses WordPress hooks, leaves orphaned meta/taxonomy data
- **Partial removal:** Leaving dead code "just in case" creates maintenance burden
- **Premature unregistration:** Deleting registration code before data prevents WP-CLI queries
- **Using `flush_rewrite_rules()` in code:** Performance issue; use WP-CLI manually after deploy
- **Forgetting ACF JSON:** Field group files persist and cause admin UI clutter

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Bulk post deletion | Custom SQL DELETE FROM wp_posts | `wp post delete $(wp post list ...)` | WP-CLI handles meta, comments, taxonomy relationships, hooks |
| Find code dependencies | Manual file-by-file review | `grep -r` or `git grep` | Regex search finds all variations (camelCase, snake_case, kebab-case) |
| Taxonomy term deletion | Direct `$wpdb->delete()` | `wp term delete` | WordPress manages term relationships, counts, caches |
| Rewrite rules flush | Custom transient/cache clearing | `wp rewrite flush` | WordPress handles complex rewrite rule regeneration |

**Key insight:** WordPress has mature deletion APIs that handle cascading relationships. Direct database manipulation seems faster but creates data integrity issues (orphaned meta, broken term counts, stale caches).

## Common Pitfalls

### Pitfall 1: Argument List Too Long Error
**What goes wrong:** `wp post delete $(wp post list ...)` fails with "Argument list too long" for 1000+ posts
**Why it happens:** Shell command line length limits (ARG_MAX, typically ~2MB)
**How to avoid:** Use `xargs` to batch deletions
**Warning signs:** Error message "Argument list too long" when running nested WP-CLI commands

**Solution:**
```bash
# Source: https://dev.to/ko31/how-to-bulk-delete-huge-post-data-in-wordpress-16e8
# Delete in batches of 100
wp post list --post_type='important_date' --format=ids | xargs -n 100 wp post delete --force
```

### Pitfall 2: Orphaned Rewrite Rules
**What goes wrong:** Old CPT rewrite rules persist, causing 404 errors or unexpected redirects
**Why it happens:** WordPress caches rewrite rules in `wp_options` table; removing CPT code doesn't auto-flush
**How to avoid:** Manually flush rewrite rules via WP-CLI after deploy
**Warning signs:** 404 errors on other routes, unexpected behavior on permalinks

**Solution:**
```bash
# Source: https://developer.wordpress.org/reference/functions/flush_rewrite_rules/
# After deploying code removal
wp rewrite flush

# Alternative (some sources prefer this for removals)
wp option delete rewrite_rules
```

### Pitfall 3: Forgetting ACF Field Group JSON
**What goes wrong:** ACF field group file remains in `acf-json/`, causing admin UI to show "Important Date Fields" group
**Why it happens:** ACF loads field groups from JSON files; deleting CPT doesn't remove field definitions
**How to avoid:** Delete `acf-json/group_important_date_fields.json` as part of cleanup
**Warning signs:** ACF admin shows field groups for non-existent post types

**Solution:**
```bash
rm acf-json/group_important_date_fields.json
```

### Pitfall 4: Frontend Build Not Updated
**What goes wrong:** Old React components still bundled in `dist/`, causing JS errors or dead UI
**Why it happens:** Vite doesn't auto-clean old chunks; removing source doesn't remove built assets
**How to avoid:** Run `npm run build` before deploy to regenerate clean dist/
**Warning signs:** JS errors in browser console referencing deleted components

**Solution:**
```bash
# Always build before deploy
npm run build
bin/deploy.sh
```

### Pitfall 5: Incomplete Dependency Removal
**What goes wrong:** Unused imports remain, causing linter errors or bundle bloat
**Why it happens:** Removing component usage but not the import statement
**How to avoid:** Use grep to find all import/require statements for deleted entities
**Warning signs:** ESLint errors about unused imports, larger bundle size than expected

**Solution:**
```bash
# Find all imports of deleted components
grep -r "ImportantDateModal\|DatesList\|useDates" --include="*.jsx" --include="*.js" -n

# Remove import lines from each file
```

## Code Examples

Verified patterns from official sources:

### WP-CLI Post Deletion (Official)
```bash
# Source: https://developer.wordpress.org/cli/commands/post/delete/
# Basic deletion (moves to trash)
wp post delete 123

# Permanent deletion with --force
wp post delete 123 --force

# Bulk delete all posts of a type
wp post delete $(wp post list --post_type='important_date' --format=ids) --force

# Large dataset handling (batched)
wp post list --post_type='important_date' --format=ids | xargs -n 100 wp post delete --force
```

### WP-CLI Taxonomy Term Deletion (Official)
```bash
# Source: https://developer.wordpress.org/cli/commands/term/delete/
# Delete single term by ID
wp term delete date_type 15

# Delete single term by slug
wp term delete date_type birthday --by=slug

# Delete all terms in taxonomy
wp term list date_type --field=term_id | xargs -n1 wp term delete date_type

# Verify deletion
wp term list date_type
```

### WordPress Unregister Post Type (Official)
```php
// Source: https://developer.wordpress.org/reference/functions/unregister_post_type/
// Remove from class-post-types.php
public function register_post_types() {
    $this->register_person_post_type();
    $this->register_team_post_type();
    $this->register_commissie_post_type();
    // DELETE THIS LINE:
    // $this->register_important_date_post_type();
    $this->register_todo_statuses();
    // ... rest
}

// DELETE THIS ENTIRE METHOD:
// private function register_important_date_post_type() { ... }
```

### WordPress Unregister Taxonomy (Official)
```php
// Source: https://developer.wordpress.org/reference/functions/register_taxonomy/
// Remove from class-taxonomies.php
public function register_taxonomies() {
    $this->register_person_label_taxonomy();
    $this->register_team_label_taxonomy();
    $this->register_commissie_label_taxonomy();
    $this->register_relationship_type_taxonomy();
    // DELETE THIS LINE:
    // $this->register_date_type_taxonomy();
    $this->register_seizoen_taxonomy();
}

// DELETE THESE METHODS:
// private function register_date_type_taxonomy() { ... }
// private function add_default_date_types() { ... }
```

### React Router Route Removal (Official)
```javascript
// Source: https://reactrouter.com/
// Remove from src/router.jsx
// DELETE THIS IMPORT:
// const DatesList = lazy(() => import('@/pages/Dates/DatesList'));

// DELETE THIS ROUTE:
// {
//   path: 'dates',
//   element: (
//     <Suspense fallback={<LoadingSpinner />}>
//       <DatesList />
//     </Suspense>
//   ),
// },
```

### Navigation Item Removal
```javascript
// Remove from src/components/layout/Layout.jsx
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Personen', href: '/people', icon: Users },
  { name: 'Teams', href: '/teams', icon: Shield },
  { name: 'Commissies', href: '/commissies', icon: Briefcase },
  // DELETE THIS LINE:
  // { name: 'Datums', href: '/dates', icon: Calendar, requiresUnrestricted: true },
  // ... rest
];
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Direct SQL for bulk deletion | WP-CLI nested commands | WP-CLI v1.0+ (2015) | Proper cascade deletion, hooks fired |
| Manual file searching | `git grep` or `ripgrep` | Git 1.7.9+ (2012) | Git-aware searching, respects .gitignore |
| `flush_rewrite_rules()` in code | WP-CLI manual flush | Best practice since ~2014 | Avoids performance penalty on every page load |
| Keep "just in case" code | Complete removal | Modern practice (2020+) | Cleaner codebase, better tree shaking |

**Deprecated/outdated:**
- **Custom SQL deletion queries:** Use WP-CLI, respects WordPress data integrity
- **`get_posts()` + loop + `wp_delete_post()`:** Use WP-CLI bulk commands, avoids timeouts
- **Leaving unused imports:** Modern bundlers tree-shake better with clean imports

## Open Questions

Things that couldn't be fully resolved:

1. **Production data volume unknown**
   - What we know: WP-CLI handles large datasets with xargs batching
   - What's unclear: Exact number of important_date posts in production
   - Recommendation: Use `wp post list --post_type='important_date' --format=count` to check before deletion; use xargs batching if >1000 posts

2. **Test coverage for removed functionality**
   - What we know: Multiple test files reference `important_date` (CptCrudTest, SmokeTest, UserIsolationTest, SearchDashboardTest, RelationshipsSharesTest)
   - What's unclear: Which specific test methods need updating vs. full removal
   - Recommendation: Grep test files for `important_date` references, remove assertions/fixture data during plan execution

3. **iCal feed dependencies**
   - What we know: `class-ical-feed.php` likely references important dates (file too large to read fully)
   - What's unclear: Exact scope of important_date integration in iCal feed
   - Recommendation: Grep for `important_date` in class-ical-feed.php and remove feed generation logic if present

4. **Reminders system integration**
   - What we know: `class-reminders.php` line 662 references `important_date`
   - What's unclear: Whether reminders system becomes obsolete or continues with birthdate-only functionality
   - Recommendation: Check reminders system during planning; if only used for important dates, entire reminders subsystem may need removal or significant refactoring to work with person birthdate field

## Sources

### Primary (HIGH confidence)
- [WP-CLI Post Delete Command](https://developer.wordpress.org/cli/commands/post/delete/) - Official WP-CLI documentation
- [WP-CLI Term Delete Command](https://developer.wordpress.org/cli/commands/term/delete/) - Official WP-CLI documentation
- [WordPress unregister_post_type() Function](https://developer.wordpress.org/reference/functions/unregister_post_type/) - Official WordPress Codex
- [WordPress flush_rewrite_rules() Function](https://developer.wordpress.org/reference/functions/flush_rewrite_rules/) - Official WordPress Codex
- [React Router Official Documentation](https://reactrouter.com/) - Official routing library docs

### Secondary (MEDIUM confidence)
- [Using WP-CLI to Delete Posts Containing a Specific Metadata Value](https://shawnhooper.ca/2026/01/17/wp-cli-delete-posts-by-metadata/) - Recent 2026 article on WP-CLI deletion patterns
- [Delete all posts of a post type from the WordPress database](https://aurooba.com/delete-all-posts-of-a-post-type-from-the-wordpress-database/) - Detailed WP-CLI deletion guide
- [How to bulk delete huge post data in WordPress](https://dev.to/ko31/how-to-bulk-delete-huge-post-data-in-wordpress-16e8) - Batching strategies for large datasets
- [Bulk delete posts in WordPress with WP CLI (+variants)](https://remkusdevries.com/wp-cli/bulk-delete-posts-in-wordpress-with-wp-cli-variants/) - WP-CLI deletion variants
- [WP CLI Delete All Terms in Taxonomy](https://freelancewp.guide/wp-cli-delete-all-terms-in-taxonomy/) - Taxonomy cleanup guide

### Tertiary (LOW confidence)
- [Custom Post Type Cleanup Plugin](https://wordpress.com/plugins/custom-post-type-cleanup) - Alternative plugin approach (not used, but validates manual approach)
- [whatwedo ACF Cleaner Plugin](https://wordpress.org/plugins/whatwedo-acf-cleaner/) - ACF cleanup plugin (not used, but confirms ACF JSON persistence issue)
- [Git Grep - Search Through Your Codebase Like a Pro](https://www.compilenrun.com/docs/devops/git/git-advanced-features/git-grep/) - Code search patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WP-CLI and WordPress Core are proven tools with official documentation
- Architecture: HIGH - Removal order verified with multiple WordPress sources, WP-CLI patterns documented officially
- Pitfalls: HIGH - Common errors documented across multiple sources, solutions verified with official WordPress docs

**Research date:** 2026-02-06
**Valid until:** 2026-03-06 (30 days - WordPress practices are stable, WP-CLI commands rarely change)
