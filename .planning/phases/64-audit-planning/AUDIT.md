# PHP Codebase Audit: v4.4 Code Organization

**Date:** 2026-01-16
**Phase:** 64 (Audit & Planning)
**Purpose:** Guide Phase 65 (Split & Reorganize) and Phase 66 (PSR-4 Autoloader)

---

## Executive Summary

The Caelis PHP codebase contains **41 classes** across **39 files** in the `includes/` directory. Only **1 file** contains multiple class definitions. The codebase is already well-organized with functional groupings, but lacks formal namespace hierarchy and PSR-4 autoloading.

**Key Finding:** The codebase is cleaner than expected. Only `class-notification-channels.php` violates the one-class-per-file rule.

---

## Multi-Class Files

### Files Requiring Splitting

| File | Classes | Lines | Action Required |
|------|---------|-------|-----------------|
| `class-notification-channels.php` | 3 | 834 | Split into 3 files |

### Detailed Breakdown: class-notification-channels.php

| Class | Type | Line | Lines | Description |
|-------|------|------|-------|-------------|
| `PRM_Notification_Channel` | abstract | 15 | ~64 | Base class for notification channels |
| `PRM_Email_Channel` | concrete | 79 | ~342 | Email notification implementation |
| `PRM_Slack_Channel` | concrete | 426 | ~408 | Slack notification implementation |

**Split Strategy:**
1. `class-notification-channel.php` - Abstract base class (new file)
2. `class-email-channel.php` - Email implementation (new file)
3. `class-slack-channel.php` - Slack implementation (new file)

---

## Current Codebase Overview

### Directory Structure

```
includes/
├── carddav/                    # CardDAV/CalDAV Sabre backend classes
│   ├── class-auth-backend.php
│   ├── class-carddav-backend.php
│   └── class-principal-backend.php
├── class-access-control.php    # User data isolation
├── class-auto-title.php        # Automatic post title generation
├── class-caldav-provider.php   # CalDAV calendar provider
├── class-calendar-connections.php
├── class-calendar-matcher.php  # Email-to-person matching
├── class-calendar-sync.php     # Background calendar sync
├── class-carddav-server.php    # CardDAV server wrapper
├── class-comment-types.php     # Notes/Activities system
├── class-credential-encryption.php
├── class-google-calendar-provider.php
├── class-google-contacts-import.php
├── class-google-oauth.php
├── class-ical-feed.php         # iCal feed generation
├── class-inverse-relationships.php
├── class-mention-notifications.php
├── class-mentions.php
├── class-monica-import.php     # Monica CRM import
├── class-notification-channels.php  # MULTI-CLASS (3 classes)
├── class-post-types.php        # CPT registration
├── class-reminders.php         # Daily digest system
├── class-rest-api.php          # Legacy REST endpoints
├── class-rest-base.php         # Abstract REST controller
├── class-rest-calendar.php
├── class-rest-companies.php
├── class-rest-import-export.php
├── class-rest-people.php
├── class-rest-slack.php
├── class-rest-todos.php
├── class-rest-workspaces.php
├── class-taxonomies.php
├── class-todo-migration.php
├── class-user-roles.php
├── class-vcard-export.php
├── class-vcard-import.php
├── class-visibility.php
├── class-workspace-members.php
└── class-wp-cli.php
```

### Class Inventory

**Total: 41 classes in 39 files**

#### Core WordPress Integration (5 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Post_Types` | class-post-types.php | 402 | CPT registration |
| `PRM_Taxonomies` | class-taxonomies.php | 527 | Taxonomy registration |
| `PRM_Access_Control` | class-access-control.php | 481 | User data isolation |
| `PRM_Visibility` | class-visibility.php | 164 | Visibility helpers |
| `PRM_User_Roles` | class-user-roles.php | 399 | Custom role management |

