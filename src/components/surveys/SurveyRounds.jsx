import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import api from '@/api/client';
import { ErrorMessage, Field, Options, QuestionSelect } from './AnalysisControls';
import { percent, publicationLabel, uid } from './analysisUtils';

const assessments = { comparable: 'Vergelijkbaar', limited: 'Beperkt vergelijkbaar', incompatible: 'Niet vergelijkbaar' };
const blankRound = () => ({ key: uid(), survey_id: 0, version: 0, question: '', options: [], exclude: [], start: '', end: '', audience: '', compatibility: '', note: '' });

function RoundMapping({ round, index, catalog, change, remove, makeBaseline }) {
  const survey = catalog.find((item) => item.id === round.survey_id);
  const publication = survey?.versions.find((item) => item.id === round.version);
  const questions = publication?.questions || [];
  const question = questions.find((item) => item.id === round.question);
  return <fieldset className="space-y-4 border-t pt-5 dark:border-gray-700">
    <legend className="font-semibold">{index === 0 ? 'Basisronde' : `Ronde ${index + 1}`}</legend>
    <div className="grid gap-4 md:grid-cols-2">
      <Field label="Enquête voor deze ronde"><select required className="input w-full" value={round.survey_id || ''} onChange={(e) => change({ survey_id: Number(e.target.value), version: 0, question: '', options: [], exclude: [] })}>
        <option value="">Kies een afzonderlijke enquête</option>{round.survey_id > 0 && !survey && <option value={round.survey_id}>Enquête niet meer beschikbaar</option>}{catalog.map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}
      </select></Field>
      <Field label="Gepubliceerde versie"><select required className="input w-full" value={round.version || ''} onChange={(e) => change({ version: Number(e.target.value), question: '', options: [], exclude: [] })}>
        <option value="">Kies een publicatieversie</option>{round.version > 0 && !publication && <option value={round.version}>Versie niet meer beschikbaar</option>}{survey?.versions.map((item) => <option key={item.id} value={item.id}>{publicationLabel(survey.versions, item.id)}</option>)}
      </select></Field>
    </div>
    <QuestionSelect label="Vraag voor de meetdefinitie" value={round.question} questions={questions} onChange={(questionId) => change({ question: questionId, options: [], exclude: [] })} />
    <div className="grid gap-4 md:grid-cols-2"><Options label="Opties die meetellen" question={question} value={round.options} onChange={(options) => change({ options })} /><Options label="Opties uitsluiten uit de noemer" question={question} value={round.exclude} onChange={(exclude) => change({ exclude })} /></div>
    <div className="grid gap-4 md:grid-cols-2"><Field label="Begin meetperiode"><input required type="date" className="input w-full" value={round.start} onChange={(e) => change({ start: e.target.value })} /></Field><Field label="Einde meetperiode"><input required type="date" min={round.start || undefined} className="input w-full" value={round.end} onChange={(e) => change({ end: e.target.value })} /></Field></div>
    <Field label="Doelgroep van deze ronde"><input required maxLength={500} className="input w-full" value={round.audience} onChange={(e) => change({ audience: e.target.value })} /></Field>
    <Field label="Beoordeling vergelijkbaarheid"><select required className="input w-full" value={round.compatibility} onChange={(e) => change({ compatibility: e.target.value })}><option value="">Beoordeel de mapping</option>{Object.entries(assessments).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
    <Field label="Toelichting vergelijkbaarheid"><textarea required={round.compatibility !== '' && round.compatibility !== 'comparable'} rows={2} maxLength={1000} className="input w-full" value={round.note} onChange={(e) => change({ note: e.target.value })} /></Field>
    <div className="flex flex-wrap gap-3">{index > 0 && <button type="button" className="btn-tertiary" onClick={makeBaseline}>Als basisronde gebruiken</button>}<button type="button" className="btn-tertiary" onClick={remove}>Ronde verwijderen</button></div>
  </fieldset>;
}

function ComparisonEditor({ comparison, catalog, surveyId, version, base, saved, cancel }) {
  const [draft, setDraft] = useState(() => comparison ? { ...comparison, rounds: comparison.rounds.map((round) => ({ ...round, key: uid() })) } : { title: '', definition: '', rounds: [blankRound(), { ...blankRound(), survey_id: surveyId, version }] });
  const save = useMutation({ mutationFn: () => api.post(`${base}/analysis/rounds`, { id: comparison?.id || 0, revision: comparison?.revision, version, title: draft.title, definition: draft.definition, rounds: draft.rounds.map((round) => { const clean = { ...round }; delete clean.key; return clean; }) }), onSuccess: saved });
  const updateRound = (key, patch) => setDraft((old) => ({ ...old, rounds: old.rounds.map((round) => round.key === key ? { ...round, ...patch } : round) }));
  return <form className="card space-y-5 p-5" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
    <h2 className="text-lg font-semibold">{comparison ? 'Meetdefinitie bewerken' : 'Rondes koppelen'}</h2>
    <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">Kies per ronde één gepubliceerde versie van een andere enquête, met expliciete vragen en antwoordgroepen. Neem deze enquêteversie op. De eerste ronde is de basis voor verschillen.</p>
    <Field label="Naam vergelijking"><input required maxLength={200} className="input w-full" value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} /></Field>
    <Field label="Gedeelde meetdefinitie"><textarea required maxLength={2000} rows={3} className="input w-full" value={draft.definition} onChange={(e) => setDraft({ ...draft, definition: e.target.value })} /></Field>
    <p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">De opgegeven periode en doelgroep beschrijven de meting; ze filteren geen antwoorden. De cijfers gebruiken alle inzendingen van de gekozen versie. Gewijzigde schalen, vraagteksten, antwoordgroepen of doelgroepen krijgen een waarschuwing en geen automatisch verschil.</p>
    {draft.rounds.map((round, index) => <RoundMapping key={round.key} round={round} index={index} catalog={catalog} change={(patch) => updateRound(round.key, patch)} remove={() => setDraft({ ...draft, rounds: draft.rounds.filter((item) => item.key !== round.key) })} makeBaseline={() => setDraft({ ...draft, rounds: [round, ...draft.rounds.filter((item) => item.key !== round.key)] })} />)}
    <button type="button" className="btn-secondary" disabled={draft.rounds.length >= 10} onClick={() => setDraft({ ...draft, rounds: [...draft.rounds, blankRound()] })}>Ronde toevoegen</button>
    <ErrorMessage error={save.error} /><div className="flex flex-wrap gap-3"><button className="btn-primary" disabled={save.isPending || draft.rounds.length < 2}>Vergelijking opslaan</button><button type="button" className="btn-secondary" onClick={cancel}>Annuleren</button></div>
  </form>;
}

