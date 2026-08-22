import { useDeferredValue, useState } from 'react';
import { Link } from 'react-router-dom';
import { Building2, ImageOff, Plus, Search, User } from 'lucide-react';
import { useSponsors } from '@/hooks/useSponsors';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const roleLabels = {
  businessclub: 'Businessclub AWC',
  awc_sponsor: 'AWC Sponsor',
};

const clubTvLabels = {
  1: 'Soms',
  2: 'Vaak',
  3: 'Altijd',
};

export default function SponsorList() {
  useDocumentTitle('Sponsoren');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('active');
  const [role, setRole] = useState('');
  const [type, setType] = useState('');
  const [logo, setLogo] = useState('');
  const deferredSearch = useDeferredValue(search);
  const { data, isLoading, error } = useSponsors({
    search: deferredSearch || undefined,
    status,
    sponsor_role: role || undefined,
    sponsor_type: type || undefined,
    logo: logo || undefined,
    per_page: 100,
  });
  const sponsors = data?.items || [];

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Sponsoren</h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Beheer organisaties, persoonlijke sponsoren, logo&apos;s en contactpersonen.
          </p>
        </div>
        <Link to="/sponsors/new" className="btn-primary inline-flex items-center gap-2">
          <Plus className="h-4 w-4" /> Sponsor toevoegen
        </Link>
      </div>

      <div className="card grid gap-3 p-4 md:grid-cols-[minmax(16rem,1fr)_12rem_14rem_12rem_12rem]">
        <label className="relative block">
          <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          <input
            className="input input-leading-icon w-full"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Zoek op sponsor of contactpersoon"
          />
        </label>
        <select className="input" value={type} onChange={(event) => setType(event.target.value)}>
          <option value="">Alle typen</option>
          <option value="organization">Organisaties</option>
          <option value="person">Personen</option>
        </select>
        <select className="input" value={role} onChange={(event) => setRole(event.target.value)}>
          <option value="">Alle sponsorrollen</option>
          <option value="businessclub">Businessclub AWC</option>
          <option value="awc_sponsor">AWC Sponsor</option>
        </select>
        <select className="input" value={status} onChange={(event) => setStatus(event.target.value)}>
          <option value="active">Actief</option>
          <option value="archived">Gearchiveerd</option>
          <option value="all">Alles</option>
        </select>
        <select className="input" value={logo} onChange={(event) => setLogo(event.target.value)}>
          <option value="">Alle logo&apos;s</option>
          <option value="present">Met logo</option>
          <option value="missing">Zonder logo</option>
        </select>
      </div>

      {error ? (
        <div className="card p-6 text-red-700 dark:text-red-300">Sponsoren konden niet worden geladen.</div>
      ) : isLoading ? (
        <div className="card p-6 text-gray-500">Sponsoren laden…</div>
      ) : sponsors.length === 0 ? (
        <div className="card p-10 text-center">
          <Building2 className="mx-auto h-10 w-10 text-gray-300" />
          <p className="mt-3 font-medium text-gray-700 dark:text-gray-200">Geen sponsoren gevonden</p>
        </div>
      ) : (
        <div className="card overflow-hidden">
          <div className="hidden grid-cols-[5rem_minmax(14rem,1fr)_8rem_13rem_minmax(12rem,1fr)_7rem] gap-4 border-b border-gray-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 md:grid">
            <span>Logo</span><span>Sponsor</span><span>Type</span><span>Sponsorrol</span><span>Contact</span><span>Status</span>
          </div>
          <ul className="divide-y divide-gray-200 dark:divide-gray-700">
            {sponsors.map((sponsor) => {
              const primary = sponsor.fields?.contacts?.find((contact) => contact.is_primary);
              const sponsorType = sponsor.fields?.sponsor_type || 'organization';
              return (
                <li key={sponsor.id}>
                  <Link
                    to={`/sponsors/${sponsor.id}`}
                    className="grid gap-3 px-5 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 md:grid-cols-[5rem_minmax(14rem,1fr)_8rem_13rem_minmax(12rem,1fr)_7rem] md:items-center md:gap-4"
                  >
                    <div className="flex h-12 w-16 items-center justify-center rounded-lg bg-white ring-1 ring-gray-200 dark:ring-gray-700">
                      {sponsor.logo_url ? <img src={sponsor.logo_url} alt="" className="h-10 w-14 object-contain" /> : <ImageOff className="h-5 w-5 text-gray-300" />}
                    </div>
                    <div className="min-w-0">
                      <p className="truncate font-semibold text-gray-900 dark:text-gray-100">{sponsor.title}</p>
                      <p className="mt-0.5 text-sm text-gray-500 md:hidden">{roleLabels[sponsor.fields?.sponsor_role] || 'Geen sponsorrol'}</p>
                      {Number(sponsor.fields?.club_tv_priority) > 0 ? (
                        <p className="mt-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">Club TV: {clubTvLabels[sponsor.fields.club_tv_priority]}</p>
                      ) : null}
                    </div>
                    <span className="hidden items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 md:flex">
                      {sponsorType === 'person' ? <User className="h-4 w-4" /> : <Building2 className="h-4 w-4" />}
                      {sponsorType === 'person' ? 'Persoon' : 'Organisatie'}
                    </span>
                    <span className="hidden text-sm text-gray-700 dark:text-gray-300 md:block">{roleLabels[sponsor.fields?.sponsor_role] || '—'}</span>
                    <span className="text-sm text-gray-600 dark:text-gray-300">{primary?.person_name || (sponsorType === 'person' ? 'Nog niet gekoppeld' : 'Nog niet ingesteld')}</span>
                    <span className={`w-fit rounded-full px-2 py-1 text-xs font-medium ${sponsor.status === 'publish' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}`}>
                      {sponsor.status === 'publish' ? 'Actief' : 'Archief'}
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </div>
  );
}
