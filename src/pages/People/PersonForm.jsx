import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { usePerson, useCreatePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function PersonForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditing = !!id;
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(id);
  const createPerson = useCreatePerson();
  const updatePerson = useUpdatePerson();
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm();
  
  useEffect(() => {
    if (person) {
      reset({
        first_name: person.acf?.first_name || '',
        last_name: person.acf?.last_name || '',
        nickname: person.acf?.nickname || '',
        how_we_met: person.acf?.how_we_met || '',
        is_favorite: person.acf?.is_favorite || false,
      });
    }
  }, [person, reset]);
  
  // Update document title
  useDocumentTitle(
    isEditing && person
      ? `Edit ${person.title?.rendered || person.title || 'Person'}`
      : 'New Person'
  );
  
  const onSubmit = async (data) => {
    try {
      const payload = {
        status: 'publish',
        acf: {
          first_name: data.first_name,
          last_name: data.last_name,
          nickname: data.nickname,
          how_we_met: data.how_we_met,
          is_favorite: data.is_favorite,
        },
      };
      
      if (isEditing) {
        await updatePerson.mutateAsync({ id, data: payload });
        navigate(`/people/${id}`);
      } else {
        const result = await createPerson.mutateAsync(payload);
        navigate(`/people/${result.data.id}`);
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
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to People
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
