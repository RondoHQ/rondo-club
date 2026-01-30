import { useState, useMemo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUp, ArrowDown, CheckCircle, Mail, RefreshCw } from 'lucide-react';
import { useFilteredPeople } from '@/hooks/usePeople';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import CustomFieldColumn from '@/components/CustomFieldColumn';

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
function VOGRow({ person, customFieldsMap, isOdd }) {
  const email = getFirstContactByType(person, 'email');
  const phone = getFirstPhone(person);

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'}`}>
      {/* Name with badge */}
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/people/${person.id}`} className="flex items-center gap-2">
          <span className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {person.first_name || ''} {person.last_name || ''}
          </span>
          <VOGBadge person={person} />
          <VOGEmailIndicator person={person} />
        </Link>
      </td>

      {/* KNVB ID */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
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
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {email ? (
          <a href={`mailto:${email}`} className="hover:text-accent-600 dark:hover:text-accent-400">
            {email}
          </a>
        ) : '-'}
      </td>

      {/* Phone */}
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {phone ? (
          <a href={`tel:${phone}`} className="hover:text-accent-600 dark:hover:text-accent-400">
            {phone}
          </a>
        ) : '-'}
      </td>

      {/* Datum VOG */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {customFieldsMap['datum-vog'] ? (
          <CustomFieldColumn
            field={customFieldsMap['datum-vog']}
            value={person.acf?.['datum-vog']}
          />
        ) : (
          person.acf?.['datum-vog'] || '-'
        )}
      </td>
    </tr>
  );
}

export default function VOGList() {
  // Sort state
  const [orderby, setOrderby] = useState('custom_datum-vog');
  const [order, setOrder] = useState('asc');

  const queryClient = useQueryClient();

  // Fetch filtered people with VOG-specific filters
  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
    orderby,
    order,
  });

  // Extract data from response
  const people = data?.people || [];
  const totalPeople = data?.total || 0;

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('person');
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
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
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
        {/* VOG list table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
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
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {people.map((person, index) => (
                <VOGRow
                  key={person.id}
                  person={person}
                  customFieldsMap={customFieldsMap}
                  isOdd={index % 2 === 1}
                />
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </PullToRefreshWrapper>
  );
}
