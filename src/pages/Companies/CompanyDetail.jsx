import { Link, useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Edit, Trash2, Building2, Globe, Users } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function CompanyDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  
  const { data: company, isLoading, error } = useQuery({
    queryKey: ['company', id],
    queryFn: async () => {
      const response = await wpApi.getCompany(id);
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
  
  const deleteCompany = useMutation({
    mutationFn: () => wpApi.deleteCompany(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      navigate('/companies');
    },
  });
  
  // Update document title with company's name - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(company?.title?.rendered || company?.title || 'Company');
  
  const handleDelete = () => {
    if (window.confirm('Are you sure you want to delete this company?')) {
      deleteCompany.mutate();
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
        <p className="text-red-600">Failed to load company.</p>
        <Link to="/companies" className="btn-secondary mt-4">Back to Companies</Link>
      </div>
    );
  }
  
  const acf = company.acf || {};
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to="/companies" className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Companies
        </Link>
        <div className="flex gap-2">
          <Link to={`/companies/${id}/edit`} className="btn-secondary">
            <Edit className="w-4 h-4 mr-2" />
            Edit
          </Link>
          <button onClick={handleDelete} className="btn-danger">
            <Trash2 className="w-4 h-4 mr-2" />
            Delete
          </button>
        </div>
      </div>
      
      {/* Company header */}
      <div className="card p-6">
        <div className="flex items-center gap-4">
          {company._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
            <img 
              src={company._embedded['wp:featuredmedia'][0].source_url}
              alt={company.title.rendered}
              className="w-16 h-16 rounded-lg object-cover"
            />
          ) : (
            <div className="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
              <Building2 className="w-8 h-8 text-gray-400" />
            </div>
          )}
          <div>
            <h1 className="text-2xl font-bold">{company.title.rendered}</h1>
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
      
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Employees */}
        <div className="card p-6">
          <h2 className="font-semibold mb-4 flex items-center">
            <Users className="w-5 h-5 mr-2" />
            People at {company.title.rendered}
          </h2>
          
          {employees?.current?.length > 0 && (
            <div className="mb-4">
              <h3 className="text-sm font-medium text-gray-500 mb-2">Current</h3>
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
                      <div className="w-8 h-8 bg-gray-200 rounded-full" />
                    )}
                    <div className="ml-2">
                      <p className="text-sm font-medium">{person.name}</p>
                      <p className="text-xs text-gray-500">{person.job_title}</p>
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}
          
          {employees?.former?.length > 0 && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 mb-2">Former</h3>
              <div className="space-y-2">
                {employees.former.map((person) => (
                  <Link
                    key={person.id}
                    to={`/people/${person.id}`}
                    className="flex items-center p-2 rounded hover:bg-gray-50 opacity-60"
                  >
                    {person.thumbnail ? (
                      <img src={person.thumbnail} alt="" loading="lazy" className="w-8 h-8 rounded-full" />
                    ) : (
                      <div className="w-8 h-8 bg-gray-200 rounded-full" />
                    )}
                    <div className="ml-2">
                      <p className="text-sm font-medium">{person.name}</p>
                      <p className="text-xs text-gray-500">{person.job_title}</p>
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          )}
          
          {!employees?.current?.length && !employees?.former?.length && (
            <p className="text-sm text-gray-500">No people linked to this company yet.</p>
          )}
        </div>
        
        {/* Contact info */}
        {acf.contact_info?.length > 0 && (
          <div className="card p-6">
            <h2 className="font-semibold mb-4">Contact Information</h2>
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
      </div>
    </div>
  );
}
