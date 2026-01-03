import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

export default function DateForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditing = !!id;
  
  const { data: dateItem, isLoading } = useQuery({
    queryKey: ['important-date', id],
    queryFn: async () => {
      const response = await wpApi.getDate(id);
      return response.data;
    },
    enabled: isEditing,
  });
  
  const createDate = useMutation({
    mutationFn: (data) => wpApi.createDate(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      navigate('/dates');
    },
  });
  
  const updateDate = useMutation({
    mutationFn: (data) => wpApi.updateDate(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      queryClient.invalidateQueries({ queryKey: ['important-date', id] });
      navigate('/dates');
    },
  });
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      is_recurring: true,
      reminder_days_before: 7,
    },
  });
  
  useEffect(() => {
    if (dateItem) {
      reset({
        custom_label: dateItem.acf?.custom_label || '',
        date_value: dateItem.acf?.date_value || '',
        is_recurring: dateItem.acf?.is_recurring ?? true,
        reminder_days_before: dateItem.acf?.reminder_days_before || 7,
      });
    }
  }, [dateItem, reset]);
  
  const onSubmit = async (data) => {
    const payload = {
      status: 'publish',
      acf: {
        custom_label: data.custom_label,
        date_value: data.date_value,
        is_recurring: data.is_recurring,
        reminder_days_before: parseInt(data.reminder_days_before, 10),
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
  
  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <Link to="/dates" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Dates
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit Date' : 'Add New Date'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div>
            <label className="label">Label</label>
            <input
              {...register('custom_label')}
              className="input"
              placeholder="e.g., Mom's Birthday, Wedding Anniversary"
            />
            <p className="text-xs text-gray-500 mt-1">
              If left blank, a label will be generated from the linked people.
            </p>
          </div>
          
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
          
          <div>
            <label className="label">Remind me</label>
            <select {...register('reminder_days_before')} className="input">
              <option value="0">On the day</option>
              <option value="1">1 day before</option>
              <option value="3">3 days before</option>
              <option value="7">1 week before</option>
              <option value="14">2 weeks before</option>
              <option value="30">1 month before</option>
            </select>
          </div>
          
          <div className="bg-gray-50 rounded-lg p-4">
            <p className="text-sm text-gray-600">
              <strong>Note:</strong> To link people to this date, save it first, then edit the date
              in the WordPress admin to use the people selector.
            </p>
          </div>
          
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to="/dates" className="btn-secondary">Cancel</Link>
            <button type="submit" className="btn-primary" disabled={isSubmitting}>
              <Save className="w-4 h-4 mr-2" />
              {isEditing ? 'Save Changes' : 'Create Date'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
