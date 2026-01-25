import { useEffect, useState, useMemo, useRef } from 'react';
import { useForm } from 'react-hook-form';
import { X, ChevronDown, Building2, Search, User, TrendingUp } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { getTeamName, decodeHtml } from '@/utils/formatters';
import VisibilitySelector from '@/components/VisibilitySelector';

export default function TeamEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading,
  team = null // Pass team data for editing
}) {
  const isEditing = !!team;
  
  // State for parent team dropdown
  const [isParentDropdownOpen, setIsParentDropdownOpen] = useState(false);
  const [parentSearchQuery, setParentSearchQuery] = useState('');
  const [selectedParentId, setSelectedParentId] = useState('');
  
  // State for investors dropdown
  const [isInvestorsDropdownOpen, setIsInvestorsDropdownOpen] = useState(false);
  const [investorsSearchQuery, setInvestorsSearchQuery] = useState('');
  const [debouncedInvestorsQuery, setDebouncedInvestorsQuery] = useState('');
  const [selectedInvestors, setSelectedInvestors] = useState([]);

  // Visibility state
  const [visibility, setVisibility] = useState('private');
  const [selectedWorkspaces, setSelectedWorkspaces] = useState([]);

  const parentDropdownRef = useRef(null);
  const investorsDropdownRef = useRef(null);
  
  // Fetch all teams for parent selection and investors
  const { data: allTeams = [], isLoading: isLoadingTeams } = useQuery({
    queryKey: ['teams', 'all'],
    queryFn: async () => {
      const response = await wpApi.getTeams({ per_page: 100, _embed: true });
      return response.data;
    },
    enabled: isOpen,
  });
  
  // Fetch all people for investors (used for loading existing investors)
  const { data: allPeople = [], isLoading: isLoadingPeople } = useQuery({
    queryKey: ['people', 'all'],
    queryFn: async () => {
      const response = await wpApi.getPeople({ per_page: 100, _embed: true });
      return response.data;
    },
    enabled: isOpen,
  });

  // Debounce investor search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedInvestorsQuery(investorsSearchQuery.trim());
    }, 300);
    return () => clearTimeout(timer);
  }, [investorsSearchQuery]);

  // Server-side search for investors when query length >= 2
  const { data: searchResults, isLoading: isSearching } = useQuery({
    queryKey: ['investor-search', debouncedInvestorsQuery],
    queryFn: async () => {
      const response = await prmApi.search(debouncedInvestorsQuery);
      return response.data;
    },
    enabled: isOpen && debouncedInvestorsQuery.length >= 2,
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      website: '',
    },
  });

  // Filter teams for parent dropdown (exclude self and children)
  const availableParentTeams = useMemo(() => {
    const query = parentSearchQuery.toLowerCase().trim();
    let filtered = allTeams.filter(c => {
      // Exclude self
      if (isEditing && c.id === team?.id) return false;
      // Exclude teams that have this team as parent (prevents circular references)
      if (isEditing && c.parent === team?.id) return false;
      return true;
    });
    
    if (query) {
      filtered = filtered.filter(c => 
        getTeamName(c)?.toLowerCase().includes(query)
      );
    }
    
    // Sort alphabetically
    return [...filtered].sort((a, b) => 
      (getTeamName(a) || '').localeCompare(getTeamName(b) || '')
    );
  }, [allTeams, parentSearchQuery, team, isEditing]);
  
  // Get selected parent team details
  const selectedParent = useMemo(() => 
    allTeams.find(c => c.id === parseInt(selectedParentId)),
    [allTeams, selectedParentId]
  );
  
  // Combined list of people and teams for investor selection (excluding self)
  const availableInvestors = useMemo(() => {
    const query = investorsSearchQuery.toLowerCase().trim();
    let combined = [];

    // Use server-side search results when available (query length >= 2)
    if (query.length >= 2 && searchResults) {
      // Map search results to common format
      const people = (searchResults.people || []).map(p => ({
        id: p.id,
        type: 'person',
        name: p.name || '',
        thumbnail: p.thumbnail,
      }));

      const teams = (searchResults.teams || [])
        .filter(c => !isEditing || c.id !== team?.id)
        .map(c => ({
          id: c.id,
          type: 'team',
          name: c.name || '',
          thumbnail: c.thumbnail,
        }));

      combined = [...people, ...teams];
    } else if (query.length < 2) {
      // For short queries or no query, use client-side data (first 100 of each)
      const people = allPeople.map(p => ({
        id: p.id,
        type: 'person',
        name: decodeHtml(p.title?.rendered || ''),
        thumbnail: p._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));

      const teams = allTeams
        .filter(c => !isEditing || c.id !== team?.id)
        .map(c => ({
          id: c.id,
          type: 'team',
          name: getTeamName(c),
          thumbnail: c._embedded?.['wp:featuredmedia']?.[0]?.source_url,
        }));

      combined = [...people, ...teams];

      // Filter client-side for short queries (1 character)
      if (query.length === 1) {
        combined = combined.filter(item =>
          item.name?.toLowerCase().includes(query)
        );
      }
    }

    // Filter out already selected investors
    const selectedKeys = selectedInvestors.map(inv => `${inv.type}-${inv.id}`);
    combined = combined.filter(item => !selectedKeys.includes(`${item.type}-${item.id}`));

    // Sort alphabetically
    return combined.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  }, [allPeople, allTeams, investorsSearchQuery, searchResults, selectedInvestors, team, isEditing]);

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      // Reset dropdowns
      setIsParentDropdownOpen(false);
      setIsInvestorsDropdownOpen(false);
      setParentSearchQuery('');
      setInvestorsSearchQuery('');

      if (team) {
        // Editing - populate with existing data
        reset({
          title: decodeHtml(team.title?.rendered || ''),
          website: team.acf?.website || '',
        });
        // Set parent team if exists
        setSelectedParentId(team.parent ? String(team.parent) : '');
        // Investors will be loaded via separate effect
        // Load existing visibility settings
        setVisibility(team.acf?._visibility || 'private');
        setSelectedWorkspaces(team.acf?._assigned_workspaces || []);
      } else {
        // Creating - reset to defaults
        reset({
          title: '',
          website: '',
        });
        setSelectedParentId('');
        setSelectedInvestors([]);
        // Reset visibility to private
        setVisibility('private');
        setSelectedWorkspaces([]);
      }
    }
  }, [isOpen, team, reset]);
  
  // Fetch existing investors directly by their IDs (not limited to first 100)
  // Include IDs in query key to ensure refetch when investors change
  const investorIds = team?.acf?.investors || [];
  const investorIdsKey = investorIds.join(',');
  const { data: existingInvestors = [], isSuccess: investorsLoaded } = useQuery({
    queryKey: ['team-investors-edit', team?.id, investorIdsKey],
    queryFn: async ({ queryKey }) => {
      // Extract IDs from query key to avoid closure issues
      const idsString = queryKey[2];
      const ids = idsString ? idsString.split(',').map(Number) : [];
      if (!ids.length) return [];

      // Fetch people and teams by specific IDs
      const [peopleRes, teamsRes] = await Promise.all([
        wpApi.getPeople({ per_page: 100, include: idsString, _embed: true }),
        wpApi.getTeams({ per_page: 100, include: idsString, _embed: true }),
      ]);

      const people = (peopleRes.data || []).map(p => ({
        id: p.id,
        type: 'person',
        name: decodeHtml(p.title?.rendered || ''),
        thumbnail: p._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));

      const teams = (teamsRes.data || []).map(c => ({
        id: c.id,
        type: 'team',
        name: getTeamName(c),
        thumbnail: c._embedded?.['wp:featuredmedia']?.[0]?.source_url,
      }));

      // Combine and sort by original order
      const all = [...people, ...teams];
      return ids.map(iid => all.find(i => i.id === iid)).filter(Boolean);
    },
    enabled: isOpen && !!team?.id && investorIds.length > 0,
    staleTime: 0, // Always refetch when modal opens
  });

  // Load existing investors when fetched, or reset when creating new
  useEffect(() => {
    if (isOpen && team && investorsLoaded) {
      setSelectedInvestors(existingInvestors);
    } else if (isOpen && team && investorIds.length === 0) {
      // Team has no investors
      setSelectedInvestors([]);
    } else if (isOpen && !team) {
      // Creating new team
      setSelectedInvestors([]);
    }
  }, [isOpen, team, investorsLoaded, existingInvestors, investorIds.length]);
  
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

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      ...data,
      parentId: selectedParentId ? parseInt(selectedParentId) : 0,
      investors: selectedInvestors.map(inv => inv.id),
      visibility,
      assigned_workspaces: selectedWorkspaces,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Edit organization' : 'Add organization'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Organization name */}
            <div>
              <label className="label">Organization name *</label>
              <input
                {...register('title', { required: 'Organization name is required' })}
                className="input"
                placeholder="Acme Inc."
                disabled={isLoading}
                autoFocus
              />
              {errors.title && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.title.message}</p>
              )}
            </div>

            {/* Website */}
            <div>
              <label className="label">Website</label>
              <input
                {...register('website')}
                type="url"
                className="input"
                placeholder="https://example.com"
                disabled={isLoading}
              />
            </div>

            {/* Parent team selection */}
            <div>
              <label className="label">Parent organization</label>
              <div className="relative" ref={parentDropdownRef}>
                <button
                  type="button"
                  onClick={() => setIsParentDropdownOpen(!isParentDropdownOpen)}
                  className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-left focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                  disabled={isLoadingTeams || isLoading}
                >
                  {selectedParent ? (
                    <div className="flex items-center gap-2">
                      {selectedParent._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                        <img
                          src={selectedParent._embedded['wp:featuredmedia'][0].source_url}
                          alt={getTeamName(selectedParent)}
                          className="w-6 h-6 rounded object-contain dark:bg-gray-600"
                        />
                      ) : (
                        <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                          <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        </div>
                      )}
                      <span className="text-gray-900 dark:text-gray-50">{getTeamName(selectedParent)}</span>
                    </div>
                  ) : (
                    <span className="text-gray-400 dark:text-gray-500">No parent organization</span>
                  )}
                  <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isParentDropdownOpen ? 'rotate-180' : ''}`} />
                </button>
                
                {isParentDropdownOpen && (
                  <div className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-hidden">
                    {/* Search input */}
                    <div className="p-2 border-b border-gray-100 dark:border-gray-700">
                      <div className="relative">
                        <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                          type="text"
                          value={parentSearchQuery}
                          onChange={(e) => setParentSearchQuery(e.target.value)}
                          placeholder="Search organizations..."
                          className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-1 focus:ring-accent-500"
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
                        className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${
                          !selectedParentId ? 'bg-accent-50 dark:bg-accent-800' : ''
                        }`}
                      >
                        <span className="text-sm text-gray-500 dark:text-gray-400 italic">No parent organization</span>
                      </button>

                      {/* Teams list */}
                      {isLoadingTeams ? (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          Loading...
                        </div>
                      ) : availableParentTeams.length > 0 ? (
                        availableParentTeams.map((c) => (
                          <button
                            key={c.id}
                            type="button"
                            onClick={() => {
                              setSelectedParentId(String(c.id));
                              setIsParentDropdownOpen(false);
                              setParentSearchQuery('');
                            }}
                            className={`w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${
                              selectedParentId === String(c.id) ? 'bg-accent-50 dark:bg-accent-800' : ''
                            }`}
                          >
                            {c._embedded?.['wp:featuredmedia']?.[0]?.source_url ? (
                              <img
                                src={c._embedded['wp:featuredmedia'][0].source_url}
                                alt={getTeamName(c)}
                                className="w-6 h-6 rounded object-contain"
                              />
                            ) : (
                              <div className="w-6 h-6 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                              </div>
                            )}
                            <span className="text-sm text-gray-900 dark:text-gray-50 truncate">{getTeamName(c)}</span>
                          </button>
                        ))
                      ) : (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          No organizations found
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                      className="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-700 rounded-full pl-1 pr-2 py-1"
                    >
                      {investor.thumbnail ? (
                        <img
                          src={investor.thumbnail}
                          alt={investor.name}
                          className={`w-5 h-5 object-cover ${investor.type === 'person' ? 'rounded-full' : 'rounded'}`}
                        />
                      ) : (
                        <div className={`w-5 h-5 bg-gray-300 dark:bg-gray-600 flex items-center justify-center ${investor.type === 'person' ? 'rounded-full' : 'rounded'}`}>
                          {investor.type === 'person' ? (
                            <User className="w-3 h-3 text-gray-500 dark:text-gray-400" />
                          ) : (
                            <Building2 className="w-3 h-3 text-gray-500 dark:text-gray-400" />
                          )}
                        </div>
                      )}
                      <span className="text-sm text-gray-700 dark:text-gray-200">{investor.name}</span>
                      <button
                        type="button"
                        onClick={() => setSelectedInvestors(prev =>
                          prev.filter(inv => !(inv.id === investor.id && inv.type === investor.type))
                        )}
                        className="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        disabled={isLoading}
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
                  className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-left focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                  disabled={isLoadingTeams || isLoadingPeople || isLoading}
                >
                  <span className="text-gray-400 dark:text-gray-500">Add investor...</span>
                  <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isInvestorsDropdownOpen ? 'rotate-180' : ''}`} />
                </button>

                {isInvestorsDropdownOpen && (
                  <div className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-hidden">
                    {/* Search input */}
                    <div className="p-2 border-b border-gray-100 dark:border-gray-700">
                      <div className="relative">
                        <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                          type="text"
                          value={investorsSearchQuery}
                          onChange={(e) => setInvestorsSearchQuery(e.target.value)}
                          placeholder="Search people and organizations..."
                          className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-1 focus:ring-accent-500"
                          autoFocus
                        />
                      </div>
                    </div>

                    <div className="overflow-y-auto max-h-48">
                      {(isLoadingTeams || isLoadingPeople || isSearching) ? (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          {isSearching ? 'Searching...' : 'Loading...'}
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
                            className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                          >
                            {item.thumbnail ? (
                              <img
                                src={item.thumbnail}
                                alt={item.name}
                                className={`w-6 h-6 object-cover ${item.type === 'person' ? 'rounded-full' : 'rounded'}`}
                              />
                            ) : (
                              <div className={`w-6 h-6 bg-gray-200 dark:bg-gray-600 flex items-center justify-center ${item.type === 'person' ? 'rounded-full' : 'rounded'}`}>
                                {item.type === 'person' ? (
                                  <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                ) : (
                                  <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                )}
                              </div>
                            )}
                            <div className="flex-1 min-w-0">
                              <span className="text-sm text-gray-900 dark:text-gray-50 truncate block">{item.name}</span>
                              <span className="text-xs text-gray-500 dark:text-gray-400">
                                {item.type === 'person' ? 'Person' : 'Organization'}
                              </span>
                            </div>
                          </button>
                        ))
                      ) : (
                        <div className="p-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                          {investorsSearchQuery.length >= 2
                            ? 'No results found'
                            : 'No people or organizations available'}
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Select people or organizations that have invested in this team
              </p>
            </div>

            {/* Visibility */}
            <VisibilitySelector
              value={visibility}
              workspaces={selectedWorkspaces}
              onChange={({ visibility: v, workspaces: w }) => {
                setVisibility(v);
                setSelectedWorkspaces(w);
              }}
              disabled={isLoading}
            />
          </div>

          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading}
            >
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Create organization')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
