import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, ShieldAlert, Loader2, ArrowUp, ArrowDown, Eye } from 'lucide-react';
import { useFeedbackList, useUpdateFeedback } from '@/hooks/useFeedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from 'date-fns';

// Status options with badge colors
const STATUS_OPTIONS = [
  { value: 'new', label: 'New', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
  { value: 'in_progress', label: 'In Progress', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
  { value: 'resolved', label: 'Resolved', color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
  { value: 'declined', label: 'Declined', color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' },
];

// Priority options with text colors
const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Low', color: 'text-gray-600 dark:text-gray-400' },
  { value: 'medium', label: 'Medium', color: 'text-blue-600 dark:text-blue-400' },
  { value: 'high', label: 'High', color: 'text-orange-600 dark:text-orange-400' },
  { value: 'critical', label: 'Critical', color: 'text-red-600 dark:text-red-400' },
];

// Type filter options
const TYPE_OPTIONS = [
  { value: '', label: 'All Types' },
  { value: 'bug', label: 'Bugs' },
  { value: 'feature_request', label: 'Features' },
];

// Status filter options (includes "all" option)
const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'All Statuses' },
  ...STATUS_OPTIONS,
];

// Priority filter options (includes "all" option)
const PRIORITY_FILTER_OPTIONS = [
  { value: '', label: 'All Priorities' },
  ...PRIORITY_OPTIONS,
];

// Access denied component
function AccessDenied() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="card p-8 text-center">
        <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
        <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Access Denied</h1>
        <p className="text-gray-600 dark:text-gray-300 mb-6">
          You don&apos;t have permission to manage feedback. This feature is only available to administrators.
        </p>
        <Link to="/settings" className="btn-primary">
          Back to Settings
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
    feature_request: { label: 'Feature', className: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' },
  };
  const config = typeConfig[type] || { label: type, className: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };

  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.className}`}>
      {config.label}
    </span>
  );
}

export default function FeedbackManagement() {
  useDocumentTitle('Feedback Management - Settings');
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;

  // Filter and sort state
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [sortField, setSortField] = useState('date');
  const [sortOrder, setSortOrder] = useState('desc');

  // Fetch feedback with filters
  const { data: feedback = [], isLoading } = useFeedbackList({
    type: typeFilter || undefined,
    status: statusFilter || undefined,
    priority: priorityFilter || undefined,
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
        <Loader2 className="w-8 h-8 animate-spin text-accent-600 dark:text-accent-400" />
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
          <span className="hidden md:inline">Back to Settings</span>
        </Link>
        <h1 className="text-2xl font-semibold dark:text-gray-50">Feedback Management</h1>
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
              Priority:
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
        </div>
      </div>

      {/* Feedback table */}
      {feedback.length === 0 ? (
        <div className="card p-8 text-center">
          <p className="text-gray-600 dark:text-gray-300">No feedback found matching your filters.</p>
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
                  <SortableHeader
                    field="title"
                    label="Title"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Author
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
                    label="Priority"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <SortableHeader
                    field="date"
                    label="Date"
                    currentField={sortField}
                    currentOrder={sortOrder}
                    onSort={handleSort}
                  />
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {feedback.map((item, index) => {
                  const statusOption = STATUS_OPTIONS.find((s) => s.value === item.status) || STATUS_OPTIONS[0];
                  const priorityOption = PRIORITY_OPTIONS.find((p) => p.value === item.priority) || PRIORITY_OPTIONS[0];

                  return (
                    <tr
                      key={item.id}
                      className={index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-800/50'}
                    >
                      <td className="px-4 py-3 whitespace-nowrap">
                        <TypeBadge type={item.type} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-sm font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">
                          {item.title}
                        </div>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                          {item.author_name || 'Unknown'}
                        </span>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <select
                          value={item.status}
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
                          value={item.priority}
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
                        <Link
                          to={`/feedback/${item.id}`}
                          className="inline-flex items-center gap-1 text-sm text-accent-600 dark:text-accent-400 hover:text-accent-800 dark:hover:text-accent-300"
                        >
                          <Eye className="w-4 h-4" />
                          View
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
        Showing {feedback.length} feedback item{feedback.length !== 1 ? 's' : ''}
      </div>
    </div>
  );
}
