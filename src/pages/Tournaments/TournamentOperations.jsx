import { useMemo, useState } from 'react';
import {
  CheckCircle2,
  Download,
  ExternalLink,
  FileText,
  Mail,
  RotateCcw,
  Save,
  Send,
} from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import {
  useReopenTournamentEntry,
  useSaveTournamentProgram,
  useSendTournamentPaymentReminder,
  useTournamentEntries,
  useTournamentExport,
  useUpdateTournamentExternalStatus,
  useUpdateTournamentLifecycleStatus,
  useUpdateTournamentPlannerNote,
} from '@/hooks/useTournaments';
import {
  formatTournamentCurrency,
  formatTournamentDate,
  tournamentPaymentStatus,
  tournamentPaymentToneClasses,
} from './tournamentFormatters';

const tabs = [
  { id: 'overview', label: 'Overzicht' },
  { id: 'teams', label: 'Teams en betalingen' },
  { id: 'communication', label: 'Communicatie' },
];

const externalStatusLabels = {
  not_processed: 'Nog niet verwerkt',
  submitted: 'Ingediend bij organisatie',
  confirmed: 'Bevestigd door organisatie',
};

function errorMessage(error, fallback = 'Er ging iets mis.') {
  return error?.response?.data?.message || error?.message || fallback;
}

function ErrorNotice({ error }) {
  return <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{errorMessage(error)}</div>;
}

function TotalsGrid({ totals }) {
  const cards = [
    ['Geselecteerde Rondo-teams', totals.selected_team_count || 0],
    ['Ingeschreven Rondo-teams', totals.submitted_entry_count || 0],
    ['Deelnemende teams', totals.registered_team_count || 0],
    ['Spelers', totals.player_count || 0],
    ['Ontvangen', formatTournamentCurrency(totals.received_amount)],
    ['Openstaand', formatTournamentCurrency(totals.outstanding_amount)],
  ];
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
      {cards.map(([label, value]) => (
        <div key={label} className="card p-4">
          <div className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{value}</div>
          <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{label}</div>
        </div>
      ))}
    </div>
  );
}

