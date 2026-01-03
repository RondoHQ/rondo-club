import { useEffect, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { usePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

function CompanySelector({ value, onChange, companies, isLoading }) {
  return (
    <select
      value={value || ''}
      onChange={(e) => onChange(e.target.value ? parseInt(e.target.value, 10) : null)}
      className="input"
      disabled={isLoading}
    >
      <option value="">Select a company...</option>
      {companies.map(company => (
        <option key={company.id} value={company.id}>
          {company.title?.rendered || company.title}
        </option>
      ))}
    </select>
  );
}

export default function WorkHistoryForm() {
  const { personId, index } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const workHistoryIndex = parseInt(index, 10);
  const isEditing = !isNaN(workHistoryIndex);
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(personId);
  const updatePerson = useUpdatePerson();
  
  // Fetch companies for the selector
  const { data: companies = [], isLoading: isCompaniesLoading } = useQuery({
    queryKey: ['companies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ per_page: 100, orderby: 'title', order: 'asc' });
      return response.data;
    },
  });

  const { register, handleSubmit, reset, watch, control, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      company: null,
      job_title: '',
      description: '',
      start_date: '',
      end_date: '',
      is_current: false,
    },
  });

  // Load work history item when editing
  useEffect(() => {
    if (person && isEditing) {
      const workHistory = person.acf?.work_history || [];
      const workHistoryItem = workHistory[workHistoryIndex];
      
      if (workHistoryItem) {
        reset({
          company: workHistoryItem.company || null,
          job_title: workHistoryItem.job_title || '',
          description: workHistoryItem.description || '',
          start_date: workHistoryItem.start_date || '',
          end_date: workHistoryItem.end_date || '',
          is_current: workHistoryItem.is_current || false,
        });
      }
    }
  }, [person, workHistoryIndex, isEditing, reset]);

  // Update document title
  useDocumentTitle(
    isEditing
      ? `Edit Work History - ${person?.title?.rendered || person?.title || 'Person'}`
      : `Add Work History - ${person?.title?.rendered || person?.title || 'Person'}`
  );

  const onSubmit = async (data) => {
    try {
      const workHistory = [...(person.acf?.work_history || [])];
      
      const workHistoryItem = {
        company: data.company || null,
        job_title: data.job_title || '',
        description: data.description || '',
        start_date: data.start_date || '',
        end_date: data.is_current ? '' : (data.end_date || ''),
        is_current: data.is_current || false,
      };

      if (isEditing) {
        // Update existing item
        workHistory[workHistoryIndex] = workHistoryItem;
      } else {
        // Add new item
        workHistory.push(workHistoryItem);
      }

      // Ensure all repeater fields are arrays (empty array if not present)
      const acfData = {
        ...person.acf,
        work_history: workHistory,
        contact_info: Array.isArray(person.acf?.contact_info) ? person.acf.contact_info : [],
        relationships: Array.isArray(person.acf?.relationships) ? person.acf.relationships : [],
      };

      await updatePerson.mutateAsync({
        id: personId,
        data: {
          acf: acfData,
        },
      });
      
      navigate(`/people/${personId}`);
    } catch (error) {
      console.error('Failed to save work history:', error);
    }
  };

  const isCurrent = watch('is_current');

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
          {isEditing ? 'Edit Work History' : 'Add Work History'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Company */}
          <div>
            <label className="label">Company</label>
            <Controller
              name="company"
              control={control}
              render={({ field }) => (
                <CompanySelector
                  value={field.value}
                  onChange={field.onChange}
                  companies={companies}
                  isLoading={isCompaniesLoading}
                />
              )}
            />
          </div>

          {/* Job Title */}
          <div>
            <label className="label">Job Title</label>
            <input
              {...register('job_title')}
              className="input"
              placeholder="e.g., Software Engineer"
            />
          </div>

          {/* Description */}
          <div>
            <label className="label">Description</label>
            <textarea
              {...register('description')}
              className="input"
              rows={4}
              placeholder="Job description, responsibilities, achievements..."
            />
          </div>

          {/* Dates */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="label">Start Date</label>
              <input
                type="date"
                {...register('start_date')}
                className="input"
              />
            </div>
            
            <div>
              <label className="label">End Date</label>
              <input
                type="date"
                {...register('end_date')}
                className="input"
                disabled={isCurrent}
              />
              {isCurrent && (
                <p className="text-xs text-gray-500 mt-1">Leave empty for current position</p>
              )}
            </div>
          </div>

          {/* Current Position */}
          <div className="flex items-center">
            <input
              type="checkbox"
              {...register('is_current')}
              className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label className="ml-2 text-sm text-gray-700">Currently works here</label>
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
              {isEditing ? 'Save Changes' : 'Add Work History'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

