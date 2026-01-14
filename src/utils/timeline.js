import { format, formatDistanceToNow, isToday, isYesterday, isThisWeek, parseISO } from 'date-fns';

/**
 * Group timeline items by date buckets
 * Todos are separated and sorted by due date
 * @param {Array} timeline - Array of timeline items
 * @returns {Object} Grouped timeline items with separate todos section
 */
export function groupTimelineByDate(timeline) {
  if (!timeline || timeline.length === 0) {
    return {
      todos: [],
      today: [],
      yesterday: [],
      thisWeek: [],
      older: [],
    };
  }

  const groups = {
    todos: [],
    today: [],
    yesterday: [],
    thisWeek: [],
    older: [],
  };

  timeline.forEach((item) => {
    // Todos go into their own section
    if (item.type === 'todo') {
      groups.todos.push(item);
      return;
    }

    const itemDate = parseISO(item.created);
    // Combine activity_date and activity_time if both exist
    let displayDateStr = item.activity_date || item.created;
    if (item.activity_date && item.activity_time) {
      displayDateStr = `${item.activity_date}T${item.activity_time}`;
    }
    const displayDate = parseISO(displayDateStr);

    if (isToday(displayDate)) {
      groups.today.push(item);
    } else if (isYesterday(displayDate)) {
      groups.yesterday.push(item);
    } else if (isThisWeek(displayDate)) {
      groups.thisWeek.push(item);
    } else {
      groups.older.push(item);
    }
  });

  // Sort todos: open first (by due date), awaiting second (by wait time), completed last
  groups.todos.sort((a, b) => {
    // Status priority: open first, awaiting second, completed last
    const statusOrder = { open: 0, awaiting: 1, completed: 2 };
    const aOrder = statusOrder[a.status] ?? 0;
    const bOrder = statusOrder[b.status] ?? 0;

    if (aOrder !== bOrder) return aOrder - bOrder;

    // For open todos, sort by due date (earliest first)
    if (a.status === 'open') {
      if (a.due_date && b.due_date) {
        return new Date(a.due_date) - new Date(b.due_date);
      }
      if (a.due_date && !b.due_date) return -1;
      if (!a.due_date && b.due_date) return 1;
    }

    // For awaiting todos, sort by awaiting_since (oldest first = waiting longest)
    if (a.status === 'awaiting') {
      if (a.awaiting_since && b.awaiting_since) {
        return new Date(a.awaiting_since) - new Date(b.awaiting_since);
      }
    }

    // Default: sort by creation date (newest first)
    return new Date(b.created) - new Date(a.created);
  });

  return groups;
}

/**
 * Format timeline date (relative + absolute)
 * @param {string} dateString - ISO date string (can include time as YYYY-MM-DDTHH:MM)
 * @returns {string} Formatted date string
 */
export function formatTimelineDate(dateString) {
  if (!dateString) return '';

  try {
    const date = parseISO(dateString);
    const hasTime = dateString.includes('T') && dateString.length > 10;
    const timeFormat = hasTime ? ' at HH:mm' : '';

    // If it's today, show relative time
    if (isToday(date)) {
      return formatDistanceToNow(date, { addSuffix: true });
    }
    
    // If it's yesterday, show "Yesterday" with optional time
    if (isYesterday(date)) {
      return hasTime ? format(date, `'Yesterday' 'at' HH:mm`) : 'Yesterday';
    }

    // Otherwise show formatted date with optional time
    return format(date, hasTime ? `MMM d, yyyy 'at' HH:mm` : 'MMM d, yyyy');
  } catch (error) {
    return dateString;
  }
}

/**
 * Get activity type icon component name
 * @param {string} type - Activity type
 * @returns {string} Icon name from lucide-react
 */
export function getActivityTypeIcon(type) {
  const iconMap = {
    call: 'Phone',
    email: 'Mail',
    chat: 'MessageCircle',
    meeting: 'Users',
    coffee: 'Coffee',
    lunch: 'Utensils',
    note: 'FileText',
  };

  return iconMap[type] || 'Circle';
}

/**
 * Get activity type human-readable label
 * @param {string} type - Activity type
 * @returns {string} Human-readable label
 */
export function getActivityTypeLabel(type) {
  const labelMap = {
    call: 'Phone call',
    email: 'Email',
    chat: 'Chat',
    meeting: 'Meeting',
    coffee: 'Coffee',
    lunch: 'Lunch',
    note: 'Note',
  };

  return labelMap[type] || type || 'Activity';
}

/**
 * Check if a todo is overdue (only applies to open todos)
 * @param {Object} todo - Todo item
 * @returns {boolean} True if todo is overdue
 */
export function isTodoOverdue(todo) {
  // Only open todos can be overdue
  if (!todo.due_date || todo.status !== 'open') {
    return false;
  }

  try {
    const dueDate = parseISO(todo.due_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    dueDate.setHours(0, 0, 0, 0);

    return dueDate < today;
  } catch (error) {
    return false;
  }
}

/**
 * Get CSS classes for todo status
 * @param {Object} todo - Todo item
 * @returns {string} CSS classes
 */
export function getTodoStatusClass(todo) {
  const classes = [];

  if (todo.status === 'completed') {
    classes.push('opacity-60', 'line-through');
  } else if (todo.status === 'awaiting') {
    classes.push('text-orange-600');
  } else if (isTodoOverdue(todo)) {
    classes.push('text-red-600', 'font-medium');
  }

  return classes.join(' ');
}

/**
 * Calculate days since awaiting response was set
 * @param {Object} todo
 * @returns {number|null} Days since awaiting, or null if not awaiting
 */
export function getAwaitingDays(todo) {
  if (todo.status !== 'awaiting' || !todo.awaiting_since) {
    return null;
  }
  const since = new Date(todo.awaiting_since);
  const now = new Date();
  const diffTime = now - since;
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
  return diffDays;
}

/**
 * Get urgency class for awaiting response based on days
 * @param {number} days
 * @returns {string} Tailwind class for badge
 */
export function getAwaitingUrgencyClass(days) {
  if (days === null) return '';
  if (days >= 7) return 'bg-red-100 text-red-700';
  if (days >= 3) return 'bg-orange-100 text-orange-700';
  return 'bg-yellow-100 text-yellow-700';
}

