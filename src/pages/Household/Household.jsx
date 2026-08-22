import { useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Users, Mail, Phone, Smartphone, MapPin, Calendar, IdCard, ShieldCheck, ExternalLink, Building2, ImagePlus, LoaderCircle } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useUploadSponsorLogo } from '@/hooks/useSponsors';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatPersonName, parseFieldDate } from '@/utils/formatters';
import { format } from '@/utils/dateFormat';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

/**
 * Format an native field date_picker value (stored as YYYYMMDD) for display.
 */
function formatFieldDate(value) {
  const date = parseFieldDate(value);
  if (!date || Number.isNaN(date.getTime())) return null;
  return format(date, 'd MMMM yyyy');
}

function firstAddress(addresses) {
  if (!Array.isArray(addresses) || addresses.length === 0) return null;
  const {
    street_name: street,
    house_number: houseNumber,
    house_number_addition: addition,
    postal_code: postalCode,
    city,
  } = addresses[0] || {};
  const line = [street, [houseNumber, addition].filter(Boolean).join('')].filter(Boolean).join(' ');
  const place = [postalCode, city].filter(Boolean).join(' ');
  const full = [line, place].filter(Boolean).join(', ');
  return full || null;
}

function Detail({ icon: Icon, label, value }) {
  if (!value) return null;
  return (
    <div className="flex items-start gap-3 py-1.5">
      <Icon className="w-4 h-4 mt-0.5 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
      <div className="min-w-0">
        <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>
        <div className="text-sm text-gray-900 dark:text-gray-100 break-words">{value}</div>
      </div>
    </div>
  );
}

function Eyebrow({ children }) {
  return (
    <div className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
      {children}
    </div>
  );
}

function SponsorLogoEditor({ organization }) {
  const fileRef = useRef(null);
  const [errorMessage, setErrorMessage] = useState('');
  const uploadLogo = useUploadSponsorLogo();

  const handleLogoChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setErrorMessage('');
    try {
      await uploadLogo.mutateAsync({ id: organization.id, file });
    } catch (error) {
      setErrorMessage(error.response?.data?.message || 'Het logo kon niet worden opgeslagen.');
    } finally {
      event.target.value = '';
    }
  };

  return (
    <div className="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
      <div className="flex items-start gap-3">
        <Building2 className="mt-0.5 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
        <div className="min-w-0">
          <Eyebrow>Sponsor</Eyebrow>
          <h3 className="mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-gray-100">
            {organization.name}
          </h3>
        </div>
      </div>

      <button
        type="button"
        className="mt-4 flex min-h-36 w-full items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white p-4 transition-colors hover:border-bright-cobalt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt disabled:cursor-wait disabled:opacity-70 dark:border-gray-600 dark:bg-gray-900"
        onClick={() => fileRef.current?.click()}
        disabled={uploadLogo.isPending}
      >
        {uploadLogo.isPending ? (
          <span className="flex items-center gap-2 text-sm text-gray-500">
            <LoaderCircle className="h-5 w-5 animate-spin" aria-hidden="true" />
            Logo uploaden…
          </span>
        ) : organization.logo_url ? (
          <img src={organization.logo_url} alt={`Logo van ${organization.name}`} className="max-h-32 max-w-full object-contain" />
        ) : (
          <span className="flex flex-col items-center gap-2 text-sm text-gray-500">
            <ImagePlus className="h-8 w-8" aria-hidden="true" />
            Bedrijfslogo toevoegen
          </span>
        )}
      </button>
      <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" className="hidden" onChange={handleLogoChange} />
      <p className="mt-2 text-xs text-gray-500">
        Klik op het logo om het te vervangen. JPEG, PNG, GIF, WebP of SVG, maximaal 5 MB.
      </p>
      {errorMessage ? <p className="mt-2 text-sm text-red-600 dark:text-red-400">{errorMessage}</p> : null}
    </div>
  );
}

