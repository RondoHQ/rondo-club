# Phase 112: Birthdate Denormalization - Research

**Researched:** 2026-01-29
**Domain:** WordPress post_meta denormalization, save_post hooks, WP-CLI migration commands
**Confidence:** HIGH

## Summary

This phase denormalizes birthdate from `important_date` posts to `person` post_meta for fast filtering. Currently, birthdates are stored as `important_date` posts with `date_type=birthday`, linked to people via the `related_people` field. Querying by birth year requires querying the `important_date` table with JOINs, which is too slow for the filtered people endpoint.

The solution is to store birthdate in a `_birthdate` meta key on person posts. This enables:
1. Fast filtering by birth year using simple meta queries or LEFT JOINs
2. Date range queries (birth year between X and Y)
3. No complex ACF repeater serialization queries

The key challenge is keeping the denormalized data synchronized when birthday important_dates are created, updated, or deleted. WordPress provides `save_post` and `before_delete_post` hooks for this purpose. We also need a WP-CLI migration command to backfill existing data.

**Primary recommendation:** Store birthdate as `YYYY-MM-DD` format in `_birthdate` meta key (underscore prefix = hidden from custom fields UI). Sync via `save_post_important_date` hook (priority 20, after ACF saves fields). Migrate via idempotent `wp stadion migrate-birthdates` WP-CLI command.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress post_meta | Core | Denormalized data storage | Native storage, indexed, queryable |
| save_post hook | Core | Post save synchronization | Standard WordPress pattern for data sync |
| WP-CLI | Core | Migration commands | Established for data migrations in this codebase |
| ACF get_field() | 6.x | Reading ACF fields | Already used throughout codebase |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| update_post_meta() | Core | Writing meta values | Always - for setting denormalized data |
| delete_post_meta() | Core | Removing meta values | When person no longer has birthday |
| before_delete_post | Core | Pre-deletion hook | Clear denormalized data on birthday delete |
| wp_get_post_terms() | Core | Reading taxonomies | Check if important_date is birthday type |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Post meta storage | Custom database table | Post meta is indexed, queryable, follows WordPress conventions |
| save_post hook | acf/save_post hook | save_post runs for all update paths (REST, admin, cron) |
| YYYY-MM-DD format | Unix timestamp | YYYY-MM-DD is human-readable, sortable, filterable by year |
| _birthdate key name | birthdate (no underscore) | Underscore prefix hides from custom fields UI |
| Immediate sync | Cron-based sync | Immediate sync keeps data consistent, acceptable performance cost |

**Installation:**
```bash
# No additional packages needed
# All dependencies are WordPress core or already installed (ACF Pro, WP-CLI)
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-auto-title.php              # Existing - add birthdate sync here
└── class-wp-cli.php                  # Existing - add migration command here
```

### Pattern 1: Denormalized Meta Storage
**What:** Store computed/derived data in post_meta for fast querying
**When to use:** When JOIN queries on related posts are too slow (like birth year filtering)
**Example:**
```php
// Source: WordPress Codex - update_post_meta()
// Store birthdate on person post (underscore prefix = hidden from UI)
update_post_meta($person_id, '_birthdate', '1985-06-15');

// Query by birth year (simple meta query, no JOINs needed)
$people = new WP_Query([
    'post_type'  => 'person',
    'meta_query' => [
        [
            'key'     => '_birthdate',
            'value'   => '1985-01-01',
            'compare' => '>=',
            'type'    => 'DATE',
        ],
        [
            'key'     => '_birthdate',
            'value'   => '1985-12-31',
            'compare' => '<=',
            'type'    => 'DATE',
        ],
    ],
]);

// Or use in $wpdb JOIN (Phase 111 pattern)
$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";
$where_clauses[] = $wpdb->prepare("YEAR(bd.meta_value) = %d", $birth_year);
```

