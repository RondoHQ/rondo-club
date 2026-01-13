import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Building2, Filter, X, CheckSquare, Square, MinusSquare, ArrowUp, ArrowDown } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import { useCreateCompany } from '@/hooks/useCompanies';
import { wpApi } from '@/api/client';
import { getCompanyName } from '@/utils/formatters';
import CompanyEditModal from '@/components/CompanyEditModal';

function OrganizationListRow({ company, workspaces, companyLabels, isSelected, onToggleSelection, isOdd }) {
  const assignedWorkspaces = company.acf?._assigned_workspaces || [];
  const workspaceNames = assignedWorkspaces
    .map(wsId => {
      const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
      const found = workspaces.find(ws => ws.id === numId);
      return found?.title;
    })
    .filter(Boolean)
    .join(', ');

  // Get label names from label IDs
  const labelIds = company.company_label || [];
  const labelNames = labelIds
    .map(labelId => {
      const found = companyLabels.find(l => l.id === labelId);
      return found?.name;
    })
    .filter(Boolean);

  return (
    <tr className={`hover:bg-gray-100 ${isOdd ? 'bg-gray-50' : 'bg-white'}`}>
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(company.id); }}
          className="text-gray-400 hover:text-gray-600"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-primary-600" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="w-10 px-2 py-3">
        <Link to={`/companies/${company.id}`} className="flex items-center justify-center">
          {company._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
            <img
              src={company._embedded['wp:featuredmedia'][0].source_url}
              alt=""
              className="w-8 h-8 rounded-lg object-contain bg-white"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
              <Building2 className="w-4 h-4 text-gray-400" />
            </div>
          )}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/companies/${company.id}`} className="text-sm font-medium text-gray-900">
          {getCompanyName(company)}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
        {company.acf?.industry || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 truncate max-w-48">
        {company.acf?.website || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
        {workspaceNames || '-'}
      </td>
      <td className="px-4 py-3 text-sm text-gray-500">
        {labelNames.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {labelNames.slice(0, 3).map((label) => (
              <span
                key={label}
                className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
              >
                {label}
              </span>
            ))}
            {labelNames.length > 3 && (
              <span className="text-xs text-gray-400">+{labelNames.length - 3} more</span>
            )}
          </div>
        ) : (
          '-'
        )}
      </td>
    </tr>
  );
}

// Sortable header component for clickable column headers
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;

  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 cursor-pointer"
      >
        {label}
        {isActive && (
          currentSortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </button>
    </th>
  );
}

function OrganizationListView({ companies, workspaces, companyLabels, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort }) {
  return (
    <div className="card overflow-x-auto max-h-[calc(100vh-12rem)] overflow-y-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50 sticky top-0 z-10">
          <tr className="shadow-sm">
            <th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50">
              <button
                onClick={onToggleSelectAll}
                className="text-gray-400 hover:text-gray-600"
                title={isAllSelected ? 'Deselect all' : 'Select all'}
              >
                {isAllSelected ? (
                  <CheckSquare className="w-5 h-5 text-primary-600" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-primary-600" />
                ) : (
                  <Square className="w-5 h-5" />
                )}
              </button>
            </th>
            <th scope="col" className="w-10 px-2 bg-gray-50"></th>
            <SortableHeader field="name" label="Name" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="industry" label="Industry" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="website" label="Website" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="workspace" label="Workspace" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="labels" label="Labels" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {companies.map((company, index) => (
            <OrganizationListRow
              key={company.id}
              company={company}
              workspaces={workspaces}
              companyLabels={companyLabels}
              isSelected={selectedIds.has(company.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function CompaniesList() {
  const [search, setSearch] = useState('');
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [ownershipFilter, setOwnershipFilter] = useState('all'); // 'all', 'mine', 'shared'
  const [selectedWorkspaceFilter, setSelectedWorkspaceFilter] = useState('');
  const [showCompanyModal, setShowCompanyModal] = useState(false);
  const [isCreatingCompany, setIsCreatingCompany] = useState(false);
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedIds, setSelectedIds] = useState(new Set());
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

  // Fetch company labels
  const { data: companyLabelsData } = useQuery({
    queryKey: ['company-labels'],
    queryFn: async () => {
      const response = await wpApi.getCompanyLabels();
      return response.data;
    },
  });
  const companyLabels = companyLabelsData || [];

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

  // Selection helper functions
  const toggleSelection = (companyId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(companyId)) {
        next.delete(companyId);
      } else {
        next.add(companyId);
      }
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  // Helper to get workspace names for a company (used in sorting)
  const getCompanyWorkspaceNames = (company) => {
    const assignedWorkspaces = company.acf?._assigned_workspaces || [];
    return assignedWorkspaces
      .map(wsId => {
        const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
        const found = workspaces.find(ws => ws.id === numId);
        return found?.title;
      })
      .filter(Boolean)
      .join(', ');
  };

  // Helper to get label names for a company (used in sorting)
  const getCompanyLabelNames = (company) => {
    const labelIds = company.company_label || [];
    return labelIds
      .map(labelId => {
        const found = companyLabels.find(l => l.id === labelId);
        return found?.name;
      })
      .filter(Boolean);
  };

  // Filter companies
  const filteredCompanies = useMemo(() => {
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

    return filtered;
  }, [companies, ownershipFilter, selectedWorkspaceFilter, currentUserId]);

  // Sort filtered companies
  const sortedCompanies = useMemo(() => {
    if (!filteredCompanies) return [];

    return [...filteredCompanies].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'name') {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      } else if (sortField === 'industry') {
        valueA = (a.acf?.industry || '').toLowerCase();
        valueB = (b.acf?.industry || '').toLowerCase();
      } else if (sortField === 'website') {
        valueA = (a.acf?.website || '').toLowerCase();
        valueB = (b.acf?.website || '').toLowerCase();
      } else if (sortField === 'workspace') {
        valueA = getCompanyWorkspaceNames(a).toLowerCase();
        valueB = getCompanyWorkspaceNames(b).toLowerCase();
      } else if (sortField === 'labels') {
        const labelsA = getCompanyLabelNames(a);
        const labelsB = getCompanyLabelNames(b);
        valueA = (labelsA[0] || '').toLowerCase();
        valueB = (labelsB[0] || '').toLowerCase();
      } else {
        valueA = (a.title?.rendered || a.title || '').toLowerCase();
        valueB = (b.title?.rendered || b.title || '').toLowerCase();
      }

      // Empty values sort last
      if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
      if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
      if (!valueA && !valueB) return 0;

      const comparison = valueA.localeCompare(valueB);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredCompanies, sortField, sortOrder, workspaces, companyLabels]);

  // Computed selection state
  const isAllSelected = sortedCompanies.length > 0 &&
    selectedIds.size === sortedCompanies.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < sortedCompanies.length;

  const toggleSelectAll = () => {
    if (selectedIds.size === sortedCompanies.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(sortedCompanies.map(c => c.id)));
    }
  };

  // Clear selection when filters change
  useEffect(() => {
    setSelectedIds(new Set());
  }, [ownershipFilter, selectedWorkspaceFilter, companies]);
  
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

          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="name">Name</option>
              <option value="industry">Industry</option>
              <option value="website">Website</option>
              <option value="workspace">Workspace</option>
              <option value="labels">Labels</option>
            </select>
            <div className="h-4 w-px bg-gray-300"></div>
            <button
              onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
              className="p-1 hover:bg-gray-100 rounded transition-colors"
              title={`Sort ${sortOrder === 'asc' ? 'descending' : 'ascending'}`}
            >
              {sortOrder === 'asc' ? (
                <ArrowUp className="w-4 h-4 text-gray-600" />
              ) : (
                <ArrowDown className="w-4 h-4 text-gray-600" />
              )}
            </button>
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
      {!isLoading && !error && companies?.length > 0 && sortedCompanies?.length === 0 && (
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
      
      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-primary-50 border border-primary-200 rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-primary-800 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'organization' : 'organizations'} selected
          </span>
          <button
            onClick={clearSelection}
            className="text-sm text-primary-600 hover:text-primary-800 font-medium"
          >
            Clear selection
          </button>
        </div>
      )}

      {/* Organizations list */}
      {!isLoading && !error && sortedCompanies?.length > 0 && (
        <OrganizationListView
          companies={sortedCompanies}
          workspaces={workspaces}
          companyLabels={companyLabels}
          selectedIds={selectedIds}
          onToggleSelection={toggleSelection}
          onToggleSelectAll={toggleSelectAll}
          isAllSelected={isAllSelected}
          isSomeSelected={isSomeSelected}
          sortField={sortField}
          sortOrder={sortOrder}
          onSort={(field) => {
            if (field === sortField) {
              setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
            } else {
              setSortField(field);
              setSortOrder('asc');
            }
          }}
        />
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