#### REST API Controllers (9 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_REST_Base` | class-rest-base.php | 227 | Abstract base controller |
| `PRM_REST_API` | class-rest-api.php | 1178 | Legacy endpoints (dashboard, search) |
| `PRM_REST_People` | class-rest-people.php | 774 | People CRUD |
| `PRM_REST_Companies` | class-rest-companies.php | 601 | Companies CRUD |
| `PRM_REST_Todos` | class-rest-todos.php | 492 | Todos CRUD |
| `PRM_REST_Workspaces` | class-rest-workspaces.php | 1238 | Workspaces CRUD |
| `PRM_REST_Slack` | class-rest-slack.php | 714 | Slack integration |
| `PRM_REST_Import_Export` | class-rest-import-export.php | 423 | Import/Export endpoints |
| `PRM_REST_Calendar` | class-rest-calendar.php | 1220 | Calendar endpoints |

#### Notification System (3 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Notification_Channel` | class-notification-channels.php | 64 | Abstract base |
| `PRM_Email_Channel` | class-notification-channels.php | 342 | Email notifications |
| `PRM_Slack_Channel` | class-notification-channels.php | 408 | Slack notifications |

#### Calendar System (6 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Calendar_Connections` | class-calendar-connections.php | 156 | Connection management |
| `PRM_Calendar_Matcher` | class-calendar-matcher.php | 297 | Email-to-person matching |
| `PRM_Calendar_Sync` | class-calendar-sync.php | 450 | Background sync |
| `PRM_Google_Calendar_Provider` | class-google-calendar-provider.php | 296 | Google Calendar API |
| `PRM_CalDAV_Provider` | class-caldav-provider.php | 643 | CalDAV protocol |
| `PRM_Google_OAuth` | class-google-oauth.php | 190 | OAuth flow |

#### Import/Export System (5 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Monica_Import` | class-monica-import.php | 1040 | Monica CRM import |
| `PRM_VCard_Import` | class-vcard-import.php | 1038 | VCard import |
| `PRM_VCard_Export` | class-vcard-export.php | 940 | VCard export |
| `PRM_Google_Contacts_Import` | class-google-contacts-import.php | 795 | Google Contacts import |
| `PRM_ICal_Feed` | class-ical-feed.php | 477 | iCal feed generation |

#### CardDAV System (4 classes)
| Class | File | Namespace | Responsibility |
|-------|------|-----------|----------------|
| `PRM_CardDAV_Server` | class-carddav-server.php | none | Server wrapper |
| `CardDAVBackend` | carddav/class-carddav-backend.php | `Caelis\CardDAV` | Sabre backend |
| `AuthBackend` | carddav/class-auth-backend.php | `Caelis\CardDAV` | Authentication |
| `PrincipalBackend` | carddav/class-principal-backend.php | `Caelis\CardDAV` | Principal backend |

#### Collaborative Features (5 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Comment_Types` | class-comment-types.php | 616 | Notes/Activities |
| `PRM_Workspace_Members` | class-workspace-members.php | 263 | Workspace membership |
| `PRM_Mentions` | class-mentions.php | 53 | @mention parsing |
| `PRM_Mention_Notifications` | class-mention-notifications.php | 110 | Mention notifications |
| `PRM_Reminders` | class-reminders.php | 681 | Daily digest |

#### Data Processing (4 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_Auto_Title` | class-auto-title.php | 208 | Auto-generate titles |
| `PRM_Inverse_Relationships` | class-inverse-relationships.php | 660 | Bidirectional relationships |
| `PRM_Todo_Migration` | class-todo-migration.php | 102 | Todo migration helper |
| `PRM_Credential_Encryption` | class-credential-encryption.php | 56 | Sodium encryption |

#### CLI Commands (1 class)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `PRM_WP_CLI` | class-wp-cli.php | 1720 | WP-CLI commands |

---

## Proposed Folder Structure

### Current vs Proposed

