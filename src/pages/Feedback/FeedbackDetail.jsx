import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Bug, Lightbulb, Clock, User, Monitor, Link as LinkIcon, Pencil, ExternalLink, MessageCircle, Bot, Send, CircleCheck, CircleX } from 'lucide-react';
import { useFeedback, useUpdateFeedback, useFeedbackComments, useCreateFeedbackComment } from '@/hooks/useFeedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { format } from '@/utils/dateFormat';
import FeedbackEditModal from '@/components/FeedbackEditModal';

// Status badge colors (same as FeedbackList)
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
  bug: 'Bug Report',
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

const outcomeConfig = {
  resolved: {
    title: 'Resolution',
    dateLabel: 'Resolved on',
    summaryKey: 'resolution_summary',
    dateKey: 'resolved_at',
    icon: CircleCheck,
    cardClass: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20',
    iconClass: 'text-green-600 dark:text-green-400',
  },
  declined: {
    title: 'Reason for declining',
    dateLabel: 'Declined on',
    summaryKey: 'decline_reason',
    dateKey: 'declined_at',
    icon: CircleX,
    cardClass: 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50',
    iconClass: 'text-gray-500 dark:text-gray-400',
  },
};

function FeedbackOutcome({ meta }) {
  const config = outcomeConfig[meta.status];
  const summary = config ? meta[config.summaryKey] : '';

  if (!config || !summary) {
    return null;
  }

  const Icon = config.icon;
  const outcomeDate = meta[config.dateKey];

  return (
    <div className={`rounded-lg border p-6 ${config.cardClass}`}>
      <div className="flex items-start gap-3">
        <Icon className={`mt-0.5 h-5 w-5 flex-shrink-0 ${config.iconClass}`} />
        <div className="min-w-0">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{config.title}</h2>
          {outcomeDate ? (
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {config.dateLabel} {format(new Date(outcomeDate), 'MMM d, yyyy')}
            </p>
          ) : null}
          <p className="mt-3 whitespace-pre-wrap text-gray-700 dark:text-gray-300">{summary}</p>
        </div>
      </div>
    </div>
  );
}

