# PHP Codebase Audit: v4.4 Code Organization

**Date:** 2026-01-16
**Phase:** 64 (Audit & Planning)
**Purpose:** Guide Phase 65 (Split & Reorganize) and Phase 66 (PSR-4 Autoloader)

---

## Executive Summary

The Stadion PHP codebase contains **41 classes** across **39 files** in the `includes/` directory. Only **1 file** contains multiple class definitions. The codebase is already well-organized with functional groupings, but lacks formal namespace hierarchy and PSR-4 autoloading.

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
| `STADION_Notification_Channel` | abstract | 15 | ~64 | Base class for notification channels |
| `STADION_Email_Channel` | concrete | 79 | ~342 | Email notification implementation |
| `STADION_Slack_Channel` | concrete | 426 | ~408 | Slack notification implementation |

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
| `STADION_Post_Types` | class-post-types.php | 402 | CPT registration |
| `STADION_Taxonomies` | class-taxonomies.php | 527 | Taxonomy registration |
| `STADION_Access_Control` | class-access-control.php | 481 | User data isolation |
| `STADION_Visibility` | class-visibility.php | 164 | Visibility helpers |
| `STADION_User_Roles` | class-user-roles.php | 399 | Custom role management |

#### REST API Controllers (9 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_REST_Base` | class-rest-base.php | 227 | Abstract base controller |
| `STADION_REST_API` | class-rest-api.php | 1178 | Legacy endpoints (dashboard, search) |
| `STADION_REST_People` | class-rest-people.php | 774 | People CRUD |
| `STADION_REST_Companies` | class-rest-companies.php | 601 | Companies CRUD |
| `STADION_REST_Todos` | class-rest-todos.php | 492 | Todos CRUD |
| `STADION_REST_Workspaces` | class-rest-workspaces.php | 1238 | Workspaces CRUD |
| `STADION_REST_Slack` | class-rest-slack.php | 714 | Slack integration |
| `STADION_REST_Import_Export` | class-rest-import-export.php | 423 | Import/Export endpoints |
| `STADION_REST_Calendar` | class-rest-calendar.php | 1220 | Calendar endpoints |

#### Notification System (3 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_Notification_Channel` | class-notification-channels.php | 64 | Abstract base |
| `STADION_Email_Channel` | class-notification-channels.php | 342 | Email notifications |
| `STADION_Slack_Channel` | class-notification-channels.php | 408 | Slack notifications |

#### Calendar System (6 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_Calendar_Connections` | class-calendar-connections.php | 156 | Connection management |
| `STADION_Calendar_Matcher` | class-calendar-matcher.php | 297 | Email-to-person matching |
| `STADION_Calendar_Sync` | class-calendar-sync.php | 450 | Background sync |
| `STADION_Google_Calendar_Provider` | class-google-calendar-provider.php | 296 | Google Calendar API |
| `STADION_CalDAV_Provider` | class-caldav-provider.php | 643 | CalDAV protocol |
| `STADION_Google_OAuth` | class-google-oauth.php | 190 | OAuth flow |

#### Import/Export System (5 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_Monica_Import` | class-monica-import.php | 1040 | Monica CRM import |
| `STADION_VCard_Import` | class-vcard-import.php | 1038 | VCard import |
| `STADION_VCard_Export` | class-vcard-export.php | 940 | VCard export |
| `STADION_Google_Contacts_Import` | class-google-contacts-import.php | 795 | Google Contacts import |
| `STADION_ICal_Feed` | class-ical-feed.php | 477 | iCal feed generation |

#### CardDAV System (4 classes)
| Class | File | Namespace | Responsibility |
|-------|------|-----------|----------------|
| `STADION_CardDAV_Server` | class-carddav-server.php | none | Server wrapper |
| `CardDAVBackend` | carddav/class-carddav-backend.php | `Stadion\CardDAV` | Sabre backend |
| `AuthBackend` | carddav/class-auth-backend.php | `Stadion\CardDAV` | Authentication |
| `PrincipalBackend` | carddav/class-principal-backend.php | `Stadion\CardDAV` | Principal backend |

