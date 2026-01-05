import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { usePerson, useCreatePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { wpApi, prmApi } from '@/api/client';

export default function PersonForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditing = !!id;
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(id);
  const createPerson = useCreatePerson();
  const updatePerson = useUpdatePerson();
  
  // Fetch date types to get birthday term ID
  const { data: dateTypes = [] } = useQuery({
    queryKey: ['date-types'],
    queryFn: async () => {
      const response = await wpApi.getDateTypes();
      return response.data;
    },
  });
  
  const birthdayType = dateTypes.find(type => type.slug === 'birthday' || type.name.toLowerCase() === 'birthday');
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      first_name: '',
      last_name: '',
      nickname: '',
      gender: '',
      email: '',
      how_we_met: '',
      is_favorite: false,
      birthday: '',
    },
  });
  
  useEffect(() => {
    if (person) {
      // Get email from contact_info if it exists
      const emailContact = person.acf?.contact_info?.find(contact => contact.contact_type === 'email');
      const email = emailContact?.contact_value || '';
      
      reset({
        first_name: person.acf?.first_name || '',
        last_name: person.acf?.last_name || '',
        nickname: person.acf?.nickname || '',
        gender: person.acf?.gender || '',
        email: email,
        how_we_met: person.acf?.how_we_met || '',
        is_favorite: person.acf?.is_favorite || false,
        birthday: '', // Birthday is stored separately as an important_date
      });
    }
  }, [person, reset]);
  
  // Update document title - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(
    isEditing && person
      ? `Edit ${person.title?.rendered || person.title || 'Person'}`
      : 'New Person'
  );
  
  const onSubmit = async (data) => {
    try {
      // Generate title from first and last name
      const fullName = `${data.first_name || ''} ${data.last_name || ''}`.trim();
      const title = fullName || 'Unnamed Person';
      
      const payload = {
        title: title,
        status: 'publish',
        acf: {
          first_name: data.first_name,
          last_name: data.last_name,
          nickname: data.nickname,
          gender: data.gender || null, // null instead of '' for ACF enum validation
          how_we_met: data.how_we_met,
          is_favorite: data.is_favorite,
        },
      };
      
      // Add email to contact_info if provided (only when creating)
      if (!isEditing && data.email) {
        payload.acf.contact_info = [
          {
            contact_type: 'email',
            contact_value: data.email,
            contact_label: '',
          },
        ];
      }
      
      if (isEditing) {
        // Update title when editing too
        payload.title = title;
        await updatePerson.mutateAsync({ id, data: payload });
        navigate(`/people/${id}`);
      } else {
        const result = await createPerson.mutateAsync(payload);
        const personId = result.data.id;
        
        // Try to sideload Gravatar if email is provided
        if (data.email) {
          try {
            await prmApi.sideloadGravatar(personId, data.email);
          } catch (gravatarError) {
            console.error('Failed to load Gravatar:', gravatarError);
            // Continue anyway - person was created successfully, just no gravatar
          }
        }
        
        // Create birthday if provided
        if (data.birthday && birthdayType) {
          try {
            const firstName = data.first_name || 'Person';
            await wpApi.createDate({
              title: `${firstName}'s Birthday`,
              status: 'publish',
              date_type: [birthdayType.id],
              acf: {
                date_value: data.birthday,
                is_recurring: true,
                related_people: [personId],
                reminder_days_before: 7,
              },
            });
          } catch (dateError) {
            console.error('Failed to create birthday:', dateError);
            // Continue anyway - person was created successfully
          }
        }
        
        navigate(`/people/${personId}`);
      }
    } catch (error) {
      console.error('Failed to save person:', error);
    }
  };
  
  if (isEditing && isLoadingPerson) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/people" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to People</span>
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit Person' : 'Add New Person'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Basic info */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="label">First Name *</label>
              <input
                {...register('first_name', { required: 'First name is required' })}
                className="input"
                placeholder="John"
              />
              {errors.first_name && (
                <p className="text-sm text-red-600 mt-1">{errors.first_name.message}</p>
              )}
            </div>
            
            <div>
              <label className="label">Last Name</label>
              <input
                {...register('last_name')}
                className="input"
                placeholder="Doe"
              />
            </div>
          </div>
          
          <div>
            <label className="label">Nickname</label>
            <input
              {...register('nickname')}
              className="input"
              placeholder="Johnny"
            />
          </div>
          
          <div>
            <label className="label">Gender</label>
            <select
              {...register('gender')}
              className="input"
            >
              <option value="">Select gender...</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non_binary">Non-binary</option>
              <option value="other">Other</option>
              <option value="prefer_not_to_say">Prefer not to say</option>
            </select>
          </div>
          
          {!isEditing && (
            <div>
              <label className="label">Email</label>
              <input
                type="email"
                {...register('email')}
                className="input"
                placeholder="john@example.com"
              />
              <p className="text-xs text-gray-500 mt-1">
                Optional: If a Gravatar is associated with this email, it will be automatically set as the profile photo
              </p>
            </div>
          )}
          
          {!isEditing && (
            <div>
              <label className="label">Birthday</label>
              <input
                type="date"
                {...register('birthday')}
                className="input"
              />
              <p className="text-xs text-gray-500 mt-1">
                Optional: Set their birthday when creating a new person
              </p>
            </div>
          )}
          
          <div>
            <label className="label">How We Met</label>
            <textarea
              {...register('how_we_met')}
              className="input"
              rows={4}
              placeholder="We met at..."
            />
          </div>
          
          <div className="flex items-center">
            <input
              type="checkbox"
              {...register('is_favorite')}
              className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label className="ml-2 text-sm text-gray-700">Mark as favorite</label>
          </div>
          
          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to="/people" className="btn-secondary">
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
              {isEditing ? 'Save Changes' : 'Create Person'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
