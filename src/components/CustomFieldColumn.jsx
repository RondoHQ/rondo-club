import { format } from 'date-fns';

export default function CustomFieldColumn({ field, value }) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;
  }

  switch (field.type) {
    case 'text':
    case 'textarea':
      return <span className="truncate block max-w-32" title={String(value)}>{String(value)}</span>;

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
      if (items.length === 1) {
        const item = items[0];
        const name = typeof item === 'object' ? (item.post_title || item.title?.rendered) : `#${item}`;
        return <span className="truncate block max-w-32" title={name}>{name}</span>;
      }
      return <span title={items.map(i => typeof i === 'object' ? (i.post_title || i.title?.rendered) : `#${i}`).join(', ')}>{items.length} linked</span>;
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
