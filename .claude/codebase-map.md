# Rondo Club Codebase Map

Dense file index for agent planning. Use this to find relevant files instead of exploring.

## PHP Backend (`includes/`)

### Core
| File | Description |
|------|-------------|
| `functions.php` | Theme entry point: `rondo_init()` loads classes, enqueues assets, SPA routing |
| `includes/class-post-types.php` | Registers all CPTs: person, team, commissie, rondo_todo, rondo_feedback, calendar_event, discipline_case |
| `includes/class-taxonomies.php` | Registers taxonomies: relationship_type, seizoen |
| `includes/class-access-control.php` | Permission filtering at WP_Query and REST level; approved/unapproved user model |
| `includes/class-user-roles.php` | Custom "Rondo User" role with minimal capabilities |
| `includes/class-auto-title.php` | Auto-generates post titles for structured entities (person = "First Last") |
| `includes/class-volunteer-status.php` | Tracks and computes volunteer/active status for people |
| `includes/class-club-config.php` | Centralized club settings via WordPress Options API (club_name, freescout_url) |
| `includes/class-demo-protection.php` | Prevents destructive operations on demo site |
| `includes/class-demo-anonymizer.php` | Anonymizes personal data for demo exports |
| `includes/class-demo-export.php` | Exports anonymized demo dataset |
| `includes/class-demo-import.php` | Imports demo dataset into a fresh install |
| `includes/class-credential-encryption.php` | Encrypts/decrypts stored credentials (OAuth tokens, etc.) |
| `includes/class-todo-migration.php` | One-time migration of legacy todo data |
| `includes/class-wp-cli.php` | WP-CLI commands for maintenance tasks |

### REST API Controllers
| File | Description |
|------|-------------|
| `includes/class-rest-api.php` | Core custom endpoints: dashboard stats, global search, person timeline (`/rondo/v1/`) |
| `includes/class-rest-base.php` | Base class for REST controllers with shared auth/permission logic |
| `includes/class-rest-people.php` | Person CRUD, filtering, bulk updates (`/wp/v2/person`) |
| `includes/class-rest-teams.php` | Team CRUD and membership (`/wp/v2/team`) |
| `includes/class-rest-commissies.php` | Committee CRUD and staff (`/wp/v2/commissie`) |
| `includes/class-rest-todos.php` | Todo CRUD, completion, assignment (`/wp/v2/rondo_todo`) |
| `includes/class-rest-feedback.php` | Feedback CRUD with comment support and agent workflow (`/rondo/v1/feedback`) |
| `includes/class-rest-calendar.php` | Calendar events, availability, meeting management (`/rondo/v1/calendar`) |
| `includes/class-rest-custom-fields.php` | User-defined custom fields CRUD (`/rondo/v1/custom-fields`) |
| `includes/class-rest-import-export.php` | Data import/export endpoints (CSV, spreadsheet) |
| `includes/class-rest-google-contacts.php` | Google Contacts integration endpoints |
| `includes/class-rest-google-sheets.php` | Google Sheets export endpoints |

### Calendar & Contacts Integration
| File | Description |
|------|-------------|
| `includes/class-calendar-sync.php` | Background calendar sync via WP-Cron |
| `includes/class-calendar-connections.php` | Manages user calendar connections (Google, CalDAV) |
| `includes/class-calendar-matcher.php` | Matches calendar attendees to Rondo people with caching |
| `includes/class-caldav-provider.php` | CalDAV calendar provider (reads external CalDAV calendars) |
| `includes/class-google-calendar-provider.php` | Google Calendar provider implementation |
| `includes/class-carddav-server.php` | CardDAV server exposing contacts to native apps (Sabre/DAV) |
| `includes/class-ical-feed.php` | iCal feed endpoint for calendar subscriptions |
| `includes/class-google-oauth.php` | Google OAuth flow for calendar/contacts/sheets |
| `includes/class-google-contacts-sync.php` | Syncs Google Contacts with Rondo people |
| `includes/class-google-contacts-connection.php` | Manages Google Contacts connection state |
| `includes/class-google-contacts-api-import.php` | Imports contacts from Google Contacts API |
| `includes/class-google-contacts-export.php` | Exports Rondo contacts to Google Contacts |
| `includes/class-google-sheets-connection.php` | Google Sheets connection and export logic |

### Collaboration & Notifications
| File | Description |
|------|-------------|
| `includes/class-comment-types.php` | Custom comment types: rondo_note, rondo_activity, rondo_email, rondo_feedback_comment |
| `includes/class-mentions.php` | @mention parsing and detection in notes/activities |
| `includes/class-mention-notifications.php` | Sends notifications when users are @mentioned |
| `includes/class-notification-channel.php` | Base notification channel abstraction |
| `includes/class-email-channel.php` | Email notification delivery channel |
| `includes/class-reminders.php` | Scheduled reminders (VOG expiry, todo due dates) |

