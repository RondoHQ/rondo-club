import { useCallback, useId, useRef, useState } from 'react';
import { CalendarPlus, Copy, Check, X } from 'lucide-react';
import AnchoredPopover from '@/components/AnchoredPopover';

export default function TeamCalendarActions({ calendarUrl, teamName, compact = false }) {
  const [anchor, setAnchor] = useState(null);
  const closeRef = useRef(null);
  const dialogId = useId();
  const close = useCallback(() => setAnchor(null), []);
  const [copiedUrl, setCopiedUrl] = useState(null);
  const [copyError, setCopyError] = useState(false);
  const copied = copiedUrl === calendarUrl;
  const size = compact ? ' text-sm px-3 py-1.5' : '';

  async function copyCalendar() {
    try {
      await navigator.clipboard.writeText(calendarUrl);
      setCopiedUrl(calendarUrl);
      setCopyError(false);
    } catch {
      setCopyError(true);
    }
  }

  return (
    <div className="min-w-0 space-y-2">
      <div className="flex flex-wrap gap-2">
        <button type="button" onClick={(event) => setAnchor(anchor ? null : event.currentTarget)} aria-haspopup="dialog" aria-expanded={Boolean(anchor)} aria-controls={anchor ? dialogId : undefined} className={`btn-secondary${size}`} aria-label={teamName ? `Abonneren op agenda van ${teamName}` : undefined}>
          <CalendarPlus className="w-4 h-4 mr-2" aria-hidden="true" />Abonneren op agenda
        </button>
        <button type="button" onClick={copyCalendar} className={`btn-tertiary${size}`} aria-label={teamName ? `${copied ? 'ICS-link gekopieerd' : 'Kopieer ICS-link'} voor ${teamName}` : undefined}>
          {copied ? <Check className="w-4 h-4 mr-2" aria-hidden="true" /> : <Copy className="w-4 h-4 mr-2" aria-hidden="true" />}
          {copied ? 'ICS-link gekopieerd' : 'Kopieer ICS-link'}
        </button>
      </div>
      {anchor && (
        <AnchoredPopover anchor={anchor} id={dialogId} labelledBy={`${dialogId}-title`} initialFocusRef={closeRef} onClose={close} preferredHeight={460} className="p-5 space-y-4">
          <div className="flex items-start justify-between gap-3">
            <h3 id={`${dialogId}-title`} className="font-semibold">{teamName ? `Agenda van ${teamName}` : 'Teamagenda toevoegen'}</h3>
            <button ref={closeRef} type="button" onClick={() => { anchor.focus(); close(); }} className="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Sluiten"><X className="w-5 h-5" /></button>
          </div>
          <p className="text-sm text-gray-600 dark:text-gray-400">Kopieer de link en voeg deze in je agenda toe als abonnement. Wedstrijdwijzigingen worden dan automatisch opgehaald door je agenda.</p>
          <label className="block text-sm font-medium">Agendalink
            <input readOnly value={calendarUrl} onFocus={(event) => event.target.select()} className="input mt-1 w-full" />
          </label>
          <button type="button" onClick={copyCalendar} className="btn-secondary text-sm"><Copy className="w-4 h-4 mr-2" aria-hidden="true" />{copied ? 'ICS-link gekopieerd' : 'Kopieer ICS-link'}</button>
          {copyError && <p role="alert" className="text-sm">Automatisch kopiëren lukt niet. Selecteer en kopieer de link hierboven.</p>}
          <div className="space-y-3 text-sm">
            <details>
              <summary className="cursor-pointer font-medium">Google Agenda</summary>
              <p className="mt-2">Open Google Agenda op een computer. Kies naast ‘Andere agenda’s’ voor +, daarna ‘Via URL’. Plak de link en voeg de agenda toe.</p>
              <a href="https://calendar.google.com/" target="_blank" rel="noopener noreferrer" className="text-electric-cyan hover:underline inline-block mt-2">Open Google Agenda</a>
            </details>
            <details>
              <summary className="cursor-pointer font-medium">Outlook</summary>
              <p className="mt-2">Open je agenda in Outlook op het web. Kies ‘Agenda toevoegen’ en ‘Abonneren via internet’. Plak de link en sla het abonnement op.</p>
            </details>
            <details>
              <summary className="cursor-pointer font-medium">Apple Agenda of een andere agenda-app</summary>
              <p className="mt-2">Open je agenda-app met de knop hieronder. Gebeurt er niets? Voeg dan in je agenda-app een agenda-abonnement toe met de link hierboven.</p>
              <a href={calendarUrl.replace(/^https?:/, 'webcal:')} className="btn-secondary text-sm mt-2">Open agenda-app</a>
            </details>
          </div>
          <p className="text-xs text-gray-500 dark:text-gray-400">Deze link is deelbaar en bevat alleen wedstrijdgegevens.</p>
        </AnchoredPopover>
      )}
      {copied && <span className="sr-only" role="status">ICS-link gekopieerd{teamName ? ` voor ${teamName}` : ''}.</span>}
      {copyError && <label className="block text-sm">Kopiëren lukte niet. Kopieer deze link:<input aria-label={teamName ? `ICS-link voor ${teamName}` : 'ICS-link'} readOnly value={calendarUrl} onFocus={(event) => event.target.select()} className="input mt-2 w-full" /></label>}
    </div>
  );
}