function PersonCard({ person, isSelf, isParent }) {
  const fields = person.fields || {};
  const name = formatPersonName(fields.first_name, fields.infix, fields.last_name) || 'Onbekend';
  const membershipPass = person.membership_pass;
  const sponsorOrganization = person.sponsor_organization;

  return (
    <div className="card p-5">
      <div className="flex items-center justify-between gap-3 mb-3">
        <div className="min-w-0">
          {isSelf && sponsorOrganization ? (
            <Eyebrow>{isParent ? 'Contactpersoon en ouder' : 'Contactpersoon'}</Eyebrow>
          ) : null}
          <h2 className={`${isSelf && sponsorOrganization ? 'mt-0.5 ' : ''}break-words font-semibold text-gray-900 dark:text-gray-100`}>
            {name}
          </h2>
        </div>
        <span className="inline-block px-2 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300">
          {isSelf ? 'Jij' : 'Kind'}
        </span>
      </div>

      <div className="grid gap-x-8 sm:grid-cols-2">
        <Detail icon={Mail} label="E-mail" value={fields.email_1} />
        <Detail icon={Smartphone} label="Mobiel" value={fields.mobile_1} />
        <Detail icon={Phone} label="Telefoon" value={fields.telephone_1} />
        <Detail icon={MapPin} label="Adres" value={firstAddress(fields.addresses)} />
        <Detail icon={Calendar} label="Geboortedatum" value={formatFieldDate(fields.birthdate)} />
        <Detail icon={Users} label="Leeftijdsgroep" value={fields.leeftijdsgroep} />
        <Detail icon={IdCard} label="KNVB-ID" value={fields['knvb_id']} />
        <Detail icon={Calendar} label="Lid sinds" value={formatFieldDate(fields['lid_sinds'])} />
        <Detail icon={ShieldCheck} label="VOG afgegeven" value={formatFieldDate(fields['datum_vog'])} />
      </div>

      {membershipPass ? (
        <div className="mt-4 flex max-w-3xl flex-col gap-3 rounded-lg border border-cyan-100 bg-cyan-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/60 sm:flex-row sm:items-center">
          <div className="flex min-w-0 flex-1 items-start gap-3">
            <IdCard className="mt-0.5 h-5 w-5 shrink-0 text-bright-cobalt dark:text-electric-cyan" aria-hidden="true" />
            <div className="min-w-0">
              <div className="text-sm font-medium text-gray-900 dark:text-gray-100">{membershipPass.label}</div>
              <p className="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                Open de pas om hem aan Apple Wallet of Google Wallet toe te voegen.
              </p>
            </div>
          </div>
          <a
            href={membershipPass.url}
            target="_blank"
            rel="noopener noreferrer"
            className="btn-secondary justify-center gap-2 whitespace-nowrap text-sm"
          >
            {membershipPass.label} openen
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </a>
        </div>
      ) : null}

      {isSelf && sponsorOrganization?.can_edit_logo ? (
        <SponsorLogoEditor organization={sponsorOrganization} />
      ) : null}
    </div>
  );
}

/**
 * "Mijn gezin" — the member's own record plus their children under 18.
 *
 * The dedicated endpoint always returns the linked person and minor children,
 * even when the caller has broader management privileges.
 */
export default function Household() {
  useDocumentTitle('Mijn gegevens');

  const { data: currentUser } = useCurrentUser();

  const { data: people = [], isLoading, isError } = useQuery({
    queryKey: ['household'],
    queryFn: async () => {
      const response = await prmApi.getHousehold();
      return response.data;
    },
  });

  const linkedPersonId = currentUser?.linked_person_id ?? null;

  const ordered = useMemo(() => {
    const self = people.filter((person) => person.id === linkedPersonId);
    const others = people.filter((person) => person.id !== linkedPersonId);
    return [...self, ...others];
  }, [people, linkedPersonId]);

  if (isLoading) return <ContentLoadingSpinner />;

  if (isError) {
    return (
      <div className="card p-5">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          We konden je gegevens niet ophalen. Probeer het later opnieuw.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mijn gegevens</h1>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Je eigen gegevens en die van je kinderen onder de 18, zoals ze bij de club bekend zijn.
          Klopt er iets niet? Geef het door aan de ledenadministratie.
        </p>
      </div>

      {ordered.length === 0 ? (
        <div className="card p-5">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            We konden geen ledengegevens aan je account koppelen. Neem contact op met de ledenadministratie.
          </p>
        </div>
      ) : (
        ordered.map((person) => (
          <PersonCard
            key={person.id}
            person={person}
            isSelf={person.id === linkedPersonId}
            isParent={currentUser?.is_parent === true}
          />
        ))
      )}
    </div>
  );
}
