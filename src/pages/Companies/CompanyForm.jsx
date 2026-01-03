import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

export default function CompanyForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditing = !!id;
  
  const { data: company, isLoading } = useQuery({
    queryKey: ['company', id],
    queryFn: async () => {
      const response = await wpApi.getCompany(id);
      return response.data;
    },
    enabled: isEditing,
  });
  
  const createCompany = useMutation({
    mutationFn: (data) => wpApi.createCompany(data),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      navigate(`/companies/${result.data.id}`);
    },
  });
  
  const updateCompany = useMutation({
    mutationFn: (data) => wpApi.updateCompany(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company', id] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      navigate(`/companies/${id}`);
    },
  });
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm();
  
  useEffect(() => {
    if (company) {
      reset({
        title: company.title.rendered || '',
        website: company.acf?.website || '',
        industry: company.acf?.industry || '',
      });
    }
  }, [company, reset]);
  
  const onSubmit = async (data) => {
    const payload = {
      title: data.title,
      status: 'publish',
      acf: {
        website: data.website,
        industry: data.industry,
      },
    };
    
    if (isEditing) {
      updateCompany.mutate(payload);
    } else {
      createCompany.mutate(payload);
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
        <Link to="/companies" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Companies
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit Company' : 'Add New Company'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div>
            <label className="label">Company Name *</label>
            <input
              {...register('title', { required: 'Company name is required' })}
              className="input"
              placeholder="Acme Inc."
            />
            {errors.title && (
              <p className="text-sm text-red-600 mt-1">{errors.title.message}</p>
            )}
          </div>
          
          <div>
            <label className="label">Website</label>
            <input
              {...register('website')}
              type="url"
              className="input"
              placeholder="https://example.com"
            />
          </div>
          
          <div>
            <label className="label">Industry</label>
            <input
              {...register('industry')}
              className="input"
              placeholder="Technology"
            />
          </div>
          
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to="/companies" className="btn-secondary">Cancel</Link>
            <button type="submit" className="btn-primary" disabled={isSubmitting}>
              <Save className="w-4 h-4 mr-2" />
              {isEditing ? 'Save Changes' : 'Create Company'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
