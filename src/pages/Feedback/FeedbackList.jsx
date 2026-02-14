import { useState, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { MessageSquare, Bug, Lightbulb, Plus, Clock, Search } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useFeedbackList, useCreateFeedback } from '@/hooks/useFeedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import FeedbackModal from '@/components/FeedbackModal';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

// Status badge colors
const statusColors = {
  new: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
  in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
  in_review: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
  resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  declined: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400',
  needs_info: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

// Type badge colors
const typeColors = {
  bug: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  feature_request: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

// Status display labels
const statusLabels = {
  new: 'New',
  approved: 'Approved',
  in_progress: 'In Progress',
  in_review: 'In Review',
  resolved: 'Resolved',
  declined: 'Declined',
  needs_info: 'Needs Info',
};

// Type display labels
const typeLabels = {
  bug: 'Bug',
  feature_request: 'Feature Request',
};

// Project display labels
const projectLabels = {
  'rondo-club': 'Rondo Club',
  'rondo-sync': 'Rondo Sync',
  'website': 'Website',
};

// Project badge colors
const projectColors = {
  'rondo-club': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
  'rondo-sync': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
  'website': 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
};

export default function FeedbackList() {
  useDocumentTitle('Feedback');

  // URL-based filter state for persistence across refresh/navigation
  const [searchParams, setSearchParams] = useSearchParams();

  const typeFilter = searchParams.get('type') || '';
  // Default to 'open' when no status param; 'all' in URL means show everything (empty string for API)
  const rawStatus = searchParams.get('status');
  const statusFilter = rawStatus === 'all' ? '' : (rawStatus || 'open');
  const projectFilter = searchParams.get('project') || '';

  const updateSearchParams = useCallback((updates) => {
    setSearchParams(prev => {
      const next = new URLSearchParams(prev);
      Object.entries(updates).forEach(([key, value]) => {
        if (value === null || value === '' || value === undefined) {
          next.delete(key);
        } else {
          next.set(key, String(value));
        }
      });
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const setTypeFilter = useCallback((value) => {
    updateSearchParams({ type: value });
  }, [updateSearchParams]);

  const setStatusFilter = useCallback((value) => {
    if (value === 'open') {
      // 'open' is the default — remove param to keep URLs clean
      updateSearchParams({ status: null });
    } else if (value === '') {
      // "All" statuses — use 'all' sentinel in URL since empty string would be removed
      updateSearchParams({ status: 'all' });
    } else {
      updateSearchParams({ status: value });
    }
  }, [updateSearchParams]);

  const setProjectFilter = useCallback((value) => {
    updateSearchParams({ project: value });
  }, [updateSearchParams]);

  const [showModal, setShowModal] = useState(false);
  const queryClient = useQueryClient();

  // Fetch feedback with filters
  const { data: feedback, isLoading, error } = useFeedbackList({
    type: typeFilter || undefined,
    status: statusFilter || undefined,
    project: projectFilter || undefined,
  });
  const createFeedback = useCreateFeedback();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['feedback'] });
  };

  // Submit handler
  const handleSubmit = async (data) => {
    await createFeedback.mutateAsync(data);
    setShowModal(false);
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
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Failed to load feedback: {error.message}
        </p>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-brand-gradient">Feedback</h1>
        <button
          onClick={() => setShowModal(true)}
          className="btn-primary text-sm flex items-center gap-2"
        >
          <Plus className="w-4 h-4" />
          Submit feedback
        </button>
      </div>

      {/* Filter controls */}
      <div className="space-y-3">
        <div className="flex flex-wrap items-center gap-3">
          {/* Type filter */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
            <button
              onClick={() => setTypeFilter('')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                typeFilter === ''
                  ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              All Types
            </button>
            <button
              onClick={() => setTypeFilter('bug')}
              className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
                typeFilter === 'bug'
                  ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              <Bug className="w-3.5 h-3.5" />
              Bugs
            </button>
            <button
              onClick={() => setTypeFilter('feature_request')}
              className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
                typeFilter === 'feature_request'
                  ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              <Lightbulb className="w-3.5 h-3.5" />
              Features
            </button>
          </div>

          {/* Status filter */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
            <button
              onClick={() => setStatusFilter('open')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                statusFilter === 'open'
                  ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              All Open
            </button>
            <button
              onClick={() => setStatusFilter('')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                statusFilter === ''
                  ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              All
            </button>
            <button
              onClick={() => setStatusFilter('new')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                statusFilter === 'new'
                  ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              New
            </button>
            <button
              onClick={() => setStatusFilter('approved')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                statusFilter === 'approved'
                  ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              Approved
            </button>
            <button
              onClick={() => setStatusFilter('in_progress')}
              className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
                statusFilter === 'in_progress'
                  ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              <Clock className="w-3.5 h-3.5" />
              In Progress
            </button>
            <button
              onClick={() => setStatusFilter('in_review')}
              className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
                statusFilter === 'in_review'
                  ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              <Search className="w-3.5 h-3.5" />
              In Review
            </button>
            <button
              onClick={() => setStatusFilter('resolved')}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                statusFilter === 'resolved'
                  ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              Resolved
            </button>
          </div>
        </div>

        {/* Project filter (separate row) */}
        <div className="flex rounded-lg border border-gray-200 dark:border-gray-700 p-0.5 w-fit">
          <button
            onClick={() => setProjectFilter('')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              projectFilter === ''
                ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            }`}
          >
            All Projects
          </button>
          {Object.entries(projectLabels).map(([key, label]) => (
            <button
              key={key}
              onClick={() => setProjectFilter(key)}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                projectFilter === key
                  ? `${projectColors[key]}`
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Feedback list */}
      {!feedback || feedback.length === 0 ? (
        <div className="card p-12 text-center">
          <MessageSquare className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
          <h3 className="text-lg font-medium mb-1">No feedback yet</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Report bugs or request features.
          </p>
          <button onClick={() => setShowModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 mr-2" />
            Submit feedback
          </button>
        </div>
      ) : (
        <div className="card divide-y divide-gray-100 dark:divide-gray-700">
          {feedback.map((item) => (
            <Link
              key={item.id}
              to={`/feedback/${item.id}`}
              className="flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  {/* Type badge */}
                  <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[item.meta.feedback_type]}`}>
                    {item.meta.feedback_type === 'bug' ? (
                      <Bug className="w-3 h-3" />
                    ) : (
                      <Lightbulb className="w-3 h-3" />
                    )}
                    {typeLabels[item.meta.feedback_type]}
                  </span>
                  {/* Project badge (only show for non-default) */}
                  {item.meta.project && item.meta.project !== 'rondo-club' && (
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${projectColors[item.meta.project] || ''}`}>
                      {projectLabels[item.meta.project] || item.meta.project}
                    </span>
                  )}
                  {/* Status badge */}
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[item.meta.status]}`}>
                    {statusLabels[item.meta.status]}
                  </span>
                </div>
                <h3 className="font-medium text-gray-900 dark:text-gray-100 truncate">
                  {item.title}
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  #{item.id} - {format(new Date(item.date), 'dd-MM-yyyy')}
                </p>
              </div>
            </Link>
          ))}
        </div>
      )}

      {/* Submit Feedback Modal */}
      <FeedbackModal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        onSubmit={handleSubmit}
        isLoading={createFeedback.isPending}
      />
      </div>
    </PullToRefreshWrapper>
  );
}
