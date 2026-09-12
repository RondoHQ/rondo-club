import { useEffect, useRef } from 'react';

export default function TrainingDialog({ title, onClose, children }) {
  const ref = useRef(null);
  useEffect(() => { ref.current.showModal(); ref.current.querySelector('input, select, textarea')?.focus(); }, []);
  return <dialog ref={ref} onCancel={onClose} aria-labelledby="training-dialog-title" className="m-auto w-[calc(100%-2rem)] max-w-xl max-h-[90dvh] overflow-y-auto rounded-xl bg-white p-6 text-gray-900 shadow-xl backdrop:bg-black/50 dark:bg-gray-800 dark:text-gray-100"><div className="mb-5 flex items-center justify-between gap-4"><h2 id="training-dialog-title" className="text-lg font-semibold">{title}</h2><button type="button" className="btn-tertiary" aria-label="Sluiten" onClick={onClose}>Sluiten</button></div>{children}</dialog>;
}