```
includes/                        includes/
├── carddav/                     ├── carddav/              # Keep (already namespaced)
│   ├── class-auth-backend.php   │   ├── AuthBackend.php
│   ├── class-carddav-backend.php│   ├── CardDAVBackend.php
│   └── class-principal-backend.php│   └── PrincipalBackend.php
├── class-*.php (36 files)       │
                                 ├── core/                  # Core WordPress integration
                                 │   ├── PostTypes.php
                                 │   ├── Taxonomies.php
                                 │   ├── AccessControl.php
                                 │   ├── Visibility.php
                                 │   ├── UserRoles.php
                                 │   └── AutoTitle.php
                                 │
                                 ├── rest/                  # REST API controllers
                                 │   ├── Base.php
                                 │   ├── Api.php           # Legacy endpoints
                                 │   ├── People.php
                                 │   ├── Companies.php
                                 │   ├── Todos.php
                                 │   ├── Workspaces.php
                                 │   ├── Slack.php
                                 │   ├── ImportExport.php
                                 │   └── Calendar.php
                                 │
                                 ├── notifications/         # Notification system
                                 │   ├── Channel.php       # Abstract base
                                 │   ├── EmailChannel.php
                                 │   └── SlackChannel.php
                                 │
                                 ├── calendar/              # Calendar integration
                                 │   ├── Connections.php
                                 │   ├── Matcher.php
                                 │   ├── Sync.php
                                 │   ├── GoogleProvider.php
                                 │   ├── CalDAVProvider.php
                                 │   └── GoogleOAuth.php
                                 │
                                 ├── import/                # Import functionality
                                 │   ├── Monica.php
                                 │   ├── VCard.php
                                 │   └── GoogleContacts.php
                                 │
                                 ├── export/                # Export functionality
                                 │   ├── VCard.php
                                 │   └── ICalFeed.php
                                 │
                                 ├── collaboration/         # Team features
                                 │   ├── CommentTypes.php
                                 │   ├── WorkspaceMembers.php
                                 │   ├── Mentions.php
                                 │   ├── MentionNotifications.php
                                 │   └── Reminders.php
                                 │
                                 ├── data/                  # Data processing
                                 │   ├── InverseRelationships.php
                                 │   ├── TodoMigration.php
                                 │   └── CredentialEncryption.php
                                 │
                                 └── cli/                   # WP-CLI commands
                                     └── Commands.php
```

---

## Namespace Hierarchy

### Root Namespace

