import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';

const endpoint = '/rondo/v1/alv';

function localTime(timestamp, timezone) {
  const date = new Date(timestamp * 1000);
  try {
    return date.toLocaleString('nl-NL', { timeZone: timezone, dateStyle: 'long', timeStyle: 'short' });
  } catch {
    // WordPress also supports fixed UTC offsets.
    const [, sign, hour, minute] = /^([+-])(\d\d):(\d\d)$/.exec(timezone) ?? [];
    const offset = (sign === '-' ? -1 : 1) * (Number(hour) * 60 + Number(minute));
    return new Date(date.getTime() + offset * 60000).toLocaleString('nl-NL', { timeZone: 'UTC', dateStyle: 'long', timeStyle: 'short' });
  }
}

function fields(event) {
  event.preventDefault();
  return Object.fromEntries(new FormData(event.currentTarget));
}

function Result({ result }) {
  return (
    <div className="space-y-2">
      <h3 className="font-semibold">{result.title}</h3>
      <dl>
        {result.options.map((option, index) => (
          <div key={option} className="flex justify-between gap-4 border-b py-1">
            <dt>{option}</dt><dd>{result.totals[index]}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

function Question({ question, manage, timezone, act, pending }) {
  const [choice, setChoice] = useState('');
  const status = { draft: 'Concept', open: 'Open', expired: 'Stemtermijn voorbij', closed: 'Gesloten' };
  return (
    <article className="card space-y-3 p-5">
      <h3 className="font-semibold">{question.title}</h3>
      <p className="text-sm text-gray-500">
        {question.secret ? 'Geheime stemming' : 'Open stemming'} · {status[question.state]}
        {question.ends && <> · Tot {localTime(question.ends, timezone)}</>}
      </p>
      {manage && <p>Uitgebrachte stemmen {question.turnout}</p>}
      {question.totals && <Result result={question} />}
      {question.voted && <p role="status">Je stem is ontvangen.</p>}
      {question.state === 'open' && question.can_vote && !question.voted && (
        <form className="space-y-3" onSubmit={(event) => {
          event.preventDefault();
          if (choice !== '' && window.confirm('Wil je deze stem definitief uitbrengen?')) {
            act('vote', { question: question.id, choice: Number(choice) });
          }
        }}>
          <fieldset>
            <legend>Kies één antwoord</legend>
            {question.options.map((option, index) => (
              <label className="flex items-center gap-2 py-1" key={option}>
                <input type="radio" name={question.id} checked={choice === String(index)} onChange={() => setChoice(String(index))} />
                {option}
              </label>
            ))}
          </fieldset>
          <button className="btn-primary" disabled={pending || choice === ''}>Stem uitbrengen</button>
        </form>
      )}
      {manage && question.state === 'draft' && (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(event) => act('open', { ...fields(event), question: question.id })}>
          <label>Sluiting ({timezone})<input type="datetime-local" className="input block" name="ends" required /></label>
          <button className="btn-primary" disabled={pending}>Stemming openen</button>
        </form>
      )}
      {manage && ['open', 'expired'].includes(question.state) && (
        <button className="btn-secondary" disabled={pending} onClick={() => {
          if (window.confirm('Wil je de stemming definitief sluiten?')) act('close', { question: question.id });
        }}>Stemming sluiten</button>
      )}
      {question.open_votes?.length > 0 && (
        <details>
          <summary>Individuele stemmen</summary>
          <ul>{question.open_votes.map((vote) => <li key={vote.id}>{vote.name} · {question.options[vote.choice]}</li>)}</ul>
        </details>
      )}
    </article>
  );
}

function PageForm({ page, closedQuestions, pending, act }) {
  return (
    <form className="space-y-3" onSubmit={(event) => act('save_page', {
      ...fields(event), page: page?.id, version: page?.version,
    })}>
      <label className="block">Titel<input className="input block w-full" name="title" defaultValue={page?.title} required /></label>
      <label className="block">HTML<textarea className="input block w-full font-mono" name="html" rows={6} defaultValue={page?.html} /></label>
      {closedQuestions.length > 0 && (
        <label className="block">Uitslag opnemen
          <select className="input block" name="result_question">
            <option value="">{page?.result ? 'Bestaande uitslag behouden' : 'Geen uitslag'}</option>
            {closedQuestions.map((question) => <option key={question.id} value={question.id}>{question.title}</option>)}
          </select>
        </label>
      )}
      <button className="btn-primary" disabled={pending}>Concept opslaan</button>
    </form>
  );
}

function Page({ page, rights, closedQuestions, act, pending }) {
  const editable = rights.edit || rights.approve || rights.publish;
  const statuses = { draft: 'Concept', review: 'Ter goedkeuring', approved: 'Goedgekeurd', published: 'Gepubliceerd' };
  const actions = [
    ['submit', 'Ter goedkeuring indienen', 'draft', rights.edit],
    ['approve', 'Goedkeuren', 'review', rights.approve],
    ['return', 'Terug naar concept', 'review', rights.approve],
    ['publish', 'Publiceren voor leden', 'approved', rights.publish],
  ];
  return (
    <article className="card space-y-3 p-5">
      <h3 className="font-semibold">{page.title}</h3>
      {editable && <p className="text-sm text-gray-500">{statuses[page.state]}</p>}
      <div className="prose dark:prose-invert" dangerouslySetInnerHTML={{ __html: page.html }} />
      {page.result && <Result result={page.result} />}
      <div className="flex flex-wrap gap-2">
        {actions.filter(([, , state, allowed]) => allowed && page.state === state).map(([action, label]) => (
          <button key={action} className="btn-secondary" disabled={pending} onClick={() => act(action, { page: page.id, version: page.version })}>
            {label}
          </button>
        ))}
      </div>
      {rights.edit && page.state !== 'review' && (
        <details>
          <summary>Pagina bewerken</summary>
          <PageForm key={page.version} page={page} closedQuestions={closedQuestions} act={act} pending={pending} />
        </details>
      )}
    </article>
  );
}

function Publication({ pages, meetingId, selectedId }) {
  const current = pages.find((page) => String(page.id) === selectedId) ?? pages[0];
  if (!current) return null;
  return (
    <section className="space-y-4">
      <nav aria-label="ALV-stukken">
        <ul className="space-y-1">
          {pages.map((page) => (
            <li key={page.id} style={{ marginInlineStart: `${page.depth}rem` }}>
              <Link aria-current={page.id === current.id ? 'page' : undefined} className="underline" to={`/alv/${meetingId}/pagina/${page.id}`}>{page.title}</Link>
            </li>
          ))}
        </ul>
      </nav>
      <article className="card space-y-3 p-5">
        <h2 className="text-lg font-semibold">{current.title}</h2>
        <div className="prose max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: current.html }} />
        {current.files.length > 0 && (
          <ul className="space-y-1">
            {current.files.map((file) => <li key={file.id}><a className="underline" href={file.url}>{file.title}</a></li>)}
          </ul>
        )}
      </article>
    </section>
  );
}

function Secretary({ meeting, act, pending }) {
  const [search, setSearch] = useState('');
  const [presentOnly, setPresentOnly] = useState(false);
  const [question, setQuestion] = useState('');
  const { people, guests, questions } = meeting.secretary;
  const visible = people.filter((person) => (!presentOnly || person.present)
    && person.name.toLocaleLowerCase('nl-NL').includes(search.trim().toLocaleLowerCase('nl-NL')));
  return (
    <section className="space-y-4">
      <p>{people.filter((person) => person.present).length + guests.length} aanwezig</p>
      <div className="flex flex-wrap items-end gap-4">
        <label>Zoek op naam<input className="input block" value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <label>Stemvraag
          <select className="input block" value={question} onChange={(event) => setQuestion(event.target.value)}>
            <option value="">Alle stemvragen</option>
            {Object.entries(questions).map(([id, title]) => <option value={id} key={id}>{title}</option>)}
          </select>
        </label>
        <label className="flex items-center gap-2"><input type="checkbox" checked={presentOnly} onChange={(event) => setPresentOnly(event.target.checked)} />Alleen aanwezigen</label>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-left">
          <thead><tr><th>Naam</th><th>Aanwezig</th><th>Stemdeelname</th></tr></thead>
          <tbody>
            {visible.map((person) => (
              <tr key={person.id} className="border-b">
                <th scope="row" className="py-2 font-normal">{person.name}</th>
                <td><input type="checkbox" aria-label={`Aanwezig: ${person.name}`} checked={person.present} disabled={pending} onChange={(event) => act('attendance', { person: person.id, present: event.target.checked })} /></td>
                <td>{person.eligible ? (question ? (person.voted.includes(question) ? 'Gestemd' : 'Nog niet gestemd') : `${person.voted.length} van ${Object.keys(questions).length}`) : 'Geen stemrecht'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="card space-y-3 p-4">
        <h2 className="font-semibold">Overige aanwezigen</h2>
        <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => act('guest_add', fields(event))}>
          <label>Naam<input className="input block" name="title" required /></label>
          <button className="btn-secondary" disabled={pending}>Aanwezige toevoegen</button>
        </form>
        <ul>{guests.map((guest) => <li key={guest.id} className="flex items-center justify-between gap-2 py-2">{guest.name}<button type="button" className="btn-secondary" disabled={pending} onClick={() => act('guest_remove', { person: guest.id })}>Verwijderen</button></li>)}</ul>
      </div>
    </section>
  );
}

export default function Alv({ secretary = false }) {
  const { id, pageId } = useParams();
  const [publishedPreview, setPublishedPreview] = useState(false);
  const navigate = useNavigate();
  const client = useQueryClient();
  const url = id ? `${endpoint}/${id}` : endpoint;
  const query = useQuery({
    queryKey: ['alv', id],
    gcTime: 0,
    queryFn: async () => (await api.get(url)).data,
    refetchInterval: id ? 10000 : false,
  });
  const mutation = useMutation({
    mutationFn: async ({ action, ...data }) => (await api.post(url, { action, ...data })).data,
    onSuccess: async (data) => {
      if (!id && data.id) navigate(`/alv/${data.id}`);
      await client.invalidateQueries({ queryKey: ['alv'] });
    },
  });
  const act = (action, data) => mutation.mutate({ action, ...data });
  const { isPending: pending } = mutation;
  if (query.isPending) return <p>ALV laden…</p>;
  if (query.isError || !query.data) return <p role="alert">{query.error?.response?.data?.message ?? 'De ALV kon niet worden geladen.'}</p>;
  const { meetings, rights, timezone } = query.data;
  const meeting = id ? meetings[0] : null;
  const showingDraft = !publishedPreview && Array.isArray(meeting?.draft_tree);
  const documentTree = showingDraft ? meeting.draft_tree : meeting?.tree;
  const closedQuestions = meeting?.questions.filter((question) => question.state === 'closed') ?? [];

  if (secretary) {
    return (
      <div className="space-y-5">
        <Link className="underline" to={`/alv/${id}`}>Terug naar de ALV</Link>
        <h1 className="text-2xl font-semibold">Presentielijst en stemdeelname</h1>
        {mutation.isError && <p role="alert">{mutation.error?.response?.data?.message ?? 'De wijziging kon niet worden opgeslagen.'}</p>}
        {meeting?.secretary ? <Secretary meeting={meeting} act={act} pending={pending} /> : <p>Je hebt geen toegang tot de presentielijst.</p>}
      </div>
    );
  }

  return (
    <div className="space-y-5">
      {meeting && <Link to="/alv" className="underline">Alle ALV’s</Link>}
      <h1 className="text-2xl font-semibold">{meeting?.title ?? 'ALV'}</h1>
      {mutation.isError && <p role="alert" className="text-red-700">{mutation.error?.response?.data?.message ?? 'De wijziging kon niet worden opgeslagen.'}</p>}
      {!meeting ? (
        <>
          {meetings.length === 0 && <p>Er zijn geen ALV’s beschikbaar.</p>}
          {meetings.map((item) => (
            <Link key={item.id} to={`/alv/${item.id}`} className="card block p-4">
              <span className="font-semibold">{item.title}</span>
              <span className="block">{localTime(item.starts, timezone)}</span>
            </Link>
          ))}
          {rights.manage && (
            <form className="card space-y-3 p-5" onSubmit={(event) => act('create', fields(event))}>
              <h2 className="font-semibold">Nieuwe ALV</h2>
              <label className="block">Titel<input className="input block w-full" name="title" required /></label>
              <label className="block">ALV-start ({timezone})<input className="input block" name="starts" type="datetime-local" required /></label>
              <button className="btn-primary" disabled={pending}>ALV aanmaken</button>
            </form>
          )}
        </>
      ) : (
        <>
          <p>Start {localTime(meeting.starts, timezone)}</p>
          {meeting.secretary && <Link className="btn-secondary inline-flex" to={`/alv/${id}/secretaris`}>Presentielijst en stemdeelname</Link>}
          {rights.manage && (
            <section className="card space-y-3 p-5">
              <h2 className="font-semibold">Stemlijst</h2>
              <p>{meeting.frozen ? 'Definitief' : 'Voorlopig'} · {meeting.roster.length} stemgerechtigden</p>
              {!meeting.frozen && (
                <form className="flex flex-wrap items-end gap-3" onSubmit={(event) => act('reschedule', fields(event))}>
                  <label>Nieuwe ALV-start ({timezone})<input className="input block" name="starts" type="datetime-local" required /></label>
                  <button className="btn-secondary" disabled={pending}>Starttijd wijzigen</button>
                </form>
              )}
              <details>
                <summary>Stemgerechtigden bekijken</summary>
                <p>{meeting.roster.filter((person) => !person.linked).length} zonder gekoppeld account</p>
                <ul className="mt-2 max-h-72 overflow-auto">
                  {meeting.roster.map((person) => <li key={person.id}>{person.name}{!person.linked && ' · Geen gekoppeld account'}</li>)}
                </ul>
              </details>
            </section>
          )}
          {(rights.manage || meeting.eligible) && (
            <section className="space-y-3">
              <h2 className="text-lg font-semibold">Stemmingen</h2>
              {meeting.questions.length === 0 && <p>Er zijn nog geen stemvragen.</p>}
              {meeting.questions.map((question) => (
                <Question key={question.id} question={question} manage={rights.manage} timezone={timezone} act={act} pending={pending} />
              ))}
              {rights.manage && (
                <form className="card space-y-3 p-5" onSubmit={(event) => act('question', fields(event))}>
                  <h3 className="font-semibold">Nieuwe stemvraag</h3>
                  <label className="block">Vraag<input className="input block w-full" name="title" required /></label>
                  <label className="block">Eén antwoordoptie per regel<textarea className="input block w-full" name="options" rows={3} required /></label>
                  <label className="flex items-center gap-2"><input type="checkbox" name="secret" value="1" defaultChecked />Geheime stemming</label>
                  <button className="btn-primary" disabled={pending}>Stemvraag toevoegen</button>
                </form>
              )}
            </section>
          )}
          <section className="space-y-3">
            <h2 className="text-lg font-semibold">ALV-pagina’s</h2>
            {meeting.admin_pages_url && <a className="underline" href={meeting.admin_pages_url}>ALV-pagina’s beheren in WordPress</a>}
            {Array.isArray(meeting.draft_tree) && (
              <div className="flex flex-wrap items-center gap-3">
                <p role="status">{showingDraft ? 'Conceptweergave · alleen voor beheerders' : 'Gepubliceerde ledenweergave'}</p>
                <button type="button" className="btn-secondary" onClick={() => setPublishedPreview(!publishedPreview)}>
                  {showingDraft ? 'Bekijk gepubliceerde versie' : 'Bekijk concept'}
                </button>
              </div>
            )}
            {documentTree?.length > 0 && <Publication pages={documentTree} meetingId={id} selectedId={pageId} />}
            {meeting.pages.length === 0 && !documentTree?.length && <p>Er zijn geen pagina’s beschikbaar.</p>}
            {meeting.pages.map((page) => (
              <Page key={page.id} page={page} rights={meeting.wordpress_pages ? {} : rights} closedQuestions={closedQuestions} act={act} pending={pending} />
            ))}
            {rights.edit && !meeting.wordpress_pages && (
              <details className="card p-5">
                <summary>Nieuwe ALV-pagina</summary>
                <PageForm closedQuestions={closedQuestions} act={act} pending={pending} />
              </details>
            )}
          </section>
        </>
      )}
    </div>
  );
}
