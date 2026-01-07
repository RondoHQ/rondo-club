import { useEffect, useState, useRef, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save, Camera, ChevronDown, Building2, Search } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getCompanyName, decodeHtml } from '@/utils/formatters';

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
  
  // Fetch all companies for parent selection
  const { data: allCompanies = [], isLoading: isLoadingCompanies } = useQuery({
    queryKey: ['companies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ per_page: 100, _embed: true });
      return response.data;
    },
  });
  
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [isParentDropdownOpen, setIsParentDropdownOpen] = useState(false);
  const [parentSearchQuery, setParentSearchQuery] = useState('');
  const [selectedParentId, setSelectedParentId] = useState('');
  const fileInputRef = useRef(null);
  const parentDropdownRef = useRef(null);
  
  // Filter companies for parent dropdown (exclude self and children)
  const availableParentCompanies = useMemo(() => {
    const query = parentSearchQuery.toLowerCase().trim();
    let filtered = allCompanies.filter(c => {
      // Exclude self
      if (isEditing && c.id === parseInt(id)) return false;
      // Exclude companies that have this company as parent (prevents circular references)
      if (isEditing && c.parent === parseInt(id)) return false;
      return true;
    });
    
    if (query) {
      filtered = filtered.filter(c => 
        getCompanyName(c)?.toLowerCase().includes(query)
      );
    }
    
    // Sort alphabetically
    return [...filtered].sort((a, b) => 
      (getCompanyName(a) || '').localeCompare(getCompanyName(b) || '')
    );
  }, [allCompanies, parentSearchQuery, id, isEditing]);
  
  // Get selected parent company details
  const selectedParent = useMemo(() => 
    allCompanies.find(c => c.id === parseInt(selectedParentId)),
    [allCompanies, selectedParentId]
  );
  
  // Close parent dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (parentDropdownRef.current && !parentDropdownRef.current.contains(event.target)) {
        setIsParentDropdownOpen(false);
      }
    };
    
    if (isParentDropdownOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isParentDropdownOpen]);
  
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
        title: decodeHtml(company.title?.rendered || ''),
        website: company.acf?.website || '',
        industry: company.acf?.industry || '',
      });
      // Set parent company if exists
      if (company.parent) {
        setSelectedParentId(String(company.parent));
      }
    }
  }, [company, reset]);
  
  // Update document title
  useDocumentTitle(
    isEditing && company
      ? `Edit ${getCompanyName(company) || 'organization'}`
      : 'New organization'
  );
  
  const onSubmit = async (data) => {
    const payload = {
      title: data.title,
      status: 'publish',
      parent: selectedParentId ? parseInt(selectedParentId) : 0,
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
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to organizations</span>
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit organization' : 'Add new organization'}
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
                      alt={getCompanyName(company) || 'Organization logo'}
                      className="w-20 h-20 rounded-lg object-contain bg-white"
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
                    {company?._embedded?.['wp:featuredmedia']?.[0]?.source_url ? 'Change logo' : 'Upload logo'}
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
            <label className="label">Organization name *</label>
            <input
              {...register('title', { required: 'Organization name is required' })}
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
          
          {/* Parent company selection */}
          <div>
            <label className="label">Parent organization</label>
            <div className="relative" ref={parentDropdownRef}>
              <button
                type="button"
                onClick={() => setIsParentDropdownOpen(!isParentDropdownOpen)}
                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md bg-white text-left focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                disabled={isLoadingCompanies}
              >
                {selectedParent ? (
                  <div className="flex items-center gap-2">
                    {selectedParent._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                      <img
                        src={selectedParent._embedded['wp:featuredmedia'][0].source_url}
                        alt={getCompanyName(selectedParent)}
                        className="w-6 h-6 rounded object-contain bg-white"
                      />
                    ) : (
                      <div className="w-6 h-6 bg-gray-200 rounded flex items-center justify-center">
                        <Building2 className="w-4 h-4 text-gray-500" />
                      </div>
                    )}
                    <span className="text-gray-900">{getCompanyName(selectedParent)}</span>
                  </div>
                ) : (
                  <span className="text-gray-400">No parent organization</span>
                )}
                <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isParentDropdownOpen ? 'rotate-180' : ''}`} />
              </button>
              
              {isParentDropdownOpen && (
                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden">
                  {/* Search input */}
                  <div className="p-2 border-b border-gray-100">
                    <div className="relative">
                      <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <input
                        type="text"
                        value={parentSearchQuery}
                        onChange={(e) => setParentSearchQuery(e.target.value)}
                        placeholder="Search organizations..."
                        className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                        autoFocus
                      />
                    </div>
                  </div>
                  
                  {/* "None" option */}
                  <div className="overflow-y-auto max-h-48">
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedParentId('');
                        setIsParentDropdownOpen(false);
                        setParentSearchQuery('');
                      }}
                      className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 transition-colors ${
                        !selectedParentId ? 'bg-primary-50' : ''
                      }`}
                    >
                      <span className="text-sm text-gray-500 italic">No parent organization</span>
                    </button>
                    
                    {/* Companies list */}
                    {isLoadingCompanies ? (
                      <div className="p-3 text-center text-gray-500 text-sm">
                        Loading...
                      </div>
                    ) : availableParentCompanies.length > 0 ? (
                      availableParentCompanies.map((c) => (
                        <button
                          key={c.id}
                          type="button"
                          onClick={() => {
                            setSelectedParentId(String(c.id));
                            setIsParentDropdownOpen(false);
                            setParentSearchQuery('');
                          }}
                          className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 transition-colors ${
                            selectedParentId === String(c.id) ? 'bg-primary-50' : ''
                          }`}
                        >
                          {c._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                            <img
                              src={c._embedded['wp:featuredmedia'][0].source_url}
                              alt={getCompanyName(c)}
                              className="w-6 h-6 rounded object-contain bg-white"
                            />
                          ) : (
                            <div className="w-6 h-6 bg-gray-200 rounded flex items-center justify-center">
                              <Building2 className="w-4 h-4 text-gray-500" />
                            </div>
                          )}
                          <span className="text-sm text-gray-900 truncate">{getCompanyName(c)}</span>
                        </button>
                      ))
                    ) : (
                      <div className="p-3 text-center text-gray-500 text-sm">
                        No organizations found
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
            <p className="text-xs text-gray-500 mt-1">
              Select if this organization is a subsidiary or division of another
            </p>
          </div>
          
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to="/companies" className="btn-secondary">Cancel</Link>
            <button type="submit" className="btn-primary" disabled={isSubmitting}>
              <Save className="w-4 h-4 mr-2" />
              {isEditing ? 'Save changes' : 'Create organization'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