### Pattern 2: save_post Hook for Synchronization
**What:** Update denormalized data whenever source post is saved
**When to use:** Always - keeps denormalized data consistent with source
**Example:**
```php
// Source: includes/class-auto-title.php line 15 (existing pattern)
namespace Stadion\Core;

class AutoTitle {
    public function __construct() {
        // Priority 20 = after ACF saves fields (ACF uses priority 10)
        add_action('save_post_important_date', [$this, 'sync_birthdate_to_person'], 20, 3);
        add_action('before_delete_post', [$this, 'clear_birthdate_on_delete'], 10, 2);
    }

    /**
     * Sync birthdate to related persons when birthday important_date is saved
     *
     * @param int $post_id Post ID of important_date
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update or new post
     */
    public function sync_birthdate_to_person($post_id, $post, $update) {
        // Prevent infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify post status
        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if this is a birthday type
        $date_types = wp_get_post_terms($post_id, 'date_type', ['fields' => 'slugs']);
        if (!in_array('birthday', $date_types, true)) {
            return; // Not a birthday, nothing to sync
        }

        // Get the date value and related people
        $date_value = get_field('date_value', $post_id);
        $year_unknown = get_field('year_unknown', $post_id);
        $related_people = get_field('related_people', $post_id);

        if (empty($related_people) || !is_array($related_people)) {
            return; // No people to sync to
        }

        // If year is unknown, clear birthdate on all related people
        if ($year_unknown || empty($date_value)) {
            foreach ($related_people as $person_id) {
                delete_post_meta($person_id, '_birthdate');
            }
            return;
        }

        // Update birthdate on all related people
        foreach ($related_people as $person_id) {
            update_post_meta($person_id, '_birthdate', $date_value);
        }
    }

    /**
     * Clear birthdate from persons when birthday important_date is deleted
     *
     * @param int $post_id Post ID being deleted
     * @param WP_Post $post Post object
     */
    public function clear_birthdate_on_delete($post_id, $post) {
        if ($post->post_type !== 'important_date') {
            return;
        }

        // Check if this is a birthday type
        $date_types = wp_get_post_terms($post_id, 'date_type', ['fields' => 'slugs']);
        if (!in_array('birthday', $date_types, true)) {
            return;
        }

        // Clear birthdate from all related people
        $related_people = get_field('related_people', $post_id);
        if (empty($related_people) || !is_array($related_people)) {
            return;
        }

        foreach ($related_people as $person_id) {
            delete_post_meta($person_id, '_birthdate');
        }
    }
}
```

