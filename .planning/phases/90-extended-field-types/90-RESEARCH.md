# Phase 90: Extended Field Types - Research

**Researched:** 2026-01-18
**Domain:** ACF field types (image, file, link, color_picker, post_object), React media uploads, color picker components
**Confidence:** HIGH

## Summary

This phase extends the custom fields system built in Phase 89 with five advanced field types: Image, File, Link, Color, and Relationship. The existing custom fields infrastructure (Manager class, REST API, Settings UI) provides a solid foundation that needs extension rather than replacement.

The approach leverages ACF's native field types which are already well-integrated with WordPress. Image and File fields use ACF's built-in types with media attachment storage. Link fields use ACF's link type returning URL/title/target arrays. Color fields use ACF's color_picker with hex-only format. Relationship fields use ACF's post_object type configured for People and Organizations.

**Primary recommendation:** Extend the existing Manager class with new field type configurations and the FieldFormPanel with type-specific options for each extended type. Use WordPress REST API for media uploads (direct to /wp/v2/media), ACF's native field types for all storage, and @uiw/react-color for the color picker UI.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ACF Pro | Current | Field type infrastructure | Already used, provides image/file/link/color_picker/post_object types |
| @uiw/react-color | 2.9.x | Color picker UI | User decision in CONTEXT.md, modern React component |
| WordPress Media REST API | WP 6.0+ | Media uploads | Standard WP approach, already used for uploads |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @uiw/react-color-sketch | 2.9.x | Sketch-style picker | For square saturation+brightness with hue slider |
| lucide-react | Current | File type icons | Display file type indicators |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| @uiw/react-color | react-color | react-color is older, @uiw is more modern and specified in CONTEXT.md |
| Direct upload to WP | WordPress Media Library modal | User explicitly wants direct upload button, not modal |
| Custom upload endpoint | Standard /wp/v2/media | Standard endpoint is sufficient, custom naming not required for custom fields |

**Installation:**
```bash
npm install @uiw/react-color-sketch
```

Note: Install only the sketch subpackage for smaller bundle size.

## Architecture Patterns

### Existing Infrastructure to Extend

The Phase 89 implementation provides:

1. **Manager class** (`includes/customfields/class-manager.php`):
   - `create_field()` - Creates ACF fields with type-specific options
   - `update_field()` - Updates field properties via UPDATABLE_PROPERTIES constant
   - `get_fields()` - Returns fields for a post type
   - Key pattern: namespace by post_type with `field_custom_{post_type}_{slug}` keys

2. **REST API** (`includes/class-rest-custom-fields.php`):
   - GET/POST `/prm/v1/custom-fields/{post_type}` - List and create
   - GET/PUT/DELETE `/prm/v1/custom-fields/{post_type}/{key}` - Single field CRUD
   - Parameter definitions in `get_create_params()` and `get_update_params()`

3. **Settings UI** (`src/components/FieldFormPanel.jsx`):
   - Form state management with type-specific options
   - `renderTypeOptions()` switch for type-specific config UI
   - Field types list in FIELD_TYPES array
   - Extended types already in list but not implemented

### ACF Field Type Configurations

**Image Field:**
```php
[
    'type' => 'image',
    'return_format' => 'array',  // Returns {url, id, title, alt, sizes, etc.}
    'preview_size' => 'thumbnail',
    'library' => 'all',  // 'all' or 'uploadedTo'
    'mime_types' => '',  // Empty = all types (per CONTEXT.md decision)
]
```

**File Field:**
```php
[
    'type' => 'file',
    'return_format' => 'array',  // Returns {url, id, filename, title, mime_type, etc.}
    'library' => 'all',
    'mime_types' => '',  // Empty = all types (per CONTEXT.md decision)
]
```

**Link Field:**
```php
[
    'type' => 'link',
    'return_format' => 'array',  // Returns {url, title, target}
]
```

**Color Field:**
```php
[
    'type' => 'color_picker',
    'default_value' => '',
    'enable_opacity' => 0,  // Hex only per CONTEXT.md
    'return_format' => 'string',  // Returns '#FF5733'
]
```

**Relationship Field (using post_object):**
```php
[
    'type' => 'post_object',
    'post_type' => ['person', 'company'],  // Both types always
    'return_format' => 'id',  // Returns post ID(s)
    'multiple' => 0,  // 0 = single, 1 = multiple (configurable)
    'allow_null' => 1,
]
```

