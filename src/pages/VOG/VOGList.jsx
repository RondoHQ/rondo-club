import { useState, useMemo, useCallback, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUp, ArrowDown, CheckCircle, Mail, RefreshCw, Square, CheckSquare, MinusSquare, ChevronDown, X, Filter, Check, FileSpreadsheet } from 'lucide-react';
import { useFilteredPeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import { format } from '@/utils/dateFormat';
import { formatPhoneForTel } from '@/utils/formatters';

// Helper function to get first contact value by type
function getFirstContactByType(person, type) {
  const contactInfo = person.acf?.contact_info || [];
  const contact = contactInfo.find(c => c.contact_type === type);
  return contact?.contact_value || null;
}

// Helper function to get first phone (includes mobile)
function getFirstPhone(person) {
  const contactInfo = person.acf?.contact_info || [];
  const contact = contactInfo.find(c => c.contact_type === 'phone' || c.contact_type === 'mobile');
  return contact?.contact_value || null;
}

// VOG Badge component - determines badge type based on datum-vog presence
function VOGBadge({ person }) {
  const datumVog = person.acf?.['datum-vog'];

  // No VOG = new volunteer (blue badge)
  const isNew = !datumVog;

  return (
    <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${
      isNew
        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
    }`}>
      {isNew ? 'Nieuw' : 'Vernieuwing'}
    </span>
  );
}

// VOG Email Sent Indicator
function VOGEmailIndicator({ person }) {
  const emailSentDate = person.acf?.['vog-email-verzonden'];

  if (!emailSentDate) return null;

  return (
    <span
      className="inline-flex items-center text-gray-400 dark:text-gray-500 ml-2"
      title={`VOG email verzonden op ${emailSentDate}`}
    >
      <Mail className="w-4 h-4" />
    </span>
  );
}

// Sortable header component
function SortableHeader({ label, columnId, sortField, sortOrder, onSort, sortable = true }) {
  if (!sortable) {
    return (
      <th
        scope="col"
        className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800"
      >
        {label}
      </th>
    );
  }

  const isActive = sortField === columnId;
  const nextOrder = isActive && sortOrder === 'asc' ? 'desc' : 'asc';

  return (
    <th
      scope="col"
      className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
      onClick={() => onSort(columnId, nextOrder)}
    >
      <div className="flex items-center gap-1">
        {label}
        {isActive && (
          sortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </div>
    </th>
  );
}

// Empty state component with success message
function VOGEmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-3 bg-green-100 rounded-full dark:bg-green-900/30">
          <CheckCircle className="w-8 h-8 text-green-600 dark:text-green-400" />
        </div>
      </div>
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
        Alle vrijwilligers hebben een geldige VOG
      </h3>
      <p className="text-sm text-gray-600 dark:text-gray-400">
        Er zijn momenteel geen vrijwilligers die een nieuwe of vernieuwde VOG nodig hebben.
      </p>
    </div>
  );
}

// VOG row component
function VOGRow({ person, customFieldsMap, isOdd, isSelected, onToggleSelection }) {
  const email = getFirstContactByType(person, 'email');
  const phone = getFirstPhone(person);

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isSelected
        ? 'bg-cyan-50 dark:bg-obsidian/30'
        : isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    }`}>
      {/* Checkbox column */}
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
          className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      {/* Name with badge */}
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/people/${person.id}`} className="flex items-center gap-2">
          <span className={`text-sm font-medium ${
            isSelected
              ? 'text-gray-900 dark:text-white'
              : 'text-gray-900 dark:text-gray-50'
          }`}>
            {[person.first_name, person.infix, person.last_name].filter(Boolean).join(' ')}
          </span>
          <VOGBadge person={person} />
          <VOGEmailIndicator person={person} />
        </Link>
      </td>

      {/* KNVB ID */}
      <td className={`px-4 py-3 text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {customFieldsMap['knvb-id'] ? (
          <CustomFieldColumn
            field={customFieldsMap['knvb-id']}
            value={person.acf?.['knvb-id']}
          />
        ) : (
          person.acf?.['knvb-id'] || '-'
        )}
      </td>

      {/* Email */}
      <td className={`px-4 py-3 whitespace-nowrap text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {email ? (
          <a href={`mailto:${email}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
            {email}
          </a>
        ) : '-'}
      </td>

      {/* Phone */}
      <td className={`px-4 py-3 whitespace-nowrap text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {phone ? (
          <a href={`tel:${formatPhoneForTel(phone)}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
            {phone}
          </a>
        ) : '-'}
      </td>

      {/* Datum VOG */}
      <td className={`px-4 py-3 text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {customFieldsMap['datum-vog'] ? (
          <CustomFieldColumn
            field={customFieldsMap['datum-vog']}
            value={person.acf?.['datum-vog']}
          />
        ) : (
          person.acf?.['datum-vog'] || '-'
        )}
      </td>

      {/* Verzonden date */}
      <td className={`px-4 py-3 text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {person.acf?.['vog_email_sent_date']
          ? format(new Date(person.acf['vog_email_sent_date']), 'yyyy-MM-dd')
          : '-'}
      </td>

      {/* Justis date */}
      <td className={`px-4 py-3 text-sm ${
        isSelected
          ? 'text-gray-700 dark:text-gray-100'
          : 'text-gray-500 dark:text-gray-400'
      }`}>
        {person.acf?.['vog_justis_submitted_date']
          ? format(new Date(person.acf['vog_justis_submitted_date']), 'yyyy-MM-dd')
          : '-'}
      </td>
    </tr>
  );
}

