import { Link, useParams, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useState, useRef } from 'react';
import { ArrowLeft, Edit, Trash2, Building2, Globe, Users, GitBranch, TrendingUp, User, Camera } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { getCompanyName, decodeHtml } from '@/utils/formatters';
import CompanyEditModal from '@/components/CompanyEditModal';

export default function CompanyDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const fileInputRef = useRef(null);
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  
  const { data: company, isLoading, error } = useQuery({
    queryKey: ['company', id],
    queryFn: async () => {
      const response = await wpApi.getCompany(id, { _embed: true });
      return response.data;
    },
  });
  
  const { data: employees } = useQuery({
    queryKey: ['company-people', id],
    queryFn: async () => {
      const response = await prmApi.getCompanyPeople(id);
      return response.data;
    },
  });
  
  // Fetch parent company if exists
  const { data: parentCompany } = useQuery({
    queryKey: ['company', company?.parent],
    queryFn: async () => {
      const response = await wpApi.getCompany(company.parent, { _embed: true });
      return response.data;
    },
    enabled: !!company?.parent,
  });
  
  // Fetch child companies (subsidiaries)
  const { data: childCompanies = [] } = useQuery({
    queryKey: ['company-children', id],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ parent: id, per_page: 100, _embed: true });
      return response.data;
    },
  });
  
  // Fetch investor details (investors is now array of IDs)
  const investorIds = company?.acf?.investors || [];
  const { data: investorDetails = [] } = useQuery({
    queryKey: ['company-investors', id, investorIds],
    queryFn: async () => {
      if (!investorIds.length) return [];
      
      // Fetch all people and companies, then filter by IDs
      const [peopleRes, companiesRes] = await Promise.all([
        wpApi.getPeople({ per_page: 100, include: investorIds.join(','), _embed: true }),
        wpApi.getCompanies({ per_page: 100, include: investorIds.join(','), _embed: true }),
      ]);
      
      const people = (peopleRes.data || []).map(p => ({
        id: p.id,
        type: 'person',
        name: decodeHtml(p.title?.rendered || ''),
        thumbnail: p._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));
      
      const companies = (companiesRes.data || []).map(c => ({
        id: c.id,
        type: 'company',
        name: getCompanyName(c),
        thumbnail: c._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));
      
      // Combine and sort by original order
      const all = [...people, ...companies];
      return investorIds.map(iid => all.find(i => i.id === iid)).filter(Boolean);
    },
    enabled: investorIds.length > 0,
  });
  
  // Fetch companies that this company has invested in
  const { data: investments = [] } = useQuery({
    queryKey: ['investments', id],
    queryFn: async () => {
      const response = await prmApi.getInvestments(id);
      return response.data;
    },
    enabled: !!id,
  });
  
  const deleteCompany = useMutation({
    mutationFn: () => wpApi.deleteCompany(id, { force: true }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      navigate('/companies');
    },
  });
  
  const updateCompany = useMutation({
    mutationFn: (data) => wpApi.updateCompany(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company', id] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      queryClient.invalidateQueries({ queryKey: ['company-investors', id] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
  
  // Update document title with company's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(getCompanyName(company) || 'Organization');
  
  // Redirect if company is trashed
  useEffect(() => {
    if (company?.status === 'trash') {
      navigate('/companies', { replace: true });
    }
  }, [company, navigate]);
  
  const handleDelete = async () => {
    if (!window.confirm('Are you sure you want to delete this organization?')) {
      return;
    }
    
    try {
      await deleteCompany.mutateAsync();
      // Navigation will happen in onSuccess callback
    } catch (error) {
      console.error('Failed to delete company:', error);
      alert('Failed to delete organization. Please try again.');
    }
  };
  
  const handleSaveCompany = async (data) => {
    setIsSaving(true);
    try {
      const payload = {
        title: data.title,
        parent: data.parentId || 0,
        acf: {
          website: data.website,
          industry: data.industry,
          investors: data.investors || [],
        },
      };
      
      await updateCompany.mutateAsync(payload);
      setShowEditModal(false);
    } catch (error) {
      console.error('Failed to save company:', error);
      alert('Failed to save organization. Please try again.');
    } finally {
      setIsSaving(false);
    }
  };
  
  // Handle logo upload
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
      await prmApi.uploadCompanyLogo(id, file);

      // Invalidate queries to refresh company data
      queryClient.invalidateQueries({ queryKey: ['company', id] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
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
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error || !company) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load organization.</p>
        <Link to="/companies" className="btn-secondary mt-4">Back to organizations</Link>
      </div>
    );
  }
  
  // Don't render if company is trashed (redirect will happen)
  if (company.status === 'trash') {
    return null;
  }
  
  const acf = company.acf || {};
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/companies" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to organizations</span>
        </Link>
        <div className="flex gap-2">
          <button onClick={() => setShowEditModal(true)} className="btn-secondary">
            <Edit className="w-4 h-4 mr-2" />
            Edit
          </button>
          <button onClick={handleDelete} className="btn-danger">
            <Trash2 className="w-4 h-4 mr-2" />
            Delete
          </button>
        </div>
      </div>
      
      {/* Company header */}
      <div className="card p-6">
        <div className="flex items-center gap-4">
          <div className="relative group">
            {company._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
              <img 
                src={company._embedded['wp:featuredmedia'][0].source_url}
                alt={getCompanyName(company)}
                className="w-24 h-24 rounded-lg object-contain bg-white"
              />
            ) : (
              <div className="w-24 h-24 bg-white rounded-lg flex items-center justify-center border border-gray-200">
                <Building2 className="w-12 h-12 text-gray-400" />
              </div>
            )}
            {/* Upload overlay */}
            <div 
              className="absolute inset-0 rounded-lg bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center cursor-pointer"
              onClick={() => fileInputRef.current?.click()}
            >
              {isUploadingLogo ? (
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
              ) : (
                <Camera className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
              )}
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
          <div>
            {/* Parent company link */}
            {parentCompany && (
              <Link 
                to={`/companies/${parentCompany.id}`}
                className="text-sm text-primary-600 hover:underline flex items-center mb-1"
              >
                <GitBranch className="w-3 h-3 mr-1" />
                Subsidiary of {getCompanyName(parentCompany)}
              </Link>
            )}
            <h1 className="text-2xl font-bold">{getCompanyName(company)}</h1>
            {acf.industry && <p className="text-gray-500">{acf.industry}</p>}
            {acf.website && (
              <a 
                href={acf.website} 
                target="_blank" 
                rel="noopener noreferrer"
                className="text-primary-600 hover:underline flex items-center mt-1"
              >
                <Globe className="w-4 h-4 mr-1" />
                {acf.website}
              </a>
            )}
          </div>
        </div>
      </div>
      
      {/* Subsidiaries */}
      {childCompanies.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <GitBranch className="w-5 h-5 mr-2" />
            Subsidiaries
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {childCompanies.map((child) => (
              <Link
                key={child.id}
                to={`/companies/${child.id}`}
                className="flex items-center p-3 rounded-lg hover:bg-gray-50 border border-gray-200"
              >
                {child._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                  <img 
                    src={child._embedded['wp:featuredmedia'][0].source_url}
                    alt={getCompanyName(child)}
                    className="w-10 h-10 rounded object-contain bg-white"
                  />
                ) : (
                  <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                    <Building2 className="w-5 h-5 text-gray-400" />
                  </div>
                )}
                <div className="ml-3">
                  <p className="text-sm font-medium">{getCompanyName(child)}</p>
                  {child.acf?.industry && (
                    <p className="text-xs text-gray-500">{child.acf.industry}</p>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}
      
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Current Employees */}
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <Users className="w-5 h-5 mr-2" />
            Current employees
          </h2>
          
          {employees?.current?.length > 0 ? (
            <div className="space-y-2">
              {employees.current.map((person) => (
                <Link
                  key={person.id}
                  to={`/people/${person.id}`}
                  className="flex items-center p-2 rounded hover:bg-gray-50"
                >
                  {person.thumbnail ? (
                    <img src={person.thumbnail} alt="" loading="lazy" className="w-8 h-8 rounded-full" />
                  ) : (
                    <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                      <span className="text-xs text-gray-500">{person.name?.[0] || '?'}</span>
                    </div>
                  )}
                  <div className="ml-2">
                    <p className="text-sm font-medium">{person.name}</p>
                    {person.job_title && (
                      <p className="text-xs text-gray-500">{person.job_title}</p>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500">No current employees.</p>
          )}
        </div>
        
        {/* Former Employees */}
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <Users className="w-5 h-5 mr-2" />
            Former employees
          </h2>
          
          {employees?.former?.length > 0 ? (
            <div className="space-y-2">
              {employees.former.map((person) => (
                <Link
                  key={person.id}
                  to={`/people/${person.id}`}
                  className="flex items-center p-2 rounded hover:bg-gray-50"
                >
                  {person.thumbnail ? (
                    <img src={person.thumbnail} alt="" loading="lazy" className="w-8 h-8 rounded-full opacity-75" />
                  ) : (
                    <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center opacity-75">
                      <span className="text-xs text-gray-500">{person.name?.[0] || '?'}</span>
                    </div>
                  )}
                  <div className="ml-2">
                    <p className="text-sm font-medium text-gray-700">{person.name}</p>
                    {person.job_title && (
                      <p className="text-xs text-gray-500">{person.job_title}</p>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500">No former employees.</p>
          )}
        </div>
      </div>
      
      {/* Investors */}
      {investorDetails.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Investors
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investorDetails.map((investor) => {
              const isPerson = investor.type === 'person';
              const linkPath = isPerson 
                ? `/people/${investor.id}` 
                : `/companies/${investor.id}`;
              
              return (
                <Link
                  key={`${investor.type}-${investor.id}`}
                  to={linkPath}
                  className="flex items-center p-3 rounded-lg hover:bg-gray-50 border border-gray-200"
                >
                  {investor.thumbnail ? (
                    <img 
                      src={investor.thumbnail}
                      alt={investor.name}
                      loading="lazy"
                      className={`w-10 h-10 object-cover ${isPerson ? 'rounded-full' : 'rounded'}`}
                    />
                  ) : (
                    <div className={`w-10 h-10 bg-gray-100 flex items-center justify-center ${isPerson ? 'rounded-full' : 'rounded'}`}>
                      {isPerson ? (
                        <User className="w-5 h-5 text-gray-400" />
                      ) : (
                        <Building2 className="w-5 h-5 text-gray-400" />
                      )}
                    </div>
                  )}
                  <div className="ml-3">
                    <p className="text-sm font-medium">{investor.name}</p>
                    <p className="text-xs text-gray-500">
                      {isPerson ? 'Person' : 'Organization'}
                    </p>
                  </div>
                </Link>
              );
            })}
          </div>
        </div>
      )}
      
      {/* Invested in (companies this organization has invested in) */}
      {investments.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <TrendingUp className="w-5 h-5 mr-2" />
            Invested in
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {investments.map((company) => (
              <Link
                key={company.id}
                to={`/companies/${company.id}`}
                className="flex items-center p-3 rounded-lg hover:bg-gray-50 border border-gray-200"
              >
                {company.thumbnail ? (
                  <img 
                    src={company.thumbnail}
                    alt={company.name}
                    loading="lazy"
                    className="w-10 h-10 object-contain rounded"
                  />
                ) : (
                  <div className="w-10 h-10 bg-gray-100 flex items-center justify-center rounded">
                    <Building2 className="w-5 h-5 text-gray-400" />
                  </div>
                )}
                <div className="ml-3">
                  <p className="text-sm font-medium">{company.name}</p>
                  {company.industry && (
                    <p className="text-xs text-gray-500">{company.industry}</p>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}
      
      {/* Contact info */}
      {acf.contact_info?.length > 0 && (
        <div className="card p-6">
          <h2 className="font-semibold mb-4">Contact information</h2>
          <div className="space-y-3">
            {acf.contact_info.map((contact, index) => (
              <div key={index}>
                <span className="text-sm text-gray-500">
                  {contact.contact_label || contact.contact_type}:
                </span>
                <span className="ml-2">{contact.contact_value}</span>
              </div>
            ))}
          </div>
        </div>
      )}
      
      <CompanyEditModal
        isOpen={showEditModal}
        onClose={() => setShowEditModal(false)}
        onSubmit={handleSaveCompany}
        isLoading={isSaving}
        company={company}
      />
    </div>
  );
}
