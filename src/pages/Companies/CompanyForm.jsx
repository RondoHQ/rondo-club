import { useEffect, useState, useRef, useMemo } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save, Camera, ChevronDown, Building2, Search, X, User, TrendingUp } from 'lucide-react';
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
  
  // Fetch all companies for parent selection and investors
  const { data: allCompanies = [], isLoading: isLoadingCompanies } = useQuery({
    queryKey: ['companies', 'all'],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ per_page: 100, _embed: true });
      return response.data;
    },
  });
  
  // Fetch all people for investors
  const { data: allPeople = [], isLoading: isLoadingPeople } = useQuery({
    queryKey: ['people', 'all'],
    queryFn: async () => {
      const response = await wpApi.getPeople({ per_page: 100, _embed: true });
      return response.data;
    },
  });
  
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [isParentDropdownOpen, setIsParentDropdownOpen] = useState(false);
  const [parentSearchQuery, setParentSearchQuery] = useState('');
  const [selectedParentId, setSelectedParentId] = useState('');
  const [isInvestorsDropdownOpen, setIsInvestorsDropdownOpen] = useState(false);
  const [investorsSearchQuery, setInvestorsSearchQuery] = useState('');
  const [selectedInvestors, setSelectedInvestors] = useState([]);
  const fileInputRef = useRef(null);
  const parentDropdownRef = useRef(null);
  const investorsDropdownRef = useRef(null);
  
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
  
  // Combined list of people and companies for investor selection (excluding self)
  const availableInvestors = useMemo(() => {
    const query = investorsSearchQuery.toLowerCase().trim();
    
    // Map people to a common format
    const people = allPeople.map(p => ({
      id: p.id,
      type: 'person',
      name: decodeHtml(p.title?.rendered || ''),
      thumbnail: p._embedded?.['wp:featuredmedia']?.[0]?.source_url,
    }));
    
    // Map companies to a common format (exclude self)
    const companies = allCompanies
      .filter(c => !isEditing || c.id !== parseInt(id))
      .map(c => ({
        id: c.id,
        type: 'company',
        name: getCompanyName(c),
        thumbnail: c._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));
    
    // Combine and filter by search query
    let combined = [...people, ...companies];
    
    if (query) {
      combined = combined.filter(item => 
        item.name?.toLowerCase().includes(query)
      );
    }
    
    // Filter out already selected investors
    const selectedKeys = selectedInvestors.map(inv => `${inv.type}-${inv.id}`);
    combined = combined.filter(item => !selectedKeys.includes(`${item.type}-${item.id}`));
    
    // Sort alphabetically
    return combined.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  }, [allPeople, allCompanies, investorsSearchQuery, selectedInvestors, id, isEditing]);
  
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
  
  // Close investors dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (investorsDropdownRef.current && !investorsDropdownRef.current.contains(event.target)) {
        setIsInvestorsDropdownOpen(false);
      }
    };
    
    if (isInvestorsDropdownOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isInvestorsDropdownOpen]);
  
  const createCompany = useMutation({
    mutationFn: (data) => wpApi.createCompany(data),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      navigate(`/companies/${result.data.id}`);
    },
  });
  
  const updateCompany = useMutation({
    mutationFn: (data) => wpApi.updateCompany(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company', id] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['people'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
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
  
  // Load existing investors when company and all data is available
  useEffect(() => {
    if (company?.acf?.investors?.length > 0 && allPeople.length > 0 && allCompanies.length > 0) {
      const investorIds = company.acf.investors;
      const investors = investorIds.map(investorId => {
        // Check if it's a person
        const person = allPeople.find(p => p.id === investorId);
        if (person) {
          return {
            id: person.id,
            type: 'person',
            name: decodeHtml(person.title?.rendered || ''),
            thumbnail: person._embedded?.['wp:featuredmedia']?.[0]?.source_url,
          };
        }
        // Check if it's a company
        const comp = allCompanies.find(c => c.id === investorId);
        if (comp) {
          return {
            id: comp.id,
            type: 'company',
            name: getCompanyName(comp),
            thumbnail: comp._embedded?.['wp:featuredmedia']?.[0]?.source_url,
          };
        }
        return null;
      }).filter(Boolean);
      
      setSelectedInvestors(investors);
    }
  }, [company, allPeople, allCompanies]);
  
  // Update document title
  useDocumentTitle(
    isEditing && company
      ? `Edit ${getCompanyName(company) || 'organization'}`
      : 'New organization'
  );
  
  const onSubmit = async (data) => {
    // Format investors as array of post IDs for ACF relationship field
    const investorIds = selectedInvestors.map(inv => inv.id);
    
    const payload = {
      title: data.title,
      status: 'publish',
      parent: selectedParentId ? parseInt(selectedParentId) : 0,
      acf: {
        website: data.website,
        industry: data.industry,
        investors: investorIds,
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
          
          {/* Investors selection */}
          <div>
            <label className="label flex items-center">
              <TrendingUp className="w-4 h-4 mr-2" />
              Investors
            </label>
            
            {/* Selected investors */}
            {selectedInvestors.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-2">
                {selectedInvestors.map((investor) => (
                  <div
                    key={`${investor.type}-${investor.id}`}
                    className="flex items-center gap-1.5 bg-gray-100 rounded-full pl-1 pr-2 py-1"
                  >
                    {investor.thumbnail ? (
                      <img
                        src={investor.thumbnail}
                        alt={investor.name}
                        className={`w-5 h-5 object-cover ${investor.type === 'person' ? 'rounded-full' : 'rounded'}`}
                      />
                    ) : (
                      <div className={`w-5 h-5 bg-gray-300 flex items-center justify-center ${investor.type === 'person' ? 'rounded-full' : 'rounded'}`}>
                        {investor.type === 'person' ? (
                          <User className="w-3 h-3 text-gray-500" />
                        ) : (
                          <Building2 className="w-3 h-3 text-gray-500" />
                        )}
                      </div>
                    )}
                    <span className="text-sm text-gray-700">{investor.name}</span>
                    <button
                      type="button"
                      onClick={() => setSelectedInvestors(prev => 
                        prev.filter(inv => !(inv.id === investor.id && inv.type === investor.type))
                      )}
                      className="ml-1 text-gray-400 hover:text-gray-600"
                    >
                      <X className="w-3.5 h-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            )}
            
            <div className="relative" ref={investorsDropdownRef}>
              <button
                type="button"
                onClick={() => setIsInvestorsDropdownOpen(!isInvestorsDropdownOpen)}
                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md bg-white text-left focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                disabled={isLoadingCompanies || isLoadingPeople}
              >
                <span className="text-gray-400">Add investor...</span>
                <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isInvestorsDropdownOpen ? 'rotate-180' : ''}`} />
              </button>
              
              {isInvestorsDropdownOpen && (
                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden">
                  {/* Search input */}
                  <div className="p-2 border-b border-gray-100">
                    <div className="relative">
                      <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <input
                        type="text"
                        value={investorsSearchQuery}
                        onChange={(e) => setInvestorsSearchQuery(e.target.value)}
                        placeholder="Search people and organizations..."
                        className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                        autoFocus
                      />
                    </div>
                  </div>
                  
                  <div className="overflow-y-auto max-h-48">
                    {(isLoadingCompanies || isLoadingPeople) ? (
                      <div className="p-3 text-center text-gray-500 text-sm">
                        Loading...
                      </div>
                    ) : availableInvestors.length > 0 ? (
                      availableInvestors.map((item) => (
                        <button
                          key={`${item.type}-${item.id}`}
                          type="button"
                          onClick={() => {
                            setSelectedInvestors(prev => [...prev, item]);
                            setInvestorsSearchQuery('');
                          }}
                          className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 transition-colors"
                        >
                          {item.thumbnail ? (
                            <img
                              src={item.thumbnail}
                              alt={item.name}
                              className={`w-6 h-6 object-cover ${item.type === 'person' ? 'rounded-full' : 'rounded'}`}
                            />
                          ) : (
                            <div className={`w-6 h-6 bg-gray-200 flex items-center justify-center ${item.type === 'person' ? 'rounded-full' : 'rounded'}`}>
                              {item.type === 'person' ? (
                                <User className="w-4 h-4 text-gray-500" />
                              ) : (
                                <Building2 className="w-4 h-4 text-gray-500" />
                              )}
                            </div>
                          )}
                          <div className="flex-1 min-w-0">
                            <span className="text-sm text-gray-900 truncate block">{item.name}</span>
                            <span className="text-xs text-gray-500">
                              {item.type === 'person' ? 'Person' : 'Organization'}
                            </span>
                          </div>
                        </button>
                      ))
                    ) : (
                      <div className="p-3 text-center text-gray-500 text-sm">
                        {investorsSearchQuery ? 'No results found' : 'No people or organizations available'}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
            <p className="text-xs text-gray-500 mt-1">
              Select people or organizations that have invested in this company
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
