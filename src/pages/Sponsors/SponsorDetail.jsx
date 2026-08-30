import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Archive, ImagePlus, Plus, Save, Search, Trash2, UserPlus, X } from 'lucide-react';
import { wpApi } from '@/api/client';
import {
  useArchiveSponsor,
  useCreateSponsor,
  useCreateSponsorContact,
  useSponsor,
  useSponsorPersonOptions,
  useUpdateSponsor,
} from '@/hooks/useSponsors';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const emptyForm = {
  title: '', status: 'publish', sponsor_type: 'organization', sponsor_role: 'awc_sponsor', sponsit_contact_id: '',
  club_tv_priority: 0,
  club_tv_opt_out: false,
  website: '',
  address_street_name: '', address_house_number: '', address_house_number_addition: '',
  address_postal_code: '', address_city: '', address_country: 'Nederland', address_country_code: 'NL',
  contacts: [], logo_attachment_id: 0, logo_url: null,
};

function sponsorToForm(sponsor) {
  const fields = sponsor?.fields || {};
  return {
    ...emptyForm,
    title: sponsor?.title || '',
    status: sponsor?.status || 'publish',
    sponsor_type: fields.sponsor_type || 'organization',
    sponsor_role: fields.sponsor_role || 'awc_sponsor',
    club_tv_priority: Number(fields.club_tv_priority) || 0,
    club_tv_opt_out: Boolean(fields.club_tv_opt_out),
    sponsit_contact_id: fields.sponsit_contact_id || '',
    website: fields.website || '',
    address_street_name: fields.address_street_name || '',
    address_house_number: fields.address_house_number || '',
    address_house_number_addition: fields.address_house_number_addition || '',
    address_postal_code: fields.address_postal_code || '',
    address_city: fields.address_city || '',
    address_country: fields.address_country || 'Nederland',
    address_country_code: fields.address_country_code || 'NL',
    contacts: fields.contacts || [],
    logo_attachment_id: sponsor?.logo_attachment_id || 0,
    logo_url: sponsor?.logo_url || null,
  };
}