`Caelis\` as the root namespace, mapping to `includes/`.

### Full Namespace Map

| Current Class | Target Namespace | Target File |
|---------------|------------------|-------------|
| **Core** | | |
| `PRM_Post_Types` | `Caelis\Core\PostTypes` | `core/PostTypes.php` |
| `PRM_Taxonomies` | `Caelis\Core\Taxonomies` | `core/Taxonomies.php` |
| `PRM_Access_Control` | `Caelis\Core\AccessControl` | `core/AccessControl.php` |
| `PRM_Visibility` | `Caelis\Core\Visibility` | `core/Visibility.php` |
| `PRM_User_Roles` | `Caelis\Core\UserRoles` | `core/UserRoles.php` |
| `PRM_Auto_Title` | `Caelis\Core\AutoTitle` | `core/AutoTitle.php` |
| **REST API** | | |
| `PRM_REST_Base` | `Caelis\REST\Base` | `rest/Base.php` |
| `PRM_REST_API` | `Caelis\REST\Api` | `rest/Api.php` |
| `PRM_REST_People` | `Caelis\REST\People` | `rest/People.php` |
| `PRM_REST_Companies` | `Caelis\REST\Companies` | `rest/Companies.php` |
| `PRM_REST_Todos` | `Caelis\REST\Todos` | `rest/Todos.php` |
| `PRM_REST_Workspaces` | `Caelis\REST\Workspaces` | `rest/Workspaces.php` |
| `PRM_REST_Slack` | `Caelis\REST\Slack` | `rest/Slack.php` |
| `PRM_REST_Import_Export` | `Caelis\REST\ImportExport` | `rest/ImportExport.php` |
| `PRM_REST_Calendar` | `Caelis\REST\Calendar` | `rest/Calendar.php` |
| **Notifications** | | |
| `PRM_Notification_Channel` | `Caelis\Notifications\Channel` | `notifications/Channel.php` |
| `PRM_Email_Channel` | `Caelis\Notifications\EmailChannel` | `notifications/EmailChannel.php` |
| `PRM_Slack_Channel` | `Caelis\Notifications\SlackChannel` | `notifications/SlackChannel.php` |
| **Calendar** | | |
| `PRM_Calendar_Connections` | `Caelis\Calendar\Connections` | `calendar/Connections.php` |
| `PRM_Calendar_Matcher` | `Caelis\Calendar\Matcher` | `calendar/Matcher.php` |
| `PRM_Calendar_Sync` | `Caelis\Calendar\Sync` | `calendar/Sync.php` |
| `PRM_Google_Calendar_Provider` | `Caelis\Calendar\GoogleProvider` | `calendar/GoogleProvider.php` |
| `PRM_CalDAV_Provider` | `Caelis\Calendar\CalDAVProvider` | `calendar/CalDAVProvider.php` |
| `PRM_Google_OAuth` | `Caelis\Calendar\GoogleOAuth` | `calendar/GoogleOAuth.php` |
| **Import** | | |
| `PRM_Monica_Import` | `Caelis\Import\Monica` | `import/Monica.php` |
| `PRM_VCard_Import` | `Caelis\Import\VCard` | `import/VCard.php` |
| `PRM_Google_Contacts_Import` | `Caelis\Import\GoogleContacts` | `import/GoogleContacts.php` |
| **Export** | | |
| `PRM_VCard_Export` | `Caelis\Export\VCard` | `export/VCard.php` |
| `PRM_ICal_Feed` | `Caelis\Export\ICalFeed` | `export/ICalFeed.php` |
| **CardDAV** (already namespaced) | | |
| `Caelis\CardDAV\CardDAVBackend` | (keep) | `carddav/CardDAVBackend.php` |
| `Caelis\CardDAV\AuthBackend` | (keep) | `carddav/AuthBackend.php` |
| `Caelis\CardDAV\PrincipalBackend` | (keep) | `carddav/PrincipalBackend.php` |
| `PRM_CardDAV_Server` | `Caelis\CardDAV\Server` | `carddav/Server.php` |
| **Collaboration** | | |
| `PRM_Comment_Types` | `Caelis\Collaboration\CommentTypes` | `collaboration/CommentTypes.php` |
| `PRM_Workspace_Members` | `Caelis\Collaboration\WorkspaceMembers` | `collaboration/WorkspaceMembers.php` |
| `PRM_Mentions` | `Caelis\Collaboration\Mentions` | `collaboration/Mentions.php` |
| `PRM_Mention_Notifications` | `Caelis\Collaboration\MentionNotifications` | `collaboration/MentionNotifications.php` |
| `PRM_Reminders` | `Caelis\Collaboration\Reminders` | `collaboration/Reminders.php` |
| **Data** | | |
| `PRM_Inverse_Relationships` | `Caelis\Data\InverseRelationships` | `data/InverseRelationships.php` |
| `PRM_Todo_Migration` | `Caelis\Data\TodoMigration` | `data/TodoMigration.php` |
| `PRM_Credential_Encryption` | `Caelis\Data\CredentialEncryption` | `data/CredentialEncryption.php` |
| **CLI** | | |
| `PRM_WP_CLI` | `Caelis\CLI\Commands` | `cli/Commands.php` |

---

## Migration Strategy

### Phase 65: Split & Reorganize

**Step 1: Split multi-class file**
- Split `class-notification-channels.php` into 3 separate files
- Keep in current location initially
- Update autoloader map in functions.php

**Step 2: Create folder structure**
- Create subdirectories: `core/`, `rest/`, `notifications/`, `calendar/`, `import/`, `export/`, `collaboration/`, `data/`, `cli/`
- Move files to appropriate folders
- Keep old class names (PRM_* prefix)
- Update autoloader paths in functions.php

**Step 3: Verify**
- Run PHPCS to confirm one-class-per-file
- Run tests to ensure functionality preserved

### Phase 66: PSR-4 Autoloader

**Step 1: Add namespaces**
- Add namespace declarations to all classes
- Update class references throughout codebase
- Add `use` statements where needed

**Step 2: Configure Composer**
- Add PSR-4 autoload configuration to composer.json
- Run `composer dump-autoload`

**Step 3: Remove manual autoloader**
- Remove `prm_autoloader()` function from functions.php
- Ensure Composer autoloader is loaded early

**Step 4: Backward Compatibility**
- Add class aliases for old PRM_* names (optional, for plugin compatibility)

---

## PHPCS Configuration

### Current Rule Exclusion

The following rule is currently excluded in `phpcs.xml.dist` (line 37):

```xml
<exclude name="Generic.Files.OneObjectStructurePerFile.MultipleFound"/>
```

**Rationale:** The `class-notification-channels.php` file contains 3 related classes.

### Post-Phase 65 Action

After Phase 65 completes:
1. Remove the exclusion from phpcs.xml.dist
2. Run `composer phpcs` to verify compliance
3. All files should pass the one-object-per-file rule

### Other Rules to Monitor

| Rule | Status | Note |
|------|--------|------|
| `WordPress.Files.FileName.InvalidClassFileName` | Excluded | Will remain excluded - files use `class-*.php` naming |
| `WordPress.NamingConventions.ValidVariableName` | Excluded for carddav/ | Sabre libraries use camelCase |
| `Generic.CodeAnalysis.EmptyStatement.DetectedCatch` | Excluded for vcard | Graceful degradation |

---

## Backward Compatibility Plan

### Requirements

1. **No breaking changes to class interfaces**
   - All public methods retain same signatures
   - All class properties remain accessible

2. **REST API endpoints unchanged**
   - All `/prm/v1/` endpoints work identically
   - Response formats preserved

3. **Hook compatibility**
   - All action/filter hooks fire at same points
   - Hook names unchanged

### Class Aliases (Phase 66)

For backward compatibility, add class aliases in functions.php:

```php
// Backward compatibility aliases
class_alias( 'Caelis\Core\PostTypes', 'PRM_Post_Types' );
class_alias( 'Caelis\Core\Taxonomies', 'PRM_Taxonomies' );
// ... etc for all classes
```

This ensures any code referencing old class names continues to work.

### Testing

After each phase:
1. Run full PHPUnit test suite (120 tests)
2. Manual smoke test of key functionality
3. Verify REST API responses match expected format

---

## Success Criteria

### Phase 65 Success
- [ ] All files have exactly one class/interface/trait
- [ ] Files moved to appropriate subdirectories
- [ ] Autoloader updated with new paths
- [ ] PHPCS passes (with one-object-per-file rule enabled)
- [ ] All tests pass

### Phase 66 Success
- [ ] All classes have namespace declarations
- [ ] Composer PSR-4 autoloader configured
- [ ] Manual autoloader removed
- [ ] Class aliases provide backward compatibility
- [ ] All tests pass
- [ ] REST API endpoints unchanged

---

## File Count Summary

| Metric | Before | After Phase 65 | After Phase 66 |
|--------|--------|----------------|----------------|
| Total files | 39 | 41 | 41 |
| Multi-class files | 1 | 0 | 0 |
| Total classes | 41 | 41 | 41 |
| Subdirectories | 1 | 9 | 9 |
| Namespaced classes | 3 | 3 | 41 |

---

## Notes

1. **CardDAV classes already namespaced:** The 3 classes in `carddav/` already use the `Caelis\CardDAV` namespace and follow PSR-4 naming conventions. These only need minor file renaming (remove `class-` prefix).

2. **WP-CLI conditional loading:** The `class-wp-cli.php` file is only loaded when WP-CLI is active. This pattern should be preserved.

3. **Sabre library compatibility:** The CardDAV/CalDAV classes extend Sabre library base classes. Variable naming rules are relaxed for these files as Sabre uses camelCase.

4. **REST controller inheritance:** All PRM_REST_* classes extend PRM_REST_Base. This inheritance chain must be preserved during namespace migration.
