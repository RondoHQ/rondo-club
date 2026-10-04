import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import api from '@/api/client';

const root = '/rondo/v1/match-compensation';
const statuses = [['', 'Kies status'], ['basis', 'Basis'], ['bank', 'Bank'], ['not_selected', 'Niet in selectie'], ['injured', 'Geblesseerd'], ['suspended', 'Geschorst'], ['other_team', 'Ander team']];
const euro = (cents) => new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(cents / 100);
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Amsterdam' }).format(new Date());
const category = (value) => ({ regulier: 'competitie', competitie: 'competitie', nacompetitie: 'nacompetitie', beker: 'beker', oefen: 'oefen', oefenwedstrijd: 'oefen' })[value?.trim().toLowerCase()] || '';
const request = (path) => api.get(root + path).then(({ data }) => data);
function ErrorMessage({ error }) { return error ? <p role="alert" className="text-red-700 dark:text-red-300">{error.response?.data?.message || 'Laden of opslaan mislukt. Probeer opnieuw.'}</p> : null; }
function useAction(path, method = 'post') {
  const client = useQueryClient();
  const last = useRef(null);
  return useMutation({ mutationFn: async (payload) => {
    const signature = JSON.stringify(payload);
    if (last.current?.signature !== signature) last.current = { signature, id: crypto.randomUUID() };
    return (await api[method](root + path, { ...payload, request_id: last.current.id })).data;
  }, onSuccess: () => client.invalidateQueries({ queryKey: ['match-compensation'] }) });
}
function Input({ label, ...props }) { return <label className="block text-sm">{label}<input className="input mt-1 w-full" {...props} /></label>; }
function Select({ label, children, ...props }) { return <label className="block text-sm">{label}<select className="input mt-1 w-full" {...props}>{children}</select></label>; }

