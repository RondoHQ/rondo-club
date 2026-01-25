---
created: 2026-01-14T13:55
title: Convert todos to custom post type
area: api
files:
  - includes/class-comment-types.php
---

## Problem

Currently, todos are implemented as a custom comment type (stored in `wp_comments` table). This limits functionality because:

1. Comments can't have their own comments — users can't add notes/updates to a todo
2. Limited metadata capabilities compared to posts
3. Can't leverage WordPress post features (revisions, custom fields, etc.)

To-dos would benefit from being a full post type so they can:
- Have their own comment threads (notes, updates, discussions)
- Link to people/teams via ACF relationships
- Support richer metadata

## Solution

Migrate todos from comment type to custom post type:

1. Create new `stadion_todo` post type in `class-post-types.php`
2. Define ACF fields for todo properties (status, due date, linked person/org)
3. Create migration script to convert existing todo comments to posts
4. Update REST API endpoints for the new structure
5. Update frontend components to use new API
6. Remove old comment type handling

TBD: Data migration strategy (one-time script vs gradual migration)
TBD: Whether to keep backward compatibility during transition