### Financial
| File | Description |
|------|-------------|
| `includes/class-membership-fees.php` | Per-season fee category system, fee calculations, batch operations |
| `includes/class-fee-cache-invalidator.php` | Invalidates fee caches when related data changes |
| `includes/class-vog-email.php` | VOG (background check) email notifications and tracking |

### Relationships
| File | Description |
|------|-------------|
| `includes/class-inverse-relationships.php` | Manages bidirectional relationships between people |
| `includes/class-vcard-export.php` | Exports person data as vCard format |

## React Frontend (`src/`)

### Entry Points & Routing
| File | Description |
|------|-------------|
| `src/main.jsx` | React app entry point, mounts to `#rondo-app` |
| `src/App.jsx` | Root layout: version check, theme provider, offline banner |
| `src/router.jsx` | createBrowserRouter with lazy-loaded pages, auth guard |
| `src/api/client.js` | Axios HTTP client with WordPress nonce injection (`X-WP-Nonce`) |

### Pages
| File | Description |
|------|-------------|
| `src/pages/Dashboard.jsx` | Dashboard with customizable cards, lazy sections, query client |
| `src/pages/Login.jsx` | Login page (delegates to WordPress authentication) |
| `src/pages/People/PeopleList.jsx` | Contact list with advanced filtering, column config, bulk operations |
| `src/pages/People/PersonDetail.jsx` | Full person profile: timeline, contacts, relationships, finances, VOG, custom fields |
| `src/pages/People/ColumnSettingsModal.jsx` | Column visibility/ordering settings for people list |
| `src/pages/Teams/TeamsList.jsx` | Teams list view |
| `src/pages/Teams/TeamDetail.jsx` | Team detail with members and info |
| `src/pages/Commissies/CommissiesList.jsx` | Committees list view |
| `src/pages/Commissies/CommissieDetail.jsx` | Committee detail with staff |
| `src/pages/Todos/TodosList.jsx` | Global todo list with filtering |
| `src/pages/Feedback/FeedbackList.jsx` | Feedback items list with status filtering |
| `src/pages/Feedback/FeedbackDetail.jsx` | Feedback detail with comments and agent workflow |
| `src/pages/Contributie/Contributie.jsx` | Fee management parent component |
| `src/pages/Contributie/ContributieList.jsx` | Fee list by person |
| `src/pages/Contributie/ContributieOverzicht.jsx` | Fee overview/summary |
| `src/pages/Contributie/SeasonSelector.jsx` | Season picker for fee views |
| `src/pages/VOG/VOG.jsx` | VOG (background check) parent component |
| `src/pages/VOG/VOGList.jsx` | VOG status list |
| `src/pages/VOG/VOGUpcoming.jsx` | Upcoming VOG expiries |
| `src/pages/VOG/VOGSettings.jsx` | VOG notification settings |
| `src/pages/DisciplineCases/DisciplineCasesList.jsx` | Discipline case list |
| `src/pages/Settings/Settings.jsx` | Settings parent component |
| `src/pages/Settings/CustomFields.jsx` | Custom field definitions management |
| `src/pages/Settings/FeeCategorySettings.jsx` | Fee category configuration |
| `src/pages/Settings/FeedbackManagement.jsx` | Feedback workflow settings |
| `src/pages/Settings/RelationshipTypes.jsx` | Relationship type management |

### Hooks (`src/hooks/`)
| File | Description |
|------|-------------|
| `useAuth.js` | Authentication state and login/logout |
| `useCurrentUser.js` | Current logged-in user data |
| `usePeople.js` | Person CRUD, search, filtering, bulk updates (TanStack Query) |
| `useTeams.js` | Team CRUD and membership queries |
| `useCommissies.js` | Committee queries and mutations |
| `useDashboard.js` | Dashboard data and card preferences |
| `useFeedback.js` | Feedback CRUD, comments, status transitions |
| `useFees.js` | Fee queries, calculations, batch operations |
| `useMeetings.js` | Meeting/calendar event queries |
| `useDisciplineCases.js` | Discipline case queries |
| `useListPreferences.js` | Persisted list view preferences (columns, sort, filters) |
| `useColumnResize.js` | Draggable column resize for data tables |
| `useDocumentTitle.js` | Dynamic page title updates |
| `useEngagementTracking.js` | User engagement analytics |
| `useInstallPrompt.js` | PWA install prompt handling |
| `useOnlineStatus.js` | Online/offline detection |
| `useSharing.js` | Web Share API integration |
| `useTheme.js` | Dark/light theme toggle (localStorage) |
| `useTodoCompletion.js` | Todo completion mutations |
| `useVersionCheck.js` | App version polling and reload prompt |
| `useVOGCount.js` | VOG expiry count for badge display |
| `useVolunteerRoleSettings.js` | Volunteer role configuration |

