import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, ChevronRight, Loader2, Search } from 'lucide-react';
import { prmApi } from '@/api/client';

const queryOptions = { staleTime: 15 * 60 * 1000, retry: false, refetchOnWindowFocus: false };
const sourceTime = value => new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Amsterdam' }).format(new Date(value));

function ContactLinks({ phone, mobile, email }) {
  const numbers = [...new Set([phone, mobile].filter(Boolean))];
  return <div className="space-y-1 break-words">
    {numbers.map(number => <a className="block text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan" href={`tel:${number.replace(/[^+0-9*#]/g, '')}`} key={number}>{number}</a>)}
    {email && <a className="block break-all text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan" href={`mailto:${email}`}>{email}</a>}
    {!numbers.length && !email && <span className="text-gray-500 dark:text-gray-400">Geen contactgegevens beschikbaar</span>}
  </div>;
}

function RequestState({ query }) {
  if (query.isPending) return <p role="status" className="flex items-center gap-2 py-8 text-gray-600 dark:text-gray-300"><Loader2 className="h-5 w-5 animate-spin" aria-hidden="true" /> Gegevens worden opgehaald bij Sportlink. Dit kan even duren.</p>;
  if (query.error) return <div role="alert" className="space-y-3 py-6"><p className="text-red-700 dark:text-red-300">{query.error.response?.data?.message || 'Het adresboek kon niet worden geladen. Probeer het opnieuw.'}</p><button className="btn-secondary" onClick={() => query.refetch()}>Opnieuw proberen</button></div>;
  return null;
}

function ClubDetails({ id, back }) {
  const [role, setRole] = useState('');
  const query = useQuery({ queryKey: ['club-directory', 'detail', id], queryFn: async () => (await prmApi.getExternalClub(id)).data, ...queryOptions });
  const data = query.data;
  const roles = data ? [...new Set(data.contacts.map(contact => contact.function).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'nl')) : [];
  const visible = data?.contacts.filter(contact => !role || contact.function === role) || [];
  return <div className="space-y-6">
    <Link to={back} className="inline-flex items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan"><ArrowLeft className="h-4 w-4" aria-hidden="true" /> Terug naar verenigingen</Link>
    <RequestState query={query} />
    {data && <>
      <header className="space-y-2"><h1 className="text-2xl font-bold">{data.club.name}</h1><p className="text-gray-600 dark:text-gray-300">{[data.club.city, data.club.district].filter(Boolean).join(' · ')}</p></header>
      <div className="grid gap-6 border-y border-gray-200 py-5 dark:border-gray-700 sm:grid-cols-2">
        <div><h2 className="mb-2 text-sm font-semibold">Verenigingsgegevens</h2><p>{data.club.address}</p><p>{[data.club.postcode, data.club.city].filter(Boolean).join(' ')}</p><p className="mt-2 text-sm text-gray-600 dark:text-gray-300">Verenigingscode: {data.club.id}</p></div>
        <div><h2 className="mb-2 text-sm font-semibold">Algemeen contact</h2><ContactLinks {...data.club} /></div>
      </div>
      <section aria-labelledby="directory-contacts" className="space-y-4">
        <div className="flex flex-wrap items-end justify-between gap-4"><h2 id="directory-contacts" className="text-xl font-semibold">Contactpersonen</h2><div className="w-full sm:w-auto sm:min-w-64"><label className="label" htmlFor="directory-role">Functie</label><select id="directory-role" className="input w-full" value={role} onChange={event => setRole(event.target.value)}><option value="">Alle functies</option>{roles.map(value => <option key={value}>{value}</option>)}</select></div></div>
        <p role="status" className="text-sm text-gray-600 dark:text-gray-300">{visible.length} {visible.length === 1 ? 'functievermelding' : 'functievermeldingen'}. Een contactpersoon kan meerdere functies hebben.</p>
        {visible.length ? <ul className="divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-700 dark:border-gray-700">{visible.map((contact, index) => <li key={`${contact.id}-${contact.function}-${index}`} className="grid gap-3 py-4 sm:grid-cols-2 lg:grid-cols-3"><div><p className="font-semibold">{contact.name}</p><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{contact.function || 'Functie niet vermeld'}</p></div><div className="lg:col-span-2"><ContactLinks {...contact} /></div></li>)}</ul> : <p className="py-6 text-gray-600 dark:text-gray-300">Sportlink toont geen contactpersonen voor deze selectie.</p>}
      </section>
      <p className="text-sm text-gray-500 dark:text-gray-400">Bron: Sportlink · opgehaald op {sourceTime(data.fetched_at)}. Ververs de pagina om opnieuw te controleren; Sportlink-resultaten worden vijftien minuten hergebruikt.</p>
    </>}
  </div>;
}

