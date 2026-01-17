import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Building2, Filter, X, CheckSquare, Square, MinusSquare, ArrowUp, ArrowDown, ChevronDown, Lock, Users, Tag, Check } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import { useCreateCompany, useBulkUpdateCompanies } from '@/hooks/useCompanies';
import { wpApi } from '@/api/client';
import { getCompanyName } from '@/utils/formatters';
import CompanyEditModal from '@/components/CompanyEditModal';

// Bulk Visibility Modal Component for Organizations
function BulkVisibilityModal({ isOpen, onClose, selectedCount, onSubmit, isLoading }) {
  const [selectedVisibility, setSelectedVisibility] = useState('private');

  // Reset selection when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedVisibility('private');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const visibilityOptions = [
    {
      value: 'private',
      label: 'Private',
      description: 'Only you can see these organizations',
      icon: Lock
    },
    {
      value: 'workspace',
      label: 'Workspace',
      description: 'Share with workspace members',
      icon: Users
    },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Change Visibility</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Select visibility for {selectedCount} {selectedCount === 1 ? 'organization' : 'organizations'}:
          </p>

          <div className="space-y-2">
            {visibilityOptions.map((option) => {
              const Icon = option.icon;
              const isSelected = selectedVisibility === option.value;
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setSelectedVisibility(option.value)}
                  disabled={isLoading}
                  className={`w-full flex items-start gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                    isSelected
                      ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/30'
                      : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                  }`}
                >
                  <Icon className={`w-5 h-5 mt-0.5 ${isSelected ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
                  <div className="flex-1">
                    <div className={`text-sm font-medium ${isSelected ? 'text-accent-900 dark:text-accent-100' : 'text-gray-900 dark:text-gray-50'}`}>
                      {option.label}
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">{option.description}</div>
                  </div>
                  {isSelected && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400 mt-0.5" />}
                </button>
              );
            })}
          </div>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
            disabled={isLoading}
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedVisibility)}
            className="btn-primary"
            disabled={isLoading}
          >
            {isLoading ? 'Applying...' : `Apply to ${selectedCount} ${selectedCount === 1 ? 'organization' : 'organizations'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Workspace Modal Component for Organizations
function BulkWorkspaceModal({ isOpen, onClose, selectedCount, workspaces, onSubmit, isLoading }) {
  const [selectedWorkspaceIds, setSelectedWorkspaceIds] = useState([]);

  // Reset selection when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedWorkspaceIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleWorkspaceToggle = (workspaceId) => {
    setSelectedWorkspaceIds(prev =>
      prev.includes(workspaceId)
        ? prev.filter(id => id !== workspaceId)
        : [...prev, workspaceId]
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Assign to Workspace</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Select workspaces for {selectedCount} {selectedCount === 1 ? 'organization' : 'organizations'}:
          </p>

          {workspaces.length === 0 ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Users className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">No workspaces available.</p>
              <p className="text-xs">Create a workspace first to use this feature.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {workspaces.map((workspace) => {
                const isChecked = selectedWorkspaceIds.includes(workspace.id);
                return (
                  <button
                    key={workspace.id}
                    type="button"
                    onClick={() => handleWorkspaceToggle(workspace.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/30'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked ? 'bg-accent-600 border-accent-600' : 'border-gray-300 dark:border-gray-500'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{workspace.title}</div>
                      <div className="text-xs text-gray-500 dark:text-gray-400">{workspace.member_count} members</div>
                    </div>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
            disabled={isLoading}
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedWorkspaceIds)}
            className="btn-primary"
            disabled={isLoading || workspaces.length === 0}
          >
            {isLoading ? 'Assigning...' : `Assign to ${selectedCount} ${selectedCount === 1 ? 'organization' : 'organizations'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Labels Modal Component for Organizations
function BulkLabelsModal({ isOpen, onClose, selectedCount, labels, onSubmit, isLoading }) {
  const [mode, setMode] = useState('add'); // 'add' or 'remove'
  const [selectedLabelIds, setSelectedLabelIds] = useState([]);

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setMode('add');
      setSelectedLabelIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleLabelToggle = (labelId) => {
    setSelectedLabelIds(prev =>
      prev.includes(labelId)
        ? prev.filter(id => id !== labelId)
        : [...prev, labelId]
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Manage Labels</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            {mode === 'add' ? 'Add' : 'Remove'} labels for {selectedCount} {selectedCount === 1 ? 'organization' : 'organizations'}:
          </p>

          {/* Mode toggle */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-600 p-1">
            <button
              type="button"
              onClick={() => { setMode('add'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'add' ? 'bg-accent-100 dark:bg-accent-900/50 text-accent-700 dark:text-accent-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Add Labels
            </button>
            <button
              type="button"
              onClick={() => { setMode('remove'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'remove' ? 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Remove Labels
            </button>
          </div>

          {/* Label list */}
          {(!labels || labels.length === 0) ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Tag className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">No labels available.</p>
              <p className="text-xs">Create labels first to use this feature.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {labels.map((label) => {
                const isChecked = selectedLabelIds.includes(label.id);
                return (
                  <button
                    key={label.id}
                    type="button"
                    onClick={() => handleLabelToggle(label.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? mode === 'add' ? 'border-accent-500 bg-accent-50 dark:bg-accent-900/30' : 'border-red-500 bg-red-50 dark:bg-red-900/30'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked
                        ? mode === 'add' ? 'bg-accent-600 border-accent-600' : 'bg-red-600 border-red-600'
                        : 'border-gray-300 dark:border-gray-500'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-50">{label.name}</span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
            Cancel
          </button>
          <button
            type="button"
            onClick={() => onSubmit(mode, selectedLabelIds)}
            className={mode === 'add' ? 'btn-primary' : 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50'}
            disabled={isLoading || selectedLabelIds.length === 0}
          >
            {isLoading
              ? (mode === 'add' ? 'Adding...' : 'Removing...')
              : `${mode === 'add' ? 'Add' : 'Remove'} ${selectedLabelIds.length} ${selectedLabelIds.length === 1 ? 'label' : 'labels'}`
            }
          </button>
        </div>
      </div>
    </div>
  );
}

function OrganizationListRow({ company, workspaces, isSelected, onToggleSelection, isOdd }) {
  const assignedWorkspaces = company.acf?._assigned_workspaces || [];
  const workspaceNames = assignedWorkspaces
    .map(wsId => {
      const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
      const found = workspaces.find(ws => ws.id === numId);
      return found?.title;
    })
    .filter(Boolean)
    .join(', ');

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'}`}>
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(company.id); }}
          className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
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
              className="w-8 h-8 rounded-lg"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center justify-center">
              <Building2 className="w-4 h-4 text-gray-400 dark:text-gray-500" />
            </div>
          )}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/companies/${company.id}`} className="text-sm font-medium text-gray-900 dark:text-gray-50">
          {getCompanyName(company)}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {company.acf?.industry || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-48">
        {company.acf?.website ? (
          <a
            href={company.acf.website.startsWith('http') ? company.acf.website : `https://${company.acf.website}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent-600 dark:text-accent-400 hover:underline truncate block"
          >
            {company.acf.website}
          </a>
        ) : '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {workspaceNames || '-'}
      </td>
    </tr>
  );
}

// Sortable header component for clickable column headers
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;

  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
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

function OrganizationListView({ companies, workspaces, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort }) {
  return (
    <div className="card overflow-x-auto max-h-[calc(100vh-12rem)] overflow-y-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
          <tr className="shadow-sm">
            <th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800">
              <button
                onClick={onToggleSelectAll}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                title={isAllSelected ? 'Deselect all' : 'Select all'}
              >
                {isAllSelected ? (
                  <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : (
                  <Square className="w-5 h-5" />
                )}
              </button>
            </th>
            <th scope="col" className="w-10 px-2 bg-gray-50 dark:bg-gray-800"></th>
            <SortableHeader field="name" label="Name" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="industry" label="Industry" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="website" label="Website" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="workspace" label="Workspace" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
          {companies.map((company, index) => (
            <OrganizationListRow
              key={company.id}
              company={company}
              workspaces={workspaces}
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
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkVisibilityModal, setShowBulkVisibilityModal] = useState(false);
  const [showBulkWorkspaceModal, setShowBulkWorkspaceModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const navigate = useNavigate();

  const { data: workspaces = [] } = useWorkspaces();
  const bulkUpdateMutation = useBulkUpdateCompanies();

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
      // Also close bulk dropdown when clicking outside
      if (
        bulkDropdownRef.current &&
        !bulkDropdownRef.current.contains(event.target)
      ) {
        setShowBulkDropdown(false);
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
  }, [filteredCompanies, sortField, sortOrder, workspaces]);

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
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="search"
              placeholder="Search organizations..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input pl-9"
            />
          </div>

          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 dark:border-gray-600 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent dark:text-gray-200 focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="name">Name</option>
              <option value="industry">Industry</option>
              <option value="website">Website</option>
              <option value="workspace">Workspace</option>
            </select>
            <div className="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>
            <button
              onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              title={`Sort ${sortOrder === 'asc' ? 'descending' : 'ascending'}`}
            >
              {sortOrder === 'asc' ? (
                <ArrowUp className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              ) : (
                <ArrowDown className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              )}
            </button>
          </div>

          <div className="relative" ref={filterRef}>
            <button
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-accent-50 text-accent-700 border-accent-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-accent-600 text-white text-xs rounded-full">
                  {(ownershipFilter !== 'all' ? 1 : 0) + (selectedWorkspaceFilter ? 1 : 0)}
                </span>
              )}
            </button>

            {/* Filter Dropdown */}
            {isFilterOpen && (
              <div
                ref={dropdownRef}
                className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50"
              >
                <div className="p-4 space-y-4">
                  {/* Ownership Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
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
                          className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
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
                              ? 'border-accent-600'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {ownershipFilter === option.value && (
                              <div className="w-2 h-2 bg-accent-600 rounded-full" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">{option.label}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Workspace Filter */}
                  {workspaces.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Workspace
                      </h3>
                      <select
                        value={selectedWorkspaceFilter}
                        onChange={(e) => setSelectedWorkspaceFilter(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
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
                      className="w-full text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
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
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-accent-100 dark:bg-accent-900/50 text-accent-800 dark:text-accent-200 rounded-full text-xs">
                  {ownershipFilter === 'mine' ? 'My Organizations' : 'Shared with Me'}
                  <button onClick={() => setOwnershipFilter('all')} className="hover:text-accent-600 dark:hover:text-accent-300">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {selectedWorkspaceFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-full text-xs">
                  {workspaces.find(ws => ws.id === parseInt(selectedWorkspaceFilter))?.title || 'Workspace'}
                  <button onClick={() => setSelectedWorkspaceFilter('')} className="hover:text-blue-600 dark:hover:text-blue-300">
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
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
        </div>
      )}
      
      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Failed to load organizations.</p>
        </div>
      )}
      
      {/* Empty - no organizations at all */}
      {!isLoading && !error && companies?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Building2 className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">No organizations found</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
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
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">No organizations match your filters</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Try adjusting your filters to see more results.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Clear filters
          </button>
        </div>
      )}
      
      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-accent-50 border border-accent-200 rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-accent-800 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'organization' : 'organizations'} selected
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-accent-700 bg-white border border-accent-300 rounded-md hover:bg-accent-50"
              >
                Actions
                <ChevronDown className={`w-4 h-4 transition-transform ${showBulkDropdown ? 'rotate-180' : ''}`} />
              </button>
              {showBulkDropdown && (
                <div className="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
                  <div className="py-1">
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkVisibilityModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50"
                    >
                      <Lock className="w-4 h-4" />
                      Change visibility...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkWorkspaceModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50"
                    >
                      <Users className="w-4 h-4" />
                      Assign to workspace...
                    </button>
                  </div>
                </div>
              )}
            </div>
            <button
              onClick={clearSelection}
              className="text-sm text-accent-600 hover:text-accent-800 font-medium"
            >
              Clear selection
            </button>
          </div>
        </div>
      )}

      {/* Organizations list */}
      {!isLoading && !error && sortedCompanies?.length > 0 && (
        <OrganizationListView
          companies={sortedCompanies}
          workspaces={workspaces}
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

      {/* Bulk Visibility Modal */}
      <BulkVisibilityModal
        isOpen={showBulkVisibilityModal}
        onClose={() => setShowBulkVisibilityModal(false)}
        selectedCount={selectedIds.size}
        onSubmit={async (visibility) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { visibility }
            });
            clearSelection();
            setShowBulkVisibilityModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

      {/* Bulk Workspace Modal */}
      <BulkWorkspaceModal
        isOpen={showBulkWorkspaceModal}
        onClose={() => setShowBulkWorkspaceModal(false)}
        selectedCount={selectedIds.size}
        workspaces={workspaces}
        onSubmit={async (workspaceIds) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { assigned_workspaces: workspaceIds }
            });
            clearSelection();
            setShowBulkWorkspaceModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

    </div>
  );
}
