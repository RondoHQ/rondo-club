import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Check, Mail, MapPin, Pencil, Phone, Trash2, X } from 'lucide-react';
import {
  useCancelProfileEmailChange,
  usePendingProfileEmail,
  useRemoveSecondaryProfileEmail,
  useRequestProfileEmailChange,
  useUpdateHouseholdAddress,
  useUpdateProfilePhones,
} from '@/hooks/useMemberProfile';
import { formatPersonName } from '@/utils/formatters';

const PHONE_FIELDS = ['mobile_1', 'mobile_2', 'telephone_1', 'telephone_2'];
const TEXT_ACTION_CLASS = 'text-bright-cobalt transition-colors hover:text-deep-midnight focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt dark:text-electric-cyan dark:hover:text-electric-cyan-light';
const DANGER_ACTION_CLASS = 'text-red-600 transition-colors hover:text-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 dark:text-red-400 dark:hover:text-red-300';
const EMPTY_ADDRESS = {
  street_name: '',
  house_number: '',
  house_number_addition: '',
  postal_code: '',
  city: '',
  country: 'Nederland',
  country_code: 'NL',
};

function errorMessage(error, fallback) {
  return error?.response?.data?.message || fallback;
}

function SectionHeader({ icon: Icon, title, onEdit, editing }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <div className="flex items-center gap-2">
        <Icon className="h-5 w-5 text-gray-400" aria-hidden="true" />
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
      </div>
      {!editing ? (
        <button type="button" className="btn-secondary gap-2 px-3 py-1.5 text-sm" onClick={onEdit}>
          <Pencil className="h-4 w-4" aria-hidden="true" /> Aanpassen
        </button>
      ) : null}
    </div>
  );
}

function EmailEditor({ person, embedded }) {
  const fields = person.fields || {};
  const { data: pending } = usePendingProfileEmail();
  const requestChange = useRequestProfileEmailChange();
  const cancelChange = useCancelProfileEmailChange();
  const removeSecondary = useRemoveSecondaryProfileEmail();
  const [editing, setEditing] = useState(null);
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');

  const open = (slot, value) => {
    setEditing(slot);
    setEmail(value || '');
    setError('');
  };

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await requestChange.mutateAsync({ slot: editing, email });
      setEditing(null);
    } catch (requestError) {
      setError(errorMessage(requestError, 'De verificatiemail kon niet worden verstuurd.'));
    }
  };

  const promote = async () => {
    setError('');
    try {
      await requestChange.mutateAsync({ slot: 'primary', email: fields.email_2 });
    } catch (requestError) {
      setError(errorMessage(requestError, 'De verificatiemail kon niet worden verstuurd.'));
    }
  };

  const remove = async () => {
    if (!window.confirm('Tweede e-mailadres verwijderen?')) return;
    setError('');
    try {
      await removeSecondary.mutateAsync();
    } catch (removeError) {
      setError(errorMessage(removeError, 'Het tweede e-mailadres kon niet worden verwijderd.'));
    }
  };

  return (
    <section className={embedded ? 'rounded-lg border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/60' : 'card max-w-3xl p-5'}>
      <SectionHeader icon={Mail} title="E-mailadressen" onEdit={() => open('primary', fields.email_1)} editing={Boolean(editing)} />
      <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Een nieuw e-mailadres wordt pas opgeslagen nadat je de link in de verificatiemail hebt geopend.</p>
      <div className="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-100">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-300" aria-hidden="true" />
        <p>Let op: dit e-mailadres wordt ook aangepast in Sportlink, het systeem van de KNVB. Daarna moet je in Voetbal.nl inloggen met het nieuwe e-mailadres.</p>
      </div>

      {pending ? (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-100">
          <div className="font-medium">Wacht op bevestiging van {pending.email}</div>
          <button type="button" className="mt-2 underline" disabled={cancelChange.isPending} onClick={() => cancelChange.mutate()}>Aanvraag annuleren</button>
        </div>
      ) : null}

      <dl className="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <dt className="text-xs text-gray-500 dark:text-gray-400">Primair</dt>
          <dd className="mt-1 break-words text-sm text-gray-900 dark:text-gray-100">{fields.email_1 || 'Niet ingesteld'}</dd>
          {!editing ? <button type="button" className={`mt-2 text-sm font-medium ${TEXT_ACTION_CLASS}`} onClick={() => open('primary', fields.email_1)}>Wijzigen</button> : null}
        </div>
        <div>
          <dt className="text-xs text-gray-500 dark:text-gray-400">Tweede e-mailadres</dt>
          <dd className="mt-1 break-words text-sm text-gray-900 dark:text-gray-100">{fields.email_2 || 'Niet ingesteld'}</dd>
          {!editing ? (
            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium">
              <button type="button" className={TEXT_ACTION_CLASS} onClick={() => open('secondary', fields.email_2)}>{fields.email_2 ? 'Wijzigen' : 'Toevoegen'}</button>
              {fields.email_2 ? <button type="button" className={TEXT_ACTION_CLASS} onClick={promote}>Primair maken</button> : null}
              {fields.email_2 ? <button type="button" className={`inline-flex items-center gap-1 ${DANGER_ACTION_CLASS}`} onClick={remove}><Trash2 className="h-3.5 w-3.5" /> Verwijderen</button> : null}
            </div>
          ) : null}
        </div>
      </dl>

      {editing ? (
        <form className="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700" onSubmit={submit}>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300" htmlFor={`profile-email-${editing}`}>{editing === 'primary' ? 'Nieuw primair e-mailadres' : 'Nieuw tweede e-mailadres'}</label>
          <input id={`profile-email-${editing}`} className="input-field mt-1 w-full" type="email" required autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} />
          <div className="mt-4 flex gap-2">
            <button type="submit" className="btn-primary gap-2" disabled={requestChange.isPending}><Check className="h-4 w-4" /> Verificatielink sturen</button>
            <button type="button" className="btn-secondary gap-2" onClick={() => setEditing(null)}><X className="h-4 w-4" /> Annuleren</button>
          </div>
        </form>
      ) : null}
      {error ? <p className="mt-3 text-sm text-red-600 dark:text-red-400">{error}</p> : null}
    </section>
  );
}