export default function FeedbackDetail() {
  const { id } = useParams();
  const { data: feedback, isLoading, error } = useFeedback(id);
  const { data: comments = [] } = useFeedbackComments(id);
  const { data: currentUser } = useCurrentUser();
  const updateFeedback = useUpdateFeedback();
  const createComment = useCreateFeedbackComment();
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [replyText, setReplyText] = useState('');
  useDocumentTitle(feedback?.title || 'Feedback');

  const isAdmin = currentUser?.is_admin ?? false;
  const canEditFeedback = isAdmin || (currentUser?.id && feedback?.author?.id && currentUser.id === feedback.author.id);

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

  const handleReply = (e) => {
    e.preventDefault();
    if (!replyText.trim()) return;
    createComment.mutate(
      { id, content: replyText.trim() },
      {
        onSuccess: () => setReplyText(''),
      }
    );
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
        <Link to="/feedback" className="text-electric-cyan dark:text-electric-cyan hover:underline mt-4 inline-block">
          Back to feedback list
        </Link>
      </div>
    );
  }

  if (!feedback) {
    return (
      <div className="card p-8 text-center">
        <p className="text-gray-500 dark:text-gray-400">Feedback not found.</p>
        <Link to="/feedback" className="text-electric-cyan dark:text-electric-cyan hover:underline mt-4 inline-block">
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

      {/* Needs Info banner */}
      {feedback.meta.status === 'needs_info' && (
        <div className="rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20 p-4">
          <div className="flex items-center gap-2 text-orange-700 dark:text-orange-400">
            <MessageCircle className="w-5 h-5 flex-shrink-0" />
            <p className="font-medium">Waiting for your response</p>
          </div>
          <p className="text-sm text-orange-600 dark:text-orange-400/80 mt-1 ml-7">
            The agent needs more information to continue. Reply below to resume processing.
          </p>
        </div>
      )}

      {/* Header */}
      <div className="card p-6">
        <div className="flex items-start gap-4">
          <div className="flex-1">
            {/* Badges */}
            <div className="flex items-center gap-2 mb-3">
              <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${typeColors[feedback.meta.feedback_type]}`}>
                {feedback.meta.feedback_type === 'bug' ? (
                  <Bug className="w-3 h-3" />
                ) : (
                  <Lightbulb className="w-3 h-3" />
                )}
                {typeLabels[feedback.meta.feedback_type]}
              </span>
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[feedback.meta.status]}`}>
                {statusLabels[feedback.meta.status]}
              </span>
              {feedback.meta.project && (
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${projectColors[feedback.meta.project] || ''}`}>
                  {projectLabels[feedback.meta.project] || feedback.meta.project}
                </span>
              )}
            </div>

            {/* Title */}
            <h1 className="text-2xl font-bold text-brand-gradient">
              {feedback.title}
            </h1>
          </div>

          {/* Edit button */}
          {canEditFeedback && (
            <button
              onClick={() => setIsEditModalOpen(true)}
              className="btn-tertiary gap-2"
            >
              <Pencil className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>
      </div>

      <FeedbackOutcome meta={feedback.meta} />

      {/* PR Link */}
      {feedback.meta.pr_url && (
        <div className="card p-4">
          <div className="flex items-center gap-3">
            <ExternalLink className="w-5 h-5 text-electric-cyan" />
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Pull Request</h3>
              <a
                href={feedback.meta.pr_url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-electric-cyan hover:underline text-sm"
              >
                {feedback.meta.pr_url}
              </a>
            </div>
          </div>
        </div>
      )}

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
      {feedback.meta.feedback_type === 'bug' && (
        <div className="card p-6 space-y-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Bug Details</h2>

          {feedback.meta.steps_to_reproduce && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Steps to Reproduce</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.meta.steps_to_reproduce}
              </p>
            </div>
          )}

          {feedback.meta.expected_behavior && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Expected Behavior</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.meta.expected_behavior}
              </p>
            </div>
          )}

          {feedback.meta.actual_behavior && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Actual Behavior</h3>
              <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                {feedback.meta.actual_behavior}
              </p>
            </div>
          )}

          {!feedback.meta.steps_to_reproduce && !feedback.meta.expected_behavior && !feedback.meta.actual_behavior && (
            <p className="text-gray-500 dark:text-gray-400 text-sm italic">No additional bug details provided.</p>
          )}
        </div>
      )}

      {/* Feature request field */}
      {feedback.meta.feedback_type === 'feature_request' && feedback.meta.use_case && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Use Case</h2>
          <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
            {feedback.meta.use_case}
          </p>
        </div>
      )}

      {/* System info */}
      {(feedback.meta.browser_info || feedback.meta.app_version || feedback.meta.url_context) && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">System Information</h2>
          <div className="space-y-3">
            {feedback.meta.browser_info && (
              <div className="flex items-start gap-3">
                <Monitor className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Browser</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm break-all">
                    {feedback.meta.browser_info}
                  </p>
                </div>
              </div>
            )}

            {feedback.meta.app_version && (
              <div className="flex items-start gap-3">
                <Clock className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">App Version</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm">
                    {feedback.meta.app_version}
                  </p>
                </div>
              </div>
            )}

            {feedback.meta.url_context && (
              <div className="flex items-start gap-3">
                <LinkIcon className="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" />
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">URL Context</h3>
                  <p className="text-gray-700 dark:text-gray-300 text-sm break-all">
                    {feedback.meta.url_context}
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Conversation Thread */}
      {(comments.length > 0 || feedback.meta.status === 'needs_info') && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <MessageCircle className="w-5 h-5" />
            Conversation
          </h2>

          {/* Comments list */}
          {comments.length > 0 && (
            <div className="space-y-4 mb-6">
              {comments.map((comment) => (
                <div
                  key={comment.id}
                  className={`flex gap-3 ${comment.author_type === 'agent' ? '' : ''}`}
                >
                  <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
                    comment.author_type === 'agent'
                      ? 'bg-electric-cyan/10 text-electric-cyan'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                  }`}>
                    {comment.author_type === 'agent' ? (
                      <Bot className="w-4 h-4" />
                    ) : (
                      <User className="w-4 h-4" />
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {comment.author_type === 'agent' ? 'Agent' : comment.author_name}
                      </span>
                      <span className="text-xs text-gray-500 dark:text-gray-400">
                        {format(new Date(comment.created), 'MMM d, yyyy \'at\' h:mm a')}
                      </span>
                    </div>
                    <p className="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                      {comment.content}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Reply form (shown when needs_info) */}
          {feedback.meta.status === 'needs_info' && (
            <form onSubmit={handleReply} className="flex gap-3">
              <input
                type="text"
                value={replyText}
                onChange={(e) => setReplyText(e.target.value)}
                placeholder="Type your reply..."
                className="input-field flex-1"
              />
              <button
                type="submit"
                disabled={!replyText.trim() || createComment.isPending}
                className="btn-primary gap-2"
              >
                <Send className="w-4 h-4" />
                Reply
              </button>
            </form>
          )}
        </div>
      )}

      {/* Metadata footer */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Metadata</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {feedback.author?.name && (
            <div className="flex items-center gap-3">
              <User className="w-5 h-5 text-gray-400" />
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted by</h3>
                <p className="text-gray-700 dark:text-gray-300">{feedback.author.name}</p>
              </div>
            </div>
          )}

          {feedback.date && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted on</h3>
              <p className="text-gray-700 dark:text-gray-300">
                {format(new Date(feedback.date), 'MMM d, yyyy \'at\' h:mm a')}
              </p>
            </div>
          )}

          {feedback.modified && feedback.modified !== feedback.date && (
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Last updated</h3>
              <p className="text-gray-700 dark:text-gray-300">
                {format(new Date(feedback.modified), 'MMM d, yyyy \'at\' h:mm a')}
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
        isAdmin={isAdmin}
      />
    </div>
  );
}
