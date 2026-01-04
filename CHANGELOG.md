# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.99] - 2024-12-19

### Fixed
- Family Tree: Fixed inverted getParents/getChildren logic
- Family Tree: Relationship type describes WHO the neighbor is (not the person's role)
- Family Tree: If person has "parent" relationship to neighbor, neighbor IS their parent
- Family Tree: Tree now correctly shows parents above children

## [1.0.98] - 2024-12-19

### Changed
- Family Tree: Complete rewrite of tree building algorithm with clear two-phase approach
- Family Tree: Phase 1 collects all relevant family members (ancestors + their siblings, descendants)
- Family Tree: Phase 2 builds tree from root ancestors downward
- Family Tree: Clean helper functions (getParents, getChildren, getSiblings, findRoots)
- Family Tree: Properly handles multiple lineages with virtual root
- Family Tree: Each person included only once in tree
- Family Tree: Removed complex legacy logic and excessive comments

## [1.0.97] - 2024-12-19

### Fixed
- Family Tree: Fix adjacency list to use correct inverse relationship types
- Family Tree: When edge is "parent", reverse edge should be "child" (and vice versa)
- Family Tree: Fix tree hierarchy so parents appear above children (not inverted)
- Family Tree: Ensure siblings are correctly identified as children of same parent

## [1.0.96] - 2024-12-19

### Fixed
- Family Tree: Refactor findUltimateAncestor to collect ALL ancestors and find eldest by birth date
- Family Tree: Use BFS traversal to find all ancestors (not just those with no parents)
- Family Tree: Ensure current person is included in tree and verify inclusion
- Family Tree: Tree now flows from eldest ancestor (top) down to current person and all descendants

## [1.0.95] - 2024-12-19

### Fixed
- Family Tree: Find oldest ancestor by birth date when multiple people have no parents
- Family Tree: Include siblings in tree visualization (children of same parents)
- Family Tree: Sort siblings by birth date (oldest first)
- Family Tree: Ensure tree flows from oldest (top) to youngest (bottom)

## [1.0.94] - 2024-12-19

### Fixed
- Family Tree: Auto-center and auto-zoom tree on initial render
- Family Tree: Remove blue connector dots, center nodes on connector points
- Family Tree: Fix children ordering - ensure oldest appears first (leftmost)
- Family Tree: Position nodes so connector lines connect to center-top of cards

## [1.0.93] - 2024-12-19

### Changed
- Family Tree: Switched back to react-d3-tree from react-family-tree
- Family Tree: Rewrote TreeVisualization component to use react-d3-tree
- Family Tree: Updated PersonNode to work with foreignObject rendering
- Family Tree: Configured vertical orientation with proper spacing
- Family Tree: Added zoom and pan controls

## [1.0.92] - 2024-12-19

### Fixed
- Family Tree: Center nodes on connector points by offsetting by half node size
- Family Tree: Add padding to container so connectors above nodes are visible
- Family Tree: Ensure connecting lines align with center of person blocks

## [1.0.91] - 2024-12-19

### Fixed
- Family Tree: Separate node display size from spacing (160x100 display, 220x140 spacing)
- Family Tree: Increase spacing between nodes for visible connecting lines
- Family Tree: Reverse children order to compensate for library's rendering order
- Family Tree: Ensure oldest person appears at top of tree

## [1.0.90] - 2024-12-19

### Fixed
- Family Tree: Sort children by birth date (oldest first) in tree builder
- Family Tree: Increase node dimensions (180x120) for better spacing and visibility
- Family Tree: Sort children when building relationships to ensure correct order

## [1.0.89] - 2024-12-19

### Fixed
- Family Tree: Fix node positioning using transform translate instead of absolute positioning
- Family Tree: Library calculates positions using left/top multiplied by half dimensions
- Family Tree: Add absolute positioning class to PersonNode for proper rendering

## [1.0.88] - 2024-12-19

### Fixed
- Family Tree: Prevent duplicate relations in parents/children/siblings arrays
- Family Tree: Added comprehensive validation before rendering
- Family Tree: Create deep copy of nodes array for immutability
- Family Tree: Added detailed logging of node structure for debugging
- Family Tree: Better error handling when root node not found

## [1.0.87] - 2024-12-19

### Fixed
- Family Tree: Filter relations to only include IDs that exist in nodes array
- Family Tree: Prevent library errors from referencing non-existent node IDs
- Family Tree: Added validation to ensure all relation IDs are valid
- Family Tree: Added debugging logs to help diagnose issues

## [1.0.86] - 2024-12-19

### Fixed
- Family Tree: Added comprehensive validation and normalization of node structure
- Family Tree: Ensure all relation objects have valid id and type properties
- Family Tree: Filter out any invalid nodes before passing to library
- Family Tree: Normalize all IDs to strings in relations

## [1.0.85] - 2024-12-19

### Fixed
- Family Tree: Fixed "Cannot read properties of undefined (reading 'find')" error
- Family Tree: Ensure all arrays (parents, children, siblings) are always initialized
- Family Tree: Added defensive checks to prevent undefined array access
- Family Tree: Final validation pass to ensure all nodes have required arrays

## [1.0.84] - 2024-12-19

### Fixed
- Family Tree: Convert IDs to strings (react-family-tree expects string IDs, not numbers)
- Family Tree: Added better error handling and logging for debugging
- Family Tree: Prevent duplicate nodes in flat nodes array
- Family Tree: Improved validation of node structure before processing

## [1.0.83] - 2024-12-19

### Fixed
- Family Tree: Fixed JavaScript error "Cannot read properties of undefined (reading 'length')"
- Family Tree: Added proper null/undefined checks in tree traversal
- Family Tree: Ensure children array always exists (even if empty) for react-family-tree compatibility

## [1.0.82] - 2024-12-19

### Changed
- Family Tree: Switched from react-d3-tree to react-family-tree library
- Family Tree: Now correctly displays oldest ancestors at top, youngest descendants at bottom
- Family Tree: Updated TreeVisualization and PersonNode components for new library API

## [1.0.81] - 2024-12-19

### Fixed
- Family Tree: Ensured tree builds downward from ultimate ancestor (oldest person)
- Family Tree: Ultimate ancestor (oldest, no parents) now appears at top of tree
- Family Tree: All descendants appear below, maintaining proper hierarchy

## [1.0.80] - 2024-12-19

### Fixed
- Family Tree: Fixed duplicate people appearing in tree
- Family Tree: Changed logic to traverse up to find ultimate ancestor, then build tree downward
- Family Tree: Prevents cycles and ensures each person appears only once

## [1.0.79] - 2024-12-19

### Fixed
- Family Tree: Simplified to only show parent/child relationships (ignores niece/nephew/aunt/uncle)
- Family Tree: Fixed hierarchy - parents now correctly appear above root person
- Family Tree: Fixed multiple parents display - all parents now show as siblings at top level
- Family Tree: Corrected relationship direction logic

## [1.0.78] - 2024-12-19

### Fixed
- Family Tree: Fixed hierarchy display - parents now appear above root, children below
- Family Tree: Fixed name truncation by increasing node width and using break-words
- Family Tree: Fixed relationship direction logic (child relationship means parent of root)
- Family Tree: Improved tree structure to show proper up/down hierarchy

## [1.0.77] - 2024-12-19

### Changed
- Family Tree: Increased person node size to fully display gender icon
- Family Tree: Added date of birth display in dd-mm-yyyy format on person nodes
- Family Tree: Improved node spacing and layout

## [1.0.76] - 2024-12-19

### Fixed
- Family Tree: Fixed "Unknown" node names by properly extracting person names from various data formats
- Family Tree: Fixed relationship parsing to handle REST API expanded relationship format (relationship_slug field)
- Family Tree: Improved age calculation from birth_date field
- Family Tree: Added better error handling and debugging

## [1.0.75] - 2024-12-19

### Added
- Family Tree visualization: New family tree feature to visualize family relationships
- Family Tree page: Accessible from person detail page, shows hierarchical family tree
- Tree visualization component: Interactive tree with zoom, pan, and node navigation
- Person nodes: Display person photos, names, ages, and gender symbols in tree
- Tree builder utilities: Builds family tree structure from relationship data
- Family relationship filtering: Automatically filters to show only family relationships (parent, child, sibling, etc.)

### Changed
- Person Detail page: Added "View Family Tree" button in Relationships section

## [1.0.74] - 2024-12-19

### Fixed
- JavaScript error: Fixed "data is not defined" error when saving relationships
- Gender-dependent inverse resolution: Fixed logic to correctly resolve aunt/uncle → niece/nephew based on related person's gender
- Inverse mapping: Aunt can now correctly map to either Niece or Nephew depending on the related person's gender

### Changed
- Gender resolution: When source type is gender-dependent (aunt/uncle), inverse is resolved to target group (niece/nephew) based on related person's gender

## [1.0.73] - 2024-12-19

### Added
- Default relationship configurations: System now ships with pre-configured inverse mappings and gender-dependent settings
- Restore defaults button: Added "Restore Defaults" button in Relationship Types settings page
- REST API endpoint: `/prm/v1/relationship-types/restore-defaults` to restore default configurations
- Automatic setup: Default configurations are applied when relationship types are first created

### Changed
- Relationship type initialization: Now automatically sets up inverse mappings and gender-dependent groups on first run

## [1.0.72] - 2024-12-19

### Added
- Gender-dependent relationship types: Support for gender-aware inverse relationship resolution
- ACF fields: Added `is_gender_dependent` and `gender_dependent_group` fields to relationship types
- Automatic gender resolution: System automatically resolves gender-dependent types (e.g., aunt/uncle → niece/nephew) based on related person's gender
- Helper functions: `resolve_gender_dependent_inverse()`, `get_types_in_gender_group()`, `infer_gender_type_from_group()`

### Changed
- Inverse relationship sync: Now checks for gender-dependent types and resolves to correct specific type
- Relationship type configuration: Can now mark types as gender-dependent and assign them to groups

## [1.0.71] - 2024-12-19

### Added
- Documentation: Created comprehensive docs/ folder with relationship system documentation
- docs/relationships.md: Complete guide to how bidirectional relationships work
- docs/relationship-types.md: Configuration guide for relationship types and inverse mappings
- docs/architecture.md: Technical architecture documentation with extension points
- README.md: Added links to documentation

## [1.0.70] - 2024-12-19

### Changed
- Relationship Types page: Inverse relationship type selector is now searchable dropdown
- Relationship Types page: Inverse selector includes the type itself (e.g., "Acquaintance" can have "Acquaintance" as inverse)
- Relationship Types page: Improved UX with searchable dropdown similar to person selector

## [1.0.69] - 2024-12-19

### Added
- Settings: New Relationship Types management page accessible from Settings
- Relationship Types page: Edit relationship type names and inverse relationships from the frontend
- Relationship Types page: Create new relationship types with inverse mappings
- Relationship Types page: Delete relationship types
- REST API: Added ACF field support for relationship_type taxonomy terms

## [1.0.68] - 2024-12-19

### Changed
- Inverse relationships: Moved inverse relationship mappings from hardcoded PHP array to ACF taxonomy field
- Relationship types now have an "Inverse Relationship Type" field that can be configured in WordPress admin
- Removed hardcoded `$inverse_mappings` array from `PRM_Inverse_Relationships` class

## [1.0.67] - 2024-12-19

### Fixed
- Monica import: Gender field is now properly imported from Monica CRM SQL exports (maps M→male, F→female, O→prefer_not_to_say)

## [1.0.66] - 2024-12-19

### Added
- Person form: Added gender field with dropdown selection (Male, Female, Non-binary, Other, Prefer not to say)
- Person detail: Gender symbol (♂/♀/⚧) now displays left of age
- Relationships: Automatic bidirectional relationship synchronization - when a relationship is created/updated/deleted from person A to person B, the inverse relationship is automatically created/updated/deleted from B to A
- Inverse relationship mappings for all relationship types (e.g., Parent ↔ Child, Boss ↔ Subordinate, Spouse ↔ Spouse)

### Changed
- Person form: Gender field changed from text input to select dropdown
- Cache invalidation: Related person cache is now invalidated when relationships are updated, ensuring UI reflects inverse relationships immediately

## [1.0.65] - 2024-12-19

### Changed
- Person detail: Contact information section is now hidden for deceased people

## [1.0.64] - 2024-12-19

### Added
- People list: Deceased people now show † next to their name

## [1.0.63] - 2024-12-19

### Added
- Person detail: For deceased people, shows † next to their name
- Person detail: Displays death date and age at death instead of current age for deceased people
- Person detail: "Died" date type now displays † as its icon

## [1.0.62] - 2024-12-19

### Changed
- Companies list: Companies are now sorted alphabetically by name

## [1.0.61] - 2024-12-19

### Changed
- Person detail: Relationships are now sorted by age (descending - oldest first)

## [1.0.60] - 2024-12-19

### Fixed
- Company form: Fixed `getCompany` API method to accept params (including `_embed`), ensuring logos display on company list and in work history
- Company form: Added explicit query refetching after logo upload to ensure embedded media data is refreshed

## [1.0.59] - 2024-12-19

### Fixed
- Company form: Created custom REST endpoint to set company logo using WordPress `set_post_thumbnail()` function, ensuring featured image is properly saved

## [1.0.58] - 2024-12-19

### Fixed
- Company form: Fixed logo upload payload structure - featured_media now properly saved

## [1.0.57] - 2024-12-19

### Fixed
- Company detail: Logo now properly loads and displays on company detail page

## [1.0.56] - 2024-12-19

### Added
- Company form: Logo upload functionality when editing a company
- Person detail: Company logos now displayed in work history section instead of generic icon

## [1.0.55] - 2024-12-19

### Changed
- Dates overview: Today's dates now display in green (matching dashboard reminders)
- Dates overview: Removed days-until indicators, showing only the date number

## [1.0.54] - 2024-12-19

### Added
- Favicon: Added sparkles favicon (SVG) to match the app branding

## [1.0.53] - 2024-12-19

### Changed
- Layout: Changed sidebar logo icon from Home to Sparkles

## [1.0.52] - 2024-12-19

### Changed
- Rebranded application from "Oikos" to "Koinastra"
- Centralized app name configuration in `src/constants/app.js` for easy future changes
- All app name references now use the centralized `APP_NAME` constant

## [1.0.51] - 2024-12-19

### Fixed
- Date form: Prevented form reset from clearing date type selection after user selects a value
- Date form: Added form initialization tracking to prevent unwanted resets

## [1.0.50] - 2024-12-19

### Fixed
- Date form: Date type select now properly updates when selecting a value

## [1.0.49] - 2024-12-19

### Changed
- Person detail: Important dates are now ordered by date ascending (earliest first)

## [1.0.48] - 2024-12-19

### Added
- Layout: Added home icon next to Oikos title in the sidebar

## [1.0.47] - 2024-12-19

### Changed
- Design: Switched color palette from blue to warmer amber tones throughout the application

## [1.0.46] - 2024-12-19

### Changed
- Rebranded application from "Personal CRM" to "Oikos" across all user-facing text
- Updated logo, welcome message, document titles, and email reminders

## [1.0.45] - 2024-12-19

### Added
- User menu: Shows current user's avatar with dropdown menu
- User menu: "Edit profile" link to WordPress user profile page
- User menu: "WordPress admin" link (only visible for admin users)
- Backend: New REST endpoint `/prm/v1/user/me` to get current user information

## [1.0.44] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders happening today now display in green instead of red

## [1.0.43] - 2024-12-19

### Added
- Person form: Email field when creating a new person
- Person form: Automatically fetches and sets Gravatar profile photo if email has a Gravatar
- Backend: New REST endpoint to sideload Gravatar images for people

## [1.0.42] - 2024-12-19

### Fixed
- Date form: People selector now shows all people, not just the first 100
- Date form: Uses pagination-aware usePeople hook instead of limited direct query

## [1.0.41] - 2024-12-19

### Added
- Person detail: Click on person's photo to upload/change their picture
- Person detail: Photo upload with file validation (image files only, max 5MB)
- Person detail: Loading indicator during photo upload

## [1.0.40] - 2024-12-19

### Added
- Work history: Can now set both "current job" and a future end date simultaneously
- Work history: Daily cron job automatically sets is_current=false when end_date passes

### Changed
- Work history form: End date field is no longer disabled when "current job" is checked
- Work history form: End date can be set even for current positions to schedule automatic transition

## [1.0.39] - 2024-12-19

### Fixed
- Company detail: Employees with end dates in the future are now correctly shown as current employees, not former

## [1.0.38] - 2024-12-19

### Fixed
- Person form: Now properly sets post title when creating or updating a person
- Person form: Data storage now works correctly

### Added
- Person form: Birthday field when creating a new person
- Person form: Automatically creates an important_date post for birthday when provided

## [1.0.37] - 2024-12-19

### Added
- People list: Sort controls to sort by first name or last name, ascending or descending
- People list: Default sorting is now first name ascending (changed from last name)

### Changed
- People list: Sorting now uses a dropdown selector and order toggle button

## [1.0.36] - 2024-12-19

### Changed
- Relationship form: Related Person field now uses a searchable dropdown instead of a simple select
- Relationship form: People are sorted alphabetically by first name, ascending
- Relationship form: Search works by name, first name, or last name

## [1.0.35] - 2024-12-19

### Changed
- Person detail: Work history is now sorted by start date descending (most recent first)
- Person detail: Current positions appear at the top of the work history list

## [1.0.34] - 2024-12-19

### Fixed
- Person detail: HTML entities in relationship names and labels are now properly decoded and displayed

## [1.0.33] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now correctly pre-selects the current relationship type when editing

## [1.0.32] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now shows all available types (increased limit from 10 to 100)

## [1.0.31] - 2024-12-19

### Fixed
- Person detail: Edit and Add Relationship buttons now navigate to dedicated relationship form instead of person edit form
- Person detail: Relationship form allows editing individual relationships without editing the entire person

### Added
- Person detail: New RelationshipForm component for adding and editing relationships independently

## [1.0.30] - 2024-12-19

### Added
- Person detail: Add and remove labels directly from the person detail page
- Person detail: Remove button (X) appears on hover for each label
- Person detail: Dropdown selector to add new labels from available labels

## [1.0.29] - 2024-12-19

### Changed
- Person detail: LinkedIn contact types now show LinkedIn icon instead of label text
- Person detail: LinkedIn icon styled with brand color (blue-600)

## [1.0.28] - 2024-12-19

### Changed
- Company detail: Removed person-level access control restriction - if you can view a company, you can see all its employees
- Company detail: Now checks company access instead of filtering employees by person-level access permissions

## [1.0.27] - 2024-12-19

### Fixed
- Company detail: Fixed company people query by removing unreliable meta_query with ACF repeater fields and filtering in PHP instead
- Company detail: Now properly finds people by checking work_history using ACF's get_field() function which handles repeater fields correctly

## [1.0.26] - 2024-12-19

### Fixed
- Company detail: Fixed bug where employees weren't showing due to missing admin check in access control filtering
- Company detail: Fixed type comparison issue between company IDs (string vs integer) that prevented matching work history entries

## [1.0.25] - 2024-12-19

### Changed
- Performance: Optimized company people query to apply access control filtering early, reducing query scope
- Performance: People list now batches company fetches into a single API call instead of individual queries (fixes N+1 query problem)
- Performance: Made `get_accessible_post_ids` method public in access control class for reuse in optimized queries

## [1.0.24] - 2024-12-19

### Changed
- Company detail: Reorganized employee display into two separate sections: "Current Employees" and "Former Employees"
- Company detail: Each section now has its own card for better visual separation
- Company detail: Improved empty states for both current and former employee sections

## [1.0.23] - 2024-12-19

### Changed
- People list: Now fetches all people using pagination (removed 100 person limit)
- People list: Added lazy loading for person thumbnails to improve page load performance
- Dashboard: Added lazy loading for person thumbnails in Recent People and Reminders sections
- Dates list: Added lazy loading for person thumbnails
- Company detail: Added lazy loading for employee thumbnails

## [1.0.22] - 2024-12-19

### Added
- People list: Filter functionality with favorites toggle and label multi-select
- People list: Active filter chips showing applied filters with quick remove options
- People list: Filter button shows badge count when filters are active
- People list: "No results" state when filters don't match any people

## [1.0.21] - 2024-12-19

### Removed
- Removed duplicate search box from People list page (global search in top bar is sufficient)

## [1.0.20] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders now link to the related person's detail page when clicked
- Dashboard: Increased reminder photo size from 6x6 to 10x10 pixels for better visibility

## [1.0.19] - 2024-12-19

### Added
- Added WhatsApp button for mobile phone numbers in contact details (opens WhatsApp with the phone number)

## [1.0.18] - 2024-12-19

### Fixed
- Fixed tel: links for phone numbers: now properly removes Unicode marks and all non-digit characters (except + at the start)

## [1.0.17] - 2024-12-19

### Fixed
- Changed ACF repeater fields to use empty arrays `[]` instead of `null` when empty, as WordPress REST API requires arrays

## [1.0.16] - 2024-12-19

### Fixed
- Fixed error when saving contact details: ACF repeater fields (work_history, relationships) are now properly formatted as arrays or null

## [1.0.15] - 2024-12-19

### Fixed
- Person names now properly decode HTML entities (e.g., &#8211; displays as a normal dash)

## [1.0.14] - 2024-12-19

### Changed
- People list: People are now sorted alphabetically by last name (with first name as secondary sort)
- People list: Company name is now displayed below each person's name (shows current company or most recent)

## [1.0.13] - 2024-12-19

### Added
- Contact information: Website and URL type contacts (LinkedIn, Twitter, Instagram, Facebook) are now clickable links opening in a new tab
- Contact information: Address type contacts now link to Google Maps opening in a new tab

## [1.0.12] - 2024-12-19

### Fixed
- Date type dropdown now fetches all date types (increased limit from 10 to 100)

## [1.0.11] - 2024-12-19

### Changed
- Date type dropdown now properly fetches and displays all date types from the Date Types taxonomy
- Date types are sorted alphabetically for better user experience

## [1.0.10] - 2024-12-19

### Changed
- Removed "Anniversary" date type, replaced with "Wedding" date type
- Wedding date type now auto-generates title as "Wedding of <person 1> & <person 2>" format
- Updated auto-title generation logic to handle wedding dates with proper format

## [1.0.9] - 2024-12-19

### Changed
- Person detail page: Contact detail edit button now navigates to dedicated contact detail edit form instead of person edit screen
- Person detail page: "Add contact detail" button now navigates to dedicated contact detail form instead of person edit screen

### Added
- Added dedicated Contact Detail form page for adding and editing individual contact details

## [1.0.8] - 2024-12-19

### Changed
- Person detail page: Work history edit button now navigates to dedicated work history edit form instead of person edit screen
- Added dedicated Work History form page for editing individual work history items

## [1.0.7] - 2024-12-19

### Changed
- Person detail page: Work History section now always visible (even when empty)
- Person detail page: Company names now displayed instead of "View Company" link in work history
- Person detail page: Added "Add Work History" button to Work History section

### Added
- Person detail page: Added edit button for each work history item
- Person detail page: Added delete button for each work history item

## [1.0.6] - 2024-12-19

### Added
- Person detail page: Added delete button for each contact detail
- Person detail page: Added delete button for each important date
- Person detail page: Added delete button for each relationship
- Person detail page: Added delete button for each note/timeline item
- Person detail page: Added edit button for each relationship

## [1.0.5] - 2024-12-19

### Changed
- Person detail page: Show person labels underneath age in the main card
- Person detail page: Removed birthday from main card, now appears as first date in Important Dates card
- Person detail page: Contact Information card now always visible with "Add contact detail" button
- Person detail page: Email fields are now clickable mailto: links
- Person detail page: Phone numbers are now clickable tel: links (spaces and dashes removed)
- Person detail page: Added edit button for each contact field
- Person detail page: Important Dates card now always visible with "Add Important Date" button
- Person detail page: Relationships card now always visible with "Add Relationship" button

## [1.0.4] - 2024-12-19

### Fixed
- Fixed 404 errors when navigating to individual person, company, or date pages
- WordPress now properly serves index.php for all app routes, allowing React Router to handle routing
- Disabled rewrite rules for custom post types to prevent URL conflicts
- Fixed React error #310 ("Rendered more hooks than during the previous render") by removing `useParams()` from `useRouteTitle` hook
- `useRouteTitle` now extracts route IDs from pathname instead of using `useParams()`, ensuring consistent hook calls regardless of route context
- Fixed React error #310 on Person Detail pages by moving `useDocumentTitle` hook calls before early returns in all detail and form components
- All hooks are now called consistently on every render, even during loading/error states
- Fixed "Rendered more hooks than during the previous render" error by ensuring useSearch always receives a string (never null)
- Fixed minified React error caused by improper handling of empty search queries
- Added safety checks for search results to prevent property access errors
- Note: After updating, you may need to flush rewrite rules by going to Settings > Permalinks and clicking "Save Changes"

## [1.0.3] - 2024-12-19

### Fixed
- Search form now works and displays results in a dropdown
- Search form is now center-aligned in the header
- User menu placeholder is now right-aligned

## [1.0.2] - 2024-12-19

### Fixed
- Page title now updates dynamically based on current route instead of always showing "Page not found"
- Document title now shows appropriate page names (Dashboard, People, Companies, etc.) and entity names for detail pages

## [1.0.1] - 2024-12-19

### Changed
- Important Dates overview now uses masonry layout for date blocks
- Increased month heading size on Important Dates overview screen