### Key Components (`src/components/`)
| File | Description |
|------|-------------|
| `layout/Layout.jsx` | Sidebar navigation, capability-based menu filtering |
| `Timeline/TimelineView.jsx` | Activity timeline with notes, activities, todos |
| `Timeline/NoteModal.jsx` | Create/edit notes with @mentions |
| `Timeline/QuickActivityModal.jsx` | Quick activity logging |
| `Timeline/TodoModal.jsx` | Create/edit todos |
| `Timeline/CompleteTodoModal.jsx` | Todo completion dialog |
| `Timeline/GlobalTodoModal.jsx` | Create todo from global context |
| `PersonEditModal.jsx` | Edit person details |
| `PersonAvatar.jsx` | Person photo/initials avatar |
| `ContactEditModal.jsx` | Edit contact info (phone, email, social) |
| `AddressEditModal.jsx` | Edit address |
| `WorkHistoryEditModal.jsx` | Edit work/team history |
| `RelationshipEditModal.jsx` | Manage person relationships |
| `CustomFieldsSection.jsx` | Display custom fields on person detail |
| `CustomFieldsEditModal.jsx` | Edit custom field values |
| `TeamEditModal.jsx` | Edit team details |
| `CommissieEditModal.jsx` | Edit committee details |
| `FinancesCard.jsx` | Person's fee/payment overview |
| `VOGCard.jsx` | Person's VOG status card |
| `SportlinkCard.jsx` | External Sportlink integration data |
| `FeedbackModal.jsx` | Submit new feedback |
| `FeedbackEditModal.jsx` | Edit feedback item |
| `MeetingDetailModal.jsx` | Calendar meeting details |
| `AddAttendeePopup.jsx` | Add attendee to meeting |
| `DisciplineCaseTable.jsx` | Discipline case data table |
| `RichTextEditor.jsx` | Rich text editor for notes/descriptions |
| `MentionInput/MentionInput.jsx` | @mention autocomplete input |
| `SearchableMultiSelect.jsx` | Multi-select with search |
| `Pagination.jsx` | Paginated list navigation |
| `LoadingSpinner.jsx` | Loading states (page, content, inline) |
| `OfflineBanner.jsx` | Offline status notification |
| `InstallPrompt.jsx` | PWA install prompt |
| `IOSInstallModal.jsx` | iOS-specific install instructions |
| `ReloadPrompt.jsx` | New version reload prompt |
| `PullToRefreshWrapper.jsx` | Mobile pull-to-refresh |
| `ShareModal.jsx` | Share person/team via Web Share API |
| `TabButton.jsx` | Tab navigation component |
| `DashboardCard.jsx` | Dashboard card wrapper |
| `DashboardCustomizeModal.jsx` | Dashboard card arrangement |
| `TodoItem.jsx` | Single todo display |
| `DomErrorBoundary.jsx` | React error boundary |

## Data Model

### Custom Post Types
| Slug | Description | Key ACF Fields |
|------|-------------|----------------|
| `person` | Contact records | contact_info (repeater), work_history (repeater), relationships, photo_gallery |
| `team` | Teams | logo, industry, contact details |
| `commissie` | Committees | staff members, team structure |
| `rondo_todo` | Tasks | assigned_to, due_date, linked person |
| `rondo_feedback` | User feedback | feedback_type, status, priority, agent workflow meta |
| `calendar_event` | Calendar events | start/end time, attendees, location |
| `discipline_case` | Discipline tracking | season, involved people, status |

### ACF Field Groups (`acf-json/`)
- `group_person_fields.json` — Person: contact_info, work_history, relationships, social, sportlink_id
- `group_team_fields.json` — Team: logo, industry, description
- `group_commissie_fields.json` — Committee: staff, structure
- `group_todo_fields.json` — Todo: assignment, dates, linked person
- `group_feedback_fields.json` — Feedback: type, status, priority, steps_to_reproduce
- `group_discipline_case_fields.json` — Discipline: season, people, status
- `group_relationship_type_fields.json` — Relationship type: inverse label, category

### API Namespaces
- `/wp/v2/` — Standard WordPress REST (person, team, commissie, rondo_todo)
- `/rondo/v1/` — Custom endpoints (dashboard, search, timeline, feedback, calendar, custom-fields)

## Build & Config
| File | Description |
|------|-------------|
| `vite.config.js` | Vite build config, React plugin, path aliases |
| `tailwind.config.js` | Tailwind CSS v4 with OKLCH brand colors |
| `package.json` | Dependencies: React 18, React Router 6, TanStack Query, Axios, Tailwind |
| `style.css` | WordPress theme header (version number lives here) |
| `bin/deploy.sh` | Production deploy script (rsync + cache clear) |
