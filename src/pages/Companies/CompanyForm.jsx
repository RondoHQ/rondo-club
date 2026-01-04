import { useEffect, useState, useRef } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save, Camera } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function CompanyForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEditing = !!id;
  
  const { data: company, isLoading } = useQuery({
    queryKey: ['company', id],
    queryFn: async () => {
      const response = await wpApi.getCompany(id, { _embed: true });
      return response.data;
    },
    enabled: isEditing,
  });
  
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const fileInputRef = useRef(null);
  
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
      queryClient.invalidateQueries({ queryKey: ['people'] });
      navigate(`/companies/${id}`);
    },
  });
  
  const handleLogoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Image size must be less than 5MB');
      return;
    }

    setIsUploadingLogo(true);

    try {
      // Upload the file using the new endpoint that properly names files
      await prmApi.uploadCompanyLogo(id, file);

      // Invalidate and refetch queries to refresh company data with embedded media
      queryClient.invalidateQueries({ queryKey: ['company', id] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['people'] });
      
      // Explicitly refetch to ensure embedded data is loaded
      queryClient.refetchQueries({ queryKey: ['company', id] });
      queryClient.refetchQueries({ queryKey: ['companies'] });
      queryClient.refetchQueries({ queryKey: ['people'] });
    } catch (error) {
      console.error('Failed to upload logo:', error);
      alert('Failed to upload logo. Please try again.');
    } finally {
      setIsUploadingLogo(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };
  
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
  
  // Update document title
  useDocumentTitle(
    isEditing && company
      ? `Edit ${company.title?.rendered || company.title || 'Company'}`
      : 'New Company'
  );
  
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
          {/* Logo upload */}
          {isEditing && (
            <div>
              <label className="label">Logo</label>
              <div className="flex items-center gap-4">
                {company?._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                  <div className="relative group">
                    <img
                      src={company._embedded['wp:featuredmedia'][0].source_url}
                      alt={company.title?.rendered || 'Company logo'}
                      className="w-20 h-20 rounded-lg object-cover"
                    />
                    <div className="absolute inset-0 rounded-lg bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center cursor-pointer"
                         onClick={() => fileInputRef.current?.click()}
                    >
                      {isUploadingLogo ? (
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
                      ) : (
                        <Camera className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                      )}
                    </div>
                  </div>
                ) : (
                  <div className="relative group">
                    <div className="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                      <Camera className="w-8 h-8 text-gray-400" />
                    </div>
                    <div className="absolute inset-0 rounded-lg bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center cursor-pointer"
                         onClick={() => fileInputRef.current?.click()}
                    >
                      {isUploadingLogo ? (
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
                      ) : (
                        <Camera className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                      )}
                    </div>
                  </div>
                )}
                <div>
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="btn-secondary text-sm"
                    disabled={isUploadingLogo}
                  >
                    {company?._embedded?.['wp:featuredmedia']?.[0]?.source_url ? 'Change Logo' : 'Upload Logo'}
                  </button>
                  <p className="text-xs text-gray-500 mt-1">
                    Click the logo or button to upload a new logo
                  </p>
                </div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  onChange={handleLogoUpload}
                  className="hidden"
                  disabled={isUploadingLogo}
                />
              </div>
            </div>
          )}
          
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
