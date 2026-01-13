import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Building2, Filter, X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import { useCreateCompany } from '@/hooks/useCompanies';
import { wpApi } from '@/api/client';
import { getCompanyName } from '@/utils/formatters';
import CompanyEditModal from '@/components/CompanyEditModal';

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
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [ownershipFilter, setOwnershipFilter] = useState('all'); // 'all', 'mine', 'shared'
  const [selectedWorkspaceFilter, setSelectedWorkspaceFilter] = useState('');
  const [showCompanyModal, setShowCompanyModal] = useState(false);
  const [isCreatingCompany, setIsCreatingCompany] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const navigate = useNavigate();

  const { data: workspaces = [] } = useWorkspaces();

  // Get current user ID from prmConfig
  const currentUserId = window.prmConfig?.userId;

  const { data: companies, isLoading, error } = useQuery({
    queryKey: ['companies', search],
    queryFn: async () => {
      const response = await wpApi.getCompanies({ search, per_page: 100, _embed: true });
      return response.data;
    },
  });

  // Create company mutation
  const createCompanyMutation = useCreateCompany({
    onSuccess: (result) => {
      setShowCompanyModal(false);
      navigate(`/companies/${result.id}`);
    },
  });

  const handleCreateCompany = async (data) => {
    setIsCreatingCompany(true);
    try {
      await createCompanyMutation.mutateAsync(data);
    } finally {
      setIsCreatingCompany(false);
    }
  };

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        filterRef.current &&
        !filterRef.current.contains(event.target)
      ) {
        setIsFilterOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const hasActiveFilters = ownershipFilter !== 'all' || selectedWorkspaceFilter;

  const clearFilters = () => {
    setOwnershipFilter('all');
    setSelectedWorkspaceFilter('');
  };

  // Filter and sort companies
  const filteredAndSortedCompanies = useMemo(() => {
    if (!companies) return [];

    let filtered = [...companies];

    // Apply ownership filter
    if (ownershipFilter === 'mine') {
      filtered = filtered.filter(company => company.author === currentUserId);
    } else if (ownershipFilter === 'shared') {
      filtered = filtered.filter(company => company.author !== currentUserId);
    }

    // Apply workspace filter
    if (selectedWorkspaceFilter) {
      filtered = filtered.filter(company => {
        const assignedWorkspaces = company.acf?._assigned_workspaces || [];
        return assignedWorkspaces.includes(parseInt(selectedWorkspaceFilter));
      });
    }

    // Sort alphabetically by name
    return filtered.sort((a, b) => {
      const nameA = (a.title?.rendered || a.title || '').toLowerCase();
      const nameB = (b.title?.rendered || b.title || '').toLowerCase();
      return nameA.localeCompare(nameB);
    });
  }, [companies, ownershipFilter, selectedWorkspaceFilter, currentUserId]);
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-48 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="search"
              placeholder="Search organizations..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input pl-9"
            />
          </div>

          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-primary-50 text-primary-700 border-primary-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-primary-600 text-white text-xs rounded-full">
                  {(ownershipFilter !== 'all' ? 1 : 0) + (selectedWorkspaceFilter ? 1 : 0)}
                </span>
              )}
            </button>

            {/* Filter Dropdown */}
            {isFilterOpen && (
              <div
                ref={dropdownRef}
                className="absolute top-full left-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50"
              >
                <div className="p-4 space-y-4">
                  {/* Ownership Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      Ownership
                    </h3>
                    <div className="space-y-1">
                      {[
                        { value: 'all', label: 'All Organizations' },
                        { value: 'mine', label: 'My Organizations' },
                        { value: 'shared', label: 'Shared with Me' },
                      ].map(option => (
                        <label
                          key={option.value}
                          className="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded"
                        >
                          <input
                            type="radio"
                            name="ownership"
                            value={option.value}
                            checked={ownershipFilter === option.value}
                            onChange={(e) => setOwnershipFilter(e.target.value)}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-4 h-4 border-2 rounded-full mr-3 ${
                            ownershipFilter === option.value
                              ? 'border-primary-600'
                              : 'border-gray-300'
                          }`}>
                            {ownershipFilter === option.value && (
                              <div className="w-2 h-2 bg-primary-600 rounded-full" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700">{option.label}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Workspace Filter */}
                  {workspaces.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Workspace
                      </h3>
                      <select
                        value={selectedWorkspaceFilter}
                        onChange={(e) => setSelectedWorkspaceFilter(e.target.value)}
                        className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary-500 focus:border-primary-500"
                      >
                        <option value="">All Workspaces</option>
                        {workspaces.map(ws => (
                          <option key={ws.id} value={ws.id}>{ws.title}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Clear Filters */}
                  {hasActiveFilters && (
                    <button
                      onClick={clearFilters}
                      className="w-full text-sm text-primary-600 hover:text-primary-700 font-medium pt-2 border-t border-gray-200"
                    >
                      Clear all filters
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Active Filter Chips */}
          {hasActiveFilters && (
            <div className="flex flex-wrap gap-2">
              {ownershipFilter !== 'all' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs">
                  {ownershipFilter === 'mine' ? 'My Organizations' : 'Shared with Me'}
                  <button onClick={() => setOwnershipFilter('all')} className="hover:text-primary-600">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {selectedWorkspaceFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                  {workspaces.find(ws => ws.id === parseInt(selectedWorkspaceFilter))?.title || 'Workspace'}
                  <button onClick={() => setSelectedWorkspaceFilter('')} className="hover:text-blue-600">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
            </div>
          )}

          <button onClick={() => setShowCompanyModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Add organization</span>
          </button>
        </div>
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
      
      {/* Empty - no organizations at all */}
      {!isLoading && !error && companies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Building2 className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No organizations found</h3>
          <p className="text-gray-500 mb-4">
            {search ? 'Try a different search.' : 'Add your first organization.'}
          </p>
          {!search && (
            <button onClick={() => setShowCompanyModal(true)} className="btn-primary">
              <Plus className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Add organization</span>
            </button>
          )}
        </div>
      )}

      {/* No results with filters */}
      {!isLoading && !error && companies?.length > 0 && filteredAndSortedCompanies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No organizations match your filters</h3>
          <p className="text-gray-500 mb-4">
            Try adjusting your filters to see more results.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Clear filters
          </button>
        </div>
      )}
      
      {/* Grid */}
      {!isLoading && !error && filteredAndSortedCompanies?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredAndSortedCompanies.map((company) => (
            <CompanyCard key={company.id} company={company} />
          ))}
        </div>
      )}
      
      {/* Company Modal */}
      <CompanyEditModal
        isOpen={showCompanyModal}
        onClose={() => setShowCompanyModal(false)}
        onSubmit={handleCreateCompany}
        isLoading={isCreatingCompany}
      />
    </div>
  );
}
