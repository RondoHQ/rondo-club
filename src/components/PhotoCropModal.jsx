import { useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { exportPhotoCrop, photoCropRect } from '@/utils/photoCrop';

export default function PhotoCropModal({ file, onClose, onSave, isSaving }) {
  const dialogRef = useRef(null);
  const imageRef = useRef(null);
  const dragRef = useRef(null);
  const [source, setSource] = useState('');
  const [dimensions, setDimensions] = useState(null);
  const [zoom, setZoom] = useState(1);
  const [position, setPosition] = useState({ x: 0.5, y: 0.5 });
  const [error, setError] = useState('');
  const [isPreparing, setIsPreparing] = useState(false);
  const busy = isSaving || isPreparing;
  const rect = dimensions ? photoCropRect(dimensions.width, dimensions.height, zoom, position) : null;

  useEffect(() => {
    const url = URL.createObjectURL(file);
    setSource(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  useEffect(() => {
    const dialog = dialogRef.current;
    const previousFocus = document.activeElement;
    dialog.showModal();
    return () => {
      dialog.close();
      previousFocus?.focus();
    };
  }, []);

  const save = async () => {
    if (!rect || busy) return;
    setError('');
    setIsPreparing(true);
    try {
      const cropped = await exportPhotoCrop(imageRef.current, rect);
      await onSave(cropped);
    } catch (failure) {
      setError(failure.response?.data?.message || failure.message || 'Opslaan is niet gelukt. Probeer het opnieuw.');
    } finally {
      setIsPreparing(false);
    }
  };

  const move = (event) => {
    const drag = dragRef.current;
    if (!drag || busy) return;
    const scale = drag.rect.size / drag.width;
    const spanX = dimensions.width - drag.rect.size;
    const spanY = dimensions.height - drag.rect.size;
    setPosition({
      x: spanX ? Math.max(0, Math.min(1, drag.position.x - (event.clientX - drag.x) * scale / spanX)) : 0.5,
      y: spanY ? Math.max(0, Math.min(1, drag.position.y - (event.clientY - drag.y) * scale / spanY)) : 0.5,
    });
  };

  return (
    <dialog
      ref={dialogRef}
      aria-labelledby="photo-crop-title"
      className="m-auto w-[calc(100%_-_2rem)] max-w-md max-h-[90dvh] overflow-y-auto rounded-xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/60 dark:bg-gray-800 dark:text-gray-100"
      onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }}
    >
      <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
        <h2 id="photo-crop-title" className="text-lg font-semibold">Foto bijsnijden</h2>
        <button type="button" onClick={onClose} disabled={busy} aria-label="Sluiten" className="rounded p-2 hover:bg-gray-100 disabled:opacity-50 dark:hover:bg-gray-700">
          <X className="h-5 w-5" />
        </button>
      </div>
      <div className="space-y-4 p-4">
        <p className="text-sm text-gray-600 dark:text-gray-300">Sleep de foto en zoom in tot het gezicht goed zichtbaar is. Alleen deze uitsnede wordt opgeslagen.</p>
        <div
          className="relative mx-auto aspect-square w-full max-w-72 touch-none overflow-hidden rounded-lg bg-gray-100 outline-offset-4 focus-visible:outline-2 focus-visible:outline-cyan-600 dark:bg-gray-900"
          role="group"
          aria-label="Uitsnede van de foto; gebruik de pijltjestoetsen om te verschuiven"
          tabIndex={busy ? -1 : 0}
          onKeyDown={(event) => {
            if (busy || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
            event.preventDefault();
            setPosition(current => ({
              x: Math.max(0, Math.min(1, current.x + (event.key === 'ArrowLeft' ? 0.02 : event.key === 'ArrowRight' ? -0.02 : 0))),
              y: Math.max(0, Math.min(1, current.y + (event.key === 'ArrowUp' ? 0.02 : event.key === 'ArrowDown' ? -0.02 : 0))),
            }));
          }}
          onPointerDown={(event) => {
            if (!rect || busy) return;
            event.currentTarget.setPointerCapture(event.pointerId);
            dragRef.current = { x: event.clientX, y: event.clientY, width: event.currentTarget.getBoundingClientRect().width, position, rect };
          }}
          onPointerMove={move}
          onPointerUp={() => { dragRef.current = null; }}
          onPointerCancel={() => { dragRef.current = null; }}
        >
          {source && (
            <img
              ref={imageRef}
              src={source}
              alt="Voorbeeld van de uitsnede"
              draggable={false}
              onLoad={(event) => setDimensions({ width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight })}
              onError={() => setError('Deze afbeelding kan niet worden geopend. Kies een JPG-, PNG- of WebP-foto.')}
              className="pointer-events-none absolute max-w-none select-none"
              style={rect ? {
                width: `${dimensions.width / rect.size * 100}%`,
                height: `${dimensions.height / rect.size * 100}%`,
                left: `${-rect.x / rect.size * 100}%`,
                top: `${-rect.y / rect.size * 100}%`,
              } : { visibility: 'hidden' }}
            />
          )}
        </div>
        <div>
          <label htmlFor="photo-crop-zoom" className="mb-1 flex justify-between text-sm font-medium">Inzoomen <span>{zoom.toFixed(1)}×</span></label>
          <input id="photo-crop-zoom" type="range" min="1" max="4" step="0.05" value={zoom} onChange={event => setZoom(Number(event.target.value))} disabled={busy || !rect} className="w-full accent-cyan-600" />
        </div>
        <button type="button" className="text-sm underline disabled:opacity-50" disabled={busy} onClick={() => { setZoom(1); setPosition({ x: 0.5, y: 0.5 }); }}>Uitsnede herstellen</button>
        {error && <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p>}
      </div>
      <div className="flex justify-end gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
        <button type="button" className="btn-secondary" onClick={onClose} disabled={busy}>Annuleren</button>
        <button type="button" className="btn-primary" onClick={save} disabled={busy || !rect}>{busy ? 'Opslaan…' : 'Uitsnede opslaan'}</button>
      </div>
    </dialog>
  );
}