function RoundAssessment({ round }) {
  return <><span className="font-medium">{round.available ? assessments[round.assessment] : 'Bron niet beschikbaar'}</span><ul className="mt-1 space-y-1 text-xs">{(round.reasons || [round.error]).filter(Boolean).map((reason, i) => <li key={i}>{reason}</li>)}</ul></>;
}

function roundDifference(round, index) {
  return index === 0 ? 'Basis' : round.delta === null ? 'Niet berekend' : `${round.delta > 0 ? '+' : ''}${round.delta.toLocaleString('nl-NL')} pp`;
}

function ComparisonResult({ comparison, canManage, edit }) {
  return <article className="card space-y-4 p-5">
    <h2 className="text-lg font-semibold">{comparison.title}</h2><p className="max-w-prose whitespace-pre-wrap text-sm">{comparison.definition}</p>
    <p className="text-sm text-gray-600 dark:text-gray-300">Live herberekend per vragenlijstversie. Verschillen zijn procentpunten ten opzichte van de eerste ronde; kleine groep betekent minder dan 10 antwoorden.</p>
    <div role="region" aria-label={`Vergelijking ${comparison.title}`} tabIndex={0} className="hidden overflow-x-auto lg:block">
      <table className="w-full text-left text-sm"><thead><tr className="border-b dark:border-gray-700"><th className="min-w-48 p-3">Ronde en periode</th><th className="min-w-40 p-3">Doelgroep</th><th className="p-3">Inzendingen</th><th className="min-w-32 p-3">Telling / noemer</th><th className="p-3">Percentage</th><th className="min-w-32 p-3">Verschil</th><th className="min-w-56 p-3">Vergelijkbaarheid</th></tr></thead>
        <tbody>{comparison.results.map((round, index) => <tr key={`${round.survey_id}:${round.version}`} className="border-b align-top dark:border-gray-700">
          <th scope="row" className="p-3 font-medium">{round.survey_title || 'Bron niet beschikbaar'}{index === 0 && <span className="block text-xs font-normal">Basisronde</span>}<span className="mt-1 block text-xs font-normal">{round.start} t/m {round.end}</span></th>
          <td className="p-3">{round.audience}</td><td className="p-3 tabular-nums">{round.available ? round.total : '—'}</td><td className="p-3 tabular-nums">{round.available ? `${round.count} / ${round.denominator}` : '—'}{round.small && <span className="block text-xs text-amber-800 dark:text-amber-200">Kleine groep</span>}</td><td className="p-3 tabular-nums">{round.available ? percent(round.rate) : 'Niet beschikbaar'}</td><td className="p-3 tabular-nums">{roundDifference(round, index)}</td>
          <td className="p-3"><RoundAssessment round={round} /></td>
        </tr>)}</tbody>
      </table>
    </div>
    <div className="divide-y dark:divide-gray-700 lg:hidden">{comparison.results.map((round, index) => <section key={`${round.survey_id}:${round.version}`} className="space-y-3 py-4 text-sm">
      <h3 className="font-semibold">{round.survey_title || 'Bron niet beschikbaar'}{index === 0 && <span className="ml-2 text-xs font-normal">Basisronde</span>}</h3>
      <p>{round.start} t/m {round.end}<span className="mt-1 block">{round.audience}</span></p>
      <dl className="grid grid-cols-2 gap-3">
        <div><dt className="text-gray-600 dark:text-gray-300">Inzendingen</dt><dd className="mt-1 font-medium tabular-nums">{round.available ? round.total : 'Niet beschikbaar'}</dd></div>
        <div><dt className="text-gray-600 dark:text-gray-300">Telling / noemer</dt><dd className="mt-1 font-medium tabular-nums">{round.available ? `${round.count} / ${round.denominator}` : 'Niet beschikbaar'}</dd></div>
        <div><dt className="text-gray-600 dark:text-gray-300">Percentage</dt><dd className="mt-1 font-medium tabular-nums">{round.available ? percent(round.rate) : 'Niet beschikbaar'}</dd></div>
        <div><dt className="text-gray-600 dark:text-gray-300">Verschil</dt><dd className="mt-1 font-medium tabular-nums">{roundDifference(round, index)}</dd></div>
      </dl>
      {round.small && <p className="text-xs text-amber-800 dark:text-amber-200">Kleine groep</p>}
      <div><RoundAssessment round={round} /></div>
    </section>)}</div>
    <details className="border-t pt-4 dark:border-gray-700"><summary className="cursor-pointer text-sm font-medium">Vraagteksten, antwoordgroepen en noemers</summary><dl className="mt-4 space-y-4 text-sm">{comparison.results.filter((round) => round.available).map((round) => <div key={`${round.survey_id}:${round.version}`}><dt className="font-semibold">{round.survey_title}</dt><dd className="mt-1 max-w-prose">{round.question_label}</dd><dd className="mt-1">Telt mee: {round.options_labels.join(', ')}</dd><dd className="mt-1">Uitgesloten uit de noemer: {round.exclude_labels.length ? round.exclude_labels.join(', ') : 'Geen antwoordopties'}. Overgeslagen vragen tellen niet mee.</dd></div>)}</dl></details>
    {canManage && <button className="btn-secondary" onClick={edit}>Meetdefinitie bewerken</button>}
  </article>;
}

