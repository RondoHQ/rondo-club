import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { usePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { sanitizePersonAcf } from '@/utils/formatters';
import { prmApi } from '@/api/client';

const CONTACT_TYPES = [
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'mobile', label: 'Mobile' },
  { value: 'address', label: 'Address' },
  { value: 'website', label: 'Website' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'twitter', label: 'Twitter' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'other', label: 'Other' },
];

export default function ContactDetailForm() {
  const { personId, index } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const contactIndex = parseInt(index, 10);
  const isEditing = !isNaN(contactIndex);
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(personId);
  const updatePerson = useUpdatePerson();
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      contact_type: '',
      contact_label: '',
      contact_value: '',
    },
  });

  // Load contact detail when editing
  useEffect(() => {
    if (person && isEditing) {
      const contactInfo = person.acf?.contact_info || [];
      const contactItem = contactInfo[contactIndex];
      
      if (contactItem) {
        reset({
          contact_type: contactItem.contact_type || '',
          contact_label: contactItem.contact_label || '',
          contact_value: contactItem.contact_value || '',
        });
      }
    }
  }, [person, contactIndex, isEditing, reset]);

  // Update document title
  useDocumentTitle(
    isEditing
      ? `Edit Contact Detail - ${person?.title?.rendered || person?.title || 'Person'}`
      : `Add Contact Detail - ${person?.title?.rendered || person?.title || 'Person'}`
  );

  const onSubmit = async (data) => {
    try {
      const contactInfo = [...(person.acf?.contact_info || [])];
      
      const contactItem = {
        contact_type: data.contact_type || '',
        contact_label: data.contact_label || '',
        contact_value: data.contact_value || '',
      };

      if (isEditing) {
        // Update existing item
        contactInfo[contactIndex] = contactItem;
      } else {
        // Add new item
        contactInfo.push(contactItem);
      }

      // Sanitize ACF data and set the updated contact_info
      const acfData = sanitizePersonAcf(person.acf, {
        contact_info: contactInfo,
      });

      await updatePerson.mutateAsync({
        id: personId,
        data: {
          acf: acfData,
        },
      });
      
      // If adding a new email and person doesn't have an image, try to load Gravatar
      if (!isEditing && data.contact_type === 'email' && data.contact_value) {
        // Check if person has a featured image/thumbnail
        const hasImage = person.featured_media > 0 || 
                        person._embedded?.['wp:featuredmedia']?.[0]?.source_url ||
                        person.thumbnail;
        
        if (!hasImage) {
          try {
            await prmApi.sideloadGravatar(personId, data.contact_value);
            // Invalidate queries to refresh the person data with new image
            queryClient.invalidateQueries({ queryKey: ['person', personId] });
          } catch (gravatarError) {
            // Silently fail - Gravatar is optional
            console.log('No Gravatar found or failed to load:', gravatarError);
          }
        }
      }
      
      navigate(`/people/${personId}`);
    } catch (error) {
      console.error('Failed to save contact detail:', error);
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
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to Person</span>
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit Contact Detail' : 'Add Contact Detail'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Contact Type */}
          <div>
            <label className="label">Contact Type *</label>
            <select
              {...register('contact_type', { required: 'Please select a contact type' })}
              className="input"
            >
              <option value="">Select a type...</option>
              {CONTACT_TYPES.map(type => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
            {errors.contact_type && (
              <p className="text-sm text-red-600 mt-1">{errors.contact_type.message}</p>
            )}
          </div>

          {/* Contact Label */}
          <div>
            <label className="label">Label</label>
            <input
              {...register('contact_label')}
              className="input"
              placeholder="e.g., Work, Personal, Home"
            />
            <p className="text-xs text-gray-500 mt-1">Optional: Add a label to distinguish multiple entries of the same type</p>
          </div>

          {/* Contact Value */}
          <div>
            <label className="label">Value *</label>
            <input
              {...register('contact_value', { required: 'Please enter a contact value' })}
              className="input"
              placeholder="e.g., john@example.com, +1 234 567 8900"
            />
            {errors.contact_value && (
              <p className="text-sm text-red-600 mt-1">{errors.contact_value.message}</p>
            )}
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
              {isEditing ? 'Save Changes' : 'Add Contact Detail'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

