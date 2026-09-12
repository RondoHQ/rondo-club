import { useEffect, useRef, useState } from 'react';
import { Ellipsis, GitMerge, Pencil, RefreshCw } from 'lucide-react';

const iconButtonClass = 'inline-flex h-9 w-9 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 [@media(pointer:coarse)]:h-11 [@media(pointer:coarse)]:w-11';
const actionClass = 'flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700';

export default function PersonHeaderActions({ onEdit, onMerge, onSync, isSyncing }) {
  const menuRef = useRef(null);
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    const closeOutside = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) menuRef.current.open = false;
    };
    const closeOnEscape = (event) => {
      if (event.key === 'Escape' && menuRef.current) {
        menuRef.current.open = false;
        menuRef.current.querySelector('summary').focus();
      }
    };
    document.addEventListener('pointerdown', closeOutside);
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.removeEventListener('pointerdown', closeOutside);
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, [isOpen]);

  const runAction = (action) => {
    menuRef.current.open = false;
    menuRef.current.querySelector('summary').focus();
    action();
  };

  return (
    <div className="absolute right-4 top-4 z-10 flex items-start gap-1 sm:right-6 sm:top-6">
      {onEdit && (
        <button type="button" onClick={onEdit} className={iconButtonClass} aria-label="Persoon bewerken" title="Persoon bewerken">
          <Pencil className="h-4 w-4" aria-hidden="true" />
        </button>
      )}
      {(onSync || onMerge) && <details ref={menuRef} onToggle={(event) => setIsOpen(event.currentTarget.open)} className="relative">
        <summary className={`${iconButtonClass} cursor-pointer list-none [&::-webkit-details-marker]:hidden`} aria-label="Meer persoonsacties" title="Meer acties">
          <Ellipsis className="h-4 w-4" aria-hidden="true" />
        </summary>
        <div className="absolute right-0 top-full mt-2 w-60 max-w-[calc(100vw-4rem)] rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-600 dark:bg-gray-800">
          {onSync && (
            <button type="button" onClick={() => runAction(onSync)} disabled={isSyncing} className={actionClass}>
              <RefreshCw className={`h-4 w-4 shrink-0 ${isSyncing ? 'animate-spin' : ''}`} aria-hidden="true" />
              {isSyncing ? 'Bezig met verversen…' : 'Ververs uit Sportlink'}
            </button>
          )}
          {onMerge && (
            <button type="button" onClick={() => runAction(onMerge)} className={actionClass}>
              <GitMerge className="h-4 w-4 shrink-0" aria-hidden="true" />Persoon samenvoegen
            </button>
          )}
        </div>
      </details>}
    </div>
  );
}