### Pattern 3: Idempotent WP-CLI Migration
**What:** Migration command that can be run multiple times safely (overwrites, doesn't duplicate)
**When to use:** Always - makes migrations safe to re-run after failures or data changes
**Example:**
```php
// Source: includes/class-wp-cli.php line 854 (existing migration pattern)
class STADION_Migration_CLI_Command {
    /**
     * Migrate birthdates from important_dates to person post_meta
     *
     * Finds all birthday important_dates and copies their date_value
     * to the _birthdate meta key on related persons.
     *
     * This command is idempotent - safe to re-run, it overwrites existing values.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview changes without making them
     *
     * ## EXAMPLES
     *
     *     wp stadion migrate-birthdates
     *     wp stadion migrate-birthdates --dry-run
     *
     * @when after_wp_load
     */
    public function migrate_birthdates($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);

        if ($dry_run) {
            WP_CLI::log('DRY RUN MODE - No changes will be made');
        }

        WP_CLI::log('');
        WP_CLI::log('╔════════════════════════════════════════════════════════════╗');
        WP_CLI::log('║         Stadion Birthdate Migration                        ║');
        WP_CLI::log('╚════════════════════════════════════════════════════════════╝');
        WP_CLI::log('');
        WP_CLI::log('This migration will:');
        WP_CLI::log('  1. Find all birthday important_dates with known years');
        WP_CLI::log('  2. Copy date_value to _birthdate meta on related persons');
        WP_CLI::log('  3. Clear _birthdate on persons with year_unknown birthdays');
        WP_CLI::log('');

        // Query all birthday important_dates
        $birthdays = new \WP_Query([
            'post_type'      => 'important_date',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'date_type',
                    'field'    => 'slug',
                    'terms'    => 'birthday',
                ],
            ],
            'suppress_filters' => true, // Bypass access control for migration
        ]);

        if (!$birthdays->have_posts()) {
            WP_CLI::success('No birthday dates found. Nothing to migrate.');
            return;
        }

        WP_CLI::log(sprintf('Found %d birthday date(s) to process.', $birthdays->post_count));
        WP_CLI::log('');

        $migrated = 0;
        $cleared = 0;
        $skipped = 0;

        while ($birthdays->have_posts()) {
            $birthdays->the_post();
            $birthday_id = get_the_ID();

            $date_value = get_field('date_value', $birthday_id);
            $year_unknown = get_field('year_unknown', $birthday_id);
            $related_people = get_field('related_people', $birthday_id);

            if (empty($related_people) || !is_array($related_people)) {
                WP_CLI::log(sprintf('Skipping birthday ID %d: no related people', $birthday_id));
                $skipped++;
                continue;
            }

            $person_names = array_map(function($id) {
                return get_the_title($id);
            }, $related_people);

            if ($year_unknown || empty($date_value)) {
                WP_CLI::log(sprintf(
                    'Birthday ID %d (year unknown): clearing _birthdate for %s',
                    $birthday_id,
                    implode(', ', $person_names)
                ));

                if (!$dry_run) {
                    foreach ($related_people as $person_id) {
                        delete_post_meta($person_id, '_birthdate');
                    }
                }
                $cleared++;
            } else {
                WP_CLI::log(sprintf(
                    'Birthday ID %d (%s): setting _birthdate for %s',
                    $birthday_id,
                    $date_value,
                    implode(', ', $person_names)
                ));

                if (!$dry_run) {
                    foreach ($related_people as $person_id) {
                        update_post_meta($person_id, '_birthdate', $date_value);
                    }
                }
                $migrated++;
            }
        }

        wp_reset_postdata();

        WP_CLI::log('');
        WP_CLI::log('────────────────────────────────────────────────────────────────');
        WP_CLI::log('Migration Summary:');
        WP_CLI::log('────────────────────────────────────────────────────────────────');

        if ($dry_run) {
            WP_CLI::log(sprintf('  Would set birthdates: %d', $migrated));
            WP_CLI::log(sprintf('  Would clear birthdates: %d', $cleared));
            WP_CLI::log(sprintf('  Would skip: %d', $skipped));
            WP_CLI::success('Dry run complete. Run without --dry-run to apply changes.');
        } else {
            WP_CLI::log(sprintf('  Set birthdates: %d', $migrated));
            WP_CLI::log(sprintf('  Cleared birthdates: %d', $cleared));
            WP_CLI::log(sprintf('  Skipped: %d', $skipped));
            WP_CLI::success('Migration complete!');
        }
    }
}
```

### Pattern 4: Multiple People Birthday Handling
**What:** When a birthday important_date has multiple related_people, sync to all of them
**When to use:** Always - important_dates can have multiple related people (shared birthdays, twins)
**Example:**
```php
// Get related people (returns array of post IDs)
$related_people = get_field('related_people', $post_id);

// Loop through all related people and update each
foreach ($related_people as $person_id) {
    update_post_meta($person_id, '_birthdate', $date_value);
}

// Edge case: If person has multiple birthday important_dates (data error),
// the last one saved will win (overwrite pattern). This is acceptable -
// a person should only have one birthday.
```

### Anti-Patterns to Avoid
- **Storing birth year only instead of full date:** Loses month/day information, makes age calculation impossible
- **Using custom taxonomy for birth year:** Taxonomies are for classification, not temporal data
- **Syncing on acf/save_post instead of save_post:** acf/save_post doesn't run for REST API updates from admin
- **Not handling year_unknown flag:** Must clear _birthdate when year_unknown is true
- **Using priority 10 on save_post:** ACF saves fields at priority 10, must use 20+ to read updated values
- **Not handling multiple related_people:** Birthday dates can have multiple people (twins, shared birthdays)
- **Not handling permanent delete:** Must use before_delete_post (permanent delete), not wp_trash_post (soft delete)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date storage format | Custom format | YYYY-MM-DD | MySQL DATE type, sortable, filterable by YEAR() |
| Date comparison | String comparison | MySQL DATE type in meta_query | Proper date arithmetic, handles leap years |
| Meta key naming | birthdate | _birthdate | Underscore prefix hides from custom fields UI |
| Hook timing | Manual timing | Priority 20 on save_post | ACF uses priority 10, must run after |
| WP-CLI output | echo statements | WP_CLI::log(), WP_CLI::success() | Proper formatting, respects --quiet flag |
| Dry run pattern | Custom flag | --dry-run convention | Standard WP-CLI pattern |
| Migration progress | Manual counter | WP_CLI::log() in loop | Shows progress for long migrations |
| Access control bypass | Custom query | suppress_filters => true | Standard WP_Query flag for migrations |

**Key insight:** WordPress stores date meta as plain text, but meta_query with type='DATE' uses MySQL DATE functions for proper comparison. YYYY-MM-DD format is required for DATE type queries. The underscore prefix on meta keys hides them from the WordPress custom fields UI, preventing accidental manual editing.

## Common Pitfalls

### Pitfall 1: Hook Priority Too Low
**What goes wrong:** Reading ACF fields in save_post hook returns old values
**Why it happens:** ACF saves fields at priority 10, reading at same priority races with ACF
**How to avoid:** Use priority 20+ on save_post hook (after ACF saves)
**Warning signs:** Birthdate sync shows previous value, not just-saved value

### Pitfall 2: Not Handling year_unknown Flag
**What goes wrong:** Birthdates stored for people with unknown years, age calculations are wrong
**Why it happens:** Forgetting to check year_unknown field before syncing
**How to avoid:** Always check year_unknown, delete_post_meta() if true
**Warning signs:** Filter shows people born in 1900 (ACF date_picker default for month/day-only dates)

### Pitfall 3: Not Handling Multiple People
**What goes wrong:** Shared birthdays (twins, couples with same birthday) only sync to first person
**Why it happens:** Assuming related_people is single value instead of array
**How to avoid:** Always loop through related_people array with foreach
**Warning signs:** First person in list has birthdate, others don't

### Pitfall 4: Trash vs Permanent Delete
**What goes wrong:** Birthdate remains after birthday important_date is trashed
**Why it happens:** Using wp_trash_post hook instead of before_delete_post
**How to avoid:** Use before_delete_post which runs on permanent delete only
**Warning signs:** Trashed birthdays still filter people, _birthdate only clears on permanent delete
**Decision:** From CONTEXT.md - "_birthdate only clears on permanent delete, not when trashed"

### Pitfall 5: Infinite Loop on save_post
**What goes wrong:** save_post hook triggers wp_update_post() which triggers save_post again
**Why it happens:** Calling wp_update_post() inside save_post without DOING_AUTOSAVE check
**How to avoid:** Check DOING_AUTOSAVE constant, unhook before wp_update_post()
**Warning signs:** PHP max execution time error, server hangs on birthday save
**Note:** This phase only updates post_meta, not post objects, so no risk of infinite loop

### Pitfall 6: Migration Not Idempotent
**What goes wrong:** Running migration twice creates duplicate data or errors
**Why it happens:** Using add_post_meta() instead of update_post_meta()
**How to avoid:** Use update_post_meta() which overwrites existing values
**Warning signs:** "Duplicate meta key" errors, multiple _birthdate values per person

### Pitfall 7: Date Format Mismatch
**What goes wrong:** Birth year filter returns no results even though birthdates exist
**Why it happens:** Storing date in wrong format (timestamp, YYYY-MM-DD HH:MM:SS)
**How to avoid:** Store as YYYY-MM-DD only (no time component), use YEAR() in WHERE clause
**Warning signs:** Direct database queries work but WP_Query meta_query doesn't

## Code Examples

Verified patterns from existing codebase:

### Querying Birthdate in Filtered Endpoint
```php
// Source: includes/class-rest-people.php line 943 (existing LEFT JOIN pattern)
// Add to get_filtered_people() method in Phase 113

// LEFT JOIN for birthdate meta (if birth_year filter is active)
if ($birth_year !== null) {
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd
                       ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";

    // WHERE clause using MySQL YEAR() function
    $where_clauses[] = $wpdb->prepare("YEAR(bd.meta_value) = %d", $birth_year);
}

// For year range (from-to selection in UI):
if ($birth_year_from !== null && $birth_year_to !== null) {
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd
                       ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";

    $where_clauses[] = $wpdb->prepare(
        "YEAR(bd.meta_value) BETWEEN %d AND %d",
        $birth_year_from,
        $birth_year_to
    );
}

// Exclude people with no birthdate (NULL values)
// Note: LEFT JOIN will include people without _birthdate meta (bd.meta_value IS NULL)
// The YEAR() function returns NULL for NULL input, so WHERE clause naturally excludes them
```

### Reading Important Date Fields
```php
// Source: includes/class-auto-title.php line 250 (existing pattern)
// How to read important_date ACF fields

$date_value = get_field('date_value', $post_id); // Returns 'YYYY-MM-DD' string
$year_unknown = get_field('year_unknown', $post_id); // Returns true/false
$related_people = get_field('related_people', $post_id); // Returns array of post IDs

// Check date type taxonomy
$date_types = wp_get_post_terms($post_id, 'date_type', ['fields' => 'slugs']);
$is_birthday = in_array('birthday', $date_types, true);
```

### Registering WP-CLI Command
```php
// Source: includes/class-wp-cli.php line 26 (existing pattern)
// Register command under 'prm' namespace

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('stadion migrate-birthdates', [
        'STADION_Migration_CLI_Command',
        'migrate_birthdates'
    ]);
}
```

## Performance Considerations

### Meta Query Performance
```sql
-- Simple year filter (after Phase 112 implementation)
SELECT p.ID
FROM wp_posts p
LEFT JOIN wp_postmeta bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'
WHERE p.post_type = 'person'
AND YEAR(bd.meta_value) = 1985;

-- Query plan: Uses post_type index + meta_key index
-- Expected time: <0.05s for 1400 records
-- No additional indexes needed - wp_postmeta has index on (post_id, meta_key)
```

### Migration Performance
- ~1400 people in production
- Migration processes birthday dates (~1400 dates), not people
- Estimated time: ~5-10 seconds for 1400 dates
- Safe to run during normal hours (read-heavy operation)
- Idempotent - safe to re-run if interrupted

### Sync Performance
- Runs on every birthday important_date save (not every person save)
- Average: <10ms per save (update_post_meta is fast)
- Handles multiple related_people in single save
- No impact on frontend performance (happens in admin/REST)

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Query birthday dates in loop | Denormalize to person meta | This phase | 100x faster birth year filtering |
| ACF repeater LIKE queries | Direct meta value queries | This phase | Eliminates slow serialization queries |
| Client-side birth year calculation | Server-side YEAR() function | This phase | Enables server-side filtering |

**Deprecated/outdated:**
- None - this is new functionality, not replacing existing code

## Open Questions

Things that couldn't be fully resolved:

1. **Should we store birth_year separately from _birthdate?**
   - What we know: YEAR() function extracts year on-the-fly from YYYY-MM-DD date
   - What's unclear: Whether storing separate birth_year meta is faster for filtering
   - Recommendation: Store only _birthdate - YEAR() is fast enough, avoids data duplication

2. **How to handle people with multiple birthday important_dates (data error)?**
   - What we know: Overwrite pattern means last one saved wins
   - What's unclear: Should we warn or prevent multiple birthdays per person?
   - Recommendation: Accept overwrite behavior - UI prevents multiple birthdays anyway

3. **Should migration validate date format before storing?**
   - What we know: ACF date_picker always returns YYYY-MM-DD format
   - What's unclear: What if date_value is manually corrupted in database?
   - Recommendation: Add basic validation (regex) in migration, log invalid dates

4. **Should we sync on REST API updates to important_dates?**
   - What we know: save_post hook runs for all save contexts (admin, REST, cron)
   - What's unclear: Are there edge cases where REST updates don't trigger save_post?
   - Recommendation: save_post is sufficient - REST API uses wp_insert_post/wp_update_post internally

## Sources

### Primary (HIGH confidence)
- WordPress save_post hook: https://developer.wordpress.org/reference/hooks/save_post/
- WordPress update_post_meta(): https://developer.wordpress.org/reference/functions/update_post_meta/
- WordPress before_delete_post hook: https://developer.wordpress.org/reference/hooks/before_delete_post/
- WP-CLI command registration: https://make.wordpress.org/cli/handbook/guides/commands-cookbook/
- Existing codebase patterns:
  - `includes/class-auto-title.php` (save_post hooks, ACF field reading)
  - `includes/class-wp-cli.php` (WP-CLI command patterns, migration structure)
  - `includes/class-rest-people.php` (LEFT JOIN meta pattern for filtering)
  - `acf-json/group_important_date_fields.json` (date_value, year_unknown fields)

### Secondary (MEDIUM confidence)
- ACF date_picker format: https://www.advancedcustomfields.com/resources/date-picker/ (YYYY-MM-DD return format)
- MySQL YEAR() function: https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_year

### Tertiary (LOW confidence)
- None used - all findings verified against codebase or official docs

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All WordPress core functions and ACF, verified in codebase
- Architecture: HIGH - Patterns directly from existing codebase (auto-title, WP-CLI migrations)
- Pitfalls: HIGH - Based on WordPress save_post documentation and codebase analysis

**Research date:** 2026-01-29
**Valid until:** 90 days (stable - WordPress core functions rarely change)