function PhoneEditor({ person, embedded }) {
  const fields = useMemo(() => person.fields || {}, [person.fields]);
  const updatePhones = useUpdateProfilePhones();
  const [editing, setEditing] = useState(false);
  const [values, setValues] = useState(() => Object.fromEntries(PHONE_FIELDS.map((field) => [field, fields[field] || ''])));
  const [error, setError] = useState('');

  useEffect(() => {
    if (!editing) setValues(Object.fromEntries(PHONE_FIELDS.map((field) => [field, fields[field] || ''])));
  }, [editing, fields]);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await updatePhones.mutateAsync(values);
      setEditing(false);
    } catch (updateError) {
      setError(errorMessage(updateError, 'De telefoonnummers konden niet worden opgeslagen.'));
    }
  };

  const labels = { mobile_1: 'Mobiel', mobile_2: 'Mobiel 2', telephone_1: 'Telefoon', telephone_2: 'Telefoon 2' };

  return (
    <section className={embedded ? 'rounded-lg border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/60' : 'card max-w-3xl p-5'}>
      <SectionHeader icon={Phone} title="Telefoonnummers" onEdit={() => setEditing(true)} editing={editing} />
      {editing ? (
        <form className="mt-4" onSubmit={submit}>
          <div className="grid gap-4 sm:grid-cols-2">
            {PHONE_FIELDS.map((field) => (
              <label key={field} className="text-sm font-medium text-gray-700 dark:text-gray-300">
                {labels[field]}
                <input className="input-field mt-1 w-full" type="tel" value={values[field]} onChange={(event) => setValues((current) => ({ ...current, [field]: event.target.value }))} placeholder="+31 6 12345678" />
              </label>
            ))}
          </div>
          <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">Telefoon 2 blijft alleen in Rondo; Sportlink ondersteunt dit veld niet.</p>
          <div className="mt-4 flex gap-2">
            <button type="submit" className="btn-primary" disabled={updatePhones.isPending}>Opslaan</button>
            <button type="button" className="btn-secondary" onClick={() => setEditing(false)}>Annuleren</button>
          </div>
        </form>
      ) : (
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          {PHONE_FIELDS.map((field) => <div key={field}><div className="text-xs text-gray-500 dark:text-gray-400">{labels[field]}</div><div className="text-sm text-gray-900 dark:text-gray-100">{fields[field] || 'Niet ingesteld'}</div></div>)}
        </div>
      )}
      {error ? <p className="mt-3 text-sm text-red-600 dark:text-red-400">{error}</p> : null}
    </section>
  );
}

