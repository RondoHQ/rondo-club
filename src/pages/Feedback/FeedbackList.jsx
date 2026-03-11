import { useState, useCallback, useMemo } from 'react';
import { Link, useSearchParams, useLocation } from 'react-router-dom';
import { MessageSquare, Plus, ExternalLink } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useFeedbackList, useCreateFeedback, useUpdateFeedback } from '@/hooks/useFeedback';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import FeedbackModal from '@/components/FeedbackModal';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import { DataTable, createColumn, FILTER_TYPES } from '@/components/DataTable';

const statusColors = {
  new: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
  in_review: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
  resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  declined: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400',
  needs_info: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

const typeColors = {
  bug: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  feature_request: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

const statusLabels = {
  new: 'Nieuw',
  approved: 'Goedgekeurd',
  in_progress: 'In behandeling',
  in_review: 'In review',
  resolved: 'Opgelost',
  declined: 'Afgewezen',
  needs_info: 'Info nodig',
};

const typeLabels = {
  bug: 'Bug',
  feature_request: 'Functie',
};

const projectLabels = {
  'rondo-club': 'Rondo Club',
  'rondo-sync': 'Rondo Sync',
  'website': 'Website',
};

const projectColors = {
  'rondo-club': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
  'rondo-sync': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
  'website': 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
};

const statusOptions = [
  { value: 'open', label: 'Open' },
  { value: 'new', label: 'Nieuw' },
  { value: 'approved', label: 'Goedgekeurd' },
  { value: 'in_progress', label: 'In behandeling' },
  { value: 'in_review', label: 'In review' },
  { value: 'needs_info', label: 'Info nodig' },
  { value: 'resolved', label: 'Opgelost' },
  { value: 'declined', label: 'Afgewezen' },
];

const closedStatuses = ['resolved', 'declined'];

const statusSortOrder = {
  new: 0,
  approved: 1,
  in_progress: 2,
  in_review: 3,
  needs_info: 4,
  resolved: 5,
  declined: 6,
};

const priorityOptions = [
  { value: 'low', label: 'Laag' },
  { value: 'medium', label: 'Gemiddeld' },
  { value: 'high', label: 'Hoog' },
  { value: 'critical', label: 'Kritiek' },
];

const typeFilterOptions = [
  { value: 'bug', label: 'Bug' },
  { value: 'feature_request', label: 'Functie' },
];

const projectFilterOptions = [
  { value: 'rondo-club', label: 'Rondo Club' },
  { value: 'rondo-sync', label: 'Rondo Sync' },
  { value: 'website', label: 'Website' },
];

function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[status] || statusColors.new}`}>
      {statusLabels[status] || status}
    </span>
  );
}

function TypeBadge({ type }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[type] || typeColors.bug}`}>
      {typeLabels[type] || type}
    </span>
  );
}

