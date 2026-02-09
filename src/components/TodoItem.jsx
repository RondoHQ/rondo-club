/**
 * Reusable todo item component for displaying todos in lists.
 * Supports two variants:
 * - "compact": For PersonDetail sidebar (smaller padding, "Also:" prefix for other persons)
 * - "full": For TodosList page (larger padding, shows all persons with primary name link)
 */
import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { CheckSquare2, Square, Clock, Pencil, Trash2, RotateCcw } from 'lucide-react';
import PersonAvatar from '@/components/PersonAvatar.jsx';
import { format } from '@/utils/dateFormat';
import { isTodoOverdue, getAwaitingDays, getAwaitingUrgencyClass } from '@/utils/timeline';
import { stripHtmlTags } from '@/utils/richTextUtils';

/**
 * Props for TodoItem component.
 * @typedef {Object} TodoItemProps
 * @property {Object} todo - The todo item object
 * @property {number} [currentPersonId] - ID of the current person being viewed (for compact variant)
 * @property {Function} onToggle - Handler for toggling todo status
 * @property {Function} onEdit - Handler for editing the todo
 * @property {Function} onDelete - Handler for deleting the todo
 * @property {Function} [onReopen] - Handler for reopening a completed/awaiting todo
 * @property {boolean} [showActionsAlways=false] - Whether to always show action buttons (vs hover-only)
 * @property {'compact'|'full'} [variant='compact'] - Display variant
 */

/**
 * TodoItem component displays a single todo with its status, content, and actions.
 *
 * @param {TodoItemProps} props
 */
