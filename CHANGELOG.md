# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

