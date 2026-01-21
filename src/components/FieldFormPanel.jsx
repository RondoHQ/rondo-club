import { useState, useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import Sketch from '@uiw/react-color-sketch';

// Field type options
const FIELD_TYPES = [
  { value: 'text', label: 'Text' },
  { value: 'textarea', label: 'Textarea' },
  { value: 'number', label: 'Number' },
  { value: 'email', label: 'Email' },
  { value: 'url', label: 'URL' },
  { value: 'date', label: 'Date' },
  { value: 'select', label: 'Select' },
  { value: 'checkbox', label: 'Checkbox' },
  { value: 'true_false', label: 'True/False' },
  { value: 'image', label: 'Image' },
  { value: 'file', label: 'File' },
  { value: 'link', label: 'Link' },
  { value: 'color_picker', label: 'Color' },
  { value: 'relationship', label: 'Relationship' },
];

// Date format options
const DATE_FORMATS = [
  { value: 'd/m/Y', label: 'DD/MM/YYYY (31/12/2024)' },
  { value: 'm/d/Y', label: 'MM/DD/YYYY (12/31/2024)' },
  { value: 'Y-m-d', label: 'YYYY-MM-DD (2024-12-31)' },
  { value: 'F j, Y', label: 'Month Day, Year (December 31, 2024)' },
];

// Week start day options
const WEEK_START_OPTIONS = [
  { value: 0, label: 'Sunday' },
  { value: 1, label: 'Monday' },
];

// Default form state with all type-specific options
const getDefaultFormData = () => ({
  label: '',
  type: 'text',
  instructions: '',
  // Number options
  min: '',
  max: '',
  step: '',
  prepend: '',
  append: '',
  // Date options
  display_format: 'd/m/Y',
  return_format: 'd/m/Y',
  first_day: 1,
  // Select/Checkbox options
  choices: '',
  allow_null: false,
  multiple: false,
  layout: 'vertical',
  toggle: false,
  // True/False options
  ui: true,
  ui_on_text: 'Yes',
  ui_off_text: 'No',
  // Text options
  maxlength: '',
  placeholder: '',
  // Textarea options
  rows: 4,
  // Image options
  image_return_format: 'array',
  preview_size: 'medium',
  library: 'all',
  // File options (uses library from above, has own return format)
  file_return_format: 'array',
  // Color options
  default_color: '',
  // Relationship options
  relationship_post_types: ['person', 'company'],
  relationship_min: 0,
  relationship_max: 1,
  relationship_return_format: 'object',
  // List view options
  show_in_list_view: false,
  list_view_order: 999,
  // Validation options
  required: false,
  unique: false,
});

// Convert choices object to newline-separated string
const choicesToString = (choices) => {
  if (!choices || typeof choices !== 'object') return '';
  return Object.entries(choices)
    .map(([k, v]) => (k === v ? v : `${k} : ${v}`))
    .join('\n');
};

// Convert newline-separated string to choices object
const stringToChoices = (str) => {
  const choicesObj = {};
  str
    .split('\n')
    .filter((line) => line.trim())
    .forEach((line) => {
      if (line.includes(':')) {
        const [key, ...rest] = line.split(':');
        choicesObj[key.trim()] = rest.join(':').trim();
      } else {
        const trimmed = line.trim();
        choicesObj[trimmed] = trimmed;
      }
    });
  return choicesObj;
};

export default function FieldFormPanel({
  isOpen,
  onClose,
  onSubmit,
  field = null,
  postType,
  isSubmitting = false,
}) {
  const isEditing = !!field;
  const labelInputRef = useRef(null);

  const [formData, setFormData] = useState(getDefaultFormData());
  const [errors, setErrors] = useState({});

  // Reset form when opening/closing or when field changes
  useEffect(() => {
    if (isOpen) {
      if (field) {
        // Determine return format field based on type
        let imageReturnFormat = 'array';
        let fileReturnFormat = 'array';
        let relationshipReturnFormat = 'object';

        if (field.type === 'image' && field.return_format) {
          imageReturnFormat = field.return_format;
        }
        if (field.type === 'file' && field.return_format) {
          fileReturnFormat = field.return_format;
        }
        if (field.type === 'relationship' && field.return_format) {
          relationshipReturnFormat = field.return_format;
        }

        setFormData({
          ...getDefaultFormData(),
          label: field.label || '',
          type: field.type || 'text',
          instructions: field.instructions || '',
          // Number options
          min: field.min ?? '',
          max: field.max ?? '',
          step: field.step ?? '',
          prepend: field.prepend || '',
          append: field.append || '',
          // Date options
          display_format: field.display_format || 'd/m/Y',
          return_format: field.return_format || 'd/m/Y',
          first_day: field.first_day ?? 1,
          // Select/Checkbox options
          choices: choicesToString(field.choices),
          allow_null: field.allow_null ?? false,
          multiple: field.multiple ?? false,
          layout: field.layout || 'vertical',
          toggle: field.toggle ?? false,
          // True/False options
          ui: field.ui ?? true,
          ui_on_text: field.ui_on_text || 'Yes',
          ui_off_text: field.ui_off_text || 'No',
          // Text options
          maxlength: field.maxlength ?? '',
          placeholder: field.placeholder || '',
          // Textarea options
          rows: field.rows ?? 4,
          // Image options
          image_return_format: imageReturnFormat,
          preview_size: field.preview_size || 'medium',
          library: field.library || 'all',
          // File options
          file_return_format: fileReturnFormat,
          // Color options
          default_color: field.default_value || '',
          // Relationship options
          relationship_post_types: field.post_type || ['person', 'company'],
          relationship_min: field.min ?? 0,
          relationship_max: field.max ?? 1,
          relationship_return_format: relationshipReturnFormat,
          // List view options
          show_in_list_view: field.show_in_list_view ?? false,
          list_view_order: field.list_view_order ?? 999,
          // Validation options
          required: field.required ?? false,
          unique: field.unique ?? false,
        });
      } else {
        setFormData(getDefaultFormData());
      }
      setErrors({});
      // Focus label input after panel opens
      setTimeout(() => labelInputRef.current?.focus(), 100);
    }
  }, [isOpen, field]);

  // Prevent body scroll when panel is open
  useEffect(() => {
    if (isOpen) {
      document.body.classList.add('overflow-hidden');
    } else {
      document.body.classList.remove('overflow-hidden');
    }
    return () => {
      document.body.classList.remove('overflow-hidden');
    };
  }, [isOpen]);

  // Handle Escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    const newValue = type === 'checkbox' ? checked : value;

    setFormData((prev) => {
      const updated = { ...prev, [name]: newValue };
      // When type changes, reset type-specific options to defaults
      if (name === 'type' && value !== prev.type) {
        const defaults = getDefaultFormData();
        return {
          ...defaults,
          label: prev.label,
          type: value,
          instructions: prev.instructions,
        };
      }
      return updated;
    });

    // Clear error when user starts typing
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};
    if (!formData.label.trim()) {
      newErrors.label = 'Label is required';
    }
    if (!formData.type) {
      newErrors.type = 'Type is required';
    }
    // Validate choices for select/checkbox
    if (['select', 'checkbox'].includes(formData.type) && !formData.choices.trim()) {
      newErrors.choices = 'At least one choice is required';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    const submitData = {
      label: formData.label.trim(),
      type: formData.type,
      instructions: formData.instructions.trim(),
    };

    // Add type-specific options based on type
    if (formData.type === 'number') {
      if (formData.min !== '') submitData.min = Number(formData.min);
      if (formData.max !== '') submitData.max = Number(formData.max);
      if (formData.step !== '') submitData.step = Number(formData.step);
      if (formData.prepend) submitData.prepend = formData.prepend;
      if (formData.append) submitData.append = formData.append;
      if (formData.placeholder) submitData.placeholder = formData.placeholder;
    }

    if (formData.type === 'select' || formData.type === 'checkbox') {
      const choicesObj = stringToChoices(formData.choices);
      if (Object.keys(choicesObj).length > 0) {
        submitData.choices = choicesObj;
      }
      if (formData.type === 'select') {
        submitData.allow_null = formData.allow_null;
        if (formData.placeholder) submitData.placeholder = formData.placeholder;
      }
      if (formData.type === 'checkbox') {
        submitData.layout = formData.layout;
        submitData.toggle = formData.toggle;
      }
    }

    if (formData.type === 'date') {
      submitData.display_format = formData.display_format;
      submitData.return_format = formData.return_format;
      submitData.first_day = Number(formData.first_day);
    }

    if (formData.type === 'true_false') {
      submitData.ui = formData.ui;
      submitData.ui_on_text = formData.ui_on_text;
      submitData.ui_off_text = formData.ui_off_text;
    }

    if (formData.type === 'text') {
      if (formData.maxlength !== '') submitData.maxlength = Number(formData.maxlength);
      if (formData.placeholder) submitData.placeholder = formData.placeholder;
      if (formData.prepend) submitData.prepend = formData.prepend;
      if (formData.append) submitData.append = formData.append;
    }

    if (formData.type === 'textarea') {
      if (formData.maxlength !== '') submitData.maxlength = Number(formData.maxlength);
      if (formData.placeholder) submitData.placeholder = formData.placeholder;
      if (formData.rows !== 4) submitData.rows = Number(formData.rows);
    }

    if (formData.type === 'email' || formData.type === 'url') {
      if (formData.placeholder) submitData.placeholder = formData.placeholder;
      if (formData.prepend) submitData.prepend = formData.prepend;
    }

    if (formData.type === 'image') {
      submitData.return_format = formData.image_return_format;
      submitData.preview_size = formData.preview_size;
      submitData.library = formData.library;
    }

    if (formData.type === 'file') {
      submitData.return_format = formData.file_return_format;
      submitData.library = formData.library;
    }

    // Link type needs no special handling - stored as native ACF link

    if (formData.type === 'color_picker') {
      if (formData.default_color) {
        submitData.default_value = formData.default_color;
      }
    }

    if (formData.type === 'relationship') {
      submitData.relation_post_types = formData.relationship_post_types;
      submitData.min = formData.relationship_min;
      submitData.max = formData.relationship_max;
      submitData.return_format = formData.relationship_return_format;
      submitData.filters = ['search', 'post_type'];
    }

    // List view settings
    submitData.show_in_list_view = formData.show_in_list_view;
    if (formData.show_in_list_view) {
      submitData.list_view_order = Number(formData.list_view_order) || 999;
    }

    // Validation options
    submitData.required = formData.required;
    submitData.unique = formData.unique;

    await onSubmit(submitData);
  };

  const postTypeLabel = postType === 'person' ? 'People' : 'Organizations';

  // Common input styling
  const inputClass =
    'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500';
  const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
  const hintClass = 'mt-1 text-xs text-gray-500 dark:text-gray-400';

  // Render type-specific options
  const renderTypeOptions = () => {
    switch (formData.type) {
      case 'text':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Text Options</h4>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="maxlength" className={labelClass}>
                  Max Length
                </label>
                <input
                  id="maxlength"
                  name="maxlength"
                  type="number"
                  min="0"
                  value={formData.maxlength}
                  onChange={handleChange}
                  placeholder="No limit"
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="placeholder" className={labelClass}>
                  Placeholder
                </label>
                <input
                  id="placeholder"
                  name="placeholder"
                  type="text"
                  value={formData.placeholder}
                  onChange={handleChange}
                  className={inputClass}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="prepend" className={labelClass}>
                  Prepend
                </label>
                <input
                  id="prepend"
                  name="prepend"
                  type="text"
                  value={formData.prepend}
                  onChange={handleChange}
                  placeholder='e.g., "$"'
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="append" className={labelClass}>
                  Append
                </label>
                <input
                  id="append"
                  name="append"
                  type="text"
                  value={formData.append}
                  onChange={handleChange}
                  placeholder='e.g., "kg"'
                  className={inputClass}
                />
              </div>
            </div>
          </div>
        );

      case 'textarea':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">
              Textarea Options
            </h4>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="maxlength" className={labelClass}>
                  Max Length
                </label>
                <input
                  id="maxlength"
                  name="maxlength"
                  type="number"
                  min="0"
                  value={formData.maxlength}
                  onChange={handleChange}
                  placeholder="No limit"
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="rows" className={labelClass}>
                  Rows
                </label>
                <input
                  id="rows"
                  name="rows"
                  type="number"
                  min="1"
                  max="20"
                  value={formData.rows}
                  onChange={handleChange}
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label htmlFor="placeholder" className={labelClass}>
                Placeholder
              </label>
              <input
                id="placeholder"
                name="placeholder"
                type="text"
                value={formData.placeholder}
                onChange={handleChange}
                className={inputClass}
              />
            </div>
          </div>
        );

      case 'number':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Number Options</h4>
            <div className="grid grid-cols-3 gap-4">
              <div>
                <label htmlFor="min" className={labelClass}>
                  Min
                </label>
                <input
                  id="min"
                  name="min"
                  type="number"
                  value={formData.min}
                  onChange={handleChange}
                  placeholder="No min"
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="max" className={labelClass}>
                  Max
                </label>
                <input
                  id="max"
                  name="max"
                  type="number"
                  value={formData.max}
                  onChange={handleChange}
                  placeholder="No max"
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="step" className={labelClass}>
                  Step
                </label>
                <input
                  id="step"
                  name="step"
                  type="number"
                  min="0"
                  step="any"
                  value={formData.step}
                  onChange={handleChange}
                  placeholder="1"
                  className={inputClass}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="prepend" className={labelClass}>
                  Prepend
                </label>
                <input
                  id="prepend"
                  name="prepend"
                  type="text"
                  value={formData.prepend}
                  onChange={handleChange}
                  placeholder='e.g., "$"'
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="append" className={labelClass}>
                  Append
                </label>
                <input
                  id="append"
                  name="append"
                  type="text"
                  value={formData.append}
                  onChange={handleChange}
                  placeholder='e.g., "kg"'
                  className={inputClass}
                />
              </div>
            </div>
            <div>
              <label htmlFor="placeholder" className={labelClass}>
                Placeholder
              </label>
              <input
                id="placeholder"
                name="placeholder"
                type="text"
                value={formData.placeholder}
                onChange={handleChange}
                placeholder="e.g., Enter amount"
                className={inputClass}
              />
            </div>
          </div>
        );

      case 'email':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Email Options</h4>
            <div>
              <label htmlFor="placeholder" className={labelClass}>
                Placeholder
              </label>
              <input
                id="placeholder"
                name="placeholder"
                type="text"
                value={formData.placeholder}
                onChange={handleChange}
                placeholder="e.g., name@example.com"
                className={inputClass}
              />
            </div>
          </div>
        );

      case 'url':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">URL Options</h4>
            <div>
              <label htmlFor="placeholder" className={labelClass}>
                Placeholder
              </label>
              <input
                id="placeholder"
                name="placeholder"
                type="text"
                value={formData.placeholder}
                onChange={handleChange}
                placeholder="e.g., https://example.com"
                className={inputClass}
              />
            </div>
            <div>
              <label htmlFor="prepend" className={labelClass}>
                Prepend
              </label>
              <input
                id="prepend"
                name="prepend"
                type="text"
                value={formData.prepend}
                onChange={handleChange}
                placeholder='e.g., "https://"'
                className={inputClass}
              />
            </div>
          </div>
        );

      case 'date':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Date Options</h4>
            <div>
              <label htmlFor="display_format" className={labelClass}>
                Display Format
              </label>
              <select
                id="display_format"
                name="display_format"
                value={formData.display_format}
                onChange={handleChange}
                className={inputClass}
              >
                {DATE_FORMATS.map((fmt) => (
                  <option key={fmt.value} value={fmt.value}>
                    {fmt.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="return_format" className={labelClass}>
                Return Format
              </label>
              <select
                id="return_format"
                name="return_format"
                value={formData.return_format}
                onChange={handleChange}
                className={inputClass}
              >
                {DATE_FORMATS.map((fmt) => (
                  <option key={fmt.value} value={fmt.value}>
                    {fmt.label}
                  </option>
                ))}
              </select>
              <p className={hintClass}>Format used when storing/retrieving the date value</p>
            </div>
            <div>
              <label htmlFor="first_day" className={labelClass}>
                Week Starts On
              </label>
              <select
                id="first_day"
                name="first_day"
                value={formData.first_day}
                onChange={handleChange}
                className={inputClass}
              >
                {WEEK_START_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        );

      case 'select':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Select Options</h4>
            <div>
              <label htmlFor="choices" className={labelClass}>
                Choices <span className="text-red-500">*</span>
              </label>
              <textarea
                id="choices"
                name="choices"
                value={formData.choices}
                onChange={handleChange}
                rows={4}
                placeholder={`Enter one choice per line:\nred : Red\ngreen : Green\nblue : Blue`}
                className={`${inputClass} ${errors.choices ? 'border-red-500 dark:border-red-400' : ''}`}
              />
              <p className={hintClass}>
                Use "value : label" format or just "label" (value will equal label)
              </p>
              {errors.choices && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.choices}</p>
              )}
            </div>
            <div className="flex items-center gap-2">
              <input
                id="allow_null"
                name="allow_null"
                type="checkbox"
                checked={formData.allow_null}
                onChange={handleChange}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
              />
              <label htmlFor="allow_null" className="text-sm text-gray-700 dark:text-gray-300">
                Allow null (empty option)
              </label>
            </div>
            <div>
              <label htmlFor="placeholder" className={labelClass}>
                Placeholder
              </label>
              <input
                id="placeholder"
                name="placeholder"
                type="text"
                value={formData.placeholder}
                onChange={handleChange}
                placeholder="e.g., Select an option..."
                className={inputClass}
              />
              <p className={hintClass}>Shown when no option is selected</p>
            </div>
          </div>
        );

      case 'checkbox':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">
              Checkbox Options
            </h4>
            <div>
              <label htmlFor="choices" className={labelClass}>
                Choices <span className="text-red-500">*</span>
              </label>
              <textarea
                id="choices"
                name="choices"
                value={formData.choices}
                onChange={handleChange}
                rows={4}
                placeholder={`Enter one choice per line:\noption1 : Option 1\noption2 : Option 2\noption3 : Option 3`}
                className={`${inputClass} ${errors.choices ? 'border-red-500 dark:border-red-400' : ''}`}
              />
              <p className={hintClass}>
                Use "value : label" format or just "label" (value will equal label)
              </p>
              {errors.choices && (
                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.choices}</p>
              )}
            </div>
            <div>
              <label className={labelClass}>Layout</label>
              <div className="flex gap-4 mt-1">
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="layout"
                    value="vertical"
                    checked={formData.layout === 'vertical'}
                    onChange={handleChange}
                    className="w-4 h-4 border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">Vertical</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="layout"
                    value="horizontal"
                    checked={formData.layout === 'horizontal'}
                    onChange={handleChange}
                    className="w-4 h-4 border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">Horizontal</span>
                </label>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <input
                id="toggle"
                name="toggle"
                type="checkbox"
                checked={formData.toggle}
                onChange={handleChange}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
              />
              <label htmlFor="toggle" className="text-sm text-gray-700 dark:text-gray-300">
                Toggle All (allow select/deselect all)
              </label>
            </div>
          </div>
        );

      case 'true_false':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">
              True/False Options
            </h4>
            <div className="flex items-center gap-2">
              <input
                id="ui"
                name="ui"
                type="checkbox"
                checked={formData.ui}
                onChange={handleChange}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
              />
              <label htmlFor="ui" className="text-sm text-gray-700 dark:text-gray-300">
                Display as toggle switch
              </label>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="ui_on_text" className={labelClass}>
                  ON Text
                </label>
                <input
                  id="ui_on_text"
                  name="ui_on_text"
                  type="text"
                  value={formData.ui_on_text}
                  onChange={handleChange}
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="ui_off_text" className={labelClass}>
                  OFF Text
                </label>
                <input
                  id="ui_off_text"
                  name="ui_off_text"
                  type="text"
                  value={formData.ui_off_text}
                  onChange={handleChange}
                  className={inputClass}
                />
              </div>
            </div>
          </div>
        );

      case 'image':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Image Options</h4>
            <div>
              <label htmlFor="image_return_format" className={labelClass}>
                Return Format
              </label>
              <select
                id="image_return_format"
                name="image_return_format"
                value={formData.image_return_format}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="array">Image Array</option>
                <option value="url">Image URL</option>
                <option value="id">Image ID</option>
              </select>
              <p className={hintClass}>
                Array returns full image data (URL, sizes, alt). URL returns just the image URL. ID
                returns the attachment ID.
              </p>
            </div>
            <div>
              <label htmlFor="preview_size" className={labelClass}>
                Preview Size
              </label>
              <select
                id="preview_size"
                name="preview_size"
                value={formData.preview_size}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="thumbnail">Thumbnail (150x150)</option>
                <option value="medium">Medium (300x300)</option>
                <option value="large">Large (1024x1024)</option>
                <option value="full">Full Size</option>
              </select>
              <p className={hintClass}>Size used when displaying the image preview in forms</p>
            </div>
            <div>
              <label htmlFor="library" className={labelClass}>
                Media Library
              </label>
              <select
                id="library"
                name="library"
                value={formData.library}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="all">All media</option>
                <option value="uploadedTo">Uploaded to this post</option>
              </select>
            </div>
          </div>
        );

      case 'file':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">File Options</h4>
            <div>
              <label htmlFor="file_return_format" className={labelClass}>
                Return Format
              </label>
              <select
                id="file_return_format"
                name="file_return_format"
                value={formData.file_return_format}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="array">File Array</option>
                <option value="url">File URL</option>
                <option value="id">File ID</option>
              </select>
              <p className={hintClass}>
                Array returns full file data (URL, filename, type). URL returns just the file URL.
                ID returns the attachment ID.
              </p>
            </div>
            <div>
              <label htmlFor="library" className={labelClass}>
                Media Library
              </label>
              <select
                id="library"
                name="library"
                value={formData.library}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="all">All media</option>
                <option value="uploadedTo">Uploaded to this post</option>
              </select>
            </div>
          </div>
        );

      case 'link':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Link Options</h4>
            <div className="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Link fields capture a URL and optional display text. Users can enter a URL and
                customize the link text shown to viewers.
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-500 mt-2">
                No additional configuration needed.
              </p>
            </div>
          </div>
        );

      case 'color_picker':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">Color Options</h4>
            <div>
              <label className={labelClass}>Default Color</label>
              <div className="flex items-start gap-3 mt-2">
                <div className="relative">
                  <Sketch
                    color={formData.default_color || '#000000'}
                    disableAlpha={true}
                    presetColors={false}
                    onChange={(color) => {
                      setFormData((prev) => ({ ...prev, default_color: color.hex }));
                    }}
                    style={{ boxShadow: 'none' }}
                  />
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <div
                      className="w-10 h-10 rounded border border-gray-300 dark:border-gray-600"
                      style={{
                        backgroundColor: formData.default_color || 'transparent',
                        backgroundImage: !formData.default_color
                          ? 'linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%)'
                          : 'none',
                        backgroundSize: '8px 8px',
                        backgroundPosition: '0 0, 0 4px, 4px -4px, -4px 0px',
                      }}
                    />
                    <input
                      type="text"
                      value={formData.default_color}
                      onChange={(e) => {
                        setFormData((prev) => ({ ...prev, default_color: e.target.value }));
                      }}
                      placeholder="#000000"
                      className={`${inputClass} w-28`}
                    />
                    {formData.default_color && (
                      <button
                        type="button"
                        onClick={() => setFormData((prev) => ({ ...prev, default_color: '' }))}
                        className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                      >
                        Clear
                      </button>
                    )}
                  </div>
                  <p className={`${hintClass} mt-2`}>
                    Optional. Leave empty for no default color.
                  </p>
                </div>
              </div>
            </div>
          </div>
        );

      case 'relationship':
        return (
          <div className="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400">
              Relationship Options
            </h4>
            <div>
              <label className={labelClass}>Link to Post Types</label>
              <div className="flex flex-col gap-2 mt-2">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={formData.relationship_post_types.includes('person')}
                    onChange={(e) => {
                      setFormData((prev) => {
                        const types = e.target.checked
                          ? [...prev.relationship_post_types, 'person']
                          : prev.relationship_post_types.filter((t) => t !== 'person');
                        return { ...prev, relationship_post_types: types };
                      });
                    }}
                    className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">People</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={formData.relationship_post_types.includes('company')}
                    onChange={(e) => {
                      setFormData((prev) => {
                        const types = e.target.checked
                          ? [...prev.relationship_post_types, 'company']
                          : prev.relationship_post_types.filter((t) => t !== 'company');
                        return { ...prev, relationship_post_types: types };
                      });
                    }}
                    className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">Organizations</span>
                </label>
              </div>
              <p className={hintClass}>Select which post types can be linked</p>
            </div>
            <div>
              <label className={labelClass}>Cardinality</label>
              <div className="flex gap-4 mt-2">
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="cardinality"
                    checked={formData.relationship_max === 1}
                    onChange={() => {
                      setFormData((prev) => ({ ...prev, relationship_max: 1 }));
                    }}
                    className="w-4 h-4 border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">Single</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="cardinality"
                    checked={formData.relationship_max !== 1}
                    onChange={() => {
                      setFormData((prev) => ({ ...prev, relationship_max: 0 }));
                    }}
                    className="w-4 h-4 border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">Multiple</span>
                </label>
              </div>
              {formData.relationship_max !== 1 && (
                <div className="mt-3">
                  <label htmlFor="relationship_max" className={labelClass}>
                    Maximum Selections
                  </label>
                  <input
                    id="relationship_max"
                    type="number"
                    min="0"
                    value={formData.relationship_max}
                    onChange={(e) => {
                      setFormData((prev) => ({
                        ...prev,
                        relationship_max: parseInt(e.target.value) || 0,
                      }));
                    }}
                    placeholder="0 = unlimited"
                    className={`${inputClass} w-32`}
                  />
                  <p className={hintClass}>Leave at 0 for unlimited</p>
                </div>
              )}
            </div>
            <div>
              <label htmlFor="relationship_return_format" className={labelClass}>
                Return Format
              </label>
              <select
                id="relationship_return_format"
                name="relationship_return_format"
                value={formData.relationship_return_format}
                onChange={handleChange}
                className={inputClass}
              >
                <option value="object">Post Object</option>
                <option value="id">Post ID</option>
              </select>
              <p className={hintClass}>
                Object returns full post data. ID returns just the post ID.
              </p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 bg-black/50 z-40 transition-opacity duration-300 ${
          isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
        onClick={onClose}
      />

      {/* Panel */}
      <div
        className={`fixed inset-y-0 right-0 w-full max-w-md bg-white dark:bg-gray-800 shadow-xl transform transition-transform duration-300 ease-in-out z-50 ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="h-full flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">
                {isEditing ? 'Edit Field' : 'Add Field'}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {postTypeLabel} custom field
              </p>
            </div>
            <button
              onClick={onClose}
              className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="flex-1 flex flex-col">
            <div className="flex-1 px-6 py-4 space-y-4 overflow-y-auto">
              {/* Label field */}
              <div>
                <label
                  htmlFor="field-label"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Label <span className="text-red-500">*</span>
                </label>
                <input
                  ref={labelInputRef}
                  id="field-label"
                  name="label"
                  type="text"
                  value={formData.label}
                  onChange={handleChange}
                  placeholder="e.g., LinkedIn Profile"
                  className={`w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500 ${
                    errors.label
                      ? 'border-red-500 dark:border-red-400'
                      : 'border-gray-300 dark:border-gray-600'
                  }`}
                />
                {errors.label && (
                  <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.label}</p>
                )}
              </div>

              {/* Type field */}
              <div>
                <label
                  htmlFor="field-type"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Type <span className="text-red-500">*</span>
                </label>
                <select
                  id="field-type"
                  name="type"
                  value={formData.type}
                  onChange={handleChange}
                  disabled={isEditing}
                  className={`w-full px-3 py-2 border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500 ${
                    errors.type
                      ? 'border-red-500 dark:border-red-400'
                      : 'border-gray-300 dark:border-gray-600'
                  } ${isEditing ? 'opacity-60 cursor-not-allowed' : ''}`}
                >
                  {FIELD_TYPES.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
                {isEditing && (
                  <p className="mt-1 text-sm text-amber-600 dark:text-amber-400">
                    Field type cannot be changed after creation
                  </p>
                )}
                {errors.type && (
                  <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.type}</p>
                )}
              </div>

              {/* Type-specific options */}
              {renderTypeOptions()}

              {/* Validation Options */}
              <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">Validation Options</h4>
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <input
                      id="required"
                      name="required"
                      type="checkbox"
                      checked={formData.required}
                      onChange={handleChange}
                      className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                    />
                    <label htmlFor="required" className="text-sm text-gray-700 dark:text-gray-300">
                      Required field
                    </label>
                  </div>
                  <p className={hintClass}>Users must provide a value when saving</p>

                  <div className="flex items-center gap-2 mt-4">
                    <input
                      id="unique"
                      name="unique"
                      type="checkbox"
                      checked={formData.unique}
                      onChange={handleChange}
                      className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                    />
                    <label htmlFor="unique" className="text-sm text-gray-700 dark:text-gray-300">
                      Unique value
                    </label>
                  </div>
                  <p className={hintClass}>No two records can have the same value for this field</p>
                </div>
              </div>

              {/* Display Options */}
              <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">Display Options</h4>
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <input
                      id="show_in_list_view"
                      name="show_in_list_view"
                      type="checkbox"
                      checked={formData.show_in_list_view}
                      onChange={handleChange}
                      className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
                    />
                    <label htmlFor="show_in_list_view" className="text-sm text-gray-700 dark:text-gray-300">
                      Show as column in list view
                    </label>
                  </div>
                  {formData.show_in_list_view && (
                    <div>
                      <label htmlFor="list_view_order" className={labelClass}>
                        Column Order
                      </label>
                      <input
                        id="list_view_order"
                        name="list_view_order"
                        type="number"
                        min="1"
                        max="999"
                        value={formData.list_view_order}
                        onChange={handleChange}
                        placeholder="999"
                        className={`${inputClass} w-24`}
                      />
                      <p className={hintClass}>Lower numbers appear first (1 = leftmost)</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Description/Instructions field */}
              <div>
                <label
                  htmlFor="field-instructions"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                  Description
                </label>
                <textarea
                  id="field-instructions"
                  name="instructions"
                  value={formData.instructions}
                  onChange={handleChange}
                  rows={3}
                  placeholder="Help text shown to users when filling in this field"
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                />
              </div>
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
              <button
                type="button"
                onClick={onClose}
                className="btn-secondary"
                disabled={isSubmitting}
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="btn-primary inline-flex items-center gap-2"
              >
                {isSubmitting && (
                  <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                      fill="none"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                )}
                {isSubmitting ? 'Saving...' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