export default function SurveyRounds({ base, surveyId, version, canManage }) {
  const [editing, setEditing] = useState(null);
  const [notice, setNotice] = useState('');
  const comparisons = useQuery({ queryKey: ['survey-analysis-rounds', base, version], queryFn: async () => (await api.get(`${base}/analysis/rounds`, { params: { version } })).data });
  const catalog = useQuery({ queryKey: ['survey-analysis-round-catalog'], queryFn: async () => (await api.get('/rondo/v1/surveys/analysis-rounds')).data, enabled: canManage && editing !== null });
  return <div className="space-y-5">
    <div className="flex flex-wrap items-start justify-between gap-4"><p className="max-w-prose text-sm text-gray-600 dark:text-gray-300">Vergelijk afzonderlijke enquêtes met een gedeelde meetdefinitie. Revisies binnen één enquête worden nooit bij elkaar opgeteld; de antwoordfilters hierboven gelden niet voor rondevergelijkingen.</p>{canManage && !editing && <button className="btn-secondary" onClick={() => setEditing({})}>Vergelijking toevoegen</button>}</div>
    <ErrorMessage error={comparisons.error || catalog.error} />{(comparisons.error || catalog.error) && <button className="btn-secondary" onClick={() => { comparisons.refetch(); if (editing) catalog.refetch(); }}>Opnieuw proberen</button>}
    {notice && <p role="status" className="text-sm">{notice}</p>}
    {(comparisons.isPending || (editing && catalog.isPending)) && <p role="status">Rondevergelijkingen laden…</p>}
    {editing && catalog.data && <ComparisonEditor key={editing.id || 'new'} comparison={editing.id ? editing : null} catalog={catalog.data} surveyId={surveyId} version={version} base={base} saved={() => { setEditing(null); setNotice('Meetdefinitie opgeslagen.'); comparisons.refetch(); }} cancel={() => setEditing(null)} />}
    {comparisons.data?.length === 0 && !editing && <p className="text-sm">Er zijn nog geen rondes gekoppeld. {canManage ? 'Voeg een vergelijking toe zodra een andere enquête een overeenkomstige meting bevat.' : 'Een enquêtebeheerder kan overeenkomstige vragen en antwoordopties koppelen.'}</p>}
    {comparisons.data?.map((comparison) => <ComparisonResult key={comparison.id} comparison={comparison} canManage={canManage} edit={() => setEditing(comparison)} />)}
  </div>;
}