function Settings({ settings }) {
  const [teams, setTeams] = useState(settings.teams.map(({ team_id, scheme }) => ({ team_id, scheme })));
  const [assignments, setAssignments] = useState(settings.available_users.map((user) => ({ user_id: user.id, teams: user.teams.filter((id) => Number.isInteger(id) && id > 0) })).filter((assignment) => assignment.teams.length > 0));
  const [bankCode, setBankCode] = useState(settings.bank_code);
  const [retention, setRetention] = useState(settings.retention_policy);
  const save = useAction('/settings');
  const changeTeam = (index, fields) => setTeams((old) => old.map((team, n) => n === index ? { ...team, ...fields } : team));
  return <form className="card p-6 space-y-4" onSubmit={(event) => { event.preventDefault(); save.mutate({ teams, registrators: assignments, bank_code: bankCode, retention_policy: retention }); }}>
    <h2 className="text-lg font-semibold">Pilotteams en registratoren</h2>
    <p className="text-sm text-gray-500">Koppel de bestaande teams aan hun regeling. Registratoren behouden hun bestaande teamtoegang.</p>
    {teams.map((team, index) => <div key={index} className="grid sm:grid-cols-3 gap-3">
      <Select label="Team" value={team.team_id} onChange={(event) => changeTeam(index, { team_id: Number(event.target.value) })}><option value={0}>Kies een team</option>{settings.available_teams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select>
      <Select label="Regeling" value={team.scheme} onChange={(event) => changeTeam(index, { scheme: event.target.value })}><option value="awc1">AWC 1 · €30 per punt</option><option value="jo23">JO23-1 · €15 per punt</option></Select>
      <button type="button" className="btn-tertiary self-end" onClick={() => setTeams((old) => old.filter((_, n) => n !== index))}>Team verwijderen</button>
    </div>)}
    <button type="button" className="btn-secondary" onClick={() => setTeams((old) => [...old, { team_id: 0, scheme: 'jo23' }])}>Team toevoegen</button>
    {assignments.map((assignment, index) => <div key={index} className="grid sm:grid-cols-3 gap-3">
      <Select label="Registrator" value={assignment.user_id} onChange={(event) => setAssignments((old) => old.map((row, n) => n === index ? { ...row, user_id: Number(event.target.value) } : row))}><option value={0}>Kies een gebruiker</option>{settings.available_users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</Select>
      <fieldset className="text-sm"><legend>Toegewezen teams</legend>{teams.filter((team) => team.team_id).map((team) => <label key={team.team_id} className="block mt-2"><input type="checkbox" checked={assignment.teams.includes(team.team_id)} onChange={(event) => setAssignments((old) => old.map((row, n) => n === index ? { ...row, teams: event.target.checked ? [...row.teams, team.team_id] : row.teams.filter((id) => id !== team.team_id) } : row))} /> {settings.available_teams.find((item) => item.id === team.team_id)?.name}</label>)}</fieldset>
      <button type="button" className="btn-tertiary self-end" onClick={() => setAssignments((old) => old.filter((_, n) => n !== index))}>Toewijzing verwijderen</button>
    </div>)}
    <button type="button" className="btn-secondary" onClick={() => setAssignments((old) => [...old, { user_id: 0, teams: [] }])}>Registrator toevoegen</button>
    <Input label="Nmbrs-code bankvergoeding (mag voorlopig leeg blijven)" value={bankCode} onChange={(event) => setBankCode(event.target.value)} />
    <label className="block text-sm">Vastgesteld clubbeleid voor bewaren<textarea className="input mt-1 w-full" rows={3} value={retention} onChange={(event) => setRetention(event.target.value)} /></label>
    <p className="text-sm text-gray-500">Leg vóór de eerste maandafsluiting het vastgestelde beleid vast. Bewaren en eventuele verwijdering worden afzonderlijk beoordeeld.</p>
    <ErrorMessage error={save.error} />
    {save.isSuccess ? <p role="status">Instellingen opgeslagen.</p> : null}
    <button disabled={save.isPending} className="btn-primary">Instellingen opslaan</button>
  </form>;
}

function ImportPanel() {
  const [entries, setEntries] = useState(null);
  const [fileError, setFileError] = useState('');
  const [backup, setBackup] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const preview = useAction('/imports/preview');
  const commit = useAction('/imports');
  return <section className="card p-6 space-y-4"><h2 className="text-lg font-semibold">Spreadsheet overnemen</h2><p className="text-sm text-gray-500">Gebruik een voorbereid importbestand waarin spelers en wedstrijden aan bestaande Rondo-records zijn gekoppeld. Controleer eerst de voorvertoning; de import legt geen betaalstatus vast.</p>
    <Input label="Voorbereid importbestand" type="file" accept="application/json,.json" onChange={async (event) => {
      preview.reset(); commit.reset(); setConfirmed(false); setEntries(null); setFileError('');
      try { const file = event.target.files?.[0]; if (!file) return; if (file.size > 2_000_000) throw new Error('Bestand te groot (maximaal 2 MB).'); const data = JSON.parse(await file.text()); const registrations = data.registrations; if (!Array.isArray(registrations)) throw new Error('Het bestand bevat geen gekoppelde wedstrijden.'); setEntries(registrations); } catch (error) { setFileError(error.message); }
    }} />
    {fileError ? <p role="alert" className="text-red-700">{fileError}</p> : null}
    <button className="btn-secondary" disabled={!entries || preview.isPending} onClick={() => preview.mutate({ registrations: entries })}>Voorvertoning controleren</button>
    <ErrorMessage error={preview.error || commit.error} />
    {preview.data ? <><ul className="list-disc pl-5">{preview.data.matches.map((match, index) => <li key={index}>{match.team} · {match.date} · {match.opponent} · {match.players} spelers{match.existing_id ? ' · al geïmporteerd' : ''}</li>)}</ul>{preview.data.errors.map((error, index) => <p role="alert" className="text-red-700" key={index}>{error}</p>)}<Input label="Referentie van bronkopie en backup" value={backup} onChange={(event) => setBackup(event.target.value)} /><label className="flex gap-2 text-sm"><input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />De koppelingen en aantallen zijn gecontroleerd; de bronkopie en backup zijn beschikbaar.</label><button className="btn-primary" disabled={!confirmed || !backup || preview.data.errors.length > 0 || commit.isPending || commit.isSuccess} onClick={() => commit.mutate({ registrations: entries, preview_hash: preview.data.preview_hash, backup_reference: backup, confirmed })}>Gecontroleerde wedstrijden importeren</button></> : null}
    {commit.isSuccess ? <p role="status">{commit.data.imported_ids.length} wedstrijden overgenomen. Controleer nu de maandtotalen en externe verwerkingsstatus.</p> : null}
  </section>;
}
function NmbrsName({ row }) {
  const [name, setName] = useState(row.nmbrs_name || '');
  const save = useAction(`/people/${row.person_id}/nmbrs`, 'patch');
  return <div className="flex flex-wrap items-end gap-2"><Input label={`Naam in Nmbrs: ${row.player_name}`} value={name} onChange={(event) => setName(event.target.value)} /><button className="btn-secondary" disabled={save.isPending || !name} onClick={() => save.mutate({ nmbrs_name: name })}>Naam bevestigen</button><ErrorMessage error={save.error} /></div>;
}

function RegistrationForm({ teamId, initial, roster, onSaved }) {
  const [form, setForm] = useState(initial);
  const [search, setSearch] = useState('');
  const [guestSearch, setGuestSearch] = useState('');
  const save = useAction(initial.id ? `/registrations/${initial.id}` : '/registrations', initial.id ? 'patch' : 'post');
  const guests = useQuery({ queryKey: ['match-guests', guestSearch], queryFn: () => api.get('/wp/v2/people', { params: { search: guestSearch, per_page: 20, _fields: 'id,title' } }).then(({ data }) => data), enabled: guestSearch.length >= 3 });
  const update = (fields) => setForm((old) => ({ ...old, ...fields }));
  const add = (person, guest = false) => {
    if (!form.selection.some((row) => row.person_id === person.id)) update({ selection: [...form.selection, { person_id: person.id, player_name: person.name || person.title?.rendered || '', participation: '', guest }] });
  };
  const submit = (phase) => {
    const fields = Object.fromEntries(['source_match_id', 'played_on', 'opponent_name', 'home', 'category', 'home_score', 'away_score', 'selection', 'reason'].map((key) => [key, form[key]]));
    save.mutate({ team_id: teamId, expected_version: initial.version, fields: { ...fields, phase } }, { onSuccess: onSaved });
  };
  return <section className="card p-4 sm:p-6 space-y-4">
    <h2 className="text-lg font-semibold">{initial.id ? 'Registratie aanpassen' : 'Wedstrijd registreren'}</h2>
    <div className="grid sm:grid-cols-2 gap-4">
      <Input label="Speeldatum" type="date" value={form.played_on} onChange={(event) => update({ played_on: event.target.value })} />
      <Input label="Tegenstander" value={form.opponent_name} onChange={(event) => update({ opponent_name: event.target.value })} />
      <Select label="Thuis of uit" value={String(form.home)} onChange={(event) => update({ home: event.target.value === 'true' })}><option value="true">Thuis</option><option value="false">Uit</option></Select>
      <Select label="Wedstrijdsoort" value={form.category} onChange={(event) => update({ category: event.target.value })}><option value="">Beoordeel de wedstrijdsoort</option>{['competitie', 'nacompetitie', 'beker', 'oefen'].map((value) => <option key={value}>{value}</option>)}</Select>
      <Input label="Doelpunten thuis" type="number" min="0" max="99" value={form.home_score ?? ''} onChange={(event) => update({ home_score: event.target.value === '' ? null : Number(event.target.value) })} />
      <Input label="Doelpunten uit" type="number" min="0" max="99" value={form.away_score ?? ''} onChange={(event) => update({ away_score: event.target.value === '' ? null : Number(event.target.value) })} />
    </div>
    <p className="text-sm">{form.selection.filter((row) => row.participation === 'basis').length} basisspelers · {form.selection.filter((row) => row.participation === 'bank').length} bankspelers</p>
    <ul className="divide-y dark:divide-gray-700">{form.selection.map((player) => <li key={player.person_id} className="py-3 flex flex-wrap items-center gap-3"><span className="flex-1 min-w-32">{player.player_name}{player.guest ? ' (gast)' : ''}</span><select aria-label={`Status ${player.player_name}`} className="input" value={player.participation} onChange={(event) => update({ selection: form.selection.map((row) => row.person_id === player.person_id ? { ...row, participation: event.target.value } : row) })}>{statuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><button className="btn-tertiary" onClick={() => update({ selection: form.selection.filter((row) => row.person_id !== player.person_id) })}>Verwijderen</button></li>)}</ul>
    <Select label="Speler uit het huidige team toevoegen" value={search} onChange={(event) => { const person = roster.find((person) => person.id === Number(event.target.value)); if (person) add(person); setSearch(''); }}><option value="">Kies een speler</option>{roster.filter((person) => !form.selection.some((row) => row.person_id === person.id)).map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</Select>
    <Input label="Gastspeler zoeken (minimaal 3 tekens)" value={guestSearch} onChange={(event) => setGuestSearch(event.target.value)} />
    <ErrorMessage error={guests.error} />
    {guestSearch.length >= 3 ? <div className="flex flex-wrap gap-2">{guests.data?.map((person) => <button className="btn-secondary" key={person.id} onClick={() => { add(person, true); setGuestSearch(''); }}>{person.title.rendered}</button>)}</div> : null}
    <Input label="Toelichting bij handmatige invoer, afwijking of correctie" value={form.reason} onChange={(event) => update({ reason: event.target.value })} />
    <ErrorMessage error={save.error} />
    <div className="flex flex-wrap gap-3"><button className="btn-secondary" disabled={save.isPending} onClick={() => submit('draft')}>Concept opslaan</button><button className="btn-primary" disabled={save.isPending} onClick={() => submit('completed')}>Registratie afronden</button><button className="btn-tertiary" onClick={() => onSaved()}>Sluiten</button></div>
  </section>;
}
function Registrations({ team }) {
  const [selected, setSelected] = useState(null);
  const registrations = useQuery({ queryKey: ['match-compensation', 'registrations', team.team_id], queryFn: () => request(`/teams/${team.team_id}/registrations`) });
  const fixtures = useQuery({ queryKey: ['team-matches', team.team_id], queryFn: () => api.get(`/rondo/v1/teams/${team.team_id}/matches`).then(({ data }) => data) });
  const roster = useQuery({ queryKey: ['team-people', String(team.team_id)], queryFn: () => api.get(`/rondo/v1/teams/${team.team_id}/people`).then(({ data }) => data) });
  const blank = (match) => { const score = match?.result?.trim().match(/^(\d+)\s*-\s*(\d+)$/); return ({ source_match_id: match?.id || `manual:${crypto.randomUUID()}`, played_on: match?.date || today(), opponent_name: match ? (match.home ? match.away_team : match.home_team) : '', home: match?.home ?? true, category: category(match?.competition), home_score: score ? Number(score[1]) : null, away_score: score ? Number(score[2]) : null, selection: [], reason: '' }); };
  return <div className="space-y-4">
    <ErrorMessage error={registrations.error || fixtures.error || roster.error} />
    <div className="card p-6 space-y-4"><h2 className="text-lg font-semibold">Registraties {team.name}</h2><p className="text-sm text-gray-500">Controleer per wedstrijd de uitslag, wedstrijdsoort en selectie. Beker en oefenwedstrijden tellen niet mee voor vergoedingen.</p>
      {registrations.data?.map((record) => <button key={record.id} className="block w-full text-left rounded border p-3 dark:border-gray-700" onClick={() => setSelected(record)}>{record.played_on} · {record.opponent_name} · {record.phase === 'completed' ? 'Afgerond' : 'Concept'}</button>)}
      <Select label="Wedstrijd uit het programma" value="" onChange={(event) => { const match = fixtures.data?.matches.find((match) => String(match.id) === event.target.value); if (match) setSelected(blank(match)); }}><option value="">Kies een wedstrijd</option>{fixtures.data?.matches.filter((match) => !match.cancelled && !registrations.data?.some((record) => String(record.source_match_id) === String(match.id))).map((match) => <option key={match.id} value={match.id}>{match.date} · {match.home_team} - {match.away_team}{match.result ? ` · ${match.result}` : ''}</option>)}</Select>
      <button className="btn-secondary" onClick={() => setSelected(blank())}>Handmatige wedstrijd</button>
    </div>
    {selected ? <RegistrationForm key={`${selected.id || selected.source_match_id}:${selected.version || 0}`} teamId={team.team_id} initial={selected} roster={roster.data?.current || []} onSaved={() => setSelected(null)} /> : null}
  </div>;
}

function BatchActions({ batch, canManage, onChanged }) {
  const [account, setAccount] = useState({ debtor_name: '', debtor_iban: '', execution_date: today(), confirmed: false });
  const [processing, setProcessing] = useState({ date: today(), reference: '', kind: batch.scheme === 'awc1' ? 'nmbrs' : batch.rows.some((row) => row.amount_cents < 0) ? 'manual_correction' : 'paid' });
  const [reason, setReason] = useState('');
  const [unused, setUnused] = useState(false);
  const exportAction = useAction(`/batches/${batch.id}/exports`);
  const process = useAction(`/batches/${batch.id}/processing`);
  const correct = useAction(`/batches/${batch.id}/corrections`);
  const download = () => exportAction.mutate(account, { onSuccess: (file) => {
    const url = URL.createObjectURL(new Blob([file.content], { type: file.mime }));
    const anchor = document.createElement('a'); anchor.href = url; anchor.download = file.filename; anchor.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); onChanged();
  } });
  if (!canManage || batch.phase !== 'closed') return null;
  return <div className="space-y-5 border-t pt-4 dark:border-gray-700">
    {batch.scheme === 'jo23' && !batch.summary.exported ? <div className="grid sm:grid-cols-3 gap-3"><Input label="Naam rekeninghouder club" value={account.debtor_name} onChange={(event) => setAccount({ ...account, debtor_name: event.target.value })} /><Input label="Rabobank-IBAN club" value={account.debtor_iban} onChange={(event) => setAccount({ ...account, debtor_iban: event.target.value })} /><Input label="Uitvoerdatum" type="date" min={today()} value={account.execution_date} onChange={(event) => setAccount({ ...account, execution_date: event.target.value })} /></div> : null}
    {!batch.summary.exported ? <label className="flex gap-2 text-sm"><input type="checkbox" checked={account.confirmed} onChange={(event) => setAccount({ ...account, confirmed: event.target.checked })} />Ik heb bedragen en gegevens gecontroleerd; deze maand is nog niet verwerkt of klaargezet.</label> : <p className="text-sm">Opnieuw downloaden levert hetzelfde bestand op. Importeer een bestand niet tweemaal.</p>}
    <button className="btn-primary" disabled={exportAction.isPending || (!batch.summary.exported && !account.confirmed)} onClick={download}>{batch.summary.exported ? 'Bestaand bestand downloaden' : batch.scheme === 'awc1' ? 'Nmbrs-overzicht downloaden' : 'Rabobank-bestand downloaden'}</button>
    <ErrorMessage error={exportAction.error} />
    {batch.summary.processing ? <p role="status">Verwerking vastgelegd: {batch.summary.processing.date} · {batch.summary.processing.reference}</p> : <details><summary className="cursor-pointer font-medium">Verwerking buiten Rondo vastleggen</summary><div className="mt-3 space-y-3"><Input label="Verwerkingsdatum" type="date" value={processing.date} onChange={(event) => setProcessing({ ...processing, date: event.target.value })} /><Input label="Referentie van betaling of Nmbrs-verwerking" value={processing.reference} onChange={(event) => setProcessing({ ...processing, reference: event.target.value })} /><button className="btn-secondary" disabled={process.isPending} onClick={() => process.mutate(processing, { onSuccess: onChanged })}>Verwerking vastleggen</button><ErrorMessage error={process.error} /></div></details>}
    {<details><summary className="cursor-pointer font-medium">Maand corrigeren</summary><div className="mt-3 space-y-3"><Input label="Reden" value={reason} onChange={(event) => setReason(event.target.value)} />{!batch.summary.processing ? <label className="flex gap-2 text-sm"><input type="checkbox" checked={unused} onChange={(event) => setUnused(event.target.checked)} />Het oude bestand is niet verwerkt en zal niet meer worden gebruikt.</label> : <p className="text-sm">De nieuwe maandversie bevat alleen het verschil met de al verwerkte bedragen. Negatieve correcties handel je handmatig af.</p>}<button className="btn-secondary" disabled={(!batch.summary.processing && !unused) || !reason || correct.isPending} onClick={() => correct.mutate({ reason, previous_file_unused: unused }, { onSuccess: onChanged })}>Correctieronde openen</button><ErrorMessage error={correct.error} /></div></details>}
  </div>;
}
function Rows({ rows, scheme }) {
  return <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{['Speler', 'Basis', 'Bank', 'Winst', 'Gelijk', scheme === 'awc1' ? 'Wedstrijden' : 'Premie'].map((title) => <th key={title} className="p-2 text-left">{title}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={row.person_id} className="border-t dark:border-gray-700"><td className="p-2"><Link className="hover:underline" to={`/people/${row.person_id}`}>{row.player_name}</Link></td>{['basis', 'bank', 'wins', 'draws'].map((key) => <td key={key} className="p-2">{row[key]}</td>)}<td className="p-2">{scheme === 'awc1' ? row.days : euro(row.amount_cents)}</td></tr>)}</tbody></table></div>;
}
function Monthly({ team, settings }) {
  const [month, setMonth] = useState(today().slice(0, 7));
  const [confirmed, setConfirmed] = useState(false);
  const [batchId, setBatchId] = useState(null);
  const preview = useQuery({ queryKey: ['match-compensation', 'month', team.team_id, month], queryFn: () => request(`/months?team_id=${team.team_id}&month=${month}`) });
  const batch = useQuery({ queryKey: ['match-compensation', 'batch', batchId], queryFn: () => request(`/batches/${batchId}`), enabled: !!batchId });
  const close = useAction('/batches');
  const total = preview.data?.rows.reduce((sum, row) => sum + row.amount_cents, 0) || 0;
  return <div className="space-y-4"><section className="card p-6 space-y-4">
    <div className="flex flex-wrap justify-between gap-4"><h2 className="text-lg font-semibold">Maandverwerking {team.name}</h2><Input label="Maand" type="month" min="2026-07" max="2027-06" value={month} onChange={(event) => { setMonth(event.target.value); setConfirmed(false); setBatchId(null); }} /></div>
    <ErrorMessage error={preview.error} />
    {preview.isPending ? <p role="status">Maand laden…</p> : preview.data ? <><Rows rows={preview.data.rows} scheme={team.scheme} />{team.scheme === 'jo23' ? <p className="font-semibold">Totaal {euro(total)}</p> : <p className="text-sm">Nmbrs: aantallen voor snelinvoer. Bankvergoedingcode: {preview.data.bank_code || 'nog niet ingesteld'}.</p>}
      {preview.data.is_correction ? <div className="space-y-3"><h3 className="font-semibold">Te verwerken correctie op deze maand</h3><p className="text-sm">Alleen dit verschil met de al verwerkte maand komt in de nieuwe versie. Negatieve premies worden handmatig afgehandeld.</p><Rows rows={preview.data.settlement_rows} scheme={team.scheme} /></div> : null}
      {preview.data.errors.length ? <ul className="list-disc pl-5 text-amber-800 dark:text-amber-200">{preview.data.errors.map((message, index) => <li key={index}>{message}</li>)}</ul> : null}
      {team.scheme === 'awc1' && settings.can_manage ? <details><summary className="cursor-pointer">Namen in Nmbrs bevestigen</summary><div className="mt-4 space-y-4">{preview.data.rows.map((row) => <NmbrsName key={row.person_id} row={row} />)}</div></details> : null}
      {settings.can_manage && !preview.data.batches.some((batch) => batch.phase === 'closed') ? <><label className="flex gap-2 text-sm"><input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />Alle gespeelde competitie- en nacompetitiewedstrijden van deze maand zijn gecontroleerd en geregistreerd.</label><button className="btn-primary" disabled={!confirmed || preview.data.errors.length > 0 || close.isPending} onClick={() => close.mutate({ team_id: team.team_id, month, fingerprint: preview.data.fingerprint, all_matches_confirmed: true }, { onSuccess: (result) => { setBatchId(result.id); setConfirmed(false); } })}>Maand afsluiten</button><ErrorMessage error={close.error} /></> : null}
      {preview.data.batches.map((batch) => <button key={batch.id} className="block btn-secondary" onClick={() => setBatchId(batch.id)}>Versie {batch.version} · {batch.phase === 'closed' ? 'Afgesloten' : 'Vervangen'}{batch.exported ? ' · Geëxporteerd' : ''}{batch.processing ? ' · Verwerkt' : ''}</button>)}
    </> : null}
  </section>
    <ErrorMessage error={batch.error} />
    {batch.data ? <section className="card p-6 space-y-4"><h2 className="text-lg font-semibold">Vastgelegde maand · {batch.data.month} · versie {batch.data.version}</h2><Rows rows={batch.data.rows} scheme={batch.data.scheme} /><BatchActions key={batch.data.id} batch={batch.data} canManage={settings.can_manage} onChanged={() => batch.refetch()} /></section> : null}
  </div>;
}

export default function MatchCompensation() {
  const params = useParams();
  const [teamId, setTeamId] = useState(params.id ? Number(params.id) : 0);
  const [tab, setTab] = useState(params.id ? 'registration' : 'finance');
  const query = useQuery({ queryKey: ['match-compensation', 'settings'], queryFn: () => request('/settings') });
  if (query.isPending) return <p role="status">Wedstrijdvergoedingen laden…</p>;
  if (query.error) return <ErrorMessage error={query.error} />;
  const settings = query.data;
  const team = settings.teams.find((team) => team.team_id === teamId) || settings.teams[0];
  return <div className="max-w-6xl mx-auto space-y-6"><header><h1 className="text-2xl font-bold">Wedstrijdvergoedingen</h1><p className="text-gray-500 mt-1">AWC 1 en JO23-1 · seizoen 2026–2027</p></header>
    <nav className="flex flex-wrap gap-2" aria-label="Wedstrijdvergoedingen">{team?.can_register ? <button className={tab === 'registration' ? 'btn-primary' : 'btn-secondary'} onClick={() => setTab('registration')}>Registratie</button> : null}{settings.can_finance ? <button className={tab === 'finance' ? 'btn-primary' : 'btn-secondary'} onClick={() => setTab('finance')}>Maandverwerking</button> : null}{settings.can_configure ? <button className={tab === 'settings' ? 'btn-primary' : 'btn-secondary'} onClick={() => setTab('settings')}>Instellingen</button> : null}</nav>
    {tab === 'settings' && settings.can_configure ? <><Settings key={JSON.stringify(settings.teams)} settings={settings} /><ImportPanel /></> : <>{team ? <><Select label="Team" value={team.team_id} onChange={(event) => setTeamId(Number(event.target.value))}>{settings.teams.map((team) => <option key={team.team_id} value={team.team_id}>{team.name}</option>)}</Select>{tab === 'registration' && team.can_register ? <Registrations key={team.team_id} team={team} /> : settings.can_finance ? <Monthly key={team.team_id} team={team} settings={settings} /> : <p>Geen toegang tot dit onderdeel.</p>}<Link className="text-electric-cyan" to={`/teams/${team.team_id}`}>Terug naar team</Link></> : <div className="card p-6">Er zijn nog geen teams voor jou ingeschakeld.{settings.can_configure ? ' Koppel de pilotteams bij Instellingen.' : ''}</div>}</>}
  </div>;
}