export default function TodoItem({
  todo,
  currentPersonId,
  onToggle,
  onEdit,
  onDelete,
  onReopen,
  showActionsAlways = false,
  variant = 'compact',
}) {
  const isOverdue = isTodoOverdue(todo);
  const awaitingDays = getAwaitingDays(todo);

  // Support both new persons array and legacy person fields
  const allPersons = useMemo(() => {
    if (todo.persons && todo.persons.length > 0) {
      return todo.persons;
    }
    if (todo.person_id) {
      return [{
        id: todo.person_id,
        name: todo.person_name,
        thumbnail: todo.person_thumbnail
      }];
    }
    return [];
  }, [todo.persons, todo.person_id, todo.person_name, todo.person_thumbnail]);

  // For compact variant, filter out the current person
  const displayPersons = variant === 'compact'
    ? allPersons.filter(p => p.id !== currentPersonId)
    : allPersons;

  // Get notes preview (stripped of HTML, truncated)
  const notesPreview = useMemo(() => {
    if (!todo.notes) return null;
    const stripped = stripHtmlTags(todo.notes);
    if (!stripped) return null;
    const limit = variant === 'compact' ? 60 : 100;
    return stripped.length > limit ? stripped.slice(0, limit) + '...' : stripped;
  }, [todo.notes, variant]);

  // Styling based on variant
  const containerClass = variant === 'compact'
    ? 'flex items-start p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 group'
    : 'flex items-start p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group';

  const marginClass = variant === 'compact' ? 'mr-2' : 'mr-3';
  const notesLineClamp = variant === 'compact' ? 'line-clamp-1' : 'line-clamp-2';
  const notesMt = variant === 'compact' ? 'mt-0.5' : 'mt-1';

  // Content class with font-medium for overdue in full variant
  const contentClass = (() => {
    if (todo.status === 'completed') {
      return 'line-through text-gray-400 dark:text-gray-500';
    }
    if (todo.status === 'awaiting') {
      return 'text-orange-700 dark:text-orange-400';
    }
    if (isOverdue) {
      return variant === 'full'
        ? 'text-red-600 dark:text-red-300 font-medium'
        : 'text-red-600 dark:text-red-300';
    }
    return variant === 'full' ? 'text-gray-900 dark:text-gray-100' : 'dark:text-gray-100';
  })();

  return (
    <div className={containerClass}>
      {/* Status toggle button */}
      <button
        onClick={() => onToggle(todo)}
        className={`mt-0.5 ${marginClass} flex-shrink-0`}
        title={
          todo.status === 'completed'
            ? 'Taak heropenen'
            : todo.status === 'awaiting'
            ? 'Markeren als afgerond'
            : 'Taak afronden'
        }
      >
        {todo.status === 'completed' ? (
          <CheckSquare2 className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        ) : todo.status === 'awaiting' ? (
          <Clock className="w-5 h-5 text-orange-500 dark:text-orange-400" />
        ) : (
          <Square
            className={`w-5 h-5 ${
              isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-400 dark:text-gray-500'
            }`}
          />
        )}
      </button>

      {/* Todo content */}
      <div className="flex-1 min-w-0">
        <p className={`text-sm ${contentClass}`}>{todo.content}</p>

        {/* Notes preview */}
        {notesPreview && (
          <p className={`text-xs text-gray-500 dark:text-gray-400 ${notesMt} ${notesLineClamp}`}>
            {notesPreview}
          </p>
        )}

        {/* Full variant: wrap metadata in a flex container */}
        {variant === 'full' ? (
          <div className="flex flex-wrap items-center gap-3 mt-2">
            {/* Multi-person avatars with primary name link */}
            {displayPersons.length > 0 && (
              <div className="flex items-center">
                <div className="flex items-center -space-x-2" title={displayPersons.map(p => p.name).join(', ')}>
                  {displayPersons.slice(0, 3).map((person, idx) => (
                    <Link
                      key={person.id}
                      to={`/people/${person.id}`}
                      className="relative hover:z-10"
                      style={{ zIndex: 3 - idx }}
                    >
                      <PersonAvatar
                        thumbnail={person.thumbnail}
                        name={person.name}
                        size="sm"
                        borderClassName="border-2 border-white dark:border-gray-800"
                      />
                    </Link>
                  ))}
                  {displayPersons.length > 3 && (
                    <span className="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 text-xs flex items-center justify-center border-2 border-white dark:border-gray-800 text-gray-600 dark:text-gray-300">
                      +{displayPersons.length - 3}
                    </span>
                  )}
                </div>
                {/* Primary person name link */}
                <Link
                  to={`/people/${displayPersons[0].id}`}
                  className="ml-2 text-xs text-electric-cyan dark:text-electric-cyan hover:text-bright-cobalt dark:hover:text-electric-cyan-light hover:underline"
                >
                  {displayPersons[0].name}
                  {displayPersons.length > 1 && (
                    <span className="text-gray-500 dark:text-gray-400"> +{displayPersons.length - 1}</span>
                  )}
                </Link>
              </div>
            )}

            {/* Due date - only show for open todos */}
            {todo.due_date && todo.status === 'open' && (
              <span className={`text-xs ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
                Deadline: {format(new Date(todo.due_date), 'MMM d, yyyy')}
                {isOverdue && ' (te laat)'}
              </span>
            )}

            {/* Awaiting indicator */}
            {todo.status === 'awaiting' && awaitingDays !== null && (
              <span className={`text-xs px-2 py-0.5 rounded-full flex items-center gap-1 ${getAwaitingUrgencyClass(awaitingDays)}`}>
                <Clock className="w-3 h-3" />
                {awaitingDays === 0 ? 'Wacht sinds vandaag' : `Wacht ${awaitingDays}d`}
              </span>
            )}
          </div>
        ) : (
          <>
            {/* Compact variant: original layout */}
            {/* Due date - only show for open todos */}
            {todo.due_date && todo.status === 'open' && (
              <p
                className={`text-xs mt-0.5 ${
                  isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'
                }`}
              >
                Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
                {isOverdue && ' (overdue)'}
              </p>
            )}

            {/* Awaiting indicator */}
            {todo.status === 'awaiting' && awaitingDays !== null && (
              <span
                className={`text-xs px-1.5 py-0.5 rounded-full inline-flex items-center gap-0.5 mt-1 ${getAwaitingUrgencyClass(awaitingDays)}`}
              >
                <Clock className="w-3 h-3" />
                {awaitingDays === 0 ? 'Waiting since today' : `Waiting ${awaitingDays}d`}
              </span>
            )}

            {/* Multi-person indicator with stacked avatars */}
            {displayPersons.length > 0 && (
              <div className="flex items-center gap-1 mt-1">
                <span className="text-xs text-gray-500 dark:text-gray-400">Also:</span>
                <div className="flex -space-x-1.5" title={displayPersons.map(p => p.name).join(', ')}>
                  {displayPersons.slice(0, 2).map((person, idx) => (
                    <Link
                      key={person.id}
                      to={`/people/${person.id}`}
                      className="relative hover:z-10"
                      style={{ zIndex: 2 - idx }}
                      onClick={e => e.stopPropagation()}
                    >
                      <PersonAvatar
                        thumbnail={person.thumbnail}
                        name={person.name}
                        size="xs"
                        borderClassName="border border-white"
                      />
                    </Link>
                  ))}
                  {displayPersons.length > 2 && (
                    <span className="w-5 h-5 rounded-full bg-gray-200 text-[10px] flex items-center justify-center border border-white text-gray-600">
                      +{displayPersons.length - 2}
                    </span>
                  )}
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Action buttons */}
      <div
        className={`flex items-center gap-1 ml-2 ${
          showActionsAlways ? '' : 'opacity-0 group-hover:opacity-100 transition-opacity'
        }`}
      >
        {/* Reopen button for awaiting/completed todos (only when onReopen is provided) */}
        {onReopen && todo.status !== 'open' && (
          <button
            onClick={() => onReopen(todo)}
            className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
            title="Taak heropenen"
          >
            <RotateCcw className="w-4 h-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" />
          </button>
        )}
        <button
          onClick={() => onEdit(todo)}
          className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
          title="Taak bewerken"
        >
          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" />
        </button>
        <button
          onClick={() => onDelete(todo.id)}
          className="p-1 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
          title="Taak verwijderen"
        >
          <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600 dark:hover:text-red-400" />
        </button>
      </div>
    </div>
  );
}
