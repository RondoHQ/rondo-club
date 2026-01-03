# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

