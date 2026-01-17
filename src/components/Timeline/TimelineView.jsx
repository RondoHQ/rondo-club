import { useMemo } from 'react';
import { format } from 'date-fns';
import {
  Phone, Mail, Users, Coffee, Utensils, FileText, Circle, MessageCircle, Video,
  CheckSquare2, Square, Pencil, Trash2, Link as LinkIcon, Lock, Globe, Clock
} from 'lucide-react';
import { Link } from 'react-router-dom';
import {
  groupTimelineByDate,
  formatTimelineDate,
  getActivityTypeIcon,
  getActivityTypeLabel,
  isTodoOverdue,
  getTodoStatusClass,
  getAwaitingDays,
  getAwaitingUrgencyClass,
} from '@/utils/timeline';

const ICON_MAP = {
  Phone,
  Mail,
  Users,
  Coffee,
  Utensils,
  FileText,
  Circle,
  MessageCircle,
  Video,
};

export default function TimelineView({
  timeline,
  onEdit,
  onDelete,
  onToggleTodo,
  personId,
  allPeople = [],
}) {
  const groupedTimeline = useMemo(() => {
    return groupTimelineByDate(timeline || []);
  }, [timeline]);

  const getPersonById = (id) => {
    return allPeople.find(p => p.id.toString() === id.toString());
  };

  const getIconForItem = (item) => {
    if (item.type === 'todo') {
      if (item.status === 'completed') return CheckSquare2;
      if (item.status === 'awaiting') return Clock;
      return Square;
    }
    if (item.type === 'activity' && item.activity_type) {
      const iconName = getActivityTypeIcon(item.activity_type);
      return ICON_MAP[iconName] || Circle;
    }
    if (item.type === 'note') {
      return FileText;
    }
    return Circle;
  };

  const renderTimelineItem = (item) => {
    const Icon = getIconForItem(item);
    const isTodo = item.type === 'todo';
    const isActivity = item.type === 'activity';
    const isNote = item.type === 'note';
    
    // Combine date and time for proper relative time calculation
    let displayDateTime = item.activity_date || item.created;
    if (item.activity_date && item.activity_time) {
      displayDateTime = `${item.activity_date}T${item.activity_time}`;
    }
    const formattedDate = formatTimelineDate(displayDateTime);
    
    const todoClasses = getTodoStatusClass(item);
    const isOverdue = isTodo && isTodoOverdue(item);

    return (
      <div key={item.id} className={`relative ${isTodo ? 'pl-0' : 'pl-8'} pb-6 group`}>
        {/* Timeline dot */}
        {!isTodo && (
          <div className="absolute left-0 top-1">
            <div className={`w-4 h-4 rounded-full border-2 ${
              isActivity
                ? 'bg-blue-500 border-blue-500'
                : 'bg-gray-400 border-gray-400'
            }`} />
          </div>
        )}

        {/* Content - shared notes get a subtle left border */}
        <div className={`${todoClasses} ${isNote && item.visibility === 'shared' ? 'border-l-2 border-blue-200 pl-2' : ''}`}>
          <div className="flex items-start justify-between">
            <div className="flex-1 min-w-0">
              {/* Date line - only for notes and activities, not todos */}
              {!isTodo && (
                <div className="flex items-center gap-2 mb-1">
                  <Icon className={`w-4 h-4 flex-shrink-0 ${
                    isActivity
                      ? 'text-blue-600 dark:text-blue-400'
                      : 'text-gray-500 dark:text-gray-400'
                  }`} />
                  {isActivity && item.activity_type && (
                    <span className="text-xs font-medium text-gray-600 dark:text-gray-300">
                      {getActivityTypeLabel(item.activity_type)}
                    </span>
                  )}
                  <span className="text-xs text-gray-400 dark:text-gray-500">•</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">{formattedDate}</span>
                  {/* Note visibility indicator */}
                  {isNote && (
                    <span
                      className="ml-1"
                      title={item.visibility === 'shared' ? 'Shared note' : 'Private note - only you can see this'}
                    >
                      {item.visibility === 'shared' ? (
                        <Globe className="w-3 h-3 text-blue-500" />
                      ) : (
                        <Lock className="w-3 h-3 text-gray-400" />
                      )}
                    </span>
                  )}
                </div>
              )}
              
                <div className="flex items-start gap-2">
                {isTodo && (
                  <button
                    onClick={() => onToggleTodo && onToggleTodo(item)}
                    className="mt-0.5 flex-shrink-0"
                    title={item.status === 'completed' ? 'Reopen' : item.status === 'awaiting' ? 'Mark complete' : 'Complete'}
                  >
                    {item.status === 'completed' ? (
                      <CheckSquare2 className="w-5 h-5 text-accent-600" />
                    ) : item.status === 'awaiting' ? (
                      <Clock className="w-5 h-5 text-orange-500" />
                    ) : (
                      <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600' : 'text-gray-400'}`} />
                    )}
                  </button>
                )}
                {/* Render rich text content for notes/activities, plain text for todos */}
                {(isNote || isActivity) && item.content?.includes('<') ? (
                  <div
                    className={`text-sm flex-1 timeline-content ${item.status === 'completed' ? 'line-through opacity-60' : ''}`}
                    dangerouslySetInnerHTML={{ __html: item.content }}
                  />
                ) : (
                  <p className={`text-sm flex-1 ${item.status === 'completed' ? 'line-through opacity-60' : item.status === 'awaiting' ? 'text-orange-700' : ''}`}>
                    {item.content}
                  </p>
                )}
              </div>

              {/* Activity participants */}
              {isActivity && item.participants && item.participants.length > 0 && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">With:</span>
                  {item.participants.map((participantId) => {
                    const participant = getPersonById(participantId);
                    if (!participant) return null;
                    return (
                      <Link
                        key={participantId}
                        to={`/people/${participantId}`}
                        className="inline-flex items-center gap-1 text-xs text-accent-600 hover:text-accent-700 hover:underline"
                      >
                        <LinkIcon className="w-3 h-3" />
                        {participant.name}
                      </Link>
                    );
                  })}
                </div>
              )}

              {/* Todo due date - only show for open todos */}
              {isTodo && item.due_date && item.status === 'open' && (
                <div className="mt-2">
                  <span className={`text-xs ${
                    isOverdue ? 'text-red-600 font-medium' : 'text-gray-500 dark:text-gray-400'
                  }`}>
                    Due: {format(new Date(item.due_date), 'MMM d, yyyy')}
                    {isOverdue && ' (overdue)'}
                  </span>
                </div>
              )}

              {/* Awaiting response indicator */}
              {isTodo && item.status === 'awaiting' && (
                <div className="mt-2">
                  <span className={`text-xs px-2 py-0.5 rounded-full inline-flex items-center gap-1 ${getAwaitingUrgencyClass(getAwaitingDays(item))}`}>
                    <Clock className="w-3 h-3" />
                    {getAwaitingDays(item) === 0 ? 'Waiting since today' : `Waiting ${getAwaitingDays(item)}d`}
                  </span>
                </div>
              )}
            </div>

            {/* Actions */}
            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
              {onEdit && (
                <button
                  onClick={() => onEdit(item)}
                  className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                  title="Edit"
                >
                  <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" />
                </button>
              )}
              {onDelete && (
                <button
                  onClick={() => onDelete(item)}
                  className="p-1 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                  title="Delete"
                >
                  <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400" />
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderGroup = (title, items) => {
    if (!items || items.length === 0) return null;

    return (
      <div className="mb-6">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wide">
          {title}
        </h3>
        <div className="relative pl-4 border-l-2 border-gray-200 dark:border-gray-700">
          {items.map(renderTimelineItem)}
        </div>
      </div>
    );
  };

  // Only check for notes and activities (todos are shown in sidebar)
  const hasAnyItems = groupedTimeline.today.length > 0 ||
                      groupedTimeline.yesterday.length > 0 ||
                      groupedTimeline.thisWeek.length > 0 ||
                      groupedTimeline.older.length > 0;

  if (!hasAnyItems) {
    return (
      <div className="text-center py-8">
        <p className="text-sm text-gray-500 dark:text-gray-400">No timeline items yet.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {renderGroup('Today', groupedTimeline.today)}
      {renderGroup('Yesterday', groupedTimeline.yesterday)}
      {renderGroup('This week', groupedTimeline.thisWeek)}
      {renderGroup('Older', groupedTimeline.older)}
    </div>
  );
}

