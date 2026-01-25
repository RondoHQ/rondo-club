import { useEffect, useRef, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, Upload, Trash2, Search, User, Building2 } from 'lucide-react';
import Sketch from '@uiw/react-color-sketch';
import { wpApi, prmApi } from '@/api/client';
import { useQuery } from '@tanstack/react-query';
import { decodeHtml, getPersonName, getTeamName } from '@/utils/formatters';

/**
 * Build default form values from field definitions and current values
 */
const buildDefaultValues = (fieldDefs, currentValues) => {
  const defaults = {};

  fieldDefs.forEach((field) => {
    const currentValue = currentValues?.[field.name];

    switch (field.type) {
      case 'checkbox':
        // Checkbox stores array of selected values
        defaults[field.name] = Array.isArray(currentValue) ? currentValue : [];
        break;

      case 'true_false':
        // True/false stores 0 or 1
        defaults[field.name] = currentValue ? 1 : 0;
        break;

      case 'link':
        // Link stores object with url and title
        defaults[field.name] = {
          url: currentValue?.url || '',
          title: currentValue?.title || '',
          target: currentValue?.target || '_blank',
        };
        break;

      case 'relationship':
        // Relationship can be array of IDs or objects
        if (Array.isArray(currentValue)) {
          defaults[field.name] = currentValue.map((item) => {
            if (typeof item === 'object' && item !== null) {
              return item.ID;
            }
            return item;
          });
        } else if (typeof currentValue === 'object' && currentValue !== null) {
          defaults[field.name] = [currentValue.ID];
        } else if (typeof currentValue === 'number') {
          defaults[field.name] = [currentValue];
        } else {
          defaults[field.name] = [];
        }
        break;

      case 'image':
      case 'file':
        // Store the current value as-is (ID, URL, or object)
        // We'll handle upload separately
        if (typeof currentValue === 'object' && currentValue !== null) {
          defaults[field.name] = currentValue.ID || currentValue.id || currentValue;
        } else {
          defaults[field.name] = currentValue || null;
        }
        break;

      default:
        defaults[field.name] = currentValue ?? '';
    }
  });

  return defaults;
};

/**
 * Image/File input component with preview and upload
 */
function MediaInput({ value, onChange, type = 'image', onRemove }) {
  const fileInputRef = useRef(null);
  const [isUploading, setIsUploading] = useState(false);
  const [preview, setPreview] = useState(null);
  const [filename, setFilename] = useState(null);

  // Determine preview from current value
  useEffect(() => {
    if (typeof value === 'string') {
      if (type === 'image') setPreview(value);
      else setFilename(value.split('/').pop());
    } else if (typeof value === 'object' && value !== null) {
      if (type === 'image') setPreview(value.sizes?.thumbnail || value.url);
      else setFilename(value.filename || value.title || 'Uploaded file');
    } else {
      setPreview(null);
      setFilename(null);
    }
  }, [value, type]);

  const handleFileChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    try {
      const response = await wpApi.uploadMedia(file);
      const media = response.data;

      // Store the ID - ACF will handle the rest
      onChange(media.id);

      // Update preview
      if (type === 'image') {
        setPreview(media.source_url || media.media_details?.sizes?.thumbnail?.source_url);
      } else {
        setFilename(file.name);
      }
    } catch (error) {
      console.error('Failed to upload file:', error);
      alert('Failed to upload file. Please try again.');
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const handleRemove = () => {
    onChange(null);
    setPreview(null);
    setFilename(null);
    if (onRemove) onRemove();
  };

  return (
    <div className="space-y-2">
      {type === 'image' && preview && (
        <div className="relative inline-block">
          <img
            src={preview}
            alt=""
            className="w-20 h-20 rounded object-cover border border-gray-200 dark:border-gray-700"
          />
          <button
            type="button"
            onClick={handleRemove}
            className="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600"
          >
            <X className="w-3 h-3" />
          </button>
        </div>
      )}

      {type === 'file' && filename && (
        <div className="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded">
          <span className="text-sm flex-1 truncate">{filename}</span>
          <button
            type="button"
            onClick={handleRemove}
            className="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      )}

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isUploading}
          className="btn-secondary text-sm inline-flex items-center gap-1"
        >
          <Upload className="w-4 h-4" />
          {isUploading ? 'Uploading...' : value ? 'Replace' : 'Upload'}
        </button>
        <input
          ref={fileInputRef}
          type="file"
          accept={type === 'image' ? 'image/*' : '*'}
          onChange={handleFileChange}
          className="hidden"
        />
      </div>
    </div>
  );
}

/**
 * Color picker input with popup
 */
function ColorPickerInput({ value, onChange }) {
  const [showPicker, setShowPicker] = useState(false);
  const containerRef = useRef(null);

  // Close picker when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setShowPicker(false);
      }
    };

    if (showPicker) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [showPicker]);

  return (
    <div ref={containerRef} className="relative">
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => setShowPicker(!showPicker)}
          className="w-10 h-10 rounded border border-gray-300 dark:border-gray-600"
          style={{
            backgroundColor: value || 'transparent',
            backgroundImage: !value
              ? 'linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%)'
              : 'none',
            backgroundSize: '8px 8px',
            backgroundPosition: '0 0, 0 4px, 4px -4px, -4px 0px',
          }}
        />
        <input
          type="text"
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder="#000000"
          className="input w-28 font-mono text-sm"
        />
        {value && (
          <button
            type="button"
            onClick={() => onChange('')}
            className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            Clear
          </button>
        )}
      </div>

      {showPicker && (
        <div className="absolute z-10 mt-2">
          <Sketch
            color={value || '#000000'}
            disableAlpha={true}
            presetColors={false}
            onChange={(color) => onChange(color.hex)}
            style={{ boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)' }}
          />
        </div>
      )}
    </div>
  );
}