### Frontend Component Patterns

**Media Upload Pattern (existing in codebase):**
```javascript
// From src/api/client.js - existing pattern
uploadMedia: (file) => {
  const formData = new FormData();
  formData.append('file', file);
  return api.post('/wp/v2/media', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
},
```

**Search/Selection Pattern (existing in codebase):**
```javascript
// From src/components/ImportantDateModal.jsx - PeopleSelector component
// Shows search-as-you-type with chips for selected items
// This pattern applies to Relationship field
```

**Color Picker Pattern (@uiw/react-color-sketch):**
```javascript
import { Sketch } from '@uiw/react-color-sketch';

function ColorPicker({ value, onChange }) {
  return (
    <Sketch
      color={value || '#000000'}
      onChange={(color) => onChange(color.hex)}
    />
  );
}
```

### Recommended Project Structure Changes

```
includes/customfields/
├── class-manager.php           # Extend with new field type options

src/components/
├── FieldFormPanel.jsx          # Add renderTypeOptions cases for new types
├── ColorPickerInput.jsx        # New: Color picker wrapper component
├── RelationshipSelector.jsx    # New: Entity search/select component (reusable)
└── FileUploader.jsx            # New: Direct file upload component
```

### Anti-Patterns to Avoid
- **Custom upload endpoints per field type:** Use standard /wp/v2/media for all uploads
- **Storing file URLs instead of IDs:** Always store attachment IDs, retrieve URLs via ACF
- **Custom color picker implementation:** Use @uiw/react-color, don't build from scratch
- **WordPress Media Library modal:** User explicitly requested direct upload button

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Color picker UI | Custom color input/canvas | @uiw/react-color-sketch | Complex UI, accessibility, touch support |
| File upload handling | Custom upload endpoint | /wp/v2/media REST endpoint | Handles all WP media processing |
| File type icons | Custom icon mapping | lucide-react File/Image icons or mime-type detection | Standard icon library already in use |
| Relationship search | Custom entity search | Existing prmApi.search() endpoint | Already searches People and Organizations |
| Image preview | Custom preview component | HTML img tag with attachment URL | ACF returns full image data |

**Key insight:** ACF already handles all the complex field storage, validation, and retrieval. The work is in UI components and connecting them to ACF field configurations.

## Common Pitfalls

### Pitfall 1: Media Upload Race Condition
**What goes wrong:** User uploads file, submits form before upload completes
**Why it happens:** Async upload returns attachment ID after form state captured
**How to avoid:** Disable form submission during upload, show upload progress
**Warning signs:** Null attachment IDs saved, "ghost" uploads in media library

### Pitfall 2: ACF Return Format Mismatch
**What goes wrong:** Frontend expects array, ACF returns ID or vice versa
**Why it happens:** Inconsistent return_format settings between creation and retrieval
**How to avoid:** Always use 'array' return_format for image/file/link; store IDs for relationships
**Warning signs:** "Cannot read property 'url' of undefined" errors

### Pitfall 3: Color Picker Z-Index Issues
**What goes wrong:** Color picker dropdown appears behind other elements
**Why it happens:** Stacking context in slide-over panels
**How to avoid:** Use portal/popover pattern or ensure proper z-index stacking
**Warning signs:** Picker visible but not clickable, disappears behind panel

### Pitfall 4: Relationship Field Infinite Loops
**What goes wrong:** Query for entities triggers itself recursively
**Why it happens:** ACF expanding relationships during REST response preparation
**How to avoid:** Use 'id' return format for post_object, expand in frontend
**Warning signs:** Slow page loads, timeout errors, deep nested data

### Pitfall 5: File Type Detection
**What goes wrong:** Wrong icon displayed for file type
**Why it happens:** MIME type not reliable, extension varies
**How to avoid:** Use file extension for icon selection, fallback to generic
**Warning signs:** PDF showing image icon, or all files showing same icon

## Code Examples

Verified patterns from official sources and existing codebase:

### Manager Class Extension
```php
// Add to UPDATABLE_PROPERTIES in class-manager.php
// Source: ACF documentation
private const UPDATABLE_PROPERTIES = array(
    // ... existing properties ...
    // Image/File options
    'return_format',
    'preview_size',
    'library',
    'mime_types',
    // Color options
    'enable_opacity',
    // Post Object options
    'post_type',
    'multiple',
    'allow_null',
);
```

