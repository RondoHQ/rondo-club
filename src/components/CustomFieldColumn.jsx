import { format } from '@/utils/dateFormat';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { getPersonName, getTeamName } from '@/utils/formatters';

/**
 * Compact relationship item for list view - fetches name if only ID is available
 */
function RelationshipItemCompact({ itemId, allowedPostTypes }) {
  const { data: itemData, isLoading } = useQuery({
    queryKey: ['relationship-item', itemId],
    queryFn: async () => {
      // Try person first if allowed
      if (allowedPostTypes.includes('person')) {
        try {
          const response = await wpApi.getPerson(itemId);
          return { name: getPersonName(response.data) };
        } catch {
          // Not a person, try team
        }
      }

      // Try team if allowed
      if (allowedPostTypes.includes('team')) {
        try {
          const response = await wpApi.getTeam(itemId);
          return { name: getTeamName(response.data) };
        } catch {
          // Not found
        }
      }

      return null;
    },
    staleTime: 60000,
  });

  if (isLoading) return <span className="text-gray-400">...</span>;
  if (!itemData) return <span className="text-gray-400">#{itemId}</span>;
  return <span>{itemData.name}</span>;
}

export default function CustomFieldColumn({ field, value }) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;
  }

  switch (field.type) {
    case 'text':
    case 'textarea':
      return <span className="truncate block max-w-32" title={String(value)}>{String(value)}</span>;

    case 'wysiwyg': {
      // Strip HTML tags for list view preview
      const plainText = String(value).replace(/<[^>]*>/g, '').trim();
      return <span className="truncate block max-w-32" title={plainText}>{plainText || '-'}</span>;
    }

    case 'number':
      return (
        <span>
          {field.prepend && <span className="text-gray-400 dark:text-gray-500">{field.prepend}</span>}
          {value}
          {field.append && <span className="text-gray-400 dark:text-gray-500">{field.append}</span>}
        </span>
      );

    case 'email':
      return (
        <a
          href={`mailto:${value}`}
          className="text-accent-600 dark:text-accent-400 hover:underline truncate block max-w-32"
          title={value}
        >
          {value}
        </a>
      );

    case 'url': {
      const displayUrl = value.replace(/^https?:\/\//, '').replace(/\/$/, '');
      return (
        <a
          href={value.startsWith('http') ? value : `https://${value}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-accent-600 dark:text-accent-400 hover:underline truncate block max-w-32"
          title={value}
        >
          {displayUrl}
        </a>
      );
    }

    case 'date':
      try {
        return <span>{format(new Date(value), 'MMM d, yyyy')}</span>;
      } catch {
        return <span>{value}</span>;
      }

    case 'select':
      return <span className="truncate block max-w-32" title={value}>{value}</span>;

    case 'checkbox':
      if (!Array.isArray(value) || value.length === 0) return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;
      if (value.length <= 2) return <span className="truncate block max-w-32" title={value.join(', ')}>{value.join(', ')}</span>;
      return <span title={value.join(', ')}>{value.length} selected</span>;

    case 'true_false':
      return (
        <span className={value ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'}>
          {value ? (field.ui_on_text || 'Yes') : (field.ui_off_text || 'No')}
        </span>
      );

    case 'image': {
      const imageUrl = typeof value === 'object' ? (value.sizes?.thumbnail || value.url) : value;
      return imageUrl ? (
        <img src={imageUrl} alt="" className="w-8 h-8 rounded object-cover" />
      ) : <span className="text-gray-400 dark:text-gray-500 italic">-</span>;
    }

    case 'color_picker':
      return (
        <div
          className="w-6 h-6 rounded border border-gray-200 dark:border-gray-600"
          style={{ backgroundColor: value }}
          title={value}
        />
      );

    case 'relationship': {
      const items = Array.isArray(value) ? value : (value ? [value] : []);
      if (items.length === 0 || !items[0]) return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;

      const allowedPostTypes = field.post_type || ['person', 'team'];

      if (items.length === 1) {
        const item = items[0];
        // If it's an object with post_title, use that directly
        if (typeof item === 'object' && item !== null) {
          const name = item.post_title || item.title?.rendered || `#${item.ID || item.id}`;
          return <span className="truncate block max-w-32" title={name}>{name}</span>;
        }
        // If it's just an ID, fetch the name
        const itemId = typeof item === 'string' ? parseInt(item, 10) : item;
        return (
          <span className="truncate block max-w-32">
            <RelationshipItemCompact itemId={itemId} allowedPostTypes={allowedPostTypes} />
          </span>
        );
      }

      // Multiple items - show count with names in tooltip if available
      const names = items.map(i => {
        if (typeof i === 'object' && i !== null) {
          return i.post_title || i.title?.rendered || `#${i.ID || i.id}`;
        }
        return `#${i}`;
      });
      return <span title={names.join(', ')}>{items.length} linked</span>;
    }

    case 'file': {
      const fileName = typeof value === 'object' ? value.filename : 'File';
      return <span className="text-gray-500 dark:text-gray-400 truncate block max-w-24" title={fileName}>{fileName}</span>;
    }

    case 'link':
      if (typeof value === 'object' && value.url) {
        return (
          <a
            href={value.url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline truncate block max-w-32"
            title={value.title || value.url}
          >
            {value.title || 'Link'}
          </a>
        );
      }
      return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;

    default:
      return <span className="truncate block max-w-32" title={String(value)}>{String(value)}</span>;
  }
}
