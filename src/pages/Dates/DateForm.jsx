import { useEffect, useState, useMemo } from 'react';
import { Link, useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { ArrowLeft, Save, X } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { usePeople } from '@/hooks/usePeople';
import { decodeHtml, getPersonName } from '@/utils/formatters';

function PeopleSelector({ value = [], onChange, people = [], isLoading }) {
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
    onChange(value.filter(id => id !== personId));
  };

  return (
    <div className="space-y-2">
      {/* Selected people */}
      {selectedPeople.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {selectedPeople.map(person => (
            <span
              key={person.id}
              className="inline-flex items-center gap-1 px-3 py-1 bg-primary-100 text-primary-800 rounded-full text-sm"
            >
              {getPersonName(person)}
              <button
                type="button"
                onClick={() => handleRemove(person.id)}
                className="hover:text-primary-600"
              >
                <X className="w-3 h-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      {/* Search input */}
      <div className="relative">
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          placeholder="Search for people..."
          className="input"
          disabled={isLoading}
        />

        {/* Dropdown */}
        {searchTerm && (
          <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
            {filteredPeople.length > 0 ? (
              filteredPeople.map(person => (
                <button
                  key={person.id}
                  type="button"
                  onClick={() => handleAdd(person.id)}
                  disabled={value.includes(person.id)}
                  className={`w-full text-left px-4 py-2 hover:bg-gray-50 ${
                    value.includes(person.id) ? 'opacity-50 cursor-not-allowed' : ''
                  }`}
                >
                  {getPersonName(person)}
                </button>
              ))
            ) : (
              <p className="px-4 py-2 text-gray-500 text-sm">No people found</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export default function DateForm() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditing = !!id;

  // Get URL params for pre-filling (for new dates)
  const prefilledPersonId = searchParams.get('person');
  const prefilledType = searchParams.get('type');

  // Fetch the date if editing
  const { data: dateItem, isLoading } = useQuery({
    queryKey: ['important-date', id],
    queryFn: async () => {
      const response = await wpApi.getDate(id);
      return response.data;
    },
    enabled: isEditing,
  });

  // Extract related people IDs from the date being edited (for fetching specific people)
  const relatedPeopleFromDate = useMemo(() => {
    if (!dateItem?.acf?.related_people) return [];
    const raw = dateItem.acf.related_people;
    if (Array.isArray(raw)) {
      return raw.map(p => {
        if (typeof p === 'object' && p !== null) {
          return parseInt(p.ID || p.id || p.post_id, 10);
        }
        return parseInt(p, 10);
      }).filter(id => !isNaN(id) && id > 0);
    }
    return [];
  }, [dateItem]);

  // Fetch all people for the selector (usePeople hook handles pagination automatically)
  const { data: basePeople = [], isLoading: isPeopleLoading } = usePeople({ 
    orderby: 'title', 
    order: 'asc' 
  });

  // Fetch specific people that are related to this date (in case they're not in the paginated results)
  const { data: relatedPeopleData = [] } = useQuery({
    queryKey: ['people', 'specific', relatedPeopleFromDate],
    queryFn: async () => {
      // Fetch each related person individually
      const promises = relatedPeopleFromDate.map(personId =>
        wpApi.getPerson(personId, { _embed: true }).then(res => res.data).catch(() => null)
      );
      const results = await Promise.all(promises);
      return results.filter(Boolean);
    },
    enabled: relatedPeopleFromDate.length > 0,
  });

  // Combine base people with any specific related people (ensuring no duplicates)
  const allPeople = useMemo(() => {
    const peopleMap = new Map();
    // Add base people first
    basePeople.forEach(p => peopleMap.set(p.id, p));
    // Add/override with specific related people (transform them to match format)
    relatedPeopleData.forEach(p => {
      // Transform related people data to match the format from usePeople
      const transformed = {
        ...p,
        id: p.id,
        name: getPersonName(p),
        first_name: p.acf?.first_name || '',
        last_name: p.acf?.last_name || '',
        is_favorite: p.acf?.is_favorite || false,
        thumbnail: p._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                   p._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url ||
                   null,
        labels: p._embedded?.['wp:term']?.flat()
          ?.filter(term => term?.taxonomy === 'person_label')
          ?.map(term => term.name) || [],
      };
      peopleMap.set(transformed.id, transformed);
    });
    return Array.from(peopleMap.values());
  }, [basePeople, relatedPeopleData]);

  // Fetch date types
  const { data: dateTypes = [] } = useQuery({
    queryKey: ['date-types'],
    queryFn: async () => {
      const response = await wpApi.getDateTypes();
      // Sort date types alphabetically by name
      const sorted = (response.data || []).sort((a, b) => {
        const nameA = a.name || '';
        const nameB = b.name || '';
        return nameA.localeCompare(nameB);
      });
      return sorted;
    },
  });

  const createDate = useMutation({
    mutationFn: (data) => wpApi.createDate(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      // If we came from a person page, go back there
      if (prefilledPersonId) {
        navigate(`/people/${prefilledPersonId}`);
      } else {
        navigate('/dates');
      }
    },
  });

  const updateDate = useMutation({
    mutationFn: (data) => wpApi.updateDate(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      queryClient.invalidateQueries({ queryKey: ['important-date', id] });
      queryClient.invalidateQueries({ queryKey: ['people'] });
      // Navigate back to the person page if we have a related person
      const relatedPeople = watch('related_people');
      if (relatedPeople?.length > 0) {
        navigate(`/people/${relatedPeople[0]}`);
      } else {
        navigate('/dates');
      }
    },
  });

  const { register, handleSubmit, reset, watch, setValue, control, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      title: '',
      date_value: '',
      date_type: '',
      related_people: [],
      is_recurring: true,
    },
  });

  // Watch values for auto-generating title
  const watchedPeople = watch('related_people');
  const watchedDateType = watch('date_type');
  const watchedTitle = watch('title');

  // Auto-generate title when people or date type changes
  useEffect(() => {
    // Only auto-generate if title is empty or matches a previous auto-generated pattern
    const autoGeneratedPatterns = ["'s Birthday", "Wedding of "];
    const isAutoGenerated = !watchedTitle || autoGeneratedPatterns.some(p =>
      watchedTitle.endsWith(p) || watchedTitle.startsWith(p)
    );
    if (!isAutoGenerated) {
      return;
    }

    if (watchedPeople?.length > 0 && watchedDateType) {
      const dateType = dateTypes.find(t => t.id === parseInt(watchedDateType));
      const typeName = dateType?.name || '';
      const typeSlug = dateType?.slug?.toLowerCase() || typeName.toLowerCase();

      // Get first names of related people
      const firstNames = watchedPeople.map(personId => {
        const person = allPeople.find(p => p.id === personId);
        if (person) {
          return decodeHtml(person.acf?.first_name || person.title?.rendered?.split(' ')[0] || '');
        }
        return null;
      }).filter(Boolean);

      if (firstNames.length > 0 && typeName) {
        let title;
        if (typeSlug === 'wedding') {
          // For wedding, the title is "Wedding of X & Y"
          if (firstNames.length >= 2) {
            title = `Wedding of ${firstNames[0]} & ${firstNames[1]}`;
          } else {
            title = `Wedding of ${firstNames[0]}`;
          }
        } else {
          // For other types, use first person's name
          title = `${firstNames[0]}'s ${typeName}`;
        }
        setValue('title', title);
      }
    }
  }, [watchedPeople, watchedDateType, allPeople, dateTypes, setValue, watchedTitle]);

  // Track if we've initialized the form to prevent resetting after user input
  const [formInitialized, setFormInitialized] = useState(false);

  // Set default values when editing or from URL params
  useEffect(() => {
    // Don't reset if form has already been initialized (user might have made changes)
    if (formInitialized && !isEditing) {
      return;
    }

    // Wait for people to be loaded before setting defaults (needed for people selector)
    // When editing, also wait for the specific related people to be fetched
    const relatedPeopleLoaded = relatedPeopleFromDate.length === 0 || relatedPeopleData.length > 0;
    if (dateItem && allPeople.length > 0 && relatedPeopleLoaded) {
      // Get related people IDs from ACF field
      // ACF can return IDs in various formats depending on configuration
      let relatedPeopleIds = [];
      const rawRelatedPeople = dateItem.acf?.related_people;

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

      // Get date type term ID
      let dateTypeId = '';
      if (dateItem.date_type?.length > 0) {
        dateTypeId = dateItem.date_type[0].toString();
      }

      reset({
        title: decodeHtml(dateItem.title?.rendered || ''),
        date_value: dateItem.acf?.date_value || '',
        date_type: dateTypeId,
        related_people: relatedPeopleIds,
        is_recurring: dateItem.acf?.is_recurring ?? true,
      });
      setFormInitialized(true);
    } else if (!isEditing && allPeople.length > 0 && dateTypes.length > 0) {
      // Handle URL params for new dates - only reset once when both people and dateTypes are loaded
      const defaults = {
        title: '',
        date_value: '',
        date_type: '',
        related_people: [],
        is_recurring: true,
      };

      if (prefilledPersonId) {
        defaults.related_people = [parseInt(prefilledPersonId)];
      }

      if (prefilledType) {
        const matchingType = dateTypes.find(t =>
          t.slug === prefilledType || t.name.toLowerCase() === prefilledType.toLowerCase()
        );
        if (matchingType) {
          defaults.date_type = matchingType.id.toString();
        }
      }

      reset(defaults);
      setFormInitialized(true);
    }
  }, [dateItem, reset, isEditing, prefilledPersonId, prefilledType, dateTypes, allPeople, relatedPeopleFromDate, relatedPeopleData, formInitialized]);
  
  // Update document title - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(
    isEditing && dateItem
      ? `Edit ${dateItem.title?.rendered || dateItem.title || 'date'}`
      : 'New date'
  );

  const onSubmit = async (data) => {
    const payload = {
      title: data.title,
      status: 'publish',
      date_type: data.date_type ? [parseInt(data.date_type)] : [],
      acf: {
        date_value: data.date_value,
        related_people: data.related_people,
        is_recurring: data.is_recurring,
      },
    };

    if (isEditing) {
      updateDate.mutate(payload);
    } else {
      createDate.mutate(payload);
    }
  };

  if (isEditing && isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  // Determine where to navigate back to
  const currentRelatedPeople = watch('related_people');
  const backToPersonId = isEditing && currentRelatedPeople?.length > 0
    ? currentRelatedPeople[0]
    : prefilledPersonId;
  const backUrl = backToPersonId ? `/people/${backToPersonId}` : '/dates';
  const backLabel = backToPersonId ? 'Back to person' : 'Back to dates';

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <Link to={backUrl} className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">{backLabel}</span>
        </Link>
      </div>

      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit date' : 'Add new date'}
        </h1>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* People selector */}
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
              render={({ field }) => {
                const currentValue = field.value ? String(field.value) : '';
                return (
                  <select
                    value={currentValue}
                    onChange={(e) => {
                      const newValue = e.target.value;
                      field.onChange(newValue);
                    }}
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                    className="input"
                  >
                    <option value="">Select a type...</option>
                    {dateTypes.map(type => (
                      <option key={type.id} value={String(type.id)}>
                        {type.name}
                      </option>
                    ))}
                  </select>
                );
              }}
            />
            {errors.date_type && (
              <p className="text-sm text-red-600 mt-1">{errors.date_type.message}</p>
            )}
          </div>

          {/* Label/Title */}
          <div>
            <label className="label">Label</label>
            <input
              {...register('title')}
              className="input"
              placeholder="e.g., Mom's Birthday, Wedding Anniversary"
            />
            <p className="text-xs text-gray-500 mt-1">
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
            />
            {errors.date_value && (
              <p className="text-sm text-red-600 mt-1">{errors.date_value.message}</p>
            )}
          </div>

          {/* Recurring checkbox */}
          <div className="flex items-center">
            <input
              type="checkbox"
              {...register('is_recurring')}
              className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label className="ml-2 text-sm text-gray-700">
              Repeats every year
            </label>
          </div>


          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to={backUrl} className="btn-secondary">Cancel</Link>
            <button type="submit" className="btn-primary" disabled={isSubmitting}>
              <Save className="w-4 h-4 mr-2" />
              {isEditing ? 'Save changes' : 'Create date'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
