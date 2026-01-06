import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Building2 } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { getCompanyName } from '@/utils/formatters';

function CompanyCard({ company }) {
  return (
    <Link 
      to={`/companies/${company.id}`}
      className="card p-4 hover:shadow-md transition-shadow"
    >
      <div className="flex items-start">
        {company._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
          <img 
            src={company._embedded['wp:featuredmedia'][0].source_url}
            alt={getCompanyName(company)}
            className="w-12 h-12 rounded-lg object-contain bg-white"
          />
        ) : (
          <div className="w-12 h-12 bg-white rounded-lg flex items-center justify-center border border-gray-200">
            <Building2 className="w-6 h-6 text-gray-400" />
          </div>
        )}
        <div className="ml-3 flex-1 min-w-0">
          <h3 className="text-sm font-medium text-gray-900 truncate">
            {getCompanyName(company)}
          </h3>
          {company.acf?.industry && (
            <p className="text-sm text-gray-500">{company.acf.industry}</p>
          )}
          {company.acf?.website && (
            <p className="text-xs text-primary-600 truncate">{company.acf.website}</p>
          )}
        </div>
      </div>
    </Link>
  );
}

export default function CompaniesList() {
  const [search, setSearch] = useState('');
  
  const { data: companies, isLoading, error } = useQuery({
    queryKey: ['companies', search],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ search, per_page: 100, _embed: true });
      return response.data;
    },
  });

  // Sort companies alphabetically by name
  const sortedCompanies = useMemo(() => {
    if (!companies) return [];
    
    return [...companies].sort((a, b) => {
      const nameA = (a.title?.rendered || a.title || '').toLowerCase();
      const nameB = (b.title?.rendered || b.title || '').toLowerCase();
      return nameA.localeCompare(nameB);
    });
  }, [companies]);
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="search"
            placeholder="Search organizations..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input pl-9"
          />
        </div>
        
        <Link to="/companies/new" className="btn-primary">
          <Plus className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Add organization</span>
        </Link>
      </div>
      
      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>
      )}
      
      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600">Failed to load organizations.</p>
        </div>
      )}
      
      {/* Empty */}
      {!isLoading && !error && sortedCompanies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Building2 className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No organizations found</h3>
          <p className="text-gray-500 mb-4">
            {search ? 'Try a different search.' : 'Add your first organization.'}
          </p>
          {!search && (
            <Link to="/companies/new" className="btn-primary">
              <Plus className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Add organization</span>
            </Link>
          )}
        </div>
      )}
      
      {/* Grid */}
      {!isLoading && !error && sortedCompanies?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {sortedCompanies.map((company) => (
            <CompanyCard key={company.id} company={company} />
          ))}
        </div>
      )}
    </div>
  );
}