export default function SponsorDetail() {
  const { id } = useParams();
  const isNew = id === 'new';
  const navigate = useNavigate();
  const fileRef = useRef(null);
  const { data: sponsor, isLoading } = useSponsor(id, { enabled: !isNew });
  const [form, setForm] = useState(emptyForm);
  const [personSearch, setPersonSearch] = useState('');
  const [showNewContact, setShowNewContact] = useState(false);
  const [newContact, setNewContact] = useState({ first_name: '', infix: '', last_name: '', email: '', mobile: '', contact_role: 'Contactpersoon', receives_pass: true });
  const [errorMessage, setErrorMessage] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const createSponsor = useCreateSponsor();
  const updateSponsor = useUpdateSponsor();
  const archiveSponsor = useArchiveSponsor();
  const createContact = useCreateSponsorContact();
  const { data: personOptions = [] } = useSponsorPersonOptions(personSearch);

  useDocumentTitle(isNew ? 'Sponsor toevoegen' : sponsor?.title || 'Sponsor');

  useEffect(() => {
    if (sponsor) setForm(sponsorToForm(sponsor));
  }, [sponsor]);

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const updateContact = (index, changes) => {
    setForm((current) => {
      const contacts = current.contacts.map((contact, contactIndex) => ({
        ...contact,
        ...(contactIndex === index ? changes : {}),
        ...(changes.is_primary && contactIndex !== index ? { is_primary: false } : {}),
      }));
      return { ...current, contacts };
    });
  };

  const buildPayload = () => ({
    title: form.title.trim(),
    status: form.status,
    logo_attachment_id: form.logo_attachment_id || 0,
    fields: {
      sponsor_type: form.sponsor_type,
      sponsor_role: form.sponsor_role,
      club_tv_priority: Number(form.club_tv_priority),
      sponsit_contact_id: form.sponsit_contact_id.trim(),
      website: form.website.trim(),
      address_street_name: form.address_street_name.trim(),
      address_house_number: form.address_house_number.trim(),
      address_house_number_addition: form.address_house_number_addition.trim(),
      address_postal_code: form.address_postal_code.trim(),
      address_city: form.address_city.trim(),
      address_country: form.address_country.trim(),
      address_country_code: form.address_country_code.trim().toUpperCase(),
      contacts: form.contacts.map(({ person_id, contact_role, is_primary, receives_pass, is_primary_pass, sponsit_person_id }) => ({
        person_id,
        contact_role: contact_role || 'Contactpersoon',
        is_primary: Boolean(is_primary),
        receives_pass: Boolean(receives_pass),
        is_primary_pass: Boolean(receives_pass && is_primary_pass),
        sponsit_person_id: sponsit_person_id || '',
      })),
    },
  });

  const save = async (event) => {
    event.preventDefault();
    setErrorMessage('');
    try {
      if (isNew) {
        const response = await createSponsor.mutateAsync(buildPayload());
        navigate(`/sponsors/${response.data.id}`, { replace: true });
      } else {
        await updateSponsor.mutateAsync({ id: Number(id), data: buildPayload() });
      }
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Sponsor kon niet worden opgeslagen.');
    }
  };

  const uploadLogo = async (file) => {
    if (!file) return;
    setIsUploading(true);
    setErrorMessage('');
    try {
      const response = await wpApi.uploadMedia(file);
      updateField('logo_attachment_id', response.data.id);
      updateField('logo_url', response.data.source_url);
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Logo kon niet worden geüpload.');
    } finally {
      setIsUploading(false);
    }
  };

  const linkPerson = (person) => {
    if (form.contacts.some((contact) => Number(contact.person_id) === Number(person.id))) return;
    const relation = {
      person_id: person.id,
      person_name: person.name,
      person_type: person.person_type,
      email: person.email,
      contact_role: form.sponsor_type === 'person' ? 'Sponsor' : 'Contactpersoon',
      is_primary: true,
      receives_pass: true,
      is_primary_pass: form.sponsor_type === 'person',
      sponsit_person_id: '',
    };
    updateField('contacts', form.sponsor_type === 'person' ? [relation] : [...form.contacts, { ...relation, is_primary: form.contacts.length === 0, is_primary_pass: false }]);
    setPersonSearch('');
  };

  const submitNewContact = async (event) => {
    event.preventDefault();
    if (isNew) return;
    setErrorMessage('');
    try {
      await createContact.mutateAsync({
        sponsorId: Number(id),
        data: {
          ...newContact,
          contact_role: form.sponsor_type === 'person' ? 'Sponsor' : newContact.contact_role,
          is_primary: form.sponsor_type === 'person',
          is_primary_pass: form.sponsor_type === 'person',
        },
      });
      setShowNewContact(false);
      setNewContact({ first_name: '', infix: '', last_name: '', email: '', mobile: '', contact_role: 'Contactpersoon', receives_pass: true });
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Contactpersoon kon niet worden toegevoegd.');
    }
  };

  const archive = async () => {
    if (!confirm(`Weet je zeker dat je ${form.title || 'deze sponsor'} wilt archiveren?`)) return;
    await archiveSponsor.mutateAsync(Number(id));
    navigate('/sponsors');
  };

  if (!isNew && isLoading) return <div className="card p-6 text-gray-500">Sponsor laden…</div>;

  const isSaving = createSponsor.isPending || updateSponsor.isPending;

  return (
    <form onSubmit={save} className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Link to="/sponsors" className="btn-tertiary p-2" aria-label="Terug"><ArrowLeft className="h-5 w-5" /></Link>
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{isNew ? 'Sponsor toevoegen' : form.title}</h1>
            {!isNew && <p className="text-sm text-gray-500">Sponsor #{id}</p>}
          </div>
        </div>
        <div className="flex gap-2">
          {!isNew && form.status === 'publish' && <button type="button" className="btn-tertiary inline-flex items-center gap-2" onClick={archive}><Archive className="h-4 w-4" /> Archiveren</button>}
          <button type="submit" className="btn-primary inline-flex items-center gap-2" disabled={isSaving || isUploading}><Save className="h-4 w-4" /> {isSaving ? 'Opslaan…' : 'Opslaan'}</button>
        </div>
      </div>

      {errorMessage && <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">{errorMessage}</div>}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <section className="card space-y-5 p-5">
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Sponsorgegevens</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <label><span className="label">Type sponsor</span><select className="input w-full" value={form.sponsor_type} onChange={(event) => updateField('sponsor_type', event.target.value)}><option value="organization">Organisatie</option><option value="person">Persoon</option></select></label>
            <label><span className="label">{form.sponsor_type === 'person' ? 'Naam' : 'Organisatienaam'}</span><input className="input w-full" value={form.title} onChange={(event) => updateField('title', event.target.value)} required autoFocus={isNew} /></label>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <label><span className="label">Sponsorrol</span><select className="input w-full" value={form.sponsor_role} onChange={(event) => updateField('sponsor_role', event.target.value)}><option value="businessclub">Businessclub AWC</option><option value="awc_sponsor">AWC Sponsor</option></select></label>
            <label><span className="label">Status</span><select className="input w-full" value={form.status} onChange={(event) => updateField('status', event.target.value)}><option value="publish">Actief</option><option value="draft">Gearchiveerd</option></select></label>
          </div>
          <label className="block"><span className="label">Website</span><input className="input w-full" type="url" value={form.website} onChange={(event) => updateField('website', event.target.value)} placeholder="https://www.voorbeeld.nl" /></label>
          <label className="block"><span className="label">Sponsit contact-ID</span><input className="input w-full" value={form.sponsit_contact_id} onChange={(event) => updateField('sponsit_contact_id', event.target.value)} placeholder="Wordt normaal door Rondo Sync ingevuld" /></label>

          <div className="border-t border-gray-200 pt-5 dark:border-gray-700">
            <h3 className="mb-3 font-medium text-gray-900 dark:text-gray-100">Adres</h3>
            <div className="grid gap-4 sm:grid-cols-6">
              <label className="sm:col-span-4"><span className="label">Straat</span><input className="input w-full" value={form.address_street_name} onChange={(event) => updateField('address_street_name', event.target.value)} /></label>
              <label><span className="label">Huisnummer</span><input className="input w-full" value={form.address_house_number} onChange={(event) => updateField('address_house_number', event.target.value)} /></label>
              <label><span className="label">Toevoeging</span><input className="input w-full" value={form.address_house_number_addition} onChange={(event) => updateField('address_house_number_addition', event.target.value)} /></label>
              <label className="sm:col-span-2"><span className="label">Postcode</span><input className="input w-full" value={form.address_postal_code} onChange={(event) => updateField('address_postal_code', event.target.value)} /></label>
              <label className="sm:col-span-4"><span className="label">Plaats</span><input className="input w-full" value={form.address_city} onChange={(event) => updateField('address_city', event.target.value)} /></label>
              <label className="sm:col-span-4"><span className="label">Land</span><input className="input w-full" value={form.address_country} onChange={(event) => updateField('address_country', event.target.value)} /></label>
              <label className="sm:col-span-2"><span className="label">Landcode</span><input className="input w-full uppercase" maxLength={3} value={form.address_country_code} onChange={(event) => updateField('address_country_code', event.target.value)} /></label>
            </div>
          </div>
        </section>

        <section className="card p-5">
          <h2 className="font-semibold text-gray-900 dark:text-gray-100">Logo</h2>
          <button type="button" className="mt-4 flex aspect-video w-full items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white p-5 hover:border-gray-400 dark:border-gray-600 dark:bg-gray-900" onClick={() => fileRef.current?.click()}>
            {form.logo_url ? <img src={form.logo_url} alt="Logo" className="max-h-40 max-w-full object-contain" /> : <span className="flex flex-col items-center gap-2 text-sm text-gray-500"><ImagePlus className="h-8 w-8" /> {isUploading ? 'Uploaden…' : 'Logo kiezen'}</span>}
          </button>
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(event) => uploadLogo(event.target.files?.[0])} />
          {form.logo_url && <button type="button" className="mt-3 text-sm text-red-600" onClick={() => { updateField('logo_attachment_id', 0); updateField('logo_url', null); }}>Logo verwijderen</button>}
          <div className="mt-5 border-t border-gray-200 pt-5 dark:border-gray-700">
            {form.club_tv_opt_out ? (
              <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-100">
                Deze sponsor heeft via Mijn gegevens gekozen niet op Club TV te verschijnen.
              </p>
            ) : null}
            <label>
              <span className="label">Club TV-weergave</span>
              <select className="input w-full" value={form.club_tv_priority} onChange={(event) => updateField('club_tv_priority', Number(event.target.value))}>
                <option value={0}>Niet tonen</option>
                <option value={1}>Soms tonen</option>
                <option value={2}>Vaak tonen</option>
                <option value={3}>Altijd tonen</option>
              </select>
            </label>
            <p className="mt-2 text-xs text-gray-500">
              Vaak verschijnt ongeveer drie keer zo vaak als Soms. Maximaal zes sponsoren kunnen op Altijd staan.
            </p>
            {form.club_tv_priority > 0 && !form.logo_url ? (
              <p className="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">Deze sponsor verschijnt pas op Club TV nadat een logo is toegevoegd.</p>
            ) : null}
          </div>
        </section>
      </div>

      <section className="card space-y-4 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div><h2 className="font-semibold text-gray-900 dark:text-gray-100">{form.sponsor_type === 'person' ? 'Gekoppelde persoon' : 'Contactpersonen'}</h2><p className="text-sm text-gray-500">{form.sponsor_type === 'person' ? 'Koppel het persoonsprofiel dat bij deze sponsor hoort.' : 'Koppel bestaande personen of maak een extern contact aan.'}</p></div>
          {!isNew && (form.sponsor_type === 'organization' || form.contacts.length === 0) && <button type="button" className="btn-tertiary inline-flex items-center gap-2" onClick={() => setShowNewContact((value) => !value)}><UserPlus className="h-4 w-4" /> {form.sponsor_type === 'person' ? 'Persoon aanmaken' : 'Nieuw extern contact'}</button>}
        </div>

        {isNew ? <p className="rounded-lg bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-800">Sla de sponsor eerst op om een persoon te koppelen.</p> : (
          <div className="relative max-w-xl">
            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <input className="input input-leading-icon w-full" value={personSearch} onChange={(event) => setPersonSearch(event.target.value)} placeholder="Zoek een bestaande persoon" disabled={form.sponsor_type === 'person' && form.contacts.length > 0} />
            {personSearch.trim().length >= 2 && <div className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">{personOptions.map((person) => <button key={person.id} type="button" className="block w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700" onClick={() => linkPerson(person)}><span className="font-medium">{person.name}</span><span className="ml-2 text-xs text-gray-500">{person.email}</span></button>)}</div>}
          </div>
        )}

        {showNewContact && <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700"><div className="mb-3 flex items-center justify-between"><h3 className="font-medium">{form.sponsor_type === 'person' ? 'Persoon aanmaken' : 'Nieuw extern contact'}</h3><button type="button" onClick={() => setShowNewContact(false)}><X className="h-4 w-4" /></button></div><div className="grid gap-3 md:grid-cols-3"><input className="input" placeholder="Voornaam" value={newContact.first_name} onChange={(event) => setNewContact({ ...newContact, first_name: event.target.value })} /><input className="input" placeholder="Tussenvoegsel" value={newContact.infix} onChange={(event) => setNewContact({ ...newContact, infix: event.target.value })} /><input className="input" placeholder="Achternaam" value={newContact.last_name} onChange={(event) => setNewContact({ ...newContact, last_name: event.target.value })} /><input className="input" type="email" placeholder="E-mailadres" value={newContact.email} onChange={(event) => setNewContact({ ...newContact, email: event.target.value })} /><input className="input" placeholder="Mobiel" value={newContact.mobile} onChange={(event) => setNewContact({ ...newContact, mobile: event.target.value })} />{form.sponsor_type === 'organization' && <input className="input" placeholder="Contactrol" value={newContact.contact_role} onChange={(event) => setNewContact({ ...newContact, contact_role: event.target.value })} />}</div><button type="button" className="btn-primary mt-3 inline-flex items-center gap-2" onClick={submitNewContact} disabled={createContact.isPending}><Plus className="h-4 w-4" /> Persoon toevoegen</button></div>}

        {form.contacts.length === 0 ? <p className="text-sm text-gray-500">Nog geen persoon gekoppeld.</p> : <div className="space-y-3">{form.contacts.map((contact, index) => <div key={contact.person_id} className="grid gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,1fr)_auto_auto_auto_auto] lg:items-center"><div><Link to={`/people/${contact.person_id}`} className="font-medium text-electric-cyan hover:underline">{contact.person_name || `Persoon #${contact.person_id}`}</Link><p className="text-xs text-gray-500">{contact.email || contact.person_type}</p></div><input className="input" value={contact.contact_role || (form.sponsor_type === 'person' ? 'Sponsor' : 'Contactpersoon')} onChange={(event) => updateContact(index, { contact_role: event.target.value })} /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(contact.is_primary)} onChange={(event) => updateContact(index, { is_primary: event.target.checked })} /> Primair contact</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(contact.receives_pass)} onChange={(event) => updateContact(index, { receives_pass: event.target.checked, is_primary_pass: event.target.checked ? contact.is_primary_pass : false })} /> Sponsorpas</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(contact.is_primary_pass)} disabled={!contact.receives_pass} onChange={(event) => updateContact(index, { is_primary_pass: event.target.checked })} /> Primaire pas</label><button type="button" className="p-2 text-red-600" aria-label="Ontkoppelen" onClick={() => updateField('contacts', form.contacts.filter((_, contactIndex) => contactIndex !== index))}><Trash2 className="h-4 w-4" /></button></div>)}</div>}
      </section>
    </form>
  );
}
