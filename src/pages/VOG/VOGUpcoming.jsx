import { useState, useMemo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUp, ArrowDown, CheckCircle, Clock, RefreshCw } from 'lucide-react';
import { useFilteredPeople } from '@/hooks/usePeople';
import { useQueryClient } from '@tanstack/react-query';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { format } from '@/utils/dateFormat';

// Helper function to get first contact value by type
function getFirstContactByType(person, type) {
  const contactInfo = person.acf?.contact_info || [];
  const contact = contactInfo.find(c => c.contact_type === type);
  return contact?.contact_value || null;
}

// Calculate days until VOG expires (3 year validity)
function daysUntilExpiry(vogDate) {
  if (!vogDate) return null;
  const expiry = new Date(vogDate);
  expiry.setFullYear(expiry.getFullYear() + 3);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  expiry.setHours(0, 0, 0, 0);
  return Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
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

// Empty state component
function VOGUpcomingEmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-3 bg-green-100 rounded-full dark:bg-green-900/30">
          <CheckCircle className="w-8 h-8 text-green-600 dark:text-green-400" />
        </div>
      </div>
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
        Geen VOG-vernieuwingen binnenkort
      </h3>
      <p className="text-sm text-gray-600 dark:text-gray-400">
        Er zijn momenteel geen vrijwilligers waarvan de VOG binnen 30 dagen verloopt.
      </p>
    </div>
  );
}

// Row component
function VOGUpcomingRow({ person, isOdd }) {
  const email = getFirstContactByType(person, 'email');
  const vogDate = person.acf?.['datum-vog'];
  const days = daysUntilExpiry(vogDate);

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
      isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
    }`}>
      {/* Name */}
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/people/${person.id}`} className="flex items-center gap-2">
          <span className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {[person.first_name, person.infix, person.last_name].filter(Boolean).join(' ')}
          </span>
        </Link>
      </td>

      {/* Email */}
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {email ? (
          <a href={`mailto:${email}`} className="hover:text-electric-cyan dark:hover:text-electric-cyan">
            {email}
          </a>
        ) : '-'}
      </td>

      {/* Datum VOG */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {vogDate ? format(new Date(vogDate), 'dd-MM-yyyy') : '-'}
      </td>

      {/* Verloopt over */}
      <td className="px-4 py-3 text-sm">
        {days !== null ? (
          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
            days <= 7
              ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
              : days <= 14
                ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
          }`}>
            <Clock className="w-3 h-3" />
            {days === 0 ? 'Vandaag' : days === 1 ? 'Morgen' : `${days} dagen`}
          </span>
        ) : '-'}
      </td>
    </tr>
  );
}

export default function VOGUpcoming() {
  const [orderby, setOrderby] = useState('custom_datum-vog');
  const [order, setOrder] = useState('asc');
  const queryClient = useQueryClient();

  // Fetch current volunteers whose VOG expires within the next 30 days
  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',
    vogExpiringWithinDays: 30,
    orderby,
    order,
  });

  const people = useMemo(() => data?.people || [], [data?.people]);
  const totalPeople = data?.total || 0;

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
          Gegevens konden niet worden geladen.
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

  if (totalPeople === 0) {
    return (
      <PullToRefreshWrapper onRefresh={handleRefresh}>
        <div className="card">
          <VOGUpcomingEmptyState />
        </div>
      </PullToRefreshWrapper>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          {totalPeople} {totalPeople === 1 ? 'vrijwilliger' : 'vrijwilligers'} {totalPeople === 1 ? 'heeft' : 'hebben'} een VOG die binnen 30 dagen verloopt.
        </p>

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
                  label="Email"
                  columnId="email"
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
                  label="Verloopt over"
                  columnId="expiry"
                  sortField={orderby}
                  sortOrder={order}
                  onSort={handleSort}
                  sortable={false}
                />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {people.map((person, index) => (
                <VOGUpcomingRow
                  key={person.id}
                  person={person}
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
