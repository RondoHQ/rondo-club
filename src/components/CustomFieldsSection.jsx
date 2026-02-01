import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pencil, ExternalLink, FileText, Link as LinkIcon, User, Building2 } from 'lucide-react';
import { format, parse, isValid } from '@/utils/dateFormat';
import { Link } from 'react-router-dom';
import { prmApi, wpApi } from '@/api/client';
import { getPersonName, getTeamName } from '@/utils/formatters';
import CustomFieldsEditModal from './CustomFieldsEditModal';

/**
 * Process HTML to make links open in new tabs
 * Adds target="_blank" and rel="noopener noreferrer" to all anchor tags
 */
function processHtmlLinks(html) {
  if (!html || typeof html !== 'string') return html;

  // Add target="_blank" and rel="noopener noreferrer" to all <a> tags
  return html.replace(/<a\s+/gi, '<a target="_blank" rel="noopener noreferrer" ');
}

/**
 * Component to display a relationship item, fetching details if needed
 */
function RelationshipItem({ itemId, allowedPostTypes }) {
  // Fetch the item details
  const { data: itemData, isLoading } = useQuery({
    queryKey: ['relationship-item', itemId],
    queryFn: async () => {
      // Try person first if allowed
      if (allowedPostTypes.includes('person')) {
        try {
          const response = await wpApi.getPerson(itemId, { _embed: true });
          const thumbnail = response.data._embedded?.['wp:featuredmedia']?.[0]?.source_url || null;
          return {
            id: response.data.id,
            type: 'person',
            name: getPersonName(response.data),
            thumbnail,
          };
        } catch {
          // Not a person, try team
        }
      }

      // Try team if allowed
      if (allowedPostTypes.includes('team')) {
        try {
          const response = await wpApi.getTeam(itemId, { _embed: true });
          const thumbnail = response.data._embedded?.['wp:featuredmedia']?.[0]?.source_url || null;
          return {
            id: response.data.id,
            type: 'team',
            name: getTeamName(response.data),
            thumbnail,
          };
        } catch {
          // Not found
        }
      }

      return null;
    },
    staleTime: 60000,
  });

  if (isLoading) {
    return (
      <span className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm">
        Laden...
      </span>
    );
  }

  if (!itemData) {
    return (
      <span className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-500">
        #{itemId}
      </span>
    );
  }

  const linkPath = itemData.type === 'person' ? `/people/${itemData.id}` : `/teams/${itemData.id}`;
  const IconComponent = itemData.type === 'person' ? User : Building2;

  return (
    <Link
      to={linkPath}
      className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-medium transition-colors"
    >
      {itemData.thumbnail ? (
        <img
          src={itemData.thumbnail}
          alt=""
          className="w-6 h-6 rounded-full object-cover"
        />
      ) : (
        <div className="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
          <IconComponent className="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
        </div>
      )}
      {itemData.name}
    </Link>
  );
}

// Convert PHP date format to date-fns format
const phpToDateFnsFormat = (phpFormat) => {
  const formatMap = {
    'd': 'dd',
    'j': 'd',
    'm': 'MM',
    'n': 'M',
    'Y': 'yyyy',
    'y': 'yy',
    'F': 'MMMM',
    'M': 'MMM',
  };

  let result = '';
  for (let i = 0; i < phpFormat.length; i++) {
    const char = phpFormat[i];
    result += formatMap[char] || char;
  }
  return result;
};

// Parse a date string that might be in various formats
const parseDate = (dateStr) => {
  if (!dateStr) return null;

  // Try ISO format first (YYYY-MM-DD)
  let date = new Date(dateStr);
  if (isValid(date)) return date;

  // Try parsing common formats
  const formats = ['yyyy-MM-dd', 'dd/MM/yyyy', 'MM/dd/yyyy'];
  for (const fmt of formats) {
    try {
      date = parse(dateStr, fmt, new Date());
      if (isValid(date)) return date;
    } catch {
      // Continue to next format
    }
  }

  return null;
};

/**
 * Displays custom fields section on detail pages
 *
 * @param {Object} props
 * @param {'person'|'team'} props.postType - The post type
 * @param {number} props.postId - The post ID
 * @param {Object} props.acfData - The ACF data object from the post
 * @param {Function} props.onUpdate - Callback after successful save, receives updated ACF data
 * @param {boolean} props.isUpdating - Whether parent is currently saving
 * @param {string[]} props.excludeLabelPrefixes - Array of label prefixes to exclude from display
 */
