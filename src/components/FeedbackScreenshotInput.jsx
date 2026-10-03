import { useEffect, useId, useRef, useState } from 'react';

export default function FeedbackScreenshotInput({ file, onChange, disabled, hasScreenshot = false }) {
  const id = useId();
  const input = useRef(null);
  const [preview, setPreview] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (!file) return;
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  return (
    <div>
      <label htmlFor={id} className="label">Screenshot (optioneel)</label>
      {hasScreenshot && <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">Deze melding heeft al een screenshot. Kies een bestand om deze te vervangen.</p>}
      <input
        ref={input}
        id={id}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        className="input"
        disabled={disabled}
        onChange={(event) => {
          const selected = event.target.files?.[0];
          setError('');
          setPreview('');
          onChange(null);
          if (!selected) return;
          if (!['image/png', 'image/jpeg', 'image/webp'].includes(selected.type) || selected.size > 5 * 1024 * 1024) {
            setError('Kies een PNG, JPG of WebP van maximaal 5 MB.');
            event.target.value = '';
            return;
          }
          onChange(selected);
        }}
      />
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG of WebP, maximaal 5 MB.</p>
      {error && <p role="alert" className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
      {file && preview && (
        <div className="mt-2 space-y-2">
          <img src={preview} alt="Voorbeeld van je screenshot" className="max-h-48 max-w-full rounded border border-gray-200 dark:border-gray-700" />
          <button type="button" className="btn-secondary text-sm" disabled={disabled} onClick={() => {
            onChange(null);
            setPreview('');
            input.current.value = '';
          }}>Gekozen screenshot verwijderen</button>
        </div>
      )}
    </div>
  );
}
