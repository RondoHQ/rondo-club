import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { usePerson, useUpdatePerson, usePeople } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

// Helper to decode HTML entities
function decodeHtml(html) {
  if (!html) return '';
  const txt = document.createElement('textarea');
  txt.innerHTML = html;
  return txt.value;
}

function PersonSelector({ value, onChange, people, isLoading, excludePersonId }) {
  const filteredPeople = people.filter(p => p.id !== excludePersonId);
  
  return (
    <select
      value={value || ''}
      onChange={(e) => onChange(e.target.value ? parseInt(e.target.value, 10) : null)}
      className="input"
      disabled={isLoading}
    >
      <option value="">Select a person...</option>
      {filteredPeople.map(person => (
        <option key={person.id} value={person.id}>
          {decodeHtml(person.title?.rendered || person.title)}
        </option>
      ))}
    </select>
  );
}

export default function RelationshipForm() {
  const { personId, index } = useParams();
  const navigate = useNavigate();
  const relationshipIndex = parseInt(index, 10);
  const isEditing = !isNaN(relationshipIndex);
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(personId);
  const updatePerson = useUpdatePerson();
  
  // Fetch all people for the selector
  const { data: allPeople = [], isLoading: isPeopleLoading } = usePeople();
  
  // Fetch relationship types
  const { data: relationshipTypes = [], isLoading: isRelationshipTypesLoading } = useQuery({
    queryKey: ['relationship-types'],
    queryFn: async () => {
      const response = await wpApi.getRelationshipTypes();
      return response.data;
    },
  });

  const { register, handleSubmit, reset, control, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      related_person: null,
      relationship_type: null,
      relationship_label: '',
    },
  });

  // Load relationship item when editing
  useEffect(() => {
    if (person && isEditing) {
      const relationships = person.acf?.relationships || [];
      const relationshipItem = relationships[relationshipIndex];
      
      if (relationshipItem) {
        reset({
          related_person: relationshipItem.related_person || null,
          relationship_type: relationshipItem.relationship_type || null,
          relationship_label: relationshipItem.relationship_label || '',
        });
      }
    }
  }, [person, relationshipIndex, isEditing, reset]);

  // Update document title
  useDocumentTitle(
    isEditing
      ? `Edit Relationship - ${person?.title?.rendered || person?.title || 'Person'}`
      : `Add Relationship - ${person?.title?.rendered || person?.title || 'Person'}`
  );

  const onSubmit = async (data) => {
    try {
      const relationships = [...(person.acf?.relationships || [])];
      
      const relationshipItem = {
        related_person: data.related_person || null,
        relationship_type: data.relationship_type || null,
        relationship_label: data.relationship_label || '',
      };

      if (isEditing) {
        // Update existing item
        relationships[relationshipIndex] = relationshipItem;
      } else {
        // Add new item
        relationships.push(relationshipItem);
      }

      // Ensure all repeater fields are arrays (empty array if not present)
      const acfData = {
        ...person.acf,
        relationships: relationships,
        contact_info: Array.isArray(person.acf?.contact_info) ? person.acf.contact_info : [],
        work_history: Array.isArray(person.acf?.work_history) ? person.acf.work_history : [],
      };

      await updatePerson.mutateAsync({
        id: personId,
        data: {
          acf: acfData,
        },
      });
      
      navigate(`/people/${personId}`);
    } catch (error) {
      console.error('Failed to save relationship:', error);
    }
  };

  if (isLoadingPerson) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (!person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load person.</p>
        <Link to="/people" className="btn-secondary mt-4">Back to People</Link>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to={`/people/${personId}`} className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Person
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit Relationship' : 'Add Relationship'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Related Person */}
          <div>
            <label className="label">Related Person *</label>
            <Controller
              name="related_person"
              control={control}
              rules={{ required: 'Please select a person' }}
              render={({ field }) => (
                <PersonSelector
                  value={field.value}
                  onChange={field.onChange}
                  people={allPeople}
                  isLoading={isPeopleLoading}
                  excludePersonId={parseInt(personId, 10)}
                />
              )}
            />
            {errors.related_person && (
              <p className="text-sm text-red-600 mt-1">{errors.related_person.message}</p>
            )}
          </div>

          {/* Relationship Type */}
          <div>
            <label className="label">Relationship Type</label>
            <select
              {...register('relationship_type', { 
                setValueAs: (v) => v ? parseInt(v, 10) : null 
              })}
              className="input"
              disabled={isRelationshipTypesLoading}
            >
              <option value="">Select a type...</option>
              {relationshipTypes.map(type => (
                <option key={type.id} value={type.id}>
                  {type.name}
                </option>
              ))}
            </select>
          </div>

          {/* Custom Label */}
          <div>
            <label className="label">Custom Label</label>
            <input
              {...register('relationship_label')}
              className="input"
              placeholder="e.g., Brother-in-law"
            />
            <p className="text-xs text-gray-500 mt-1">
              Optional: Override the relationship type with a custom label
            </p>
          </div>
          
          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to={`/people/${personId}`} className="btn-secondary">
              Cancel
            </Link>
            <button 
              type="submit" 
              className="btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              ) : (
                <Save className="w-4 h-4 mr-2" />
              )}
              {isEditing ? 'Save Changes' : 'Add Relationship'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

