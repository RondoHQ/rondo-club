import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Star, Filter, X, Check, ArrowUp, ArrowDown, LayoutGrid, List, Square, CheckSquare, MinusSquare, ChevronDown, Lock, Users } from 'lucide-react';
import { usePeople, useCreatePerson, useBulkUpdatePeople } from '@/hooks/usePeople';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { getCompanyName } from '@/utils/formatters';
import PersonEditModal from '@/components/PersonEditModal';

// Helper function to get current company ID from person's work history
function getCurrentCompanyId(person) {
  const workHistory = person.acf?.work_history || [];
  if (workHistory.length === 0) return null;
  
  // First, try to find current position
  const currentJob = workHistory.find(job => job.is_current && job.company);
  if (currentJob) return currentJob.company;
  
  // Otherwise, get the most recent (by start_date)
  const jobsWithCompany = workHistory
    .filter(job => job.company)
    .sort((a, b) => {
      const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
      const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
      return dateB - dateA; // Most recent first
    });
  
  return jobsWithCompany.length > 0 ? jobsWithCompany[0].company : null;
}

function PersonCard({ person, companyName, isDeceased }) {
  return (
    <Link 
      to={`/people/${person.id}`}
      className="card p-4 hover:shadow-md transition-shadow"
    >
      <div className="flex items-start">
        {person.thumbnail ? (
          <img 
            src={person.thumbnail} 
            alt={person.name}
            loading="lazy"
            className="w-12 h-12 rounded-full object-cover"
          />
        ) : (
          <div className="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
            <span className="text-lg font-medium text-gray-500">
              {person.first_name?.[0] || '?'}
            </span>
          </div>
        )}
        <div className="ml-3 flex-1 min-w-0">
          <div className="flex items-center">
            <h3 className="text-sm font-medium text-gray-900 truncate">
              {person.name}
              {isDeceased && <span className="ml-1 text-gray-500">†</span>}
            </h3>
            {person.is_favorite && (
              <Star className="w-4 h-4 ml-1 text-yellow-400 fill-current flex-shrink-0" />
            )}
          </div>
          {companyName && (
            <p className="text-xs text-gray-500 mt-0.5 truncate">
              {companyName}
            </p>
          )}
          {person.labels?.length > 0 && (
            <div className="flex flex-wrap gap-1 mt-1">
              {person.labels.slice(0, 3).map((label) => (
                <span 
                  key={label}
                  className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
                >
                  {label}
                </span>
              ))}
            </div>
          )}
        </div>
      </div>
    </Link>
  );
}

