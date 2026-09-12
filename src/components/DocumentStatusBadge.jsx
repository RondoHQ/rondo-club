import { Check, Clock, AlertCircle } from 'lucide-react';

const statuses = {
  valid: { label: 'Geldig', icon: Check, classes: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' },
  pending: { label: 'Wacht op beoordeling', icon: Clock, classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  expired: { label: 'Verlopen', icon: AlertCircle, classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  missing: { label: 'Ontbreekt', icon: AlertCircle, classes: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
};

export default function DocumentStatusBadge({ status }) {
  const { label, icon: Icon, classes } = statuses[status];
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ${classes}`}>
      <Icon className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
      {label}
    </span>
  );
}
