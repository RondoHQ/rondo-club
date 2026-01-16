import { useEffect, useMemo } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

function CompanySelector({ value, onChange, companies, isLoading }) {
  return (
    <select
      value={value || ''}
      onChange={(e) => onChange(e.target.value ? parseInt(e.target.value, 10) : null)}
      className="input"
      disabled={isLoading}
    >
      <option value="">Select an organization...</option>
      {companies.map(company => (
        <option key={company.id} value={company.id}>
          {company.title?.rendered || company.title}
        </option>
      ))}
    </select>
  );
}

export default function WorkHistoryEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading, 
  workHistoryItem = null 
}) {
  const isEditing = !!workHistoryItem;

  // Fetch companies for the selector
  const { data: companiesData = [], isLoading: isCompaniesLoading } = useQuery({
    queryKey: ['companies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ per_page: 100 });
      return response.data;
    },
  });
  
  // Sort companies alphabetically by title
  const companies = useMemo(() => {
    return [...companiesData].sort((a, b) => {
      const titleA = (a.title?.rendered || a.title || '').toLowerCase();
      const titleB = (b.title?.rendered || b.title || '').toLowerCase();
      return titleA.localeCompare(titleB);
    });
  }, [companiesData]);

  const { register, handleSubmit, reset, watch, control, formState: { errors } } = useForm({
    defaultValues: {
      company: null,
      job_title: '',
      description: '',
      start_date: '',
      end_date: '',
      is_current: false,
    },
  });

  const isCurrent = watch('is_current');

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      if (workHistoryItem) {
        reset({
          company: workHistoryItem.company || null,
          job_title: workHistoryItem.job_title || '',
          description: workHistoryItem.description || '',
          start_date: workHistoryItem.start_date || '',
          end_date: workHistoryItem.end_date || '',
          is_current: workHistoryItem.is_current || false,
        });
      } else {
        reset({
          company: null,
          job_title: '',
          description: '',
          start_date: '',
          end_date: '',
          is_current: false,
        });
      }
    }
  }, [isOpen, workHistoryItem, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      company: data.company || null,
      job_title: data.job_title || '',
      description: data.description || '',
      start_date: data.start_date || '',
      end_date: data.end_date || '',
      is_current: data.is_current || false,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Edit work history' : 'Add work history'}</h2>
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
            {/* Company */}
            <div>
              <label className="label">Organization</label>
              <Controller
                name="company"
                control={control}
                render={({ field }) => (
                  <CompanySelector
                    value={field.value}
                    onChange={field.onChange}
                    companies={companies}
                    isLoading={isCompaniesLoading || isLoading}
                  />
                )}
              />
            </div>

            {/* Job Title */}
            <div>
              <label className="label">Job title</label>
              <input
                {...register('job_title')}
                className="input"
                placeholder="e.g., Software Engineer"
                disabled={isLoading}
              />
            </div>

            {/* Description */}
            <div>
              <label className="label">Description</label>
              <textarea
                {...register('description')}
                className="input"
                rows={3}
                placeholder="Job description, responsibilities, achievements..."
                disabled={isLoading}
              />
            </div>

            {/* Dates */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Start date</label>
                <input
                  type="date"
                  {...register('start_date')}
                  className="input"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">End date</label>
                <input
                  type="date"
                  {...register('end_date')}
                  className="input"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Current Position */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_current"
                {...register('is_current')}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500 dark:bg-gray-700"
                disabled={isLoading}
              />
              <label htmlFor="is_current" className="ml-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Currently works here
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
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Add work history')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
