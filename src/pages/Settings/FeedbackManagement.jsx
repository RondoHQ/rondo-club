import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, ShieldAlert, Loader2, ArrowUp, ArrowDown, Eye, ExternalLink } from 'lucide-react';
import { useFeedbackList, useUpdateFeedback } from '@/hooks/useFeedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';

// Status options with badge colors
const STATUS_OPTIONS = [
  { value: 'new', label: 'Nieuw', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
  { value: 'in_progress', label: 'In behandeling', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
  { value: 'in_review', label: 'In review', color: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' },
  { value: 'needs_info', label: 'Info nodig', color: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
  { value: 'resolved', label: 'Opgelost', color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
  { value: 'declined', label: 'Afgewezen', color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' },
];

// Priority options with text colors
const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Laag', color: 'text-gray-600 dark:text-gray-400' },
  { value: 'medium', label: 'Gemiddeld', color: 'text-blue-600 dark:text-blue-400' },
  { value: 'high', label: 'Hoog', color: 'text-orange-600 dark:text-orange-400' },
  { value: 'critical', label: 'Kritiek', color: 'text-red-600 dark:text-red-400' },
];

// Project options with badge colors
const PROJECT_OPTIONS = [
  { value: 'rondo-club', label: 'Rondo Club', className: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200' },
  { value: 'rondo-sync', label: 'Rondo Sync', className: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' },
  { value: 'website', label: 'Website', className: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200' },
];

// Type filter options
const TYPE_OPTIONS = [
  { value: '', label: 'Alle types' },
  { value: 'bug', label: 'Bugs' },
  { value: 'feature_request', label: 'Functies' },
];

// Project filter options
const PROJECT_FILTER_OPTIONS = [
  { value: '', label: 'Alle projecten' },
  ...PROJECT_OPTIONS,
];

// Status filter options (includes "all" option)
const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'Alle statussen' },
  ...STATUS_OPTIONS,
];

// Priority filter options (includes "all" option)
const PRIORITY_FILTER_OPTIONS = [
  { value: '', label: 'Alle prioriteiten' },
  ...PRIORITY_OPTIONS,
];

// Access denied component
function AccessDenied() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="card p-8 text-center">
        <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
        <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Toegang geweigerd</h1>
        <p className="text-gray-600 dark:text-gray-300 mb-6">
          Je hebt geen toestemming om feedback te beheren. Deze functie is alleen beschikbaar voor beheerders.
        </p>
        <Link to="/settings" className="btn-primary">
          Terug naar Instellingen
        </Link>
      </div>
    </div>
  );
}

// Sortable header component
function SortableHeader({ field, label, currentField, currentOrder, onSort }) {
  const isActive = currentField === field;
  return (
    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (currentOrder === 'asc' ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />)}
      </button>
    </th>
  );
}

// Type badge component
function TypeBadge({ type }) {
  const typeConfig = {
    bug: { label: 'Bug', className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    feature_request: { label: 'Functie', className: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' },
  };
  const config = typeConfig[type] || { label: type, className: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };

  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.className}`}>
      {config.label}
    </span>
  );
}

// Project badge component
function ProjectBadge({ project }) {
  const config = PROJECT_OPTIONS.find((p) => p.value === project) || { label: project || 'Rondo Club', className: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };

  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.className}`}>
      {config.label}
    </span>
  );
}

export default function FeedbackManagement() {
  useDocumentTitle('Feedbackbeheer - Instellingen');
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  // Filter and sort state
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [projectFilter, setProjectFilter] = useState('');
  const [sortField, setSortField] = useState('date');
  const [sortOrder, setSortOrder] = useState('desc');

  // Fetch feedback with filters
  const { data: feedback = [], isLoading } = useFeedbackList({
    type: typeFilter || undefined,
    status: statusFilter || undefined,
    priority: priorityFilter || undefined,
    project: projectFilter || undefined,
    orderby: sortField,
    order: sortOrder,
    per_page: 100,
  });

  const updateFeedback = useUpdateFeedback();

  // Admin check
  if (!isAdmin) {
    return <AccessDenied />;
  }

  // Handler for status change
  const handleStatusChange = (id, newStatus) => {
    updateFeedback.mutate({ id, data: { status: newStatus } });
  };

  // Handler for priority change
  const handlePriorityChange = (id, newPriority) => {
    updateFeedback.mutate({ id, data: { priority: newPriority } });
  };

  // Sort handler
  const handleSort = (field) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('desc');
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-electric-cyan dark:text-electric-cyan" />
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Link
          to="/settings"
          className="btn-secondary flex items-center gap-2"
        >
          <ArrowLeft className="w-4 h-4" />
          <span className="hidden md:inline">Terug naar Instellingen</span>
        </Link>
        <h1 className="text-2xl font-semibold text-brand-gradient">Feedbackbeheer</h1>
      </div>

      {/* Filters */}
      <div className="card p-4">
        <div className="flex flex-wrap gap-4">
          <div className="flex items-center gap-2">
            <label htmlFor="typeFilter" className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Type:
            </label>
            <select
              id="typeFilter"
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="input-field w-auto min-w-[120px]"
            >
              {TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2">
            <label htmlFor="statusFilter" className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Status:
            </label>
            <select
              id="statusFilter"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="input-field w-auto min-w-[140px]"
            >
              {STATUS_FILTER_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2">
            <label htmlFor="priorityFilter" className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Prioriteit:
            </label>
            <select
              id="priorityFilter"
              value={priorityFilter}
              onChange={(e) => setPriorityFilter(e.target.value)}
              className="input-field w-auto min-w-[140px]"
            >
              {PRIORITY_FILTER_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2">
            <label htmlFor="projectFilter" className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Project:
            </label>
            <select
              id="projectFilter"
              value={projectFilter}
              onChange={(e) => setProjectFilter(e.target.value)}
              className="input-field w-auto min-w-[140px]"
            >
              {PROJECT_FILTER_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Feedback table */}
      {feedback.length === 0 ? (
        <div className="card p-8 text-center">
          <p className="text-gray-600 dark:text-gray-300">Geen feedback gevonden met deze filters.</p>
        </div>
      ) : (
        <div className="card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Type
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Project
                  </th>
                  <SortableHeader
                    field="title"
                    label="Titel"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Auteur
                  </th>
                  <SortableHeader
                    field="status"
                    label="Status"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <SortableHeader
                    field="priority"
                    label="Prioriteit"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <SortableHeader
                    field="date"
                    label="Datum"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    PR
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Acties
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {feedback.map((item, index) => {
                  const itemStatus = item.meta?.status || item.status || 'new';
                  const itemPriority = item.meta?.priority || item.priority || 'medium';
                  const itemType = item.meta?.feedback_type || item.type || '';
                  const itemProject = item.meta?.project || 'rondo-club';
                  const itemAuthor = item.author?.name || item.author_name || 'Onbekend';
                  const statusOption = STATUS_OPTIONS.find((s) => s.value === itemStatus) || STATUS_OPTIONS[0];
                  const priorityOption = PRIORITY_OPTIONS.find((p) => p.value === itemPriority) || PRIORITY_OPTIONS[0];

                  return (
                    <tr
                      key={item.id}
                      className={index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-800/50'}
                    >
                      <td className="px-4 py-3 whitespace-nowrap">
                        <TypeBadge type={itemType} />
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <ProjectBadge project={itemProject} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">
                          {item.title}
                        </div>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                          {itemAuthor}
                        </span>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <select
                          value={itemStatus}
                          onChange={(e) => handleStatusChange(item.id, e.target.value)}
                          className={`text-xs font-medium px-2 py-1 rounded border-0 cursor-pointer ${statusOption.color}`}
                        >
                          {STATUS_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                              {option.label}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <select
                          value={itemPriority}
                          onChange={(e) => handlePriorityChange(item.id, e.target.value)}
                          className={`text-xs font-medium px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 cursor-pointer ${priorityOption.color}`}
                        >
                          {PRIORITY_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                              {option.label}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {item.date ? format(new Date(item.date), 'MMM d, yyyy') : '-'}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        {item.meta?.pr_url ? (
                          <a
                            href={item.meta.pr_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-sm text-electric-cyan hover:text-deep-midnight dark:hover:text-electric-cyan-light"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                            PR
                          </a>
                        ) : (
                          <span className="text-sm text-gray-400">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <Link
                          to={`/feedback/${item.id}`}
                          className="inline-flex items-center gap-1 text-sm text-electric-cyan dark:text-electric-cyan hover:text-deep-midnight dark:hover:text-electric-cyan-light"
                        >
                          <Eye className="w-4 h-4" />
                          Bekijken
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Summary */}
      <div className="text-sm text-gray-500 dark:text-gray-400">
        {feedback.length} feedback-item{feedback.length !== 1 ? 's' : ''} weergegeven
      </div>
    </div>
  );
}