function PersonListRow({ person, companyName, workspaces, isSelected, onToggleSelection, isOdd }) {
  const assignedWorkspaces = person.acf?._assigned_workspaces || [];
  const workspaceNames = assignedWorkspaces
    .map(wsId => {
      // Try both number and string comparison
      const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
      const found = workspaces.find(ws => ws.id === numId);
      return found?.title;
    })
    .filter(Boolean)
    .join(', ');

  return (
    <tr className={`hover:bg-gray-100 ${isOdd ? 'bg-gray-50' : 'bg-white'}`}>
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
          className="text-gray-400 hover:text-gray-600"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-primary-600" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/people/${person.id}`} className="flex items-center">
          {person.thumbnail ? (
            <img
              src={person.thumbnail}
              alt=""
              className="w-8 h-8 rounded-full object-cover"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-gray-500">
                {person.first_name?.[0] || '?'}
              </span>
            </div>
          )}
          <div className="ml-3">
            <div className="flex items-center">
              <span className="text-sm font-medium text-gray-900">
                {person.first_name || ''}
                {person.is_deceased && <span className="ml-1 text-gray-500">†</span>}
              </span>
              {person.is_favorite && (
                <Star className="w-4 h-4 ml-1 text-yellow-400 fill-current" />
              )}
            </div>
          </div>
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
        {person.last_name || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
        {companyName || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
        {workspaceNames || '-'}
      </td>
      <td className="px-4 py-3 text-sm text-gray-500">
        {person.labels && person.labels.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {person.labels.slice(0, 3).map((label) => (
              <span
                key={label}
                className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
              >
                {label}
              </span>
            ))}
            {person.labels.length > 3 && (
              <span className="text-xs text-gray-400">+{person.labels.length - 3} more</span>
            )}
          </div>
        ) : (
          '-'
        )}
      </td>
    </tr>
  );
}

function PersonListView({ people, companyMap, workspaces, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected }) {
  return (
    <div className="card overflow-hidden">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            <th scope="col" className="pl-4 pr-2 py-3 w-10">
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
            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              First Name
            </th>
            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Last Name
            </th>
            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Organization
            </th>
            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Workspace
            </th>
            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Labels
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {people.map((person, index) => (
            <PersonListRow
              key={person.id}
              person={person}
              companyName={companyMap[person.id]}
              workspaces={workspaces}
              isSelected={selectedIds.has(person.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

// Bulk Visibility Modal Component
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
      description: 'Only you can see these contacts',
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
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Change Visibility</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600">
            Select visibility for {selectedCount} {selectedCount === 1 ? 'person' : 'people'}:
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
                      ? 'border-primary-500 bg-primary-50'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <Icon className={`w-5 h-5 mt-0.5 ${isSelected ? 'text-primary-600' : 'text-gray-400'}`} />
                  <div className="flex-1">
                    <div className={`text-sm font-medium ${isSelected ? 'text-primary-900' : 'text-gray-900'}`}>
                      {option.label}
                    </div>
                    <div className="text-xs text-gray-500">{option.description}</div>
                  </div>
                  {isSelected && <Check className="w-5 h-5 text-primary-600 mt-0.5" />}
                </button>
              );
            })}
          </div>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t bg-gray-50">
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
            {isLoading ? 'Applying...' : `Apply to ${selectedCount} ${selectedCount === 1 ? 'person' : 'people'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Workspace Modal Component
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
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Assign to Workspace</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600">
            Select workspaces for {selectedCount} {selectedCount === 1 ? 'person' : 'people'}:
          </p>

          {workspaces.length === 0 ? (
            <div className="text-center py-6 text-gray-500">
              <Users className="w-8 h-8 mx-auto mb-2 text-gray-400" />
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
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked ? 'bg-primary-600 border-primary-600' : 'border-gray-300'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900">{workspace.title}</div>
                      <div className="text-xs text-gray-500">{workspace.member_count} members</div>
                    </div>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t bg-gray-50">
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
            {isLoading ? 'Assigning...' : `Assign to ${selectedCount} ${selectedCount === 1 ? 'person' : 'people'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function PeopleList() {
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [showFavoritesOnly, setShowFavoritesOnly] = useState(false);
  const [selectedLabels, setSelectedLabels] = useState([]);
  const [selectedBirthYear, setSelectedBirthYear] = useState('');
  const [lastModifiedFilter, setLastModifiedFilter] = useState('');
  const [ownershipFilter, setOwnershipFilter] = useState('all'); // 'all', 'mine', 'shared'
  const [selectedWorkspaceFilter, setSelectedWorkspaceFilter] = useState('');
  const [sortField, setSortField] = useState('first_name'); // 'first_name' or 'last_name'
  const [sortOrder, setSortOrder] = useState('asc'); // 'asc' or 'desc'
  const [viewMode, setViewMode] = useState(() => {
    return localStorage.getItem('peopleViewMode') || 'card';
  });
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkVisibilityModal, setShowBulkVisibilityModal] = useState(false);
  const [showBulkWorkspaceModal, setShowBulkWorkspaceModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const navigate = useNavigate();

  const { data: people, isLoading, error } = usePeople();
  const { data: workspaces = [] } = useWorkspaces();
  const bulkUpdateMutation = useBulkUpdatePeople();

  // Get current user ID from prmConfig
  const currentUserId = window.prmConfig?.userId;
  
  // Create person mutation (using shared hook)
  const createPersonMutation = useCreatePerson({
    onSuccess: (result) => {
      setShowPersonModal(false);
      navigate(`/people/${result.id}`);
    },
  });
  
  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };
  
  // Fetch person labels
  const { data: labelsData } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });
  
  const availableLabels = labelsData?.map(label => label.name) || [];
  
  // Get available birth years from people data
  const availableBirthYears = useMemo(() => {
    if (!people) return [];
    const years = people
      .map(p => p.birth_year)
      .filter(year => year !== null && year !== undefined);
    // Sort descending (most recent first)
    return [...new Set(years)].sort((a, b) => b - a);
  }, [people]);
  
  // Persist view mode to localStorage
  useEffect(() => {
    localStorage.setItem('peopleViewMode', viewMode);
  }, [viewMode]);

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
  
  // Filter and sort people
  const filteredAndSortedPeople = useMemo(() => {
    if (!people) return [];
    
    let filtered = [...people];
    
    // Apply favorites filter
    if (showFavoritesOnly) {
      filtered = filtered.filter(person => person.is_favorite);
    }
    
    // Apply label filters
    if (selectedLabels.length > 0) {
      filtered = filtered.filter(person => {
        const personLabels = person.labels || [];
        return selectedLabels.some(label => personLabels.includes(label));
      });
    }
    
    // Apply birth year filter
    if (selectedBirthYear) {
      const year = parseInt(selectedBirthYear, 10);
      filtered = filtered.filter(person => person.birth_year === year);
    }
    
    // Apply last modified filter
    if (lastModifiedFilter) {
      const now = new Date();
      let cutoffDate;

      switch (lastModifiedFilter) {
        case '7':
          cutoffDate = new Date(now.setDate(now.getDate() - 7));
          break;
        case '30':
          cutoffDate = new Date(now.setDate(now.getDate() - 30));
          break;
        case '90':
          cutoffDate = new Date(now.setDate(now.getDate() - 90));
          break;
        case '365':
          cutoffDate = new Date(now.setDate(now.getDate() - 365));
          break;
        default:
          cutoffDate = null;
      }

      if (cutoffDate) {
        filtered = filtered.filter(person => {
          if (!person.modified) return false;
          const modifiedDate = new Date(person.modified);
          return modifiedDate >= cutoffDate;
        });
      }
    }

    // Apply ownership filter
    if (ownershipFilter === 'mine') {
      filtered = filtered.filter(person => person.author === currentUserId);
    } else if (ownershipFilter === 'shared') {
      filtered = filtered.filter(person => person.author !== currentUserId);
    }

    // Apply workspace filter
    if (selectedWorkspaceFilter) {
      filtered = filtered.filter(person => {
        const assignedWorkspaces = person.acf?._assigned_workspaces || [];
        return assignedWorkspaces.includes(parseInt(selectedWorkspaceFilter));
      });
    }

    return filtered;
  }, [people, showFavoritesOnly, selectedLabels, selectedBirthYear, lastModifiedFilter, ownershipFilter, selectedWorkspaceFilter, currentUserId]);

  const hasActiveFilters = showFavoritesOnly || selectedLabels.length > 0 || selectedBirthYear || lastModifiedFilter || ownershipFilter !== 'all' || selectedWorkspaceFilter;
  
  const handleLabelToggle = (label) => {
    setSelectedLabels(prev => 
      prev.includes(label)
        ? prev.filter(l => l !== label)
        : [...prev, label]
    );
  };
  
  const clearFilters = () => {
    setShowFavoritesOnly(false);
    setSelectedLabels([]);
    setSelectedBirthYear('');
    setLastModifiedFilter('');
    setOwnershipFilter('all');
    setSelectedWorkspaceFilter('');
  };

  // Selection helper functions
  const toggleSelection = (personId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(personId)) {
        next.delete(personId);
      } else {
        next.add(personId);
      }
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === filteredAndSortedPeople.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(filteredAndSortedPeople.map(p => p.id)));
    }
  };

  const clearSelection = () => setSelectedIds(new Set());

  const isAllSelected = filteredAndSortedPeople.length > 0 &&
    selectedIds.size === filteredAndSortedPeople.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < filteredAndSortedPeople.length;

  // Clear selection when filters change or data changes
  useEffect(() => {
    setSelectedIds(new Set());
  }, [showFavoritesOnly, selectedLabels, selectedBirthYear, lastModifiedFilter, ownershipFilter, selectedWorkspaceFilter, people]);

  // Collect all company IDs
  const companyIds = useMemo(() => {
    if (!filteredAndSortedPeople) return [];
    const ids = filteredAndSortedPeople
      .map(person => getCurrentCompanyId(person))
      .filter(Boolean);
    // Remove duplicates
    return [...new Set(ids)];
  }, [filteredAndSortedPeople]);

  // Batch fetch all companies at once instead of individual queries
  const { data: companiesData } = useQuery({
    queryKey: ['companies', 'batch', companyIds.sort().join(',')],
    queryFn: async () => {
      if (companyIds.length === 0) return [];
      // Fetch all companies in one request
      const response = await wpApi.getCompanies({ 
        per_page: 100,
        include: companyIds.join(','),
      });
      return response.data;
    },
    enabled: companyIds.length > 0,
  });

  // Create a map of company ID to company name
  const companyMap = useMemo(() => {
    const map = {};
    if (companiesData) {
      companiesData.forEach(company => {
        map[company.id] = getCompanyName(company);
      });
    }
    return map;
  }, [companiesData]);

  // Create a map of person ID to company name
  const personCompanyMap = useMemo(() => {
    const map = {};
    filteredAndSortedPeople.forEach(person => {
      const companyId = getCurrentCompanyId(person);
      if (companyId && companyMap[companyId]) {
        map[person.id] = companyMap[companyId];
      }
    });
    return map;
  }, [filteredAndSortedPeople, companyMap]);

  // Helper to get workspace names for a person (used in sorting)
  const getPersonWorkspaceNames = (person) => {
    const assignedWorkspaces = person.acf?._assigned_workspaces || [];
    return assignedWorkspaces
      .map(wsId => {
        const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
        const found = workspaces.find(ws => ws.id === numId);
        return found?.title;
      })
      .filter(Boolean)
      .join(', ');
  };

  // Sort the filtered people
  const sortedPeople = useMemo(() => {
    if (!filteredAndSortedPeople) return [];

    return [...filteredAndSortedPeople].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'modified') {
        // Sort by last modified date
        valueA = a.modified ? new Date(a.modified).getTime() : 0;
        valueB = b.modified ? new Date(b.modified).getTime() : 0;
        const comparison = valueA - valueB;
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'organization') {
        // Sort by company name (from personCompanyMap)
        valueA = (personCompanyMap[a.id] || '').toLowerCase();
        valueB = (personCompanyMap[b.id] || '').toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'workspace') {
        // Sort by workspace names
        valueA = getPersonWorkspaceNames(a).toLowerCase();
        valueB = getPersonWorkspaceNames(b).toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'labels') {
        // Sort by first label name
        valueA = (a.labels?.[0] || '').toLowerCase();
        valueB = (b.labels?.[0] || '').toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      // Default: first_name or last_name
      if (sortField === 'first_name') {
        valueA = (a.acf?.first_name || a.first_name || '').toLowerCase();
        valueB = (b.acf?.first_name || b.first_name || '').toLowerCase();
      } else {
        valueA = (a.acf?.last_name || a.last_name || '').toLowerCase();
        valueB = (b.acf?.last_name || b.last_name || '').toLowerCase();
      }

      // If values are equal, sort by the other field as tiebreaker
      if (valueA === valueB) {
        const tiebreakerA = sortField === 'first_name'
          ? (a.acf?.last_name || a.last_name || '').toLowerCase()
          : (a.acf?.first_name || a.first_name || '').toLowerCase();
        const tiebreakerB = sortField === 'first_name'
          ? (b.acf?.last_name || b.last_name || '').toLowerCase()
          : (b.acf?.first_name || b.first_name || '').toLowerCase();

        const tiebreakerResult = tiebreakerA.localeCompare(tiebreakerB);
        return sortOrder === 'asc' ? tiebreakerResult : -tiebreakerResult;
      }

      const comparison = valueA.localeCompare(valueB);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredAndSortedPeople, sortField, sortOrder, personCompanyMap, workspaces]);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="first_name">First name</option>
              <option value="last_name">Last name</option>
              <option value="modified">Last modified</option>
              <option value="organization">Organization</option>
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

          {/* View Mode Toggle */}
          <div className="flex items-center border border-gray-200 rounded-lg p-1">
            <button
              onClick={() => setViewMode('card')}
              className={`p-1.5 rounded transition-colors ${viewMode === 'card' ? 'bg-gray-100' : 'hover:bg-gray-50'}`}
              title="Card view"
            >
              <LayoutGrid className={`w-4 h-4 ${viewMode === 'card' ? 'text-primary-600' : 'text-gray-600'}`} />
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`p-1.5 rounded transition-colors ${viewMode === 'list' ? 'bg-gray-100' : 'hover:bg-gray-50'}`}
              title="List view"
            >
              <List className={`w-4 h-4 ${viewMode === 'list' ? 'text-primary-600' : 'text-gray-600'}`} />
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
                  {selectedLabels.length + (showFavoritesOnly ? 1 : 0) + (selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0)}
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
                  {/* Favorites Filter */}
                  <div>
                    <label className="flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={showFavoritesOnly}
                        onChange={(e) => setShowFavoritesOnly(e.target.checked)}
                        className="sr-only"
                      />
                      <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                        showFavoritesOnly 
                          ? 'bg-primary-600 border-primary-600' 
                          : 'border-gray-300'
                      }`}>
                        {showFavoritesOnly && (
                          <Check className="w-3 h-3 text-white" />
                        )}
                      </div>
                      <div className="flex items-center">
                        <Star className="w-4 h-4 mr-2 text-yellow-400 fill-current" />
                        <span className="text-sm font-medium text-gray-900">Favorites only</span>
                      </div>
                    </label>
                  </div>
                  
                  {/* Labels Filter */}
                  {availableLabels.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Labels
                      </h3>
                      <div className="space-y-1 max-h-48 overflow-y-auto">
                        {availableLabels.map(label => (
                          <label
                            key={label}
                            className="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={selectedLabels.includes(label)}
                              onChange={() => handleLabelToggle(label)}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                              selectedLabels.includes(label)
                                ? 'bg-primary-600 border-primary-600'
                                : 'border-gray-300'
                            }`}>
                              {selectedLabels.includes(label) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700">{label}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}
                  
                  {/* Birth Year Filter */}
                  {availableBirthYears.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Birth year
                      </h3>
                      <select
                        value={selectedBirthYear}
                        onChange={(e) => setSelectedBirthYear(e.target.value)}
                        className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary-500 focus:border-primary-500"
                      >
                        <option value="">All years</option>
                        {availableBirthYears.map(year => (
                          <option key={year} value={year}>{year}</option>
                        ))}
                      </select>
                    </div>
                  )}
                  
                  {/* Last Modified Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      Last modified
                    </h3>
                    <select
                      value={lastModifiedFilter}
                      onChange={(e) => setLastModifiedFilter(e.target.value)}
                      className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                      <option value="">Any time</option>
                      <option value="7">Last 7 days</option>
                      <option value="30">Last 30 days</option>
                      <option value="90">Last 90 days</option>
                      <option value="365">Last year</option>
                    </select>
                  </div>

                  {/* Ownership Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      Ownership
                    </h3>
                    <div className="space-y-1">
                      {[
                        { value: 'all', label: 'All Contacts' },
                        { value: 'mine', label: 'My Contacts' },
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
              {showFavoritesOnly && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs">
                  <Star className="w-3 h-3" />
                  Favorites
                  <button
                    onClick={() => setShowFavoritesOnly(false)}
                    className="hover:text-primary-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {selectedLabels.map(label => (
                <span
                  key={label}
                  className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs"
                >
                  {label}
                  <button
                    onClick={() => handleLabelToggle(label)}
                    className="hover:text-gray-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
              {selectedBirthYear && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                  Born {selectedBirthYear}
                  <button
                    onClick={() => setSelectedBirthYear('')}
                    className="hover:text-gray-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {lastModifiedFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                  Modified: {lastModifiedFilter === '7' ? 'Last 7 days' :
                             lastModifiedFilter === '30' ? 'Last 30 days' :
                             lastModifiedFilter === '90' ? 'Last 90 days' : 'Last year'}
                  <button
                    onClick={() => setLastModifiedFilter('')}
                    className="hover:text-gray-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {ownershipFilter !== 'all' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs">
                  {ownershipFilter === 'mine' ? 'My Contacts' : 'Shared with Me'}
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
          
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Add person</span>
          </button>
        </div>
      </div>
      
      {/* Loading state */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>
      )}
      
      {/* Error state */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600">Failed to load people.</p>
        </div>
      )}
      
      {/* Empty state */}
      {!isLoading && !error && people?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Plus className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No people found</h3>
          <p className="text-gray-500 mb-4">
            Get started by adding your first person.
          </p>
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Add person</span>
          </button>
        </div>
      )}
      
      {/* People grid (card view) */}
      {!isLoading && !error && sortedPeople?.length > 0 && viewMode === 'card' && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {sortedPeople.map((person) => (
            <PersonCard
              key={person.id}
              person={person}
              companyName={personCompanyMap[person.id]}
              isDeceased={person.is_deceased}
            />
          ))}
        </div>
      )}

      {/* Selection toolbar (list view only) */}
      {viewMode === 'list' && selectedIds.size > 0 && (
        <div className="flex items-center justify-between bg-primary-50 border border-primary-200 rounded-lg px-4 py-2">
          <span className="text-sm text-primary-800 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'person' : 'people'} selected
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-primary-700 bg-white border border-primary-300 rounded-md hover:bg-primary-50"
              >
                Actions
                <ChevronDown className={`w-4 h-4 transition-transform ${showBulkDropdown ? 'rotate-180' : ''}`} />
              </button>
              {showBulkDropdown && (
                <div className="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                  <div className="py-1">
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkVisibilityModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                      <Lock className="w-4 h-4" />
                      Change visibility...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkWorkspaceModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
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
              className="text-sm text-primary-600 hover:text-primary-800 font-medium"
            >
              Clear selection
            </button>
          </div>
        </div>
      )}

      {/* People list (list view) */}
      {!isLoading && !error && sortedPeople?.length > 0 && viewMode === 'list' && (
        <PersonListView
          people={sortedPeople}
          companyMap={personCompanyMap}
          workspaces={workspaces}
          selectedIds={selectedIds}
          onToggleSelection={toggleSelection}
          onToggleSelectAll={toggleSelectAll}
          isAllSelected={isAllSelected}
          isSomeSelected={isSomeSelected}
        />
      )}
      
      {/* No results with filters */}
      {!isLoading && !error && people?.length > 0 && sortedPeople?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No people match your filters</h3>
          <p className="text-gray-500 mb-4">
            Try adjusting your filters to see more results.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Clear filters
          </button>
        </div>
      )}
      
      {/* Person Modal */}
      <PersonEditModal
        isOpen={showPersonModal}
        onClose={() => setShowPersonModal(false)}
        onSubmit={handleCreatePerson}
        isLoading={isCreatingPerson}
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
