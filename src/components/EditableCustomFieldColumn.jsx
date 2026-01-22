import { useState, useRef, useEffect } from 'react';
import { format } from 'date-fns';
import { Pencil, Check, X } from 'lucide-react';
import CustomFieldColumn from './CustomFieldColumn';

/**
 * Editable wrapper for CustomFieldColumn.
 * Displays the value normally, with a pencil icon on hover.
 * Click to enter edit mode with appropriate input for field type.
 */
export default function EditableCustomFieldColumn({ field, value, onSave, isLoading }) {
  const [isEditing, setIsEditing] = useState(false);
  const [editValue, setEditValue] = useState(value ?? '');
  const inputRef = useRef(null);

  // Focus input when entering edit mode
  useEffect(() => {
    if (isEditing && inputRef.current) {
      inputRef.current.focus();
      // Select all text for text inputs
      if (inputRef.current.select) {
        inputRef.current.select();
      }
    }
  }, [isEditing]);

  // Reset edit value when value prop changes
  useEffect(() => {
    if (!isEditing) {
      setEditValue(value ?? '');
    }
  }, [value, isEditing]);

  const handleStartEdit = (e) => {
    e.stopPropagation();
    setEditValue(value ?? '');
    setIsEditing(true);
  };

  const handleCancel = () => {
    setEditValue(value ?? '');
    setIsEditing(false);
  };

  const handleSave = async () => {
    if (editValue !== value) {
      await onSave(field.name, editValue);
    }
    setIsEditing(false);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSave();
    } else if (e.key === 'Escape') {
      handleCancel();
    }
  };

  // Render edit input based on field type
  const renderEditInput = () => {
    switch (field.type) {
      case 'text':
      case 'email':
      case 'url':
        return (
          <input
            ref={inputRef}
            type={field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'}
            value={editValue}
            onChange={(e) => setEditValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
            placeholder={field.placeholder || ''}
            disabled={isLoading}
          />
        );

      case 'textarea':
        return (
          <textarea
            ref={inputRef}
            value={editValue}
            onChange={(e) => setEditValue(e.target.value)}
            onKeyDown={handleKeyDown}
            rows={2}
            className="w-full px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100 resize-none"
            placeholder={field.placeholder || ''}
            disabled={isLoading}
          />
        );

      case 'number':
        return (
          <div className="flex items-center gap-1">
            {field.prepend && <span className="text-gray-400 text-sm">{field.prepend}</span>}
            <input
              ref={inputRef}
              type="number"
              value={editValue}
              onChange={(e) => setEditValue(e.target.value)}
              onKeyDown={handleKeyDown}
              min={field.min}
              max={field.max}
              step={field.step}
              className="w-20 px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
              disabled={isLoading}
            />
            {field.append && <span className="text-gray-400 text-sm">{field.append}</span>}
          </div>
        );

      case 'date':
        return (
          <input
            ref={inputRef}
            type="date"
            value={editValue ? format(new Date(editValue), 'yyyy-MM-dd') : ''}
            onChange={(e) => setEditValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={isLoading}
          />
        );

      case 'select':
        return (
          <select
            ref={inputRef}
            value={editValue}
            onChange={(e) => setEditValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={isLoading}
          >
            {field.allow_null !== false && <option value="">-- Select --</option>}
            {field.choices && Object.entries(field.choices).map(([key, label]) => (
              <option key={key} value={key}>{label}</option>
            ))}
          </select>
        );

      case 'true_false':
        return (
          <select
            ref={inputRef}
            value={editValue ? '1' : '0'}
            onChange={(e) => setEditValue(e.target.value === '1')}
            onKeyDown={handleKeyDown}
            className="px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={isLoading}
          >
            <option value="0">{field.ui_off_text || 'No'}</option>
            <option value="1">{field.ui_on_text || 'Yes'}</option>
          </select>
        );

      // Complex types that don't support inline editing
      case 'checkbox':
      case 'relationship':
      case 'image':
      case 'file':
      case 'link':
      case 'color_picker':
        return (
          <span className="text-xs text-gray-500 italic">
            Edit on detail page
          </span>
        );

      default:
        return (
          <input
            ref={inputRef}
            type="text"
            value={editValue}
            onChange={(e) => setEditValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="w-full px-2 py-1 text-sm border border-accent-500 rounded focus:outline-none focus:ring-1 focus:ring-accent-500 dark:bg-gray-700 dark:text-gray-100"
            disabled={isLoading}
          />
        );
    }
  };

  // Check if this field type supports inline editing
  const supportsInlineEdit = !['checkbox', 'relationship', 'image', 'file', 'link', 'color_picker'].includes(field.type);

  if (isEditing) {
    return (
      <div className="flex items-center gap-1 min-w-32" onClick={(e) => e.stopPropagation()}>
        {renderEditInput()}
        <button
          onClick={handleSave}
          disabled={isLoading}
          className="p-1 text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
          title="Save"
        >
          <Check className="w-4 h-4" />
        </button>
        <button
          onClick={handleCancel}
          disabled={isLoading}
          className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          title="Cancel"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
    );
  }

  return (
    <div className="group flex items-center gap-1">
      <CustomFieldColumn field={field} value={value} />
      {supportsInlineEdit && (
        <button
          onClick={handleStartEdit}
          className="p-1 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 transition-opacity"
          title="Edit"
        >
          <Pencil className="w-3 h-3" />
        </button>
      )}
    </div>
  );
}