function ProjectBadge({ project }) {
  const projectValue = project || 'rondo-club';
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${projectColors[projectValue] || projectColors['rondo-club']}`}>
      {projectLabels[projectValue] || projectValue}
    </span>
  );
}

function priorityLabel(value) {
  const option = priorityOptions.find((p) => p.value === value);
  return option?.label || value || '-';
}

export default function FeedbackList() {
  useDocumentTitle('Feedback');
  const location = useLocation();
  const [showModal, setShowModal] = useState(false);
  const [searchParams, setSearchParams] = useSearchParams();

  const queryClient = useQueryClient();
  const { data: currentUser } = useCurrentUser();
  const createFeedback = useCreateFeedback();
  const updateFeedback = useUpdateFeedback();

  const canManageFeedback = currentUser?.is_admin ?? false;

  const filters = useMemo(() => ({
    id: searchParams.get('id') || '',
    title: searchParams.get('title') || '',
    type: searchParams.get('type') || '',
    project: searchParams.get('project') || '',
    author: searchParams.get('author') || '',
    status: searchParams.get('status') ?? 'open',
    priority: searchParams.get('priority') || '',
  }), [searchParams]);

  const handleFilterChange = useCallback((colId, value) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (!value) {
        // For status, set empty string so we can distinguish "show all" from "never set"
        if (colId === 'status') next.set(colId, '');
        else next.delete(colId);
      } else {
        next.set(colId, value);
      }
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const handleClearFilters = useCallback(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      ['id', 'title', 'type', 'project', 'author', 'status', 'priority'].forEach((k) => next.delete(k));
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const { data: feedback = [], isLoading, error } = useFeedbackList({
    per_page: 100,
    orderby: 'date',
    order: 'desc',
  });

  const sortedFeedback = useMemo(() => (
    [...feedback].sort((a, b) => {
      const statusA = a.meta?.status || 'new';
      const statusB = b.meta?.status || 'new';
      const rankA = statusSortOrder[statusA] ?? 999;
      const rankB = statusSortOrder[statusB] ?? 999;

      if (rankA !== rankB) {
        return rankA - rankB;
      }

      return new Date(b.date).getTime() - new Date(a.date).getTime();
    })
  ), [feedback]);

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['feedback'] });
  };

  const handleSubmit = async (data) => {
    await createFeedback.mutateAsync(data);
    setShowModal(false);
  };

  const handleStatusChange = useCallback((id, newStatus) => {
    updateFeedback.mutate({ id, data: { status: newStatus } });
  }, [updateFeedback]);

  const handlePriorityChange = useCallback((id, newPriority) => {
    updateFeedback.mutate({ id, data: { priority: newPriority } });
  }, [updateFeedback]);

  const columns = useMemo(() => [
    createColumn({
      id: 'id',
      header: 'ID',
      accessorFn: (row) => String(row.id),
      cell: ({ row }) => (
        <Link to={`/feedback/${row.original.id}`} className="text-electric-cyan dark:text-electric-cyan hover:underline font-medium">
          #{row.original.id}
        </Link>
      ),
      filterType: FILTER_TYPES.TEXT,
      size: 90,
    }),
    createColumn({
      id: 'type',
      header: 'Type',
      accessorFn: (row) => row.meta?.feedback_type || '',
      cell: ({ row }) => <TypeBadge type={row.original.meta?.feedback_type} />,
      filterType: FILTER_TYPES.SELECT,
      filterOptions: typeFilterOptions,
      size: 120,
    }),
    createColumn({
      id: 'project',
      header: 'Project',
      accessorFn: (row) => row.meta?.project || 'rondo-club',
      cell: ({ row }) => <ProjectBadge project={row.original.meta?.project} />,
      filterType: FILTER_TYPES.SELECT,
      filterOptions: projectFilterOptions,
      size: 130,
    }),
    createColumn({
      id: 'title',
      header: 'Titel',
      accessorFn: (row) => row.title || '',
      cell: ({ row }) => (
        <Link to={`/feedback/${row.original.id}`} className="text-gray-900 dark:text-gray-100 hover:text-electric-cyan dark:hover:text-electric-cyan font-medium">
          {row.original.title}
        </Link>
      ),
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Titel',
    }),
    createColumn({
      id: 'author',
      header: 'Auteur',
      accessorFn: (row) => row.author?.name || '',
      cell: ({ row }) => row.original.author?.name || '-',
      filterType: FILTER_TYPES.TEXT,
      filterLabel: 'Auteur',
      size: 180,
    }),
    createColumn({
      id: 'status',
      header: 'Status',
      accessorFn: (row) => row.meta?.status || 'new',
      cell: ({ row }) => {
        const value = row.original.meta?.status || 'new';
        if (!canManageFeedback) {
          return <StatusBadge status={value} />;
        }
        return (
          <select
            value={value}
            onChange={(e) => handleStatusChange(row.original.id, e.target.value)}
            className="text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded px-2 py-1"
            disabled={updateFeedback.isPending}
          >
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        );
      },
      filterType: FILTER_TYPES.SELECT,
      filterOptions: statusOptions,
      filterFn: (row, colId, value) => {
        if (!value) return true;
        if (value === 'open') return !closedStatuses.includes(row.getValue(colId));
        return row.getValue(colId) === value;
      },
      size: 160,
    }),
    createColumn({
      id: 'priority',
      header: 'Prioriteit',
      accessorFn: (row) => row.meta?.priority || 'medium',
      cell: ({ row }) => {
        const value = row.original.meta?.priority || 'medium';
        if (!canManageFeedback) {
          return priorityLabel(value);
        }
        return (
          <select
            value={value}
            onChange={(e) => handlePriorityChange(row.original.id, e.target.value)}
            className="text-xs border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded px-2 py-1"
            disabled={updateFeedback.isPending}
          >
            {priorityOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        );
      },
      filterType: FILTER_TYPES.SELECT,
      filterOptions: priorityOptions,
      size: 150,
    }),
    createColumn({
      id: 'date',
      header: 'Datum',
      accessorFn: (row) => new Date(row.date).getTime(),
      cell: ({ row }) => format(new Date(row.original.date), 'd MMM yyyy'),
      filterType: null,
      size: 130,
    }),
    createColumn({
      id: 'resolved_at',
      header: 'Datum opgelost',
      accessorFn: (row) => {
        const resolvedAt = row.meta?.resolved_at;
        return resolvedAt ? new Date(resolvedAt).getTime() : 0;
      },
      cell: ({ row }) => {
        const resolvedAt = row.original.meta?.resolved_at;
        return resolvedAt ? format(new Date(resolvedAt), 'd MMM yyyy') : '-';
      },
      filterType: null,
      size: 150,
    }),
    createColumn({
      id: 'pr',
      header: 'PR',
      accessorFn: (row) => row.meta?.pr_url || '',
      cell: ({ row }) => {
        const prUrl = row.original.meta?.pr_url;
        if (!prUrl) {
          return <span className="text-gray-400">-</span>;
        }
        return (
          <a
            href={prUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-electric-cyan dark:text-electric-cyan hover:underline"
            title="Open PR"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            PR
          </a>
        );
      },
      filterType: null,
      size: 90,
      defaultHidden: true,
    }),
  ], [canManageFeedback, handlePriorityChange, handleStatusChange, updateFeedback.isPending]);

  if (error) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Feedback kon niet worden geladen: {error.message}
        </p>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        <DataTable
          storageKey="feedback"
          data={sortedFeedback}
          columns={columns}
          isLoading={isLoading}
          emptyIcon={<MessageSquare className="w-8 h-8 text-gray-400 dark:text-gray-500" />}
          emptyTitle="Geen feedback gevonden"
          emptyDescription="Er is nog geen feedback of niets voldoet aan de gekozen filters."
          filters={filters}
          onFilterChange={handleFilterChange}
          onClearFilters={handleClearFilters}
          toolbarEnd={(
            <button
              onClick={() => setShowModal(true)}
              className="btn-primary text-sm gap-2"
            >
              <Plus className="w-4 h-4" />
              Submit feedback
            </button>
          )}
        />

        <FeedbackModal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          onSubmit={handleSubmit}
          isLoading={createFeedback.isPending}
          urlContext={location.pathname}
        />
      </div>
    </PullToRefreshWrapper>
  );
}