export default function VOGList() {
  // Sort state
  const [orderby, setOrderby] = useState('custom_datum-vog');
  const [order, setOrder] = useState('asc');

  // Filter state
  const [emailStatusFilter, setEmailStatusFilter] = useState('');
  const [vogTypeFilter, setVogTypeFilter] = useState('');
  const [justisStatusFilter, setJustisStatusFilter] = useState('');
  const [isFilterOpen, setIsFilterOpen] = useState(false);

  const queryClient = useQueryClient();

  // Selection state
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const bulkDropdownRef = useRef(null);
  const filterRef = useRef(null);
  const filterDropdownRef = useRef(null);

  // Google Sheets export state
  const [isExporting, setIsExporting] = useState(false);

  // Modal state
  const [showSendEmailModal, setShowSendEmailModal] = useState(false);
  const [showMarkRequestedModal, setShowMarkRequestedModal] = useState(false);
  const [showMarkJustisModal, setShowMarkJustisModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [bulkActionResult, setBulkActionResult] = useState(null);

  // Fetch filtered people with VOG-specific filters
  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
    vogEmailStatus: emailStatusFilter,
    vogType: vogTypeFilter,
    vogJustisStatus: justisStatusFilter,
    orderby,
    order,
  });

  // Fetch all data for counts (without email filter)
  const { data: allData } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
    orderby,
    order,
    // Note: no vogEmailStatus - fetches all to calculate counts
  });

  // Extract data from response - memoize to avoid effect dependency issues
  const people = useMemo(() => data?.people || [], [data?.people]);
  const totalPeople = data?.total || 0;

  // Calculate counts from allData for filter dropdown
  const emailCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const sent = allPeople.filter(p => p.acf?.['vog_email_sent_date']).length;
    const notSent = allPeople.length - sent;
    return { total: allPeople.length, sent, notSent };
  }, [allData?.people]);

  const vogTypeCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const nieuw = allPeople.filter(p => !p.acf?.['datum-vog']).length;
    const vernieuwing = allPeople.length - nieuw;
    return { total: allPeople.length, nieuw, vernieuwing };
  }, [allData?.people]);

  const justisCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const submitted = allPeople.filter(p => p.acf?.['vog_justis_submitted_date']).length;
    const notSubmitted = allPeople.length - submitted;
    return { total: allPeople.length, submitted, notSubmitted };
  }, [allData?.people]);

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('person');
      return response.data;
    },
  });

  // Google Sheets connection status
  const { data: sheetsStatus } = useQuery({
    queryKey: ['google-sheets-status'],
    queryFn: async () => {
      const response = await prmApi.getSheetsStatus();
      return response.data;
    },
  });

  // Create map of custom field name to field definition
  const customFieldsMap = useMemo(() => {
    const map = {};
    customFields.forEach(field => {
      map[field.name] = field;
    });
    return map;
  }, [customFields]);

  // Selection helpers
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
    if (selectedIds.size === people.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(people.map(p => p.id)));
    }
  };

  const clearSelection = () => setSelectedIds(new Set());

  // Derived state
  const isAllSelected = people.length > 0 && selectedIds.size === people.length;
  const isSomeSelected = selectedIds.size > 0 && selectedIds.size < people.length;

  // Clear selection when data changes
  useEffect(() => {
    setSelectedIds(new Set());
  }, [people]);

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (bulkDropdownRef.current && !bulkDropdownRef.current.contains(event.target)) {
        setShowBulkDropdown(false);
      }
      if (filterRef.current && !filterRef.current.contains(event.target) &&
          filterDropdownRef.current && !filterDropdownRef.current.contains(event.target)) {
        setIsFilterOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Bulk action mutations
  const sendEmailsMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkSendVOGEmails(ids),
    onSuccess: (response) => {
      setBulkActionResult(response.data);
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  const markRequestedMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkMarkVOGRequested(ids),
    onSuccess: (response) => {
      setBulkActionResult(response.data);
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  const markJustisMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkMarkVOGJustis(ids),
    onSuccess: (response) => {
      setBulkActionResult(response.data);
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  // Bulk action handlers
  const handleSendEmails = async () => {
    setBulkActionLoading(true);
    setBulkActionResult(null);
    try {
      await sendEmailsMutation.mutateAsync({ ids: Array.from(selectedIds) });
    } finally {
      setBulkActionLoading(false);
    }
  };

  const handleMarkRequested = async () => {
    setBulkActionLoading(true);
    setBulkActionResult(null);
    try {
      await markRequestedMutation.mutateAsync({ ids: Array.from(selectedIds) });
    } finally {
      setBulkActionLoading(false);
    }
  };

  const handleMarkJustis = async () => {
    setBulkActionLoading(true);
    setBulkActionResult(null);
    try {
      await markJustisMutation.mutateAsync({ ids: Array.from(selectedIds) });
    } finally {
      setBulkActionLoading(false);
    }
  };

  const handleCloseModal = () => {
    setShowSendEmailModal(false);
    setShowMarkRequestedModal(false);
    setShowMarkJustisModal(false);
    setBulkActionResult(null);
    if (bulkActionResult && (bulkActionResult.sent > 0 || bulkActionResult?.marked > 0)) {
      clearSelection();
    }
  };

  // Handle export to Google Sheets
  const handleExportToSheets = async () => {
    if (isExporting) return;

    setIsExporting(true);

    // Open window immediately (before async) to avoid popup blocker
    const newWindow = window.open('about:blank', '_blank');

    try {
      // VOG-specific columns
      const columns = ['name', 'knvb-id', 'email', 'phone', 'datum-vog', 'vog_email_sent_date', 'vog_justis_submitted_date'];

      // VOG-specific filters (matching the useFilteredPeople params in VOGList)
      const filters = {
        huidig_vrijwilliger: '1',
        vog_missing: '1',
        vog_older_than_years: 3,
        vog_email_status: emailStatusFilter || undefined,
        vog_type: vogTypeFilter || undefined,
        vog_justis_status: justisStatusFilter || undefined,
        orderby,
        order,
      };

      const response = await prmApi.exportPeopleToSheets({ columns, filters });

      if (response.data.spreadsheet_url && newWindow) {
        newWindow.location.href = response.data.spreadsheet_url;
      }
    } catch (error) {
      console.error('Export error:', error);
      if (newWindow) newWindow.close();
      const message = error.response?.data?.message || 'Export mislukt. Probeer het opnieuw.';
      alert(message);
    } finally {
      setIsExporting(false);
    }
  };

  // Handle connect to Google Sheets
  const handleConnectSheets = async () => {
    try {
      const response = await prmApi.getSheetsAuthUrl();
      if (response.data.auth_url) {
        window.location.href = response.data.auth_url;
      }
    } catch (error) {
      console.error('Auth error:', error);
      alert('Kon geen verbinding maken met Google Sheets. Probeer het opnieuw.');
    }
  };

  // Handle sort
  const handleSort = useCallback((columnId, newOrder) => {
    setOrderby(columnId);
    setOrder(newOrder);
  }, []);

  // Handle refresh
  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400 mb-4">
          Vrijwilligers konden niet worden geladen.
        </p>
        <button
          onClick={handleRefresh}
          className="btn-secondary inline-flex items-center gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          Opnieuw proberen
        </button>
      </div>
    );
  }

  // Empty state
  if (totalPeople === 0) {
    return (
      <PullToRefreshWrapper onRefresh={handleRefresh}>
        <div className="card">
          <VOGEmptyState />
        </div>
      </PullToRefreshWrapper>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Selection toolbar - sticky */}
        {selectedIds.size > 0 && (
          <div className="sticky top-0 z-20 flex items-center justify-between bg-cyan-50 dark:bg-deep-midnight border border-cyan-200 dark:border-bright-cobalt rounded-lg px-4 py-2 shadow-sm">
            <span className="text-sm text-deep-midnight dark:text-cyan-200 font-medium">
              {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'} geselecteerd
            </span>
            <div className="flex items-center gap-3">
              {/* Bulk Actions Dropdown */}
              <div className="relative" ref={bulkDropdownRef}>
                <button
                  onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                  className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-bright-cobalt dark:text-cyan-200 bg-white dark:bg-gray-800 border border-electric-cyan-light dark:border-electric-cyan rounded-md hover:bg-cyan-50 dark:hover:bg-gray-700"
                >
                  Acties
                  <ChevronDown className={`w-4 h-4 transition-transform ${showBulkDropdown ? 'rotate-180' : ''}`} />
                </button>
                {showBulkDropdown && (
                  <div className="absolute right-0 mt-1 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
                    <div className="py-1">
                      <button
                        onClick={() => {
                          setShowBulkDropdown(false);
                          setShowSendEmailModal(true);
                        }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <Mail className="w-4 h-4" />
                        VOG email verzenden...
                      </button>
                      <button
                        onClick={() => {
                          setShowBulkDropdown(false);
                          setShowMarkRequestedModal(true);
                        }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <CheckCircle className="w-4 h-4" />
                        Markeren als aangevraagd...
                      </button>
                      <button
                        onClick={() => {
                          setShowBulkDropdown(false);
                          setShowMarkJustisModal(true);
                        }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <CheckCircle className="w-4 h-4" />
                        Markeren bij Justis aangevraagd...
                      </button>
                    </div>
                  </div>
                )}
              </div>
              <button
                onClick={clearSelection}
                className="text-sm text-electric-cyan dark:text-electric-cyan hover:text-deep-midnight dark:hover:text-electric-cyan-light font-medium"
              >
                Selectie wissen
              </button>
            </div>
          </div>
        )}

        {/* Filter and Export Section */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex flex-wrap items-center gap-2">
            {/* Filter Dropdown Button */}
            <div className="relative" ref={filterRef}>
              <button
                onClick={() => setIsFilterOpen(!isFilterOpen)}
                className={`btn-secondary ${(emailStatusFilter || vogTypeFilter || justisStatusFilter) ? 'bg-cyan-50 text-bright-cobalt border-cyan-200 dark:bg-obsidian/30 dark:text-electric-cyan-light dark:border-bright-cobalt' : ''}`}
              >
                <Filter className="w-4 h-4 md:mr-2" />
                <span className="hidden md:inline">Filter</span>
                {(emailStatusFilter || vogTypeFilter || justisStatusFilter) && (
                  <span className="ml-2 px-1.5 py-0.5 bg-electric-cyan text-white text-xs rounded-full">
                    {(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0) + (justisStatusFilter ? 1 : 0)}
                  </span>
                )}
              </button>

              {/* Filter Dropdown Panel */}
              {isFilterOpen && (
                <div
                  ref={filterDropdownRef}
                  className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50"
                >
                  <div className="p-4 space-y-4">
                    {/* VOG Type Filter */}
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        VOG type
                      </h3>
                      <div className="space-y-1">
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={vogTypeFilter === ''}
                            onChange={() => setVogTypeFilter('')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            vogTypeFilter === ''
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {vogTypeFilter === '' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Alle ({vogTypeCounts.total})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={vogTypeFilter === 'nieuw'}
                            onChange={() => setVogTypeFilter('nieuw')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            vogTypeFilter === 'nieuw'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {vogTypeFilter === 'nieuw' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Nieuw ({vogTypeCounts.nieuw})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={vogTypeFilter === 'vernieuwing'}
                            onChange={() => setVogTypeFilter('vernieuwing')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            vogTypeFilter === 'vernieuwing'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {vogTypeFilter === 'vernieuwing' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Vernieuwing ({vogTypeCounts.vernieuwing})
                          </span>
                        </label>
                      </div>
                    </div>

                    {/* Email Status Filter */}
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Email status
                      </h3>
                      <div className="space-y-1">
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={emailStatusFilter === ''}
                            onChange={() => setEmailStatusFilter('')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            emailStatusFilter === ''
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {emailStatusFilter === '' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Alle ({emailCounts.total})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={emailStatusFilter === 'not_sent'}
                            onChange={() => setEmailStatusFilter('not_sent')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            emailStatusFilter === 'not_sent'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {emailStatusFilter === 'not_sent' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Niet verzonden ({emailCounts.notSent})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={emailStatusFilter === 'sent'}
                            onChange={() => setEmailStatusFilter('sent')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            emailStatusFilter === 'sent'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {emailStatusFilter === 'sent' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Wel verzonden ({emailCounts.sent})
                          </span>
                        </label>
                      </div>
                    </div>

                    {/* Justis Status Filter */}
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Justis status
                      </h3>
                      <div className="space-y-1">
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={justisStatusFilter === ''}
                            onChange={() => setJustisStatusFilter('')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            justisStatusFilter === ''
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {justisStatusFilter === '' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Alle ({justisCounts.total})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={justisStatusFilter === 'not_submitted'}
                            onChange={() => setJustisStatusFilter('not_submitted')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            justisStatusFilter === 'not_submitted'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {justisStatusFilter === 'not_submitted' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Niet aangevraagd ({justisCounts.notSubmitted})
                          </span>
                        </label>
                        <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
                          <input
                            type="checkbox"
                            checked={justisStatusFilter === 'submitted'}
                            onChange={() => setJustisStatusFilter('submitted')}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                            justisStatusFilter === 'submitted'
                              ? 'bg-electric-cyan border-electric-cyan'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {justisStatusFilter === 'submitted' && (
                              <Check className="w-3 h-3 text-white" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">
                            Aangevraagd ({justisCounts.submitted})
                          </span>
                        </label>
                      </div>
                    </div>

                    {/* Clear Filters */}
                    {(emailStatusFilter || vogTypeFilter || justisStatusFilter) && (
                      <button
                        onClick={() => {
                          setEmailStatusFilter('');
                          setVogTypeFilter('');
                          setJustisStatusFilter('');
                        }}
                        className="w-full text-sm text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
                      >
                        Alle filters wissen
                      </button>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Active Filter Chips */}
            {(emailStatusFilter || vogTypeFilter || justisStatusFilter) && (
              <div className="flex items-center gap-2">
                {emailStatusFilter && (
                  <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-cyan-50 dark:bg-obsidian/30 text-bright-cobalt dark:text-electric-cyan-light border border-cyan-200 dark:border-bright-cobalt rounded">
                    Email: {emailStatusFilter === 'sent' ? 'Wel verzonden' : 'Niet verzonden'}
                    <button
                      onClick={() => setEmailStatusFilter('')}
                      className="hover:text-obsidian dark:hover:text-cyan-100"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}
                {vogTypeFilter && (
                  <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-cyan-50 dark:bg-obsidian/30 text-bright-cobalt dark:text-electric-cyan-light border border-cyan-200 dark:border-bright-cobalt rounded">
                    Type: {vogTypeFilter === 'nieuw' ? 'Nieuw' : 'Vernieuwing'}
                    <button
                      onClick={() => setVogTypeFilter('')}
                      className="hover:text-obsidian dark:hover:text-cyan-100"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}
                {justisStatusFilter && (
                  <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-cyan-50 dark:bg-obsidian/30 text-bright-cobalt dark:text-electric-cyan-light border border-cyan-200 dark:border-bright-cobalt rounded">
                    Justis: {justisStatusFilter === 'submitted' ? 'Aangevraagd' : 'Niet aangevraagd'}
                    <button
                      onClick={() => setJustisStatusFilter('')}
                      className="hover:text-obsidian dark:hover:text-cyan-100"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                )}
              </div>
            )}
          </div>

          {/* Export Button */}
          <div className="flex gap-2">
            {sheetsStatus?.connected ? (
              <button
                onClick={handleExportToSheets}
                disabled={isExporting}
                className="btn-secondary"
                title="Exporteren naar Google Sheets"
              >
                {isExporting ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-current"></div>
                ) : (
                  <FileSpreadsheet className="w-4 h-4" />
                )}
              </button>
            ) : sheetsStatus?.google_configured ? (
              <button
                onClick={handleConnectSheets}
                className="btn-secondary"
                title="Verbinden met Google Sheets"
              >
                <FileSpreadsheet className="w-4 h-4" />
              </button>
            ) : null}
          </div>
        </div>

        {/* VOG list table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                {/* Checkbox header */}
                <th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800">
                  <button
                    onClick={toggleSelectAll}
                    className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    title={isAllSelected ? 'Deselecteer alles' : 'Selecteer alles'}
                  >
                    {isAllSelected ? (
                      <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
                    ) : isSomeSelected ? (
                      <MinusSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
                    ) : (
                      <Square className="w-5 h-5" />
                    )}
                  </button>
                </th>
                <SortableHeader
                  label="Naam"
                  columnId="first_name"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="KNVB ID"
                  columnId="knvb-id"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                  sortable={false}
                />
                <SortableHeader
                  label="Email"
                  columnId="email"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                  sortable={false}
                />
                <SortableHeader
                  label="Telefoon"
                  columnId="phone"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                  sortable={false}
                />
                <SortableHeader
                  label="Datum VOG"
                  columnId="custom_datum-vog"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="Verzonden"
                  columnId="custom_vog_email_sent_date"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                />
                <SortableHeader
                  label="Justis"
                  columnId="custom_vog_justis_submitted_date"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {people.map((person, index) => (
                <VOGRow
                  key={person.id}
                  person={person}
                  customFieldsMap={customFieldsMap}
                  isOdd={index % 2 === 1}
                  isSelected={selectedIds.has(person.id)}
                  onToggleSelection={toggleSelection}
                />
              ))}
            </tbody>
          </table>
        </div>

        {/* Send Email Modal */}
        {showSendEmailModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
              <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">VOG email verzenden</h2>
                <button onClick={handleCloseModal} disabled={bulkActionLoading} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <X className="w-5 h-5" />
                </button>
              </div>
              <div className="p-4 space-y-4">
                {bulkActionResult ? (
                  <div className="space-y-2">
                    {bulkActionResult.sent > 0 && (
                      <p className="text-sm text-green-600 dark:text-green-400">
                        {bulkActionResult.sent} email{bulkActionResult.sent > 1 ? 's' : ''} verzonden
                      </p>
                    )}
                    {bulkActionResult.failed > 0 && (
                      <p className="text-sm text-red-600 dark:text-red-400">
                        {bulkActionResult.failed} mislukt
                      </p>
                    )}
                    {bulkActionResult.results?.filter(r => !r.success).map((r, i) => (
                      <p key={i} className="text-xs text-gray-500 dark:text-gray-400 pl-2">
                        ID {r.id}: {r.error}
                      </p>
                    ))}
                  </div>
                ) : (
                  <>
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Verstuur VOG email naar {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'}.
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      Het systeem selecteert automatisch de juiste template (nieuw of vernieuwing) op basis van de bestaande VOG datum.
                    </p>
                  </>
                )}
              </div>
              <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700">
                {bulkActionResult ? (
                  <button onClick={handleCloseModal} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md">
                      Annuleren
                    </button>
                    <button onClick={handleSendEmails} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md disabled:opacity-50">
                      {bulkActionLoading ? 'Verzenden...' : `Verstuur naar ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Mark Requested Modal */}
        {showMarkRequestedModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
              <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Markeren als aangevraagd</h2>
                <button onClick={handleCloseModal} disabled={bulkActionLoading} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <X className="w-5 h-5" />
                </button>
              </div>
              <div className="p-4 space-y-4">
                {bulkActionResult ? (
                  <div className="space-y-2">
                    {bulkActionResult.marked > 0 && (
                      <p className="text-sm text-green-600 dark:text-green-400">
                        {bulkActionResult.marked} vrijwilliger{bulkActionResult.marked > 1 ? 's' : ''} gemarkeerd
                      </p>
                    )}
                    {bulkActionResult.failed > 0 && (
                      <p className="text-sm text-red-600 dark:text-red-400">
                        {bulkActionResult.failed} mislukt
                      </p>
                    )}
                  </div>
                ) : (
                  <>
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Markeer {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'} als &quot;VOG aangevraagd&quot;.
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      Dit registreert de huidige datum als datum van VOG-aanvraag, zonder een email te versturen.
                    </p>
                  </>
                )}
              </div>
              <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700">
                {bulkActionResult ? (
                  <button onClick={handleCloseModal} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md">
                      Annuleren
                    </button>
                    <button onClick={handleMarkRequested} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md disabled:opacity-50">
                      {bulkActionLoading ? 'Markeren...' : `Markeer ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Mark Justis Modal */}
        {showMarkJustisModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
              <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Markeren bij Justis aangevraagd</h2>
                <button onClick={handleCloseModal} disabled={bulkActionLoading} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <X className="w-5 h-5" />
                </button>
              </div>
              <div className="p-4 space-y-4">
                {bulkActionResult ? (
                  <div className="space-y-2">
                    {bulkActionResult.marked > 0 && (
                      <p className="text-sm text-green-600 dark:text-green-400">
                        {bulkActionResult.marked} vrijwilliger{bulkActionResult.marked > 1 ? 's' : ''} gemarkeerd
                      </p>
                    )}
                    {bulkActionResult.failed > 0 && (
                      <p className="text-sm text-red-600 dark:text-red-400">
                        {bulkActionResult.failed} mislukt
                      </p>
                    )}
                  </div>
                ) : (
                  <>
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Markeer {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'} als &quot;bij Justis aangevraagd&quot;.
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      Dit registreert de huidige datum als datum van indiening bij het Justis-systeem.
                    </p>
                  </>
                )}
              </div>
              <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700">
                {bulkActionResult ? (
                  <button onClick={handleCloseModal} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md">
                      Annuleren
                    </button>
                    <button onClick={handleMarkJustis} disabled={bulkActionLoading} className="px-4 py-2 text-sm font-medium text-white bg-electric-cyan hover:bg-bright-cobalt rounded-md disabled:opacity-50">
                      {bulkActionLoading ? 'Markeren...' : `Markeer ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </PullToRefreshWrapper>
  );
}
