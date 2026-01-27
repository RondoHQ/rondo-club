# Quick Task 006: Delete Duplicate Important Dates Script

## Goal

Create a WP-CLI command to find and delete duplicate important_date posts where duplicates are identified by matching title AND date_value, keeping the newest and deleting the oldest.

## Tasks

### Task 1: Create WP-CLI command for duplicate date cleanup

**File:** `bin/cleanup-duplicate-dates.php`

**Requirements:**
- Find all important_date posts
- Group by title + date_value combination
- For each group with > 1 post, keep the newest (highest ID) and delete the rest
- Support --dry-run flag to preview without deleting
- Output summary of duplicates found and deleted

**Implementation:**
- Use WP_Query to get all important_date posts
- Build associative array keyed by "title|date_value"
- Sort each group by ID descending
- Delete all but first (newest) in each group
- Use wp_delete_post with force=true to bypass trash