export default function CustomFieldsSection({ postType, postId, acfData, onUpdate, isUpdating, excludeLabelPrefixes = [] }) {
  const [showModal, setShowModal] = useState(false);

  // Fetch field definitions
  const { data: allFieldDefs = [], isLoading } = useQuery({
    queryKey: ['custom-fields-metadata', postType],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata(postType);
      return response.data;
    },
  });

  // Filter out fields based on label prefixes
  const fieldDefs = excludeLabelPrefixes.length > 0
    ? allFieldDefs.filter(field =>
        !excludeLabelPrefixes.some(prefix =>
          field.label?.toLowerCase().startsWith(prefix.toLowerCase())
        )
      )
    : allFieldDefs;

  // Render a value for display based on field type
  const renderFieldValue = (field, value) => {
    // Handle null/undefined/empty values
    if (value === null || value === undefined || value === '') {
      return <span className="text-gray-400 dark:text-gray-500 italic">Niet ingesteld</span>;
    }

    switch (field.type) {
      case 'text':
      case 'textarea':
      case 'number': {
        // Handle prepend/append for number fields
        if (field.type === 'number') {
          const displayValue = typeof value === 'number' ? value : String(value);
          return (
            <span>
              {field.prepend && <span className="text-gray-500 dark:text-gray-400">{field.prepend}</span>}
              {displayValue}
              {field.append && <span className="text-gray-500 dark:text-gray-400">{field.append}</span>}
            </span>
          );
        }
        return <span className="whitespace-pre-wrap">{String(value)}</span>;
      }

      case 'wysiwyg':
        return (
          <div
            className="prose prose-sm dark:prose-invert max-w-none prose-a:text-accent-600 dark:prose-a:text-accent-400"
            dangerouslySetInnerHTML={{ __html: processHtmlLinks(value) }}
          />
        );

      case 'email':
        return (
          <a
            href={`mailto:${value}`}
            className="text-accent-600 dark:text-accent-400 hover:underline"
          >
            {value}
          </a>
        );

      case 'url':
        return (
          <a
            href={value}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline inline-flex items-center gap-1"
          >
            {value}
            <ExternalLink className="w-3 h-3" />
          </a>
        );

      case 'date': {
        const date = parseDate(value);
        if (!date) return <span>{value}</span>;

        // Use display_format if available, otherwise default to 'PP' (localized date)
        try {
          const displayFormat = field.display_format
            ? phpToDateFnsFormat(field.display_format)
            : 'PP';
          return <span>{format(date, displayFormat)}</span>;
        } catch {
          return <span>{value}</span>;
        }
      }

      case 'select':
        // Value is already the display value from ACF
        return <span>{value}</span>;

      case 'checkbox': {
        // Checkbox values are arrays
        if (!Array.isArray(value) || value.length === 0) {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niets geselecteerd</span>;
        }
        return <span>{value.join(', ')}</span>;
      }

      case 'true_false': {
        const displayText = value
          ? (field.ui_on_text || 'Ja')
          : (field.ui_off_text || 'Nee');
        return (
          <span className={value ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'}>
            {displayText}
          </span>
        );
      }

      case 'image': {
        // Handle different return formats: ID, URL, or object
        let imageUrl = null;

        if (typeof value === 'string') {
          imageUrl = value;
        } else if (typeof value === 'object' && value !== null) {
          // Prefer thumbnail for display, fall back to url
          imageUrl = value.sizes?.thumbnail || value.url;
        } else if (typeof value === 'number') {
          // ID-only format - can't display without fetching
          return <span className="text-gray-500">Afbeelding #{value}</span>;
        }

        if (!imageUrl) {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niet ingesteld</span>;
        }

        return (
          <img
            src={imageUrl}
            alt=""
            className="w-16 h-16 rounded object-cover border border-gray-200 dark:border-gray-700"
          />
        );
      }

      case 'file': {
        // Handle different return formats: ID, URL, or object
        let fileUrl = null;
        let filename = 'Bestand downloaden';

        if (typeof value === 'string') {
          fileUrl = value;
          filename = value.split('/').pop() || 'Bestand downloaden';
        } else if (typeof value === 'object' && value !== null) {
          fileUrl = value.url;
          filename = value.filename || value.title || 'Bestand downloaden';
        } else if (typeof value === 'number') {
          return <span className="text-gray-500">Bestand #{value}</span>;
        }

        if (!fileUrl) {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niet ingesteld</span>;
        }

        return (
          <a
            href={fileUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline inline-flex items-center gap-1"
          >
            <FileText className="w-4 h-4" />
            {filename}
          </a>
        );
      }

      case 'link': {
        // Link fields return object with url, title, target
        if (typeof value !== 'object' || !value?.url) {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niet ingesteld</span>;
        }

        return (
          <a
            href={value.url}
            target={value.target || '_blank'}
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline inline-flex items-center gap-1"
          >
            <LinkIcon className="w-4 h-4" />
            {value.title || value.url}
          </a>
        );
      }

      case 'color_picker': {
        if (!value || typeof value !== 'string') {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niet ingesteld</span>;
        }

        return (
          <div className="inline-flex items-center gap-2">
            <div
              className="w-6 h-6 rounded border border-gray-300 dark:border-gray-600"
              style={{ backgroundColor: value }}
            />
            <span className="font-mono text-sm">{value}</span>
          </div>
        );
      }

      case 'relationship': {
        // Relationship values can be IDs or objects depending on return_format
        if (!value || (Array.isArray(value) && value.length === 0)) {
          return <span className="text-gray-400 dark:text-gray-500 italic">Niet gekoppeld</span>;
        }

        // Ensure we're working with an array
        const items = Array.isArray(value) ? value : [value];

        // Get allowed post types from field definition
        const allowedPostTypes = field.post_type || ['person', 'team'];

        return (
          <div className="flex flex-wrap gap-2">
            {items.map((item, index) => {
              // Handle object format (post_type, ID, post_title available)
              if (typeof item === 'object' && item !== null) {
                const postTypeSlug = item.post_type;
                const linkPath = postTypeSlug === 'person' ? `/people/${item.ID}` : `/teams/${item.ID}`;
                const IconComponent = postTypeSlug === 'person' ? User : Building2;
                // ACF may include featured image in object format
                const thumbnail = item.featured_image_url || item._embedded?.['wp:featuredmedia']?.[0]?.source_url;

                return (
                  <Link
                    key={item.ID || index}
                    to={linkPath}
                    className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-medium transition-colors"
                  >
                    {thumbnail ? (
                      <img
                        src={thumbnail}
                        alt=""
                        className="w-6 h-6 rounded-full object-cover"
                      />
                    ) : (
                      <div className="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                        <IconComponent className="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                      </div>
                    )}
                    {item.post_title || `#${item.ID}`}
                  </Link>
                );
              }

              // Handle ID-only format - fetch details using RelationshipItem component
              if (typeof item === 'number' || typeof item === 'string') {
                const itemId = typeof item === 'string' ? parseInt(item, 10) : item;
                return (
                  <RelationshipItem
                    key={itemId}
                    itemId={itemId}
                    allowedPostTypes={allowedPostTypes}
                  />
                );
              }

              return null;
            })}
          </div>
        );
      }

      default:
        // Unknown field type - display as string
        return <span>{String(value)}</span>;
    }
  };

  // Handle save from modal
  const handleSubmit = (newValues) => {
    if (onUpdate) {
      onUpdate(newValues);
    }
    setShowModal(false);
  };

  // Don't show section if loading or no fields defined
  if (isLoading) {
    return null;
  }

  if (fieldDefs.length === 0) {
    return null;
  }

  // Check if any fields are editable in the UI
  const hasEditableFields = fieldDefs.some(field => field.editable_in_ui !== false);

  return (
    <>
      <div className="card p-6 break-inside-avoid mb-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-semibold">Aangepaste velden</h2>
          {hasEditableFields && (
            <button
              onClick={() => setShowModal(true)}
              className="btn-secondary text-sm"
            >
              <Pencil className="w-4 h-4 md:mr-1" />
              <span className="hidden md:inline">Bewerken</span>
            </button>
          )}
        </div>

        <div className="space-y-3">
          {fieldDefs.map((field) => (
            <div key={field.key} className="flex items-start">
              <div className="w-1/3 text-sm text-gray-500 dark:text-gray-400">
                {field.label}
              </div>
              <div className="w-2/3 text-sm">
                {renderFieldValue(field, acfData?.[field.name])}
              </div>
            </div>
          ))}
        </div>
      </div>

      {showModal && (
        <CustomFieldsEditModal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          postType={postType}
          postId={postId}
          fieldDefs={fieldDefs}
          currentValues={acfData}
          onSubmit={handleSubmit}
          isLoading={isUpdating}
        />
      )}
    </>
  );
}
