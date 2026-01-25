import { format } from '@/utils/dateFormat';

/**
 * Simple inline input for a custom field.
 * Used in row-level edit mode where all fields are editable at once.
 */
export default function InlineFieldInput({ field, value, onChange, disabled, onKeyDown }) {
  // Check if this field type supports inline editing
  const supportsInlineEdit = !['checkbox', 'relationship', 'image', 'file', 'link', 'color_picker'].includes(field.type);

  if (!supportsInlineEdit) {
    return (
      <span className="text-xs text-gray-400 italic">
        -
      </span>
    );
  }

  switch (field.type) {
    case 'text':
    case 'email':
    case 'url':
      return (
        <input
          type={field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'}
          value={value ?? ''}
          onChange={(e) => onChange(field.name, e.target.value)}
          onKeyDown={onKeyDown}
          className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          placeholder={field.placeholder || ''}
          disabled={disabled}
        />
      );

    case 'textarea':
      return (
        <input
          type="text"
          value={value ?? ''}
          onChange={(e) => onChange(field.name, e.target.value)}
          onKeyDown={onKeyDown}
          className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          placeholder={field.placeholder || ''}
          disabled={disabled}
        />
      );

    case 'number':
      return (
        <div className="flex items-center gap-1">
          {field.prepend && <span className="text-gray-400 text-xs">{field.prepend}</span>}
          <input
            type="number"
            value={value ?? ''}
            onChange={(e) => onChange(field.name, e.target.value)}
            onKeyDown={onKeyDown}
            min={field.min}
            max={field.max}
            step={field.step}
            className="w-20 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={disabled}
          />
          {field.append && <span className="text-gray-400 text-xs">{field.append}</span>}
        </div>
      );

    case 'date':
      return (
        <input
          type="date"
          value={value ? format(new Date(value), 'yyyy-MM-dd') : ''}
          onChange={(e) => onChange(field.name, e.target.value)}
          onKeyDown={onKeyDown}
          className="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          disabled={disabled}
        />
      );

    case 'select':
      return (
        <select
          value={value ?? ''}
          onChange={(e) => onChange(field.name, e.target.value)}
          onKeyDown={onKeyDown}
          className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          disabled={disabled}
        >
          {field.allow_null !== false && <option value="">-- Selecteer --</option>}
          {field.choices && Object.entries(field.choices).map(([key, label]) => (
            <option key={key} value={key}>{label}</option>
          ))}
        </select>
      );

    case 'true_false':
      return (
        <select
          value={value ? '1' : '0'}
          onChange={(e) => onChange(field.name, e.target.value === '1')}
          onKeyDown={onKeyDown}
          className="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          disabled={disabled}
        >
          <option value="0">{field.ui_off_text || 'Nee'}</option>
          <option value="1">{field.ui_on_text || 'Ja'}</option>
        </select>
      );

    default:
      return (
        <input
          type="text"
          value={value ?? ''}
          onChange={(e) => onChange(field.name, e.target.value)}
          onKeyDown={onKeyDown}
          className="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-100"
          disabled={disabled}
        />
      );
  }
}
