# 10-01 Summary: Note Visibility Controls

## Overview
This plan implemented visibility controls for timeline notes, enabling collaborative note-taking on shared contacts while preserving privacy for personal observations.

## Tasks Completed

### Task 1: Add visibility field to notes in RONDO_Comment_Types
- Added `_note_visibility` comment meta field registration with values 'private' (default) or 'shared'
- Updated `create_note()` method to accept optional `visibility` parameter, defaults to 'private'
- Updated `update_note()` method to accept `visibility` parameter
- Updated `format_comment()` to include `visibility` field in API response
- Updated `get_notes_for_post()` to filter notes based on visibility
- Updated `get_timeline()` to filter notes based on visibility
- Added `filter_notes_by_visibility()` private method implementing the filtering logic:
  - Author always sees their own notes
  - Shared notes visible to anyone who can see the contact
  - Private notes only visible to author

### Task 2: Add visibility toggle to note creation UI
- Updated API client (`src/api/client.js`) to support visibility parameter in `createNote` and `updateNote`
- Updated `useCreateNote` hook to pass visibility parameter to API
- Updated `NoteModal` component to include visibility toggle checkbox
  - Shows Lock/Users icons to indicate current selection
  - Only displays when `isContactShared` prop is true (contact visibility is 'workspace' or 'shared')
  - Default is 'private' (unchecked)
- Updated `PersonDetail.jsx` to pass `isContactShared` prop based on person's visibility setting

### Task 3: Display visibility indicator on timeline notes
- Added Lock and Globe icons to TimelineView import
- Added visibility indicator in the date line for notes:
  - Private notes: Lock icon with tooltip "Private note - only you can see this"
  - Shared notes: Globe icon with tooltip "Shared note"
- Added subtle visual distinction for shared notes:
  - Blue left border (`border-l-2 border-blue-200 pl-2`) for shared notes

## Files Modified
- `includes/class-comment-types.php` - Backend visibility support
- `src/api/client.js` - API client visibility parameter
- `src/hooks/usePeople.js` - useCreateNote hook update
- `src/components/Timeline/NoteModal.jsx` - Visibility toggle UI
- `src/components/Timeline/TimelineView.jsx` - Visibility indicators
- `src/pages/People/PersonDetail.jsx` - Pass isContactShared prop
- `CHANGELOG.md` - Documentation of changes

## Verification
- [x] Notes created via API have visibility field in response
- [x] Private notes only visible to author in API response
- [x] Note creation UI shows visibility toggle on shared contacts
- [x] Timeline displays visibility indicator (Lock/Globe) for notes
- [x] `npm run build` succeeds

## Key Design Decisions
1. Default visibility is 'private' to preserve single-user experience and backward compatibility
2. Visibility toggle only appears when contact is shared (visibility='workspace' or 'shared')
3. Shared notes have both an icon indicator and a subtle blue left border for clear visual distinction
4. Existing notes without visibility meta default to 'private' for backward compatibility