function OverviewPanel({ tournament }) {
  const totals = tournament.totals || { overall: {}, by_age_group: [] };
  const updateExternal = useUpdateTournamentExternalStatus();
  const updateLifecycle = useUpdateTournamentLifecycleStatus();
  const exportTournament = useTournamentExport();
  const [externalStatus, setExternalStatus] = useState(tournament.external_status || 'not_processed');
  const [lifecycleStatus, setLifecycleStatus] = useState(tournament.lifecycle_status);
  const [message, setMessage] = useState('');
  const archived = tournament.lifecycle_status === 'archived';

  const saveProgress = async () => {
    setMessage('');
    await updateExternal.mutateAsync({ id: tournament.id, externalStatus });
    setMessage('Externe voortgang opgeslagen.');
  };

  const saveLifecycle = async () => {
    if (lifecycleStatus === 'archived' && !window.confirm('Toernooi archiveren? Daarna is het alleen-lezen.')) return;
    setMessage('');
    await updateLifecycle.mutateAsync({ id: tournament.id, lifecycleStatus });
    setMessage('Toernooistatus opgeslagen.');
  };

  const download = async (format) => {
    const response = await exportTournament.mutateAsync({ id: tournament.id, format });
    const blob = response.data instanceof Blob ? response.data : new Blob([response.data]);
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${tournament.name.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'toernooi'}.${format}`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6">
      <TotalsGrid totals={totals.overall || {}} />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section className="card overflow-hidden">
          <div className="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Totalen per leeftijdslaag</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
              <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800">
                <tr><th className="px-4 py-3">Leeftijd</th><th className="px-4 py-3 text-right">Geselecteerd</th><th className="px-4 py-3 text-right">Ingeschreven</th><th className="px-4 py-3 text-right">Teams</th><th className="px-4 py-3 text-right">Spelers</th><th className="px-4 py-3 text-right">Ontvangen</th><th className="px-4 py-3 text-right">Openstaand</th></tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {(totals.by_age_group || []).map((row) => (
                  <tr key={row.age_group}>
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{row.age_group}</td>
                    <td className="px-4 py-3 text-right">{row.selected_team_count}</td>
                    <td className="px-4 py-3 text-right">{row.submitted_entry_count}</td>
                    <td className="px-4 py-3 text-right">{row.registered_team_count}</td>
                    <td className="px-4 py-3 text-right">{row.player_count}</td>
                    <td className="px-4 py-3 text-right">{formatTournamentCurrency(row.received_amount)}</td>
                    <td className="px-4 py-3 text-right">{formatTournamentCurrency(row.outstanding_amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="card space-y-5 p-5">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Voortgang en export</h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">De externe voortgang geldt voor het hele toernooi.</p>
          </div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Externe voortgang
            <select className="input mt-1" value={externalStatus} disabled={archived} onChange={(event) => setExternalStatus(event.target.value)}>
              {Object.entries(externalStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
          </label>
          <button type="button" className="btn-tertiary w-full" disabled={archived || updateExternal.isPending} onClick={saveProgress}>{updateExternal.isPending ? 'Opslaan…' : 'Externe voortgang opslaan'}</button>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Toernooistatus
            <select className="input mt-1" value={lifecycleStatus} disabled={archived} onChange={(event) => setLifecycleStatus(event.target.value)}>
              <option value="open">Open</option>
              <option value="closed">Gesloten</option>
              <option value="archived">Gearchiveerd</option>
            </select>
          </label>
          <button type="button" className="btn-tertiary w-full" disabled={archived || updateLifecycle.isPending} onClick={saveLifecycle}>{updateLifecycle.isPending ? 'Opslaan…' : 'Toernooistatus opslaan'}</button>
          <div className="grid grid-cols-2 gap-2 border-t border-gray-200 pt-5 dark:border-gray-700">
            <button type="button" className="btn-tertiary inline-flex items-center justify-center" disabled={exportTournament.isPending} onClick={() => download('csv')}><Download className="mr-2 h-4 w-4" />CSV</button>
            <button type="button" className="btn-tertiary inline-flex items-center justify-center" disabled={exportTournament.isPending} onClick={() => download('pdf')}><FileText className="mr-2 h-4 w-4" />PDF</button>
          </div>
          {(updateExternal.error || updateLifecycle.error || exportTournament.error) ? <ErrorNotice error={updateExternal.error || updateLifecycle.error || exportTournament.error} /> : null}
          {message ? <p className="text-sm text-green-700 dark:text-green-300">{message}</p> : null}
        </section>
      </div>

      <section className="card p-5">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Deadlines</h2>
        <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-3">
          <div><dt className="text-gray-500">Intern</dt><dd className="font-medium text-gray-900 dark:text-gray-100">{formatTournamentDate(tournament.internal_deadline)}</dd></div>
          <div><dt className="text-gray-500">Betaling</dt><dd className="font-medium text-gray-900 dark:text-gray-100">{formatTournamentDate(tournament.payment_deadline)}</dd></div>
          <div><dt className="text-gray-500">Organisatie</dt><dd className="font-medium text-gray-900 dark:text-gray-100">{formatTournamentDate(tournament.external_deadline)}</dd></div>
        </dl>
      </section>

      <section className="card p-5">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Activiteit</h2>
        {(tournament.activity || []).length === 0 ? <p className="mt-3 text-sm text-gray-500">Nog geen activiteit vastgelegd.</p> : (
          <ol className="mt-4 space-y-3">
            {tournament.activity.map((item) => (
              <li key={item.id} className="flex flex-col gap-1 border-b border-gray-100 pb-3 text-sm last:border-0 last:pb-0 dark:border-gray-800 sm:flex-row sm:justify-between">
                <span className="text-gray-800 dark:text-gray-200">{item.label}{item.team_name ? ` · ${item.team_name}` : ''}</span>
                <span className="text-xs text-gray-500">{item.actor_name} · {formatTournamentDate(item.created_at, true)}</span>
              </li>
            ))}
          </ol>
        )}
      </section>
    </div>
  );
}

function PlannerNote({ entry, archived }) {
  const updateNote = useUpdateTournamentPlannerNote();
  const [note, setNote] = useState(entry.planner_note || '');
  return (
    <div className="min-w-56">
      <textarea className="input min-h-20 text-sm" value={note} disabled={archived} placeholder="Interne notitie" onChange={(event) => setNote(event.target.value)} />
      <button type="button" className="mt-2 inline-flex items-center text-xs font-medium text-bright-cobalt disabled:text-gray-400 dark:text-electric-cyan" disabled={archived || updateNote.isPending || note === (entry.planner_note || '')} onClick={() => updateNote.mutate({ id: entry.id, plannerNote: note })}><Save className="mr-1 h-3.5 w-3.5" />{updateNote.isPending ? 'Opslaan…' : 'Notitie opslaan'}</button>
      {updateNote.error ? <p className="mt-1 text-xs text-red-600">{errorMessage(updateNote.error)}</p> : null}
    </div>
  );
}

function TeamsPaymentsPanel({ tournament, entries, isLoading, error }) {
  const sendReminder = useSendTournamentPaymentReminder();
  const reopenEntry = useReopenTournamentEntry();
  const [ageFilter, setAgeFilter] = useState('all');
  const [registrationFilter, setRegistrationFilter] = useState('all');
  const [paymentFilter, setPaymentFilter] = useState('all');
  const [actionMessage, setActionMessage] = useState('');
  const archived = tournament.lifecycle_status === 'archived';
  const ageGroups = useMemo(() => [...new Set(entries.map((entry) => entry.age_group))], [entries]);
  const filtered = useMemo(() => entries.filter((entry) => (
    (ageFilter === 'all' || entry.age_group === ageFilter)
    && (registrationFilter === 'all' || entry.registration_status === registrationFilter)
    && (paymentFilter === 'all' || entry.payment_state === paymentFilter)
  )), [ageFilter, entries, paymentFilter, registrationFilter]);

  const remind = async (entry) => {
    setActionMessage('');
    await sendReminder.mutateAsync(entry.id);
    setActionMessage(`Betaalherinnering voor ${entry.team_name} verstuurd.`);
  };
  const reopen = (entry) => {
    if (!window.confirm(`Inschrijving van ${entry.team_name} heropenen? De huidige betaallink wordt ingetrokken.`)) return;
    setActionMessage('');
    reopenEntry.mutate(entry.id, { onSuccess: () => setActionMessage(`Inschrijving van ${entry.team_name} heropend.`) });
  };

  if (isLoading) return <ContentLoadingSpinner />;
  return (
    <section className="card overflow-hidden">
      <div className="space-y-4 border-b border-gray-200 p-5 dark:border-gray-700">
        <div><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Teams en betalingen</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Niet-ingeschreven teams blijven zichtbaar en tellen niet mee in bedragen of contactgegevens.</p></div>
        <div className="grid gap-3 sm:grid-cols-3">
          <label className="text-sm text-gray-700 dark:text-gray-300">Leeftijdslaag<select className="input mt-1" value={ageFilter} onChange={(event) => setAgeFilter(event.target.value)}><option value="all">Alle leeftijden</option>{ageGroups.map((age) => <option key={age} value={age}>{age}</option>)}</select></label>
          <label className="text-sm text-gray-700 dark:text-gray-300">Inschrijving<select className="input mt-1" value={registrationFilter} onChange={(event) => setRegistrationFilter(event.target.value)}><option value="all">Alle inschrijvingen</option><option value="open">Niet ingeschreven</option><option value="submitted">Ingeschreven</option></select></label>
          <label className="text-sm text-gray-700 dark:text-gray-300">Betaling<select className="input mt-1" value={paymentFilter} onChange={(event) => setPaymentFilter(event.target.value)}><option value="all">Alle betalingen</option><option value="open">Open</option><option value="paid">Betaald</option><option value="error">Betaallink mislukt</option><option value="expired">Vervallen</option></select></label>
        </div>
        <p className="text-xs text-gray-500">{filtered.length} van {entries.length} Rondo-teams zichtbaar</p>
      </div>
      {error ? <div className="p-5"><ErrorNotice error={error} /></div> : null}
      {(sendReminder.error || reopenEntry.error) ? <div className="p-5"><ErrorNotice error={sendReminder.error || reopenEntry.error} /></div> : null}
      {actionMessage ? <div className="p-5 text-sm text-green-700 dark:text-green-300">{actionMessage}</div> : null}
      <div className="overflow-x-auto">
        <table className="min-w-[1500px] divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th className="px-4 py-3">Leeftijd en team</th><th className="px-4 py-3">Kader</th><th className="px-4 py-3">Inschrijving</th><th className="px-4 py-3">Teams en spelers</th><th className="px-4 py-3">Contactpersoon</th><th className="px-4 py-3">Bedrag en betaling</th><th className="px-4 py-3">Laatste betaalmail</th><th className="px-4 py-3">Interne notitie</th></tr></thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">{filtered.map((entry) => {
            const paymentStatus = tournamentPaymentStatus(entry);
            const submitted = entry.registration_status === 'submitted';
            return (
              <tr key={entry.id}>
                <td className="px-4 py-3 align-top"><span className="block text-xs text-gray-500">{entry.age_group}</span><span className="font-medium text-gray-900 dark:text-gray-100">{entry.team_name}</span></td>
                <td className="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{entry.assignees.map((assignee) => <div key={assignee.user_id}>{assignee.name}<span className={`ml-1 text-xs ${assignee.email ? 'text-green-600' : 'text-red-600'}`}>{assignee.email ? 'e-mail aanwezig' : 'e-mail ontbreekt'}</span></div>)}</td>
                <td className="px-4 py-3 align-top">{submitted ? <span className="inline-flex items-center text-green-700 dark:text-green-300"><CheckCircle2 className="mr-1 h-4 w-4" />Ingeschreven</span> : <span className="text-amber-700 dark:text-amber-300">Niet ingeschreven</span>}</td>
                <td className="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{submitted ? <><div>{entry.registered_team_count} teams · {entry.player_count} spelers</div><details className="mt-1"><summary className="cursor-pointer text-xs text-bright-cobalt dark:text-electric-cyan">Verdeling tonen</summary><ul className="mt-1 space-y-1 text-xs">{entry.submitted_team_entries.map((team) => <li key={team.sequence}>Team {team.sequence}: {team.player_count} spelers</li>)}</ul></details></> : '—'}</td>
                <td className="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{submitted ? <><div className="font-medium text-gray-900 dark:text-gray-100">{entry.contact_name}</div><div>{entry.contact_email}</div><div>{entry.contact_mobile}</div></> : '—'}</td>
                <td className="px-4 py-3 align-top"><div>{submitted ? formatTournamentCurrency(entry.total_amount) : '—'}</div>{submitted ? <><span className={`mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium ${tournamentPaymentToneClasses(paymentStatus.tone)}`}>{paymentStatus.label}</span>{entry.payment_state === 'paid' && entry.paid_at ? <span className="mt-1 block text-xs text-gray-500">{formatTournamentDate(entry.paid_at)}</span> : null}{entry.payment_state === 'error' || entry.payment_state === 'expired' ? <span className="mt-2 block text-xs text-gray-500">Rondo maakt de betaallink automatisch opnieuw.</span> : null}{entry.payment_state === 'open' && !archived ? <button type="button" className="mt-2 flex items-center text-xs font-medium text-bright-cobalt dark:text-electric-cyan" disabled={sendReminder.isPending} onClick={() => remind(entry)}><Mail className="mr-1 h-3.5 w-3.5" />Betaalherinnering sturen</button> : null}{entry.payment_state !== 'paid' && !archived ? <button type="button" className="mt-2 flex items-center text-xs font-medium text-gray-600 dark:text-gray-300" disabled={reopenEntry.isPending} onClick={() => reopen(entry)}><RotateCcw className="mr-1 h-3.5 w-3.5" />Inschrijving heropenen</button> : null}</> : null}</td>
                <td className="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{entry.last_payment_email_at ? <><div>{formatTournamentDate(entry.last_payment_email_at, true)}</div><div className="text-xs text-gray-500">{entry.payment_reminder_log.at(-1)?.type || ''}</div></> : '—'}</td>
                <td className="px-4 py-3 align-top"><PlannerNote key={`${entry.id}-${entry.planner_note}`} entry={entry} archived={archived} /></td>
              </tr>
            );
          })}</tbody>
        </table>
      </div>
    </section>
  );
}

function CommunicationPanel({ tournament }) {
  const saveProgram = useSaveTournamentProgram();
  const [programUrl, setProgramUrl] = useState(tournament.program_url || '');
  const [programMessage, setProgramMessage] = useState(tournament.program_message || '');
  const [subject, setSubject] = useState(tournament.program_last_send?.subject || `Programma ${tournament.name}`);
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [sent, setSent] = useState(null);
  const archived = tournament.lifecycle_status === 'archived';

  const submit = async (action) => {
    if (action === 'send' && !window.confirm(`Programma versturen naar ${preview?.recipient_count || 0} unieke adressen?`)) return;
    const data = new FormData();
    data.append('action', action);
    data.append('program_url', programUrl);
    data.append('program_message', programMessage);
    data.append('subject', subject);
    if (file) data.append('program_file', file);
    const result = await saveProgram.mutateAsync({ id: tournament.id, data });
    setPreview(result.preview);
    setSent(result.sent);
    setFile(null);
  };

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
      <section className="card space-y-5 p-5">
        <div><h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Programma verspreiden</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Rondo mailt alleen unieke adressen van definitief ingeschreven teams.</p></div>
        {tournament.program_attachment_url ? <a className="inline-flex items-center text-sm text-bright-cobalt dark:text-electric-cyan" href={tournament.program_attachment_url} target="_blank" rel="noreferrer"><FileText className="mr-2 h-4 w-4" />Huidig programmabestand openen<ExternalLink className="ml-1 h-3.5 w-3.5" /></a> : null}
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Programmabestand (PDF, maximaal 10 MB)
          <input className="input mt-1" type="file" accept="application/pdf" disabled={archived} onChange={(event) => setFile(event.target.files?.[0] || null)} />
        </label>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Programmalink
          <input className="input mt-1" type="url" value={programUrl} disabled={archived} placeholder="https://…" onChange={(event) => setProgramUrl(event.target.value)} />
        </label>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Onderwerp
          <input className="input mt-1" value={subject} disabled={archived} onChange={(event) => setSubject(event.target.value)} />
        </label>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Bericht
          <textarea className="input mt-1 min-h-36" value={programMessage} disabled={archived} onChange={(event) => setProgramMessage(event.target.value)} />
        </label>
        {saveProgram.error ? <ErrorNotice error={saveProgram.error} /> : null}
        {sent ? <p className={`text-sm ${sent.failed_count ? 'text-amber-700 dark:text-amber-300' : 'text-green-700 dark:text-green-300'}`}>{sent.sent_count} programmamails verzonden{sent.failed_count ? `, ${sent.failed_count} mislukt` : ''}.</p> : null}
        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
          <button type="button" className="btn-tertiary" disabled={archived || saveProgram.isPending} onClick={() => submit('preview')}>{saveProgram.isPending ? 'Bezig…' : 'Opslaan en doelgroep controleren'}</button>
          <button type="button" className="btn-primary inline-flex items-center justify-center" disabled={archived || saveProgram.isPending || !preview?.recipient_count || (!programUrl && !file && !tournament.program_attachment_id)} onClick={() => submit('send')}><Send className="mr-2 h-4 w-4" />Programma versturen</button>
        </div>
      </section>

      <aside className="card p-5">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Doelgroepvoorbeeld</h2>
        {!preview ? <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">Sla het programma op om de actuele doelgroep te controleren.</p> : <>
          <dl className="mt-4 grid grid-cols-3 gap-3 text-center"><div><dt className="text-xs text-gray-500">Uniek</dt><dd className="text-xl font-semibold">{preview.recipient_count}</dd></div><div><dt className="text-xs text-gray-500">Ontdubbeld</dt><dd className="text-xl font-semibold">{preview.deduplicated_count}</dd></div><div><dt className="text-xs text-gray-500">Problemen</dt><dd className="text-xl font-semibold">{preview.invalid_count}</dd></div></dl>
          <ul className="mt-5 space-y-3 text-sm">{preview.recipients.map((recipient) => <li key={recipient.email} className="border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800"><div className="font-medium text-gray-900 dark:text-gray-100">{recipient.email}</div><div className="text-xs text-gray-500">{recipient.teams.join(', ')} · {recipient.sources.join(', ')}</div></li>)}</ul>
          {preview.invalid.length ? <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950"><h3 className="text-sm font-semibold text-amber-800 dark:text-amber-200">Controle nodig</h3><ul className="mt-2 space-y-2 text-xs text-amber-800 dark:text-amber-200">{preview.invalid.map((row, index) => <li key={`${row.team}-${row.name}-${index}`}>{row.team}: {row.name} ({row.source}) - {row.reason}</li>)}</ul></div> : null}
        </>}
        {tournament.program_last_send ? <div className="mt-5 border-t border-gray-200 pt-4 text-sm dark:border-gray-700"><div className="font-medium text-gray-900 dark:text-gray-100">Laatste verzending</div><div className="mt-1 text-gray-600 dark:text-gray-400">{formatTournamentDate(tournament.program_last_send.sent_at, true)} · {tournament.program_last_send.sent_count} verzonden · {tournament.program_last_send.failed_count} mislukt</div></div> : null}
      </aside>
    </div>
  );
}

export default function TournamentOperations({ tournament }) {
  const [activeTab, setActiveTab] = useState('overview');
  const entriesQuery = useTournamentEntries(tournament.id);
  return (
    <div className="space-y-6">
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav aria-label="Toernooibeheer" className="flex gap-1 overflow-x-auto">
          {tabs.map((tab) => <button key={tab.id} type="button" className={`whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium ${activeTab === tab.id ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'}`} onClick={() => setActiveTab(tab.id)}>{tab.label}</button>)}
        </nav>
      </div>
      {activeTab === 'overview' ? <OverviewPanel tournament={tournament} /> : null}
      {activeTab === 'teams' ? <TeamsPaymentsPanel tournament={tournament} entries={entriesQuery.data || []} isLoading={entriesQuery.isLoading} error={entriesQuery.error} /> : null}
      {activeTab === 'communication' ? <CommunicationPanel tournament={tournament} /> : null}
    </div>
  );
}