function homeAddress(people) {
  for (const person of people) {
    const addresses = person.fields?.addresses;
    if (!Array.isArray(addresses)) continue;
    const home = addresses.find((address) => String(address.address_label || '').toLowerCase() === 'home');
    if (home) return { ...EMPTY_ADDRESS, ...home };
  }
  return EMPTY_ADDRESS;
}

function AddressEditor({ people, embedded }) {
  const address = useMemo(() => homeAddress(people), [people]);
  const updateAddress = useUpdateHouseholdAddress();
  const [editing, setEditing] = useState(false);
  const [values, setValues] = useState(address);
  const [error, setError] = useState('');
  useEffect(() => { if (!editing) setValues(address); }, [address, editing]);

  const names = people.map((person) => formatPersonName(person.fields?.first_name, person.fields?.infix, person.fields?.last_name)).filter(Boolean);
  const submit = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await updateAddress.mutateAsync(values);
      setEditing(false);
    } catch (updateError) {
      setError(errorMessage(updateError, 'Het adres kon niet worden opgeslagen.'));
    }
  };

  return (
    <section className={embedded ? 'rounded-lg border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/60' : 'card max-w-3xl p-5'}>
      <SectionHeader icon={MapPin} title="Woonadres" onEdit={() => setEditing(true)} editing={editing} />
      <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Deze wijziging geldt automatisch voor {names.join(', ')}.</p>
      {editing ? (
        <form className="mt-4" onSubmit={submit}>
          <div className="grid gap-4 sm:grid-cols-6">
            <label className="text-sm font-medium text-gray-700 sm:col-span-4 dark:text-gray-300">Straat<input required className="input-field mt-1 w-full" value={values.street_name} onChange={(event) => setValues((current) => ({ ...current, street_name: event.target.value }))} /></label>
            <label className="text-sm font-medium text-gray-700 sm:col-span-1 dark:text-gray-300">Nr.<input required className="input-field mt-1 w-full" value={values.house_number} onChange={(event) => setValues((current) => ({ ...current, house_number: event.target.value }))} /></label>
            <label className="text-sm font-medium text-gray-700 sm:col-span-1 dark:text-gray-300">Toevoeging<input className="input-field mt-1 w-full" value={values.house_number_addition} onChange={(event) => setValues((current) => ({ ...current, house_number_addition: event.target.value }))} /></label>
            <label className="text-sm font-medium text-gray-700 sm:col-span-2 dark:text-gray-300">Postcode<input required className="input-field mt-1 w-full" value={values.postal_code} onChange={(event) => setValues((current) => ({ ...current, postal_code: event.target.value }))} /></label>
            <label className="text-sm font-medium text-gray-700 sm:col-span-4 dark:text-gray-300">Plaats<input required className="input-field mt-1 w-full" value={values.city} onChange={(event) => setValues((current) => ({ ...current, city: event.target.value }))} /></label>
          </div>
          <div className="mt-4 flex gap-2">
            <button type="submit" className="btn-primary" disabled={updateAddress.isPending}>Opslaan voor gezin</button>
            <button type="button" className="btn-secondary" onClick={() => setEditing(false)}>Annuleren</button>
          </div>
        </form>
      ) : (
        <p className="mt-4 text-sm text-gray-900 dark:text-gray-100">{[`${address.street_name} ${address.house_number}${address.house_number_addition}`.trim(), `${address.postal_code} ${address.city}`.trim()].filter(Boolean).join(', ') || 'Niet ingesteld'}</p>
      )}
      {error ? <p className="mt-3 text-sm text-red-600 dark:text-red-400">{error}</p> : null}
    </section>
  );
}

export default function MemberProfileEditors({ people, linkedPersonId, embedded = false }) {
  const self = people.find((person) => person.id === linkedPersonId);
  if (!self) return null;
  return (
    <div className="space-y-4">
      <EmailEditor person={self} embedded={embedded} />
      <PhoneEditor person={self} embedded={embedded} />
      <AddressEditor people={people} embedded={embedded} />
    </div>
  );
}