function DirectorySearch({ search, field, onSearch, isFetching }) {
  const [input, setInput] = useState(search);
  const [inputField, setInputField] = useState(field);
  return (
    <form className="flex flex-wrap items-end gap-3" onSubmit={event => { event.preventDefault(); onSearch({ search: input.trim(), field: inputField }); }}>
      <div className="w-full sm:w-48"><label className="label" htmlFor="directory-field">Zoeken op</label><select id="directory-field" className="input w-full" value={inputField} onChange={event => setInputField(event.target.value)}><option value="name">Verenigingsnaam</option><option value="city">Plaats</option></select></div>
      <div className="min-w-0 flex-1 basis-60"><label className="label" htmlFor="directory-search">{inputField === 'city' ? 'Plaats' : 'Verenigingsnaam'}</label><input id="directory-search" className="input w-full" type="search" minLength={2} maxLength={80} required value={input} onChange={event => setInput(event.target.value)} placeholder={inputField === 'city' ? 'Bijvoorbeeld Wijchen' : 'Bijvoorbeeld Woezik'} /></div>
      <button className="btn-primary inline-flex items-center gap-2" type="submit" disabled={isFetching}><Search className="h-4 w-4" aria-hidden="true" /> Zoeken</button>
    </form>
  );
}

export default function ClubDirectory() {
  const { id } = useParams();
  const [params, setParams] = useSearchParams();
  const search = params.get('search') || '';
  const field = params.get('field') === 'city' ? 'city' : 'name';
  const query = useQuery({ queryKey: ['club-directory', 'search', search, field], queryFn: async () => (await prmApi.searchExternalClubs({ search, field })).data, enabled: !id && search.trim().length >= 2, ...queryOptions });
  const searchString = new URLSearchParams({ search, field }).toString();
  if (id) return <ClubDetails key={id} id={id} back={`/voetbal/verenigingen?${searchString}`} />;
  return <div className="space-y-6">
    <header className="space-y-2"><h1 className="text-2xl font-bold">Verenigingen</h1><p className="text-gray-600 dark:text-gray-300">Vind verenigingsgegevens en contactpersonen voor onder meer wedstrijdzaken in het Sportlink-adresboek.</p></header>
    <DirectorySearch key={searchString} search={search} field={field} onSearch={setParams} isFetching={query.isFetching} />
    {search.trim().length < 2 ? <p className="py-8 text-gray-600 dark:text-gray-300">Zoek met minimaal twee tekens in alle districten.</p> : <>
      <RequestState query={query} />
      {query.data && <section aria-label="Gevonden verenigingen" className="space-y-3">
        <p role="status" className="text-sm text-gray-600 dark:text-gray-300">{query.data.total} {query.data.total === 1 ? 'vereniging gevonden' : 'verenigingen gevonden'}.{query.data.truncated && ' De eerste 100 worden getoond. Maak je zoekopdracht specifieker.'}</p>
        <ul className="divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-700 dark:border-gray-700">{query.data.clubs.map(club => <li key={club.id}><Link className="flex items-center justify-between gap-4 px-2 py-4 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-bright-cobalt dark:hover:bg-gray-800" to={`/voetbal/verenigingen/${club.id}?${searchString}`}><div className="min-w-0"><h2 className="font-semibold">{club.name}</h2><p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{[club.city, club.district].filter(Boolean).join(' · ')}</p></div><ChevronRight className="h-5 w-5 shrink-0 text-gray-500" aria-hidden="true" /></Link></li>)}</ul>
        {query.data.total === 0 && <p className="py-6">Geen verenigingen gevonden. Probeer een andere naam of plaats.</p>}
        <p className="text-sm text-gray-500 dark:text-gray-400">Bron: Sportlink · opgehaald op {sourceTime(query.data.fetched_at)}.</p>
      </section>}
    </>}
  </div>;
}
