import { useState } from 'react';
import { Mail } from 'lucide-react';
import { usePreviewTournamentRegistrationReminder, useSendTournamentRegistrationReminder } from '@/hooks/useTournaments';
import { formatTournamentDate } from './tournamentFormatters';

export default function TournamentRegistrationReminder({ tournament }) {
  const [expanded, setExpanded] = useState(false);
  const [subject, setSubject] = useState(`Herinnering inschrijving: ${tournament.name}`);
  const [message, setMessage] = useState('Wil je de inschrijving van je team voor dit toernooi afronden? Gebruik de link hieronder om de gegevens te controleren en definitief in te schrijven.');
  const [preview, setPreview] = useState(null);
  const [result, setResult] = useState(null);
  const previewMutation = usePreviewTournamentRegistrationReminder(tournament.id);
  const sendMutation = useSendTournamentRegistrationReminder(tournament.id);
  const busy = previewMutation.isPending || sendMutation.isPending;
  const error = previewMutation.error || sendMutation.error;

  const resetPreview = () => {
    setPreview(null);
    setResult(null);
    previewMutation.reset();
    sendMutation.reset();
  };

  const checkRecipients = async (event) => {
    event.preventDefault();
    resetPreview();
    try {
      setPreview(await previewMutation.mutateAsync({ subject, message }));
    } catch { /* The mutation error is displayed below. */ }
  };

  const send = async () => {
    try {
      setResult(await sendMutation.mutateAsync({ token: preview.token }));
    } catch { /* Keep the token for a safe retry after an uncertain network result. */ }
  };

  return (
    <section className="space-y-4">
      <button type="button" className="btn-tertiary inline-flex items-center" aria-expanded={expanded} aria-controls="registration-reminder" onClick={() => setExpanded(!expanded)}>
        <Mail className="mr-2 h-4 w-4" />{expanded ? 'Herinnering sluiten' : 'Niet-ingeschreven teams herinneren'}
      </button>
      {expanded && (
        <div id="registration-reminder" className="card space-y-5 p-4 sm:p-6">
          <div>
            <h2 className="text-lg font-semibold">Inschrijfherinnering</h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Herinner toegewezen kaderleden van teams die nog niet definitief zijn ingeschreven. Controleer eerst het bericht en de ontvangers.</p>
          </div>
          <form onSubmit={checkRecipients} className="space-y-4">
            <div>
              <label htmlFor="reminder-subject" className="mb-1 block text-sm font-medium">Onderwerp</label>
              <input id="reminder-subject" className="input w-full" required maxLength={200} value={subject} disabled={busy} onChange={(event) => { setSubject(event.target.value); resetPreview(); }} />
            </div>
            <div>
              <label htmlFor="reminder-message" className="mb-1 block text-sm font-medium">Bericht</label>
              <textarea id="reminder-message" className="input w-full" rows={4} required maxLength={10000} value={message} disabled={busy} onChange={(event) => { setMessage(event.target.value); resetPreview(); }} />
              <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">De aanhef, toernooinaam, deadline en inschrijflinks voor de eigen teams worden automatisch toegevoegd, met uitleg voor kaderleden zonder Rondo-account.</p>
            </div>
            <button type="submit" className="btn-secondary" disabled={busy}>{previewMutation.isPending ? 'Ontvangers controleren…' : 'Ontvangers controleren'}</button>
          </form>
          {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{error.response?.data?.message || error.message || 'De herinnering kon niet worden verwerkt.'}</p>}
          {preview && (
            <div className="space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
              <h3 className="font-semibold">{preview.teams.length} nog niet ingeschreven teams · {preview.recipients.length} e-mailontvangers</h3>
              {preview.teams.length === 0 && <p className="text-sm">Er zijn geen teams om te herinneren.</p>}
              {preview.warnings.length > 0 && (
                <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                  <p className="font-medium">Deze kaderleden of teams worden niet bereikt:</p>
                  <ul className="mt-2 list-disc space-y-1 pl-5">{preview.warnings.map((warning, index) => <li key={index}>{warning}</li>)}</ul>
                </div>
              )}
              <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                {preview.recipients.map((recipient) => <li key={recipient.email} className="py-2 text-sm"><p className="font-medium">{recipient.name} <span className="break-all font-normal text-gray-600 dark:text-gray-300">({recipient.email})</span></p><p className="text-gray-600 dark:text-gray-300">{recipient.teams.map((team) => team.name).join(', ')}</p></li>)}
              </ul>
              <div className="rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800">
                <p className="font-semibold">{preview.subject}</p>
                <p className="mt-2 whitespace-pre-wrap">{preview.message}</p>
                <p className="mt-3">{preview.name} · Inschrijven vóór {formatTournamentDate(preview.deadline, true)}</p>
                <p className="mt-2 text-gray-600 dark:text-gray-300">Iedere ontvanger krijgt uitsluitend inschrijflinks voor de hierboven bij die ontvanger genoemde teams.</p>
              </div>
              {!result && <button type="button" className="btn-primary" disabled={busy || preview.recipients.length === 0} onClick={send}>{sendMutation.isPending ? 'Herinneringen versturen…' : `Herinnering naar ${preview.recipients.length} ontvangers versturen`}</button>}
            </div>
          )}
          {result && <p role="status" className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">{result.sent_count} herinneringen aangeboden aan de mailserver.{result.failed_count > 0 ? ` ${result.failed_count} konden niet worden verstuurd. Een nieuwe herinnering kan ook eerder bereikte ontvangers opnieuw mailen.` : ''}</p>}
        </div>
      )}
    </section>
  );
}