### REST API Parameter Extension
```php
// Add to get_create_params() in class-rest-custom-fields.php
// Source: ACF documentation
'return_format' => array(
    'type'        => 'string',
    'description' => 'Return format (array, id, url)',
),
'library' => array(
    'type'        => 'string',
    'description' => 'Media library scope (all, uploadedTo)',
),
'mime_types' => array(
    'type'        => 'string',
    'description' => 'Allowed MIME types (comma-separated)',
),
'multiple' => array(
    'type'        => 'boolean',
    'description' => 'Allow multiple selections (relationship)',
),
'post_type' => array(
    'type'        => 'array',
    'description' => 'Allowed post types for relationship',
),
```

### Color Picker Integration
```javascript
// Source: @uiw/react-color GitHub
import { Sketch } from '@uiw/react-color-sketch';

// In FieldFormPanel.jsx renderTypeOptions()
case 'color':
  return (
    <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
      <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">
        Color Options
      </h4>
      <p className="text-sm text-gray-500">
        Users will select colors using a hex color picker (#RRGGBB format).
      </p>
    </div>
  );
```

### Media Upload Component Pattern
```javascript
// Based on existing PersonDetail.jsx upload pattern
const handleFileUpload = async (file) => {
  setIsUploading(true);
  try {
    const response = await wpApi.uploadMedia(file);
    // response.data contains { id, source_url, title, ... }
    return response.data.id; // Store attachment ID
  } finally {
    setIsUploading(false);
  }
};
```

### Relationship Selector Pattern
```javascript
// Based on existing PeopleSelector in ImportantDateModal.jsx
// Extend to search both People and Organizations
const searchEntities = async (query) => {
  const response = await prmApi.search(query);
  return [
    ...response.data.people.map(p => ({ ...p, type: 'person' })),
    ...response.data.companies.map(c => ({ ...c, type: 'company' })),
  ];
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom file upload endpoints | /wp/v2/media REST endpoint | WP 4.7+ | Standardized media handling |
| ACF post_object field | ACF relationship field | ACF 5.0+ | Better bidirectional support |
| inline color inputs | Component libraries | 2020+ | Better UX, accessibility |

**Deprecated/outdated:**
- Using `wp.media` JavaScript API for programmatic uploads - prefer REST API
- ACF `return_format => 'object'` for post_object - use 'id' and expand in frontend

## Open Questions

Things that couldn't be fully resolved:

1. **Relationship Field Display in Settings**
   - What we know: Field can be single or multiple cardinality
   - What's unclear: Should settings UI show preview of how field will look?
   - Recommendation: Start simple, just show cardinality toggle

2. **Color Picker Positioning**
   - What we know: @uiw/react-color-sketch works well
   - What's unclear: Best placement in slide-over panel context
   - Recommendation: Test z-index, may need portal approach

3. **File Type Icon Mapping**
   - What we know: Need to show appropriate icon for file types
   - What's unclear: Complete icon mapping for all MIME types
   - Recommendation: Map common types (pdf, doc, xls, image, video), fallback to generic

## Sources

### Primary (HIGH confidence)
- ACF Official Documentation: [Image](https://www.advancedcustomfields.com/resources/image/), [File](https://www.advancedcustomfields.com/resources/file/), [Link](https://www.advancedcustomfields.com/resources/link/), [Color Picker](https://www.advancedcustomfields.com/resources/color-picker/), [Post Object](https://www.advancedcustomfields.com/resources/post-object/), [Relationship](https://www.advancedcustomfields.com/resources/relationship/)
- [@uiw/react-color GitHub](https://github.com/uiwjs/react-color) - Component API and examples
- Existing codebase: `class-manager.php`, `class-rest-custom-fields.php`, `FieldFormPanel.jsx`

### Secondary (MEDIUM confidence)
- [ACF Register Fields via PHP](https://www.advancedcustomfields.com/resources/register-fields-via-php/) - Programmatic field registration patterns

### Tertiary (LOW confidence)
- None - all findings verified with primary sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - ACF types well-documented, @uiw/react-color actively maintained
- Architecture: HIGH - Extends existing Phase 89 patterns, minimal new infrastructure
- Pitfalls: MEDIUM - Some based on general WordPress/React patterns

**Research date:** 2026-01-18
**Valid until:** 60 days (ACF and React patterns are stable)
