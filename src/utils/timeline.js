import { format, formatDistanceToNow, isToday, isYesterday, isThisWeek, parseISO } from 'date-fns';

/**
 * Group timeline items by date buckets
 * @param {Array} timeline - Array of timeline items
 * @returns {Object} Grouped timeline items
 */
export function groupTimelineByDate(timeline) {
  if (!timeline || timeline.length === 0) {
    return {
      today: [],
      yesterday: [],
      thisWeek: [],
      older: [],
    };
  }

  const groups = {
    today: [],
    yesterday: [],
    thisWeek: [],
    older: [],
  };

  timeline.forEach((item) => {
    const itemDate = parseISO(item.created);
    const displayDate = item.activity_date ? parseISO(item.activity_date) : itemDate;

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

  return groups;
}

/**
 * Format timeline date (relative + absolute)
 * @param {string} dateString - ISO date string
 * @returns {string} Formatted date string
 */
export function formatTimelineDate(dateString) {
  if (!dateString) return '';

  try {
    const date = parseISO(dateString);
    const relative = formatDistanceToNow(date, { addSuffix: true });

    // If it's today or yesterday, show relative time
    if (isToday(date)) {
      return relative;
    }
    if (isYesterday(date)) {
      return 'Yesterday';
    }

    // Otherwise show formatted date
    return format(date, 'MMM d, yyyy');
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
    meeting: 'Meeting',
    coffee: 'Coffee',
    lunch: 'Lunch',
    note: 'Note',
  };

  return labelMap[type] || type || 'Activity';
}

/**
 * Check if a todo is overdue
 * @param {Object} todo - Todo item
 * @returns {boolean} True if todo is overdue
 */
export function isTodoOverdue(todo) {
  if (!todo.due_date || todo.is_completed) {
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

  if (todo.is_completed) {
    classes.push('opacity-60', 'line-through');
  } else if (isTodoOverdue(todo)) {
    classes.push('text-red-600', 'font-medium');
  }

  return classes.join(' ');
}

