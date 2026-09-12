import { Check, Clock } from 'lucide-react';

export default function PhotoSyncIndicator({ status, knvbId, hasPhoto }) {
  if (!knvbId || !hasPhoto || !status || status.state === 'local_only') return null;

  const synced = status.state === 'synced';
  const waiting = ['pending', 'waiting_window', 'sending', 'review'].includes(status.state);
  if (!synced && !waiting) return null;

  const Icon = synced ? Check : Clock;
  const label = status.message || (synced ? 'Foto gesynchroniseerd naar Sportlink' : 'Foto wacht op Sportlink');

  return (
    <span
      role="img"
      aria-label={label}
      title={label}
      className={`absolute bottom-0 right-0 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white dark:border-gray-800 ${synced
        ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300'}`}
    >
      <Icon className="h-3.5 w-3.5" strokeWidth={2.5} aria-hidden="true" />
    </span>
  );
}
