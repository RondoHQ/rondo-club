import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Bug, Lightbulb, Clock, User, Monitor, Link as LinkIcon, Paperclip, Pencil } from 'lucide-react';
import { useFeedback, useUpdateFeedback } from '@/hooks/useFeedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from 'date-fns';
import FeedbackEditModal from '@/components/FeedbackEditModal';

// Status badge colors (same as FeedbackList)
const statusColors = {
  new: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
  resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  declined: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400',
};

// Type badge colors
const typeColors = {
  bug: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  feature_request: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

// Status display labels
const statusLabels = {
  new: 'New',
  in_progress: 'In Progress',
  resolved: 'Resolved',
  declined: 'Declined',
};

// Type display labels
const typeLabels = {
  bug: 'Bug Report',
  feature_request: 'Feature Request',
};

export default function FeedbackDetail() {
  const { id } = useParams();
  const { data: feedback, isLoading, error } = useFeedback(id);
  const updateFeedback = useUpdateFeedback();
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  useDocumentTitle(feedback?.title || 'Feedback');

  const handleEditSubmit = (data) => {
    updateFeedback.mutate(
      { id, data },
      {
        onSuccess: () => {
          setIsEditModalOpen(false);
        },
      }
    );
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Failed to load feedback: {error.message}
        </p>
        <Link to="/feedback" className="text-accent-600 dark:text-accent-400 hover:underline mt-4 inline-block">
          Back to feedback list
        </Link>
      </div>
    );
  }

  if (!feedback) {
    return (
      <div className="card p-8 text-center">
        <p className="text-gray-500 dark:text-gray-400">Feedback not found.</p>
        <Link to="/feedback" className="text-accent-600 dark:text-accent-400 hover:underline mt-4 inline-block">
          Back to feedback list
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Back link */}
      <Link
        to="/feedback"
        className="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
      >
        <ArrowLeft className="w-4 h-4 mr-1" />
        Back to feedback
      </Link>

      {/* Header */}
      <div className="card p-6">
        <div className="flex items-start gap-4">
          <div className="flex-1">
            {/* Badges */}
            <div className="flex items-center gap-2 mb-3">
              <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[feedback.feedback_type]}`}>
                {feedback.feedback_type === 'bug' ? (
                  <Bug className="w-3 h-3" />
                ) : (
                  <Lightbulb className="w-3 h-3" />
                )}
                {typeLabels[feedback.feedback_type]}
              </span>
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[feedback.status]}`}>
                {statusLabels[feedback.status]}
              </span>
            </div>

            {/* Title */}
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
              {feedback.title}
            </h1>
          </div>

          {/* Edit button */}
          <button
            onClick={() => setIsEditModalOpen(true)}
            className="btn-secondary flex items-center gap-2"
          >
            <Pencil className="w-4 h-4" />
            Edit
          </button>
        </div>
      </div>

      {/* Description */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Description</h2>
        <div className="prose prose-sm dark:prose-invert max-w-none">
          <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
            {feedback.content}
          </p>
        </div>
      </div>

      {/* Bug-specific fields */}
      {feedback.feedback_type === 'bug' && (
        <div className="card p-6 space-y-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Bug Details</h2>

          {feedback.steps_to_reproduce && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Steps to Reproduce</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.steps_to_reproduce}
              </p>
            </div>
          )}

          {feedback.expected_behavior && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Expected Behavior</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.expected_behavior}
              </p>
            </div>
          )}

          {feedback.actual_behavior && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Actual Behavior</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.actual_behavior}
              </p>
            </div>
          )}

          {!feedback.steps_to_reproduce && !feedback.expected_behavior && !feedback.actual_behavior && (
            <p className="text-gray-500 dark:text-gray-400 text-sm italic">No additional bug details provided.</p>
          )}
        </div>
      )}

      {/* Feature request field */}
      {feedback.feedback_type === 'feature_request' && feedback.use_case && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Use Case</h2>
          <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
            {feedback.use_case}
          </p>
        </div>
      )}

      {/* System info */}
      {(feedback.browser_info || feedback.app_version || feedback.url_context) && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">System Information</h2>
          <div className="space-y-3">
            {feedback.browser_info && (
              <div className="flex items-start gap-3">
                <Monitor className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Browser</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm break-all">
                    {feedback.browser_info}
                  </p>
                </div>
              </div>
            )}

            {feedback.app_version && (
              <div className="flex items-start gap-3">
                <Clock className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">App Version</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm">
                    {feedback.app_version}
                  </p>
                </div>
              </div>
            )}

            {feedback.url_context && (
              <div className="flex items-start gap-3">
                <LinkIcon className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">URL Context</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm break-all">
                    {feedback.url_context}
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Attachments */}
      {feedback.attachments && feedback.attachments.length > 0 && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <Paperclip className="w-5 h-5" />
            Attachments
          </h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {feedback.attachments.map((attachment, index) => (
              <a
                key={attachment.id || index}
                href={attachment.url}
                target="_blank"
                rel="noopener noreferrer"
                className="group block"
              >
                <div className="aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group-hover:border-accent-500 transition-colors">
                  <img
                    src={attachment.thumbnail || attachment.url}
                    alt={attachment.title || `Attachment ${index + 1}`}
                    className="w-full h-full object-cover"
                  />
                </div>
                {attachment.title && (
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                    {attachment.title}
                  </p>
                )}
              </a>
            ))}
          </div>
        </div>
      )}

      {/* Metadata footer */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Metadata</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {feedback.author_name && (
            <div className="flex items-center gap-3">
              <User className="w-5 h-5 text-gray-400" />
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted by</h3>
                <p className="text-gray-700 dark:text-gray-300">{feedback.author_name}</p>
              </div>
            </div>
          )}

          {feedback.created_at && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted on</h3>
              <p className="text-gray-700 dark:text-gray-300">
                {format(new Date(feedback.created_at), 'MMM d, yyyy \'at\' h:mm a')}
              </p>
            </div>
          )}

          {feedback.modified_at && feedback.modified_at !== feedback.created_at && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Last updated</h3>
              <p className="text-gray-700 dark:text-gray-300">
                {format(new Date(feedback.modified_at), 'MMM d, yyyy \'at\' h:mm a')}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Edit Modal */}
      <FeedbackEditModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onSubmit={handleEditSubmit}
        isLoading={updateFeedback.isPending}
        feedback={feedback}
      />
    </div>
  );
}