#### Collaborative Features (5 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_Comment_Types` | class-comment-types.php | 616 | Notes/Activities |
| `STADION_Workspace_Members` | class-workspace-members.php | 263 | Workspace membership |
| `STADION_Mentions` | class-mentions.php | 53 | @mention parsing |
| `STADION_Mention_Notifications` | class-mention-notifications.php | 110 | Mention notifications |
| `STADION_Reminders` | class-reminders.php | 681 | Daily digest |

#### Data Processing (4 classes)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_Auto_Title` | class-auto-title.php | 208 | Auto-generate titles |
| `STADION_Inverse_Relationships` | class-inverse-relationships.php | 660 | Bidirectional relationships |
| `STADION_Todo_Migration` | class-todo-migration.php | 102 | Todo migration helper |
| `STADION_Credential_Encryption` | class-credential-encryption.php | 56 | Sodium encryption |

#### CLI Commands (1 class)
| Class | File | Lines | Responsibility |
|-------|------|-------|----------------|
| `STADION_WP_CLI` | class-wp-cli.php | 1720 | WP-CLI commands |

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

`Stadion\` as the root namespace, mapping to `includes/`.

### Full Namespace Map

| Current Class | Target Namespace | Target File |
|---------------|------------------|-------------|
| **Core** | | |
| `STADION_Post_Types` | `Stadion\Core\PostTypes` | `core/PostTypes.php` |
| `STADION_Taxonomies` | `Stadion\Core\Taxonomies` | `core/Taxonomies.php` |
| `STADION_Access_Control` | `Stadion\Core\AccessControl` | `core/AccessControl.php` |
| `STADION_Visibility` | `Stadion\Core\Visibility` | `core/Visibility.php` |
| `STADION_User_Roles` | `Stadion\Core\UserRoles` | `core/UserRoles.php` |
| `STADION_Auto_Title` | `Stadion\Core\AutoTitle` | `core/AutoTitle.php` |
| **REST API** | | |
| `STADION_REST_Base` | `Stadion\REST\Base` | `rest/Base.php` |
| `STADION_REST_API` | `Stadion\REST\Api` | `rest/Api.php` |
| `STADION_REST_People` | `Stadion\REST\People` | `rest/People.php` |
| `STADION_REST_Companies` | `Stadion\REST\Companies` | `rest/Companies.php` |
| `STADION_REST_Todos` | `Stadion\REST\Todos` | `rest/Todos.php` |
| `STADION_REST_Workspaces` | `Stadion\REST\Workspaces` | `rest/Workspaces.php` |
| `STADION_REST_Slack` | `Stadion\REST\Slack` | `rest/Slack.php` |
| `STADION_REST_Import_Export` | `Stadion\REST\ImportExport` | `rest/ImportExport.php` |
| `STADION_REST_Calendar` | `Stadion\REST\Calendar` | `rest/Calendar.php` |
| **Notifications** | | |
| `STADION_Notification_Channel` | `Stadion\Notifications\Channel` | `notifications/Channel.php` |
| `STADION_Email_Channel` | `Stadion\Notifications\EmailChannel` | `notifications/EmailChannel.php` |
| `STADION_Slack_Channel` | `Stadion\Notifications\SlackChannel` | `notifications/SlackChannel.php` |
| **Calendar** | | |
| `STADION_Calendar_Connections` | `Stadion\Calendar\Connections` | `calendar/Connections.php` |
| `STADION_Calendar_Matcher` | `Stadion\Calendar\Matcher` | `calendar/Matcher.php` |
| `STADION_Calendar_Sync` | `Stadion\Calendar\Sync` | `calendar/Sync.php` |
| `STADION_Google_Calendar_Provider` | `Stadion\Calendar\GoogleProvider` | `calendar/GoogleProvider.php` |
| `STADION_CalDAV_Provider` | `Stadion\Calendar\CalDAVProvider` | `calendar/CalDAVProvider.php` |
| `STADION_Google_OAuth` | `Stadion\Calendar\GoogleOAuth` | `calendar/GoogleOAuth.php` |
| **Import** | | |
| `STADION_Monica_Import` | `Stadion\Import\Monica` | `import/Monica.php` |
| `STADION_VCard_Import` | `Stadion\Import\VCard` | `import/VCard.php` |
| `STADION_Google_Contacts_Import` | `Stadion\Import\GoogleContacts` | `import/GoogleContacts.php` |
| **Export** | | |
| `STADION_VCard_Export` | `Stadion\Export\VCard` | `export/VCard.php` |
| `STADION_ICal_Feed` | `Stadion\Export\ICalFeed` | `export/ICalFeed.php` |
| **CardDAV** (already namespaced) | | |
| `Stadion\CardDAV\CardDAVBackend` | (keep) | `carddav/CardDAVBackend.php` |
| `Stadion\CardDAV\AuthBackend` | (keep) | `carddav/AuthBackend.php` |
| `Stadion\CardDAV\PrincipalBackend` | (keep) | `carddav/PrincipalBackend.php` |
| `STADION_CardDAV_Server` | `Stadion\CardDAV\Server` | `carddav/Server.php` |
| **Collaboration** | | |
| `STADION_Comment_Types` | `Stadion\Collaboration\CommentTypes` | `collaboration/CommentTypes.php` |
| `STADION_Workspace_Members` | `Stadion\Collaboration\WorkspaceMembers` | `collaboration/WorkspaceMembers.php` |
| `STADION_Mentions` | `Stadion\Collaboration\Mentions` | `collaboration/Mentions.php` |
| `STADION_Mention_Notifications` | `Stadion\Collaboration\MentionNotifications` | `collaboration/MentionNotifications.php` |
| `STADION_Reminders` | `Stadion\Collaboration\Reminders` | `collaboration/Reminders.php` |
| **Data** | | |
| `STADION_Inverse_Relationships` | `Stadion\Data\InverseRelationships` | `data/InverseRelationships.php` |
| `STADION_Todo_Migration` | `Stadion\Data\TodoMigration` | `data/TodoMigration.php` |
| `STADION_Credential_Encryption` | `Stadion\Data\CredentialEncryption` | `data/CredentialEncryption.php` |
| **CLI** | | |
| `STADION_WP_CLI` | `Stadion\CLI\Commands` | `cli/Commands.php` |

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
- Keep old class names (STADION_* prefix)
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
- Remove `stadion_autoloader()` function from functions.php
- Ensure Composer autoloader is loaded early

**Step 4: Backward Compatibility**
- Add class aliases for old STADION_* names (optional, for plugin compatibility)

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
   - All `/stadion/v1/` endpoints work identically
   - Response formats preserved

3. **Hook compatibility**
   - All action/filter hooks fire at same points
   - Hook names unchanged

### Class Aliases (Phase 66)

For backward compatibility, add class aliases in functions.php:

```php
// Backward compatibility aliases
class_alias( 'Stadion\Core\PostTypes', 'STADION_Post_Types' );
class_alias( 'Stadion\Core\Taxonomies', 'STADION_Taxonomies' );
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

1. **CardDAV classes already namespaced:** The 3 classes in `carddav/` already use the `Stadion\CardDAV` namespace and follow PSR-4 naming conventions. These only need minor file renaming (remove `class-` prefix).

2. **WP-CLI conditional loading:** The `class-wp-cli.php` file is only loaded when WP-CLI is active. This pattern should be preserved.

3. **Sabre library compatibility:** The CardDAV/CalDAV classes extend Sabre library base classes. Variable naming rules are relaxed for these files as Sabre uses camelCase.

4. **REST controller inheritance:** All STADION_REST_* classes extend STADION_REST_Base. This inheritance chain must be preserved during namespace migration.
