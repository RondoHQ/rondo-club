import { useState, useMemo, useCallback, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle, Mail, RefreshCw, Square, CheckSquare, MinusSquare, ChevronDown, X, Download } from 'lucide-react';
import { useFilteredPeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { buildCsv, downloadCsv } from '@/utils/csvExport';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import CustomFieldColumn from '@/components/CustomFieldColumn';
import { format } from '@/utils/dateFormat';
import { formatPersonSurname, formatPhoneForTel, formatPhoneForDisplay } from '@/utils/formatters';
import SortableHeader from '@/components/SortableHeader';
import { DataTableToolbar, ColumnSettingsPanel, useColumnVisibility, createColumn, FILTER_TYPES } from '@/components/DataTable';

function getFirstEmail(person) {
  return person.fields?.email_1 || person.fields?.email_2 || null;
}

function getFirstPhone(person) {
  return person.fields?.mobile_1 || person.fields?.telephone_1 || person.fields?.mobile_2 || person.fields?.telephone_2 || null;
}

function VOGBadge({ person }) {
  const datumVog = person.fields?.['datum_vog'];
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

function VOGEmailIndicator({ person }) {
  const emailSentDate = person.fields?.['vog_email_sent_date'];
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

function VOGRow({ person, customFieldsMap, isOdd, isSelected, onToggleSelection, isColVisible }) {
  const email = getFirstEmail(person);
  const phone = getFirstPhone(person);

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isSelected
        ? 'bg-cyan-50 dark:bg-obsidian/30'
        : isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    }`}>
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
      {isColVisible('first_name') && (
        <td className="px-4 py-3 whitespace-nowrap">
          <Link to={`/people/${person.id}`} className="flex items-center gap-2">
            <span className={`text-sm font-medium ${isSelected ? 'text-gray-900 dark:text-white' : 'text-gray-900 dark:text-gray-50'}`}>
              {person.first_name || '-'}
            </span>
            <VOGBadge person={person} />
            <VOGEmailIndicator person={person} />
          </Link>
        </td>
      )}
      {isColVisible('last_name') && (
        <td className="px-4 py-3 whitespace-nowrap">
          <Link to={`/people/${person.id}`} className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {formatPersonSurname(person.infix, person.last_name) || '-'}
          </Link>
        </td>
      )}

      {isColVisible('knvb_id') && (
        <td className={`px-4 py-3 text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {customFieldsMap['knvb_id'] ? (
            <CustomFieldColumn field={customFieldsMap['knvb_id']} value={person.fields?.['knvb_id']} />
          ) : (
            person.fields?.['knvb_id'] || '-'
          )}
        </td>
      )}

      {isColVisible('email') && (
        <td className={`px-4 py-3 whitespace-nowrap text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {email ? (
            <a href={`mailto:${email}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
              {email}
            </a>
          ) : '-'}
        </td>
      )}

      {isColVisible('phone') && (
        <td className={`px-4 py-3 whitespace-nowrap text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {phone ? (
            <a href={`tel:${formatPhoneForTel(phone)}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
              {formatPhoneForDisplay(phone)}
            </a>
          ) : '-'}
        </td>
      )}

      {isColVisible('datum_vog') && (
        <td className={`px-4 py-3 text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {customFieldsMap['datum_vog'] ? (
            <CustomFieldColumn field={customFieldsMap['datum_vog']} value={person.fields?.['datum_vog']} />
          ) : (
            person.fields?.['datum_vog'] || '-'
          )}
        </td>
      )}

      {isColVisible('vog_email_sent') && (
        <td className={`px-4 py-3 text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {person.fields?.['vog_email_sent_date']
            ? format(new Date(person.fields['vog_email_sent_date']), 'yyyy-MM-dd')
            : '-'}
        </td>
      )}

      {isColVisible('justis') && (
        <td className={`px-4 py-3 text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {person.fields?.['vog_justis_submitted_date']
            ? format(new Date(person.fields['vog_justis_submitted_date']), 'yyyy-MM-dd')
            : '-'}
        </td>
      )}

      {isColVisible('reminder') && (
        <td className={`px-4 py-3 text-sm ${isSelected ? 'text-gray-700 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
          {person.fields?.['vog_reminder_sent_date']
            ? format(new Date(person.fields['vog_reminder_sent_date']), 'yyyy-MM-dd')
            : '-'}
        </td>
      )}
    </tr>
  );
}

export default function VOGList() {
  const [orderby, setOrderby] = useState('field_datum_vog');
  const [order, setOrder] = useState('asc');

  const [emailStatusFilter, setEmailStatusFilter] = useState('');
  const [vogTypeFilter, setVogTypeFilter] = useState('');
  const [justisStatusFilter, setJustisStatusFilter] = useState('');
  const [reminderStatusFilter, setReminderStatusFilter] = useState('');
  const [firstNameFilter, setFirstNameFilter] = useState('');
  const [lastNameFilter, setLastNameFilter] = useState('');
  const [knvbIdFilter, setKnvbIdFilter] = useState('');
  const [emailPresenceFilter, setEmailPresenceFilter] = useState('');
  const [isColumnSettingsOpen, setIsColumnSettingsOpen] = useState(false);

  const { isVisible, toggle } = useColumnVisibility('vog');

  const queryClient = useQueryClient();

  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const bulkDropdownRef = useRef(null);

  const [showSendEmailModal, setShowSendEmailModal] = useState(false);
  const [showMarkJustisModal, setShowMarkJustisModal] = useState(false);
  const [showSendReminderModal, setShowSendReminderModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const [bulkActionResult, setBulkActionResult] = useState(null);

  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogRequired: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
    vogEmailStatus: emailStatusFilter,
    vogType: vogTypeFilter,
    vogJustisStatus: justisStatusFilter,
    vogReminderStatus: reminderStatusFilter,
    firstName: firstNameFilter,
    lastName: lastNameFilter,
    orderby,
    order,
  });

  const { data: allData } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogRequired: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
    orderby,
    order,
  });

  const allServerPeople = useMemo(() => data?.people || [], [data?.people]);
  const totalPeople = data?.total || 0;

  // Presence filters are based on the complete response page; name filters are
  // server-side so sorting and filtering apply before pagination.
  const people = useMemo(() => {
    let result = allServerPeople;
    if (knvbIdFilter === 'heeft') result = result.filter(p => p.fields?.['knvb_id']);
    else if (knvbIdFilter === 'geen') result = result.filter(p => !p.fields?.['knvb_id']);
    if (emailPresenceFilter === 'heeft') result = result.filter(p => getFirstEmail(p));
    else if (emailPresenceFilter === 'geen') result = result.filter(p => !getFirstEmail(p));
    return result;
  }, [allServerPeople, knvbIdFilter, emailPresenceFilter]);

  const emailCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const sent = allPeople.filter(p => p.fields?.['vog_email_sent_date']).length;
    const notSent = allPeople.length - sent;
    return { total: allPeople.length, sent, notSent };
  }, [allData?.people]);

  const vogTypeCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const nieuw = allPeople.filter(p => !p.fields?.['datum_vog']).length;
    const vernieuwing = allPeople.length - nieuw;
    return { total: allPeople.length, nieuw, vernieuwing };
  }, [allData?.people]);

  const justisCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const submitted = allPeople.filter(p => p.fields?.['vog_justis_submitted_date']).length;
    const notSubmitted = allPeople.length - submitted;
    return { total: allPeople.length, submitted, notSubmitted };
  }, [allData?.people]);

  const knvbIdCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const heeft = allPeople.filter(p => p.fields?.['knvb_id']).length;
    return { heeft, geen: allPeople.length - heeft };
  }, [allData?.people]);

  const emailPresenceCounts = useMemo(() => {
    const allPeople = allData?.people || [];
    const heeft = allPeople.filter(p => getFirstEmail(p)).length;
    return { heeft, geen: allPeople.length - heeft };
  }, [allData?.people]);

  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('person');
      return response.data;
    },
  });

  const customFieldsMap = useMemo(() => {
    const map = {};
    customFields.forEach(field => {
      map[field.name] = field;
    });
    return map;
  }, [customFields]);

  // Filter columns for DataTableToolbar — labels include live counts from allData
  const filterColumns = useMemo(() => [
    createColumn({
      id: 'first_name',
      header: 'Voornaam',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'last_name',
      header: 'Achternaam',
      filterType: FILTER_TYPES.TEXT,
    }),
    createColumn({
      id: 'knvb_id_filter',
      header: 'KNVB ID',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'heeft', label: `Heeft KNVB ID (${knvbIdCounts.heeft})` },
        { value: 'geen', label: `Geen KNVB ID (${knvbIdCounts.geen})` },
      ],
      getFilterLabel: (val) => val === 'heeft' ? 'Heeft KNVB ID' : 'Geen KNVB ID',
    }),
    createColumn({
      id: 'email_presence',
      header: 'Email',
      filterLabel: 'Email aanwezig',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'heeft', label: `Heeft email (${emailPresenceCounts.heeft})` },
        { value: 'geen', label: `Geen email (${emailPresenceCounts.geen})` },
      ],
      getFilterLabel: (val) => val === 'heeft' ? 'Heeft email' : 'Geen email',
    }),
    createColumn({
      id: 'vog_type',
      header: 'VOG type',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'nieuw', label: `Nieuw (${vogTypeCounts.nieuw})` },
        { value: 'vernieuwing', label: `Vernieuwing (${vogTypeCounts.vernieuwing})` },
      ],
      getFilterLabel: (val) => val === 'nieuw' ? 'Nieuw' : 'Vernieuwing',
    }),
    createColumn({
      id: 'email_status',
      header: '1e email',
      filterLabel: '1e email status',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'not_sent', label: `Niet verzonden (${emailCounts.notSent})` },
        { value: 'sent', label: `Wel verzonden (${emailCounts.sent})` },
      ],
      getFilterLabel: (val) => val === 'sent' ? 'Wel verzonden' : 'Niet verzonden',
    }),
    createColumn({
      id: 'justis_status',
      header: 'Justis',
      filterLabel: 'Justis status',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'not_submitted', label: `Niet aangevraagd (${justisCounts.notSubmitted})` },
        { value: 'submitted', label: `Aangevraagd (${justisCounts.submitted})` },
      ],
      getFilterLabel: (val) => val === 'submitted' ? 'Aangevraagd' : 'Niet aangevraagd',
    }),
    createColumn({
      id: 'reminder_status',
      header: 'Herinnering',
      filterLabel: 'Herinnering status',
      filterType: FILTER_TYPES.SELECT,
      filterOptions: [
        { value: 'not_sent', label: 'Niet verzonden' },
        { value: 'sent', label: 'Wel verzonden' },
      ],
      getFilterLabel: (val) => val === 'sent' ? 'Wel verzonden' : 'Niet verzonden',
    }),
  ], [emailCounts, vogTypeCounts, justisCounts, knvbIdCounts, emailPresenceCounts]);

  const hasActiveFilters = !!(emailStatusFilter || vogTypeFilter || justisStatusFilter || reminderStatusFilter || firstNameFilter || lastNameFilter || knvbIdFilter || emailPresenceFilter);
  const activeFilterCount = [emailStatusFilter, vogTypeFilter, justisStatusFilter, reminderStatusFilter, firstNameFilter, lastNameFilter, knvbIdFilter, emailPresenceFilter].filter(Boolean).length;

  const clearFilters = () => {
    setEmailStatusFilter('');
    setVogTypeFilter('');
    setJustisStatusFilter('');
    setReminderStatusFilter('');
    setFirstNameFilter('');
    setLastNameFilter('');
    setKnvbIdFilter('');
    setEmailPresenceFilter('');
  };

  const setFilter = (colId, value) => {
    if (colId === 'email_status') setEmailStatusFilter(value || '');
    else if (colId === 'vog_type') setVogTypeFilter(value || '');
    else if (colId === 'justis_status') setJustisStatusFilter(value || '');
    else if (colId === 'reminder_status') setReminderStatusFilter(value || '');
    else if (colId === 'first_name') setFirstNameFilter(value || '');
    else if (colId === 'last_name') setLastNameFilter(value || '');
    else if (colId === 'knvb_id_filter') setKnvbIdFilter(value || '');
    else if (colId === 'email_presence') setEmailPresenceFilter(value || '');
  };

  const colVisColumns = [
    { id: 'first_name', label: 'Voornaam', isVisible: isVisible('first_name') },
    { id: 'last_name', label: 'Achternaam', isVisible: isVisible('last_name') },
    { id: 'knvb_id', label: 'KNVB ID', isVisible: isVisible('knvb_id') },
    { id: 'email', label: 'Email', isVisible: isVisible('email') },
    { id: 'phone', label: 'Telefoon', isVisible: isVisible('phone') },
    { id: 'datum_vog', label: 'Datum VOG', isVisible: isVisible('datum_vog') },
    { id: 'vog_email_sent', label: '1e email', isVisible: isVisible('vog_email_sent') },
    { id: 'justis', label: 'Justis', isVisible: isVisible('justis') },
    { id: 'reminder', label: 'Herinnering', isVisible: isVisible('reminder') },
  ];

  const toggleSelection = (personId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(personId)) next.delete(personId);
      else next.add(personId);
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

  const isAllSelected = people.length > 0 && selectedIds.size === people.length;
  const isSomeSelected = selectedIds.size > 0 && selectedIds.size < people.length;

  useEffect(() => {
    setSelectedIds(new Set());
  }, [people]);

  // Close bulk dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (bulkDropdownRef.current && !bulkDropdownRef.current.contains(event.target)) {
        setShowBulkDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const sendEmailsMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkSendVOGEmails(ids),
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

  const sendRemindersMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkSendVOGReminders(ids),
    onSuccess: (response) => {
      setBulkActionResult(response.data);
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  const handleSendEmails = async () => {
    setBulkActionLoading(true);
    setBulkActionResult(null);
    try {
      await sendEmailsMutation.mutateAsync({ ids: Array.from(selectedIds) });
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

  const handleSendReminders = async () => {
    setBulkActionLoading(true);
    setBulkActionResult(null);
    try {
      await sendRemindersMutation.mutateAsync({ ids: Array.from(selectedIds) });
    } finally {
      setBulkActionLoading(false);
    }
  };

  const handleCloseModal = () => {
    setShowSendEmailModal(false);
    setShowMarkJustisModal(false);
    setShowSendReminderModal(false);
    setBulkActionResult(null);
    if (bulkActionResult && (bulkActionResult.sent > 0 || bulkActionResult?.marked > 0)) {
      clearSelection();
    }
  };

  const handleExportCsv = () => {
    const headers = ['Voornaam', 'Achternaam', 'KNVB ID', 'Email', 'Telefoon', 'Datum VOG', 'Type', '1e email', 'Justis', 'Herinnering'];
    const formatDate = (d) => d ? format(new Date(d), 'yyyy-MM-dd') : '';
    const rows = people.map(person => [
      person.first_name || '',
      formatPersonSurname(person.infix, person.last_name),
      person.fields?.['knvb_id'] || '',
      getFirstEmail(person) || '',
      getFirstPhone(person) || '',
      formatDate(person.fields?.['datum_vog']),
      person.fields?.['datum_vog'] ? 'Vernieuwing' : 'Nieuw',
      formatDate(person.fields?.['vog_email_sent_date']),
      formatDate(person.fields?.['vog_justis_submitted_date']),
      formatDate(person.fields?.['vog_reminder_sent_date']),
    ]);
    const csv = buildCsv([headers, ...rows]);
    downloadCsv(csv, `vog-${format(new Date(), 'yyyy-MM-dd')}.csv`);
  };

  const handleSort = useCallback((columnId, newOrder) => {
    setOrderby(columnId);
    setOrder(newOrder);
  }, []);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400 mb-4">
          Vrijwilligers konden niet worden geladen.
        </p>
        <button
          onClick={handleRefresh}
          className="btn-tertiary gap-2"
        >
          <RefreshCw className="w-4 h-4" />
          Opnieuw proberen
        </button>
      </div>
    );
  }

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
                        onClick={() => { setShowBulkDropdown(false); setShowSendEmailModal(true); }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <Mail className="w-4 h-4" />
                        VOG email verzenden...
                      </button>
                      <button
                        onClick={() => { setShowBulkDropdown(false); setShowMarkJustisModal(true); }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <CheckCircle className="w-4 h-4" />
                        Markeren bij Justis aangevraagd...
                      </button>
                      <button
                        onClick={() => { setShowBulkDropdown(false); setShowSendReminderModal(true); }}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        <Mail className="w-4 h-4" />
                        Herinnering verzenden...
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

        {/* Filter toolbar + CSV export */}
        <DataTableToolbar
          columns={filterColumns}
          filters={{ first_name: firstNameFilter, last_name: lastNameFilter, knvb_id_filter: knvbIdFilter, email_presence: emailPresenceFilter, vog_type: vogTypeFilter, email_status: emailStatusFilter, justis_status: justisStatusFilter, reminder_status: reminderStatusFilter }}
          onFilterChange={setFilter}
          onClearFilters={clearFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onOpenColumnSettings={() => setIsColumnSettingsOpen(true)}
          toolbarEnd={
            <button
              onClick={handleExportCsv}
              className="btn-tertiary"
              title="Downloaden als CSV"
              disabled={!people.length}
            >
              <Download className="w-4 h-4" />
            </button>
          }
        />

        {/* VOG list table */}
        <div
          className="card !overflow-x-auto"
          data-horizontal-scroll="true"
        >
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
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
                {isVisible('first_name') && (
                  <SortableHeader label="Voornaam" columnId="first_name" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
                {isVisible('last_name') && (
                  <SortableHeader label="Achternaam" columnId="last_name" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
                {isVisible('knvb_id') && (
                  <SortableHeader label="KNVB ID" columnId="knvb_id" sortField={orderby} sortOrder={order} onSort={handleSort} sortable={false} />
                )}
                {isVisible('email') && (
                  <SortableHeader label="Email" columnId="email" sortField={orderby} sortOrder={order} onSort={handleSort} sortable={false} />
                )}
                {isVisible('phone') && (
                  <SortableHeader label="Telefoon" columnId="phone" sortField={orderby} sortOrder={order} onSort={handleSort} sortable={false} />
                )}
                {isVisible('datum_vog') && (
                  <SortableHeader label="Datum VOG" columnId="field_datum_vog" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
                {isVisible('vog_email_sent') && (
                  <SortableHeader label="1e email" columnId="custom_vog_email_sent_date" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
                {isVisible('justis') && (
                  <SortableHeader label="Justis" columnId="custom_vog_justis_submitted_date" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
                {isVisible('reminder') && (
                  <SortableHeader label="Herinnering" columnId="custom_vog_reminder_sent_date" sortField={orderby} sortOrder={order} onSort={handleSort} />
                )}
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
                  isColVisible={isVisible}
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
                      <p className="text-sm text-red-600 dark:text-red-400">{bulkActionResult.failed} mislukt</p>
                    )}
                    {bulkActionResult.results?.filter(r => !r.success).map((r, i) => (
                      <p key={i} className="text-xs text-gray-500 dark:text-gray-400 pl-2">ID {r.id}: {r.error}</p>
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
                  <button onClick={handleCloseModal} className="btn-primary">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="btn-secondary">
                      Annuleren
                    </button>
                    <button onClick={handleSendEmails} disabled={bulkActionLoading} className="btn-primary disabled:opacity-50">
                      {bulkActionLoading ? 'Verzenden...' : `Verstuur naar ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
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
                      <p className="text-sm text-red-600 dark:text-red-400">{bulkActionResult.failed} mislukt</p>
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
                  <button onClick={handleCloseModal} className="btn-primary">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="btn-secondary">
                      Annuleren
                    </button>
                    <button onClick={handleMarkJustis} disabled={bulkActionLoading} className="btn-primary disabled:opacity-50">
                      {bulkActionLoading ? 'Markeren...' : `Markeer ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Send Reminder Modal */}
        {showSendReminderModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
              <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">VOG herinnering verzenden</h2>
                <button onClick={handleCloseModal} disabled={bulkActionLoading} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <X className="w-5 h-5" />
                </button>
              </div>
              <div className="p-4 space-y-4">
                {bulkActionResult ? (
                  <div className="space-y-2">
                    {bulkActionResult.sent > 0 && (
                      <p className="text-sm text-green-600 dark:text-green-400">
                        {bulkActionResult.sent} herinnering{bulkActionResult.sent > 1 ? 'en' : ''} verzonden
                      </p>
                    )}
                    {bulkActionResult.failed > 0 && (
                      <p className="text-sm text-red-600 dark:text-red-400">{bulkActionResult.failed} mislukt</p>
                    )}
                    {bulkActionResult.results?.filter(r => !r.success).map((r, i) => (
                      <p key={i} className="text-xs text-gray-500 dark:text-gray-400 pl-2">ID {r.id}: {r.error}</p>
                    ))}
                  </div>
                ) : (
                  <>
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Verstuur VOG herinnering naar {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'}.
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      Het systeem selecteert automatisch de juiste template (nieuw of vernieuwing) op basis van de bestaande VOG datum.
                    </p>
                  </>
                )}
              </div>
              <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700">
                {bulkActionResult ? (
                  <button onClick={handleCloseModal} className="btn-primary">
                    Sluiten
                  </button>
                ) : (
                  <>
                    <button onClick={handleCloseModal} disabled={bulkActionLoading} className="btn-secondary">
                      Annuleren
                    </button>
                    <button onClick={handleSendReminders} disabled={bulkActionLoading} className="btn-primary disabled:opacity-50">
                      {bulkActionLoading ? 'Verzenden...' : `Verstuur naar ${selectedIds.size} vrijwilliger${selectedIds.size > 1 ? 's' : ''}`}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </div>

      <ColumnSettingsPanel
        isOpen={isColumnSettingsOpen}
        onClose={() => setIsColumnSettingsOpen(false)}
        columns={colVisColumns}
        onToggleColumn={toggle}
      />
    </PullToRefreshWrapper>
  );
}