/**
 * Relationship field input with search
 */
function RelationshipInput({ value = [], onChange, postTypes = ['person', 'team'], max = 0 }) {
  const [searchQuery, setSearchQuery] = useState('');
  const [showDropdown, setShowDropdown] = useState(false);
  const containerRef = useRef(null);

  // Fetch search results
  const { data: searchResults = [], isLoading: isSearching } = useQuery({
    queryKey: ['relationship-search', searchQuery, postTypes],
    queryFn: async () => {
      if (!searchQuery || searchQuery.length < 2) return [];

      const response = await prmApi.search(searchQuery);
      const data = response.data || {};

      // Flatten response and add type property
      const results = [];

      if (postTypes.includes('person') && data.people) {
        data.people.forEach((item) => {
          results.push({ ...item, type: 'person' });
        });
      }

      if (postTypes.includes('team') && data.teams) {
        data.teams.forEach((item) => {
          results.push({ ...item, type: 'team' });
        });
      }

      return results;
    },
    enabled: searchQuery.length >= 2,
    staleTime: 30000,
  });

  // Fetch details of currently selected items
  const { data: selectedItems = [] } = useQuery({
    queryKey: ['relationship-selected', value],
    queryFn: async () => {
      if (!value || value.length === 0) return [];

      // We need to fetch details for each ID
      // Since we don't know the type, try both
      const items = [];

      for (const id of value) {
        try {
          // Try person first
          const personResponse = await wpApi.getPerson(id);
          items.push({
            id: personResponse.data.id,
            type: 'person',
            name: getPersonName(personResponse.data),
          });
        } catch {
          try {
            // Try team
            const teamResponse = await wpApi.getTeam(id);
            items.push({
              id: teamResponse.data.id,
              type: 'team',
              name: getTeamName(teamResponse.data),
            });
          } catch {
            // Item not found, skip
          }
        }
      }

      return items;
    },
    enabled: value.length > 0,
    staleTime: 60000,
  });

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setShowDropdown(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleAdd = (item) => {
    if (max === 1) {
      onChange([item.id]);
    } else if (max === 0 || value.length < max) {
      if (!value.includes(item.id)) {
        onChange([...value, item.id]);
      }
    }
    setSearchQuery('');
    setShowDropdown(false);
  };

  const handleRemove = (id) => {
    onChange(value.filter((v) => v !== id));
  };

  const canAdd = max === 0 || value.length < max;

  return (
    <div ref={containerRef} className="space-y-2">
      {/* Selected items */}
      {selectedItems.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {selectedItems.map((item) => {
            const IconComponent = item.type === 'person' ? User : Building2;
            return (
              <div
                key={item.id}
                className="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-sm"
              >
                <IconComponent className="w-3 h-3" />
                <span>{item.name}</span>
                <button
                  type="button"
                  onClick={() => handleRemove(item.id)}
                  className="ml-1 p-0.5 text-gray-400 hover:text-red-500"
                >
                  <X className="w-3 h-3" />
                </button>
              </div>
            );
          })}
        </div>
      )}

      {/* Search input */}
      {canAdd && (
        <div className="relative">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setShowDropdown(true);
              }}
              onFocus={() => setShowDropdown(true)}
              placeholder="Search to add..."
              className="input pl-9 w-full"
            />
          </div>

          {/* Dropdown */}
          {showDropdown && searchQuery.length >= 2 && (
            <div className="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
              {isSearching ? (
                <div className="p-3 text-sm text-gray-500 text-center">Searching...</div>
              ) : searchResults.length === 0 ? (
                <div className="p-3 text-sm text-gray-500 text-center">No results found</div>
              ) : (
                searchResults
                  .filter((item) => !value.includes(item.id))
                  .map((item) => {
                    const IconComponent = item.type === 'person' ? User : Building2;
                    return (
                      <button
                        key={item.id}
                        type="button"
                        onClick={() => handleAdd(item)}
                        className="w-full px-3 py-2 text-left flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        {item.thumbnail ? (
                          <img
                            src={item.thumbnail}
                            alt=""
                            className="w-6 h-6 rounded-full object-cover"
                          />
                        ) : (
                          <div className="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                            <IconComponent className="w-3 h-3 text-gray-400" />
                          </div>
                        )}
                        <span className="text-sm">{decodeHtml(item.name)}</span>
                        <span className="text-xs text-gray-400 ml-auto">
                          {item.type === 'person' ? 'Person' : 'Organization'}
                        </span>
                      </button>
                    );
                  })
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Modal for editing custom field values
 */
export default function CustomFieldsEditModal({
  isOpen,
  onClose,
  postType,
  postId,
  fieldDefs,
  currentValues,
  onSubmit,
  isLoading,
}) {
  const { register, handleSubmit, control, reset, watch } = useForm({
    defaultValues: buildDefaultValues(fieldDefs, currentValues),
  });

  // Reset form when modal opens with current values
  useEffect(() => {
    if (isOpen) {
      reset(buildDefaultValues(fieldDefs, currentValues));
    }
  }, [isOpen, fieldDefs, currentValues, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    // Process form data for submission
    const processedData = {};

    fieldDefs.forEach((field) => {
      let value = data[field.name];

      // Handle special types
      switch (field.type) {
        case 'link':
          // Only submit if URL is provided
          if (value?.url) {
            processedData[field.name] = value;
          } else {
            processedData[field.name] = null;
          }
          break;

        case 'true_false':
          processedData[field.name] = value ? 1 : 0;
          break;

        case 'number':
          processedData[field.name] = value === '' ? null : Number(value);
          break;

        case 'image':
        case 'file':
          // Store the ID or null
          processedData[field.name] = value || null;
          break;

        default:
          processedData[field.name] = value;
      }
    });

    onSubmit(processedData);
  };

  const renderFieldInput = (field) => {
    const inputClass =
      'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500';

    switch (field.type) {
      case 'text':
      case 'email':
      case 'url':
        return (
          <input
            type={field.type}
            {...register(field.name)}
            placeholder={field.placeholder}
            className={inputClass}
          />
        );

      case 'textarea':
        return (
          <textarea
            {...register(field.name)}
            rows={field.rows || 4}
            placeholder={field.placeholder}
            className={inputClass}
          />
        );

      case 'number':
        return (
          <div className="flex items-center gap-2">
            {field.prepend && (
              <span className="text-gray-500 dark:text-gray-400">{field.prepend}</span>
            )}
            <input
              type="number"
              {...register(field.name)}
              min={field.min}
              max={field.max}
              step={field.step}
              className={`${inputClass} flex-1`}
            />
            {field.append && (
              <span className="text-gray-500 dark:text-gray-400">{field.append}</span>
            )}
          </div>
        );

      case 'date':
        return (
          <input
            type="date"
            {...register(field.name)}
            className={inputClass}
          />
        );

      case 'select':
        return (
          <select {...register(field.name)} className={inputClass}>
            {field.allow_null && <option value="">Select...</option>}
            {Object.entries(field.choices || {}).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        );

      case 'checkbox':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value = [], onChange } }) => (
              <div className={`space-y-2 ${field.layout === 'horizontal' ? 'flex flex-wrap gap-4' : ''}`}>
                {Object.entries(field.choices || {}).map(([key, label]) => (
                  <label key={key} className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={value.includes(key)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          onChange([...value, key]);
                        } else {
                          onChange(value.filter((v) => v !== key));
                        }
                      }}
                      className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                    />
                    <span className="text-sm">{label}</span>
                  </label>
                ))}
              </div>
            )}
          />
        );

      case 'true_false':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value, onChange } }) => (
              <label className="flex items-center gap-3 cursor-pointer">
                <button
                  type="button"
                  onClick={() => onChange(value ? 0 : 1)}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                    value ? 'bg-accent-600' : 'bg-gray-300 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      value ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
                <span className="text-sm">
                  {value ? (field.ui_on_text || 'Yes') : (field.ui_off_text || 'No')}
                </span>
              </label>
            )}
          />
        );

      case 'image':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value, onChange } }) => (
              <MediaInput value={value} onChange={onChange} type="image" />
            )}
          />
        );

      case 'file':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value, onChange } }) => (
              <MediaInput value={value} onChange={onChange} type="file" />
            )}
          />
        );

      case 'link':
        return (
          <div className="space-y-2">
            <input
              type="url"
              {...register(`${field.name}.url`)}
              placeholder="URL"
              className={inputClass}
            />
            <input
              type="text"
              {...register(`${field.name}.title`)}
              placeholder="Link text (optional)"
              className={inputClass}
            />
          </div>
        );

      case 'color_picker':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value, onChange } }) => (
              <ColorPickerInput value={value} onChange={onChange} />
            )}
          />
        );

      case 'relationship':
        return (
          <Controller
            name={field.name}
            control={control}
            render={({ field: { value, onChange } }) => (
              <RelationshipInput
                value={value}
                onChange={onChange}
                postTypes={field.post_type || ['person', 'team']}
                max={field.max || 0}
              />
            )}
          />
        );

      default:
        return (
          <input
            type="text"
            {...register(field.name)}
            className={inputClass}
          />
        );
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Edit custom fields</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {fieldDefs.map((field) => (
              <div key={field.key}>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {field.label}
                </label>
                {field.instructions && (
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{field.instructions}</p>
                )}
                {renderFieldInput(field)}
              </div>
            ))}
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading}
            >
              {isLoading ? 'Saving...' : 'Save changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
