import { useEffect, useState, useMemo, useRef } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { decodeHtml, getPersonName } from '@/utils/formatters';

function PeopleSelector({ value = [], onChange, people = [], isLoading, currentPersonId }) {
  const [searchTerm, setSearchTerm] = useState('');

  const filteredPeople = useMemo(() => {
    if (!searchTerm) return people.slice(0, 10);
    const term = searchTerm.toLowerCase();
    return people.filter(p =>
      getPersonName(p).toLowerCase().includes(term)
    ).slice(0, 10);
  }, [people, searchTerm]);

  const selectedPeople = useMemo(() => {
    return value.map(id => people.find(p => p.id === id)).filter(Boolean);
  }, [value, people]);

  const handleAdd = (personId) => {
    if (!value.includes(personId)) {
      onChange([...value, personId]);
    }
    setSearchTerm('');
  };

  const handleRemove = (personId) => {
    // Don't allow removing the current person
    if (personId === currentPersonId) return;
    onChange(value.filter(id => id !== personId));
  };

  return (
    <div className="space-y-2">
      {selectedPeople.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {selectedPeople.map(person => (
            <span
              key={person.id}
              className="inline-flex items-center gap-1 px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300 rounded-full text-sm"
            >
              {getPersonName(person)}
              {person.id !== currentPersonId && (
                <button
                  type="button"
                  onClick={() => handleRemove(person.id)}
                  className="hover:text-primary-600 dark:hover:text-primary-400"
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </span>
          ))}
        </div>
      )}

      <div className="relative">
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          placeholder="Search for people to add..."
          className="input"
          disabled={isLoading}
        />

        {searchTerm && (
          <div className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
            {filteredPeople.length > 0 ? (
              filteredPeople.map(person => (
                <button
                  key={person.id}
                  type="button"
                  onClick={() => handleAdd(person.id)}
                  disabled={value.includes(person.id)}
                  className={`w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-50 ${
                    value.includes(person.id) ? 'opacity-50 cursor-not-allowed' : ''
                  }`}
                >
                  {getPersonName(person)}
                </button>
              ))
            ) : (
              <p className="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm">No people found</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export default function ImportantDateModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  dateItem = null,
  personId,
  allPeople = [],
  isPeopleLoading = false
}) {
  const isEditing = !!dateItem;

  // Track if user has manually edited the title
  const hasUserEditedTitle = useRef(false);

  // Fetch date types
  const { data: dateTypes = [] } = useQuery({
    queryKey: ['date-types'],
    queryFn: async () => {
      const response = await wpApi.getDateTypes();
      const sorted = (response.data || []).sort((a, b) => {
        const nameA = a.name || '';
        const nameB = b.name || '';
        return nameA.localeCompare(nameB);
      });
      return sorted;
    },
  });

  const { register, handleSubmit, reset, watch, setValue, control, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      date_value: '',
      date_type: '',
      related_people: [],
      is_recurring: true,
      year_unknown: false,
    },
  });

  const watchedPeople = watch('related_people');
  const watchedDateType = watch('date_type');
  const watchedTitle = watch('title');

  // Auto-generate title when people or date type changes
  useEffect(() => {
    // Skip auto-generation if user has manually edited the title
    if (hasUserEditedTitle.current) return;

    if (watchedPeople?.length > 0 && watchedDateType) {
      const dateType = dateTypes.find(t => t.id === parseInt(watchedDateType));
      const typeName = dateType?.name || '';
      const typeSlug = dateType?.slug?.toLowerCase() || typeName.toLowerCase();

      // Get full names for auto-generation (matches backend behavior)
      const fullNames = watchedPeople.map(pId => {
        const person = allPeople.find(p => p.id === pId);
        if (person) {
          // Use full name from first_name + last_name fields
          const firstName = decodeHtml(person.acf?.first_name || person.first_name || '');
          const lastName = decodeHtml(person.acf?.last_name || person.last_name || '');
          const fullName = `${firstName} ${lastName}`.trim();

          // Fall back to title.rendered (already contains full name)
          return fullName || decodeHtml(person.title?.rendered || '');
        }
        return null;
      }).filter(Boolean);

      if (fullNames.length > 0 && typeName) {
        let title;
        if (typeSlug === 'wedding') {
          title = fullNames.length >= 2
            ? `Wedding of ${fullNames[0]} & ${fullNames[1]}`
            : `Wedding of ${fullNames[0]}`;
        } else {
          title = `${fullNames[0]}'s ${typeName}`;
        }
        setValue('title', title);
      }
    }
  }, [watchedPeople, watchedDateType, allPeople, dateTypes, setValue, watchedTitle]);

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      // Reset the user edit tracking when modal opens
      hasUserEditedTitle.current = false;

      if (dateItem) {
        // Editing existing date
        // related_people can be:
        // - Array of objects { id, name } from /prm/v1/people/{id}/dates
        // - Array of objects with ID/id from ACF (when dateItem.acf exists)
        // - Array of numbers
        let relatedPeopleIds = [];
        const rawRelatedPeople = dateItem.related_people || dateItem.acf?.related_people;
        if (rawRelatedPeople) {
          if (Array.isArray(rawRelatedPeople)) {
            relatedPeopleIds = rawRelatedPeople.map(p => {
              if (typeof p === 'object' && p !== null) {
                return parseInt(p.ID || p.id || p.post_id, 10);
              }
              return parseInt(p, 10);
            }).filter(id => !isNaN(id) && id > 0);
          } else if (typeof rawRelatedPeople === 'number') {
            relatedPeopleIds = [rawRelatedPeople];
          }
        }

        // date_type can be:
        // - Array of term NAMES (strings) from /prm/v1/people/{id}/dates
        // - Array of term IDs (numbers) from WP REST API
        let dateTypeId = '';
        if (dateItem.date_type?.length > 0 && dateItem.date_type[0] != null) {
          const firstType = dateItem.date_type[0];
          if (typeof firstType === 'string') {
            // It's a name - find the matching ID from dateTypes
            const matchingType = dateTypes.find(t => 
              t.name.toLowerCase() === firstType.toLowerCase()
            );
            dateTypeId = matchingType ? String(matchingType.id) : '';
          } else {
            // It's already an ID
            dateTypeId = String(firstType);
          }
        }

        reset({
          title: decodeHtml(dateItem.title?.rendered || dateItem.title || ''),
          date_value: dateItem.acf?.date_value || dateItem.date_value || '',
          date_type: dateTypeId,
          related_people: relatedPeopleIds,
          is_recurring: dateItem.acf?.is_recurring ?? dateItem.is_recurring ?? true,
          year_unknown: dateItem.acf?.year_unknown ?? dateItem.year_unknown ?? false,
        });

        // If custom_label is present, mark title as user-edited to prevent auto-generation
        if (dateItem.custom_label) {
          hasUserEditedTitle.current = true;
        }
      } else {
        // New date - pre-fill with current person and today's date
        const today = new Date().toISOString().split('T')[0];
        reset({
          title: '',
          date_value: today,
          date_type: '',
          related_people: personId ? [parseInt(personId)] : [],
          is_recurring: true,
          year_unknown: false,
        });
      }
    }
  }, [isOpen, dateItem, personId, reset, dateTypes]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    // Parse date_type and filter out invalid values
    const dateTypeId = data.date_type ? parseInt(data.date_type, 10) : null;
    const dateType = dateTypeId && !isNaN(dateTypeId) ? [dateTypeId] : [];
    
    onSubmit({
      title: data.title,
      date_type: dateType,
      date_value: data.date_value,
      related_people: data.related_people,
      is_recurring: data.is_recurring,
      year_unknown: data.year_unknown,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Edit date' : 'Add date'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Related people */}
            <div>
              <label className="label">Related people</label>
              <Controller
                name="related_people"
                control={control}
                render={({ field }) => (
                  <PeopleSelector
                    value={field.value}
                    onChange={field.onChange}
                    people={allPeople}
                    isLoading={isPeopleLoading}
                    currentPersonId={parseInt(personId)}
                  />
                )}
              />
            </div>

            {/* Date type */}
            <div>
              <label className="label">Date type *</label>
              <Controller
                name="date_type"
                control={control}
                rules={{ required: 'Please select a date type' }}
                render={({ field }) => (
                  <select
                    value={field.value ? String(field.value) : ''}
                    onChange={(e) => field.onChange(e.target.value)}
                    className="input"
                    disabled={isLoading}
                  >
                    <option value="">Select a type...</option>
                    {dateTypes.map(type => (
                      <option key={type.id} value={String(type.id)}>
                        {type.name}
                      </option>
                    ))}
                  </select>
                )}
              />
              {errors.date_type && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.date_type.message}</p>
              )}
            </div>

            {/* Label/Title */}
            <div>
              <label className="label">Label</label>
              <input
                {...register('title', {
                  onChange: () => { hasUserEditedTitle.current = true; }
                })}
                className="input"
                placeholder="e.g., Mom's Birthday"
                disabled={isLoading}
              />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Auto-generated from person and date type. You can customize it.
              </p>
            </div>

            {/* Date value */}
            <div>
              <label className="label">Date *</label>
              <input
                {...register('date_value', { required: 'Date is required' })}
                type="date"
                className="input"
                disabled={isLoading}
              />
              {errors.date_value && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.date_value.message}</p>
              )}
            </div>

            {/* Year unknown */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="year_unknown"
                {...register('year_unknown')}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:bg-gray-700"
                disabled={isLoading}
              />
              <label htmlFor="year_unknown" className="ml-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Year unknown
              </label>
            </div>

            {/* Recurring */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_recurring"
                {...register('is_recurring')}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:bg-gray-700"
                disabled={isLoading}
              />
              <label htmlFor="is_recurring" className="ml-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Repeats every year
              </label>
            </div>
          </div>
          
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
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Add date')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
