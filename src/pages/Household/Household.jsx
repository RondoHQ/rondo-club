import { useCallback, useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Users, Mail, Phone, Smartphone, MapPin, Calendar, IdCard, ShieldCheck, Building2, ReceiptEuro, ImagePlus, LoaderCircle, Pencil, UserRoundPlus, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useUploadSponsorLogo } from '@/hooks/useSponsors';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatCurrency, formatPersonName, parseFieldDate } from '@/utils/formatters';
import { format } from '@/utils/dateFormat';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import AnchoredPopover from '@/components/AnchoredPopover';
import ParentRelationshipModal from '@/components/ParentRelationshipModal';
import { useAddHouseholdParent } from '@/hooks/useMemberProfile';
import MemberProfileEditors from './MemberProfileEditors';

const WALLET_TYPES = ['apple', 'google'];

function isWalletVisibleOnDevice(wallet) {
  const userAgent = navigator.userAgent.toLowerCase();
  const isAndroid = userAgent.includes('android');
  const isIOS = /iphone|ipad|ipod/.test(userAgent)
    || (userAgent.includes('macintosh') && userAgent.includes('mobile'));

  return wallet === 'apple' ? !isAndroid : !isIOS;
}

function getWalletBadge(wallet) {
  const themeUrl = window.rondoConfig?.themeUrl || '';
  if (wallet === 'apple') {
    return {
      alt: 'Voeg toe aan Apple Wallet',
      src: `${themeUrl}/public/icons/NL_Add_to_Apple_Wallet_RGB_101921.svg`,
    };
  }

  return {
    alt: 'Voeg toe aan Google Wallet',
    src: `${themeUrl}/public/icons/nl_add_to_google_wallet_add-wallet-badge.svg`,
  };
}

function addRoleToWalletUrl(url, role) {
  const walletUrl = new URL(url, window.location.origin);
  walletUrl.searchParams.set('role', role);
  return walletUrl.toString();
}

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

const CONTRIBUTION_STATUS_STYLES = {
  paid: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  overdue: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  sent: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  installments: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
};

function contributionDisplay(contribution) {
  const installmentCount = contribution.installment_count || 0;
  if (contribution.status === 'paid') {
    return { key: 'paid', label: 'Betaald' };
  }
  if (contribution.status === 'overdue') {
    return { key: 'overdue', label: 'Achterstallig' };
  }
  if (installmentCount > 1) {
    return { key: 'installments', label: `In ${installmentCount} termijnen` };
  }
  return { key: 'sent', label: 'Te betalen' };
}

function formatContributionDate(value) {
  const date = parseFieldDate(value);
  if (!date || Number.isNaN(date.getTime())) return null;
  return format(date, 'd MMMM yyyy');
}

function ContributionStatus({ contribution }) {
  if (!contribution) return null;

  const display = contributionDisplay(contribution);
  const installmentCount = contribution.installment_count || 0;
  const isInstallmentPlan = installmentCount > 1;
  const nextInstallment = contribution.next_installment;
  const dueDate = formatContributionDate(isInstallmentPlan ? nextInstallment?.due_date : contribution.due_date);
  let description = `${formatCurrency(contribution.total_amount, 2)} · factuur ${contribution.invoice_number}`;

  if (isInstallmentPlan) {
    description = `${contribution.paid_installments} van ${installmentCount} voldaan`;
    if (nextInstallment) {
      description += ` · volgende termijn ${formatCurrency(nextInstallment.amount, 2)}`;
      if (dueDate) description += ` op ${dueDate}`;
    }
  } else if (display.key === 'paid') {
    description = `${formatCurrency(contribution.total_amount, 2)} · volledig voldaan`;
  } else if (dueDate) {
    description = `${formatCurrency(contribution.total_amount, 2)} · betaal uiterlijk ${dueDate}`;
  }

  const actionLabel = isInstallmentPlan ? 'Betaal termijn' : 'Bekijk en betaal';

  return (
    <div className="mt-4 grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-3 border-t border-gray-200 pt-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] dark:border-gray-700">
      <ReceiptEuro className="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-medium text-gray-900 dark:text-gray-100">Contributie {contribution.season}</span>
          <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${CONTRIBUTION_STATUS_STYLES[display.key]}`}>
            {display.label}
          </span>
        </div>
        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{description}</p>
        {isInstallmentPlan ? (
          <div
            className="mt-2 h-1 max-w-52 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            aria-label={`${contribution.paid_installments} van ${installmentCount} termijnen voldaan`}
            aria-valuemin="0"
            aria-valuemax={installmentCount}
            aria-valuenow={contribution.paid_installments}
          >
            <div
              className="h-full rounded-full bg-brand-gradient"
              style={{ width: `${Math.min(100, (contribution.paid_installments / installmentCount) * 100)}%` }}
            />
          </div>
        ) : null}
      </div>
      {contribution.payment_url ? (
        <a
          href={contribution.payment_url}
          className="btn-primary col-span-2 justify-center text-sm sm:col-span-1"
        >
          {actionLabel}
        </a>
      ) : null}
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

function MembershipPassActions({ membershipPass, personId }) {
  const [rolePicker, setRolePicker] = useState(null);
  const firstRoleRef = useRef(null);
  const closeRolePicker = useCallback(() => setRolePicker(null), []);
  const wallets = WALLET_TYPES.filter((wallet) => (
    isWalletVisibleOnDevice(wallet)
    && membershipPass.wallets?.[wallet]?.available
    && membershipPass.wallets[wallet].url
  ));

  const openWallet = (event, wallet) => {
    if (!membershipPass.requires_role) return;
    event.preventDefault();
    setRolePicker({
      anchor: event.currentTarget,
      wallet,
    });
  };

  return (
    <div className="flex items-start gap-3 py-1.5 sm:col-span-2">
      <IdCard className="mt-0.5 h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
      <div className="min-w-0">
        <div className="text-xs text-gray-500 dark:text-gray-400">{membershipPass.label}</div>
        <p className="text-sm text-gray-900 dark:text-gray-100">
          Voeg deze pas direct toe aan je wallet.
        </p>

        {wallets.length > 0 ? (
          <div className="mt-3 flex flex-wrap items-center gap-2">
            {wallets.map((wallet) => {
              const badge = getWalletBadge(wallet);
              const walletData = membershipPass.wallets[wallet];
              const commonProps = {
                className: 'inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt',
                onClick: (event) => openWallet(event, wallet),
              };
              const badgeImage = <img src={badge.src} alt={badge.alt} className="h-12 w-auto" />;

              return membershipPass.requires_role ? (
                <button
                  key={wallet}
                  type="button"
                  {...commonProps}
                  aria-haspopup="dialog"
                  aria-expanded={rolePicker?.wallet === wallet}
                  aria-controls={`membership-pass-role-picker-${personId}`}
                >
                  {badgeImage}
                </button>
              ) : (
                <a key={wallet} href={walletData.url} {...commonProps}>
                  {badgeImage}
                </a>
              );
            })}
          </div>
        ) : (
          <p className="mt-3 text-xs text-gray-600 dark:text-gray-400">
            Er is op dit apparaat geen geconfigureerde wallet beschikbaar.
          </p>
        )}
      </div>

      {rolePicker ? (
        <AnchoredPopover
          anchor={rolePicker.anchor}
          id={`membership-pass-role-picker-${personId}`}
          initialFocusRef={firstRoleRef}
          labelledBy={`membership-pass-role-picker-title-${personId}`}
          maxWidth={384}
          onClose={closeRolePicker}
          preferredHeight={260}
        >
          <div className="p-4">
            <h3 id={`membership-pass-role-picker-title-${personId}`} className="text-sm font-semibold text-gray-900 dark:text-gray-100">
              Welke pas wil je toevoegen?
            </h3>
            <div className="mt-3 grid gap-2">
              {membershipPass.role_options.map((role, index) => (
                <a
                  key={role.key}
                  ref={index === 0 ? firstRoleRef : undefined}
                  href={addRoleToWalletUrl(membershipPass.wallets[rolePicker.wallet].url, role.key)}
                  className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 transition-colors hover:border-bright-cobalt hover:bg-cyan-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-800"
                >
                  {role.label}
                </a>
              ))}
            </div>
          </div>
        </AnchoredPopover>
      ) : null}
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

function PersonCard({ person, isParent, householdPeople, linkedPersonId, onAddParent }) {
  const [profileEditorAnchor, setProfileEditorAnchor] = useState(null);
  const closeProfileEditor = useCallback(() => setProfileEditorAnchor(null), []);
  const profileEditorCloseRef = useRef(null);
  const fields = person.fields || {};
  const name = formatPersonName(fields.first_name, fields.infix, fields.last_name) || 'Onbekend';
  const membershipPass = person.membership_pass;
  const sponsorOrganization = person.sponsor_organization;
  const isSelf = person.household_role === 'self';
  const relationshipLabel = isSelf
    ? 'Dit ben jij'
    : person.household_role === 'other_parent' ? 'Andere ouder/verzorger' : 'Jouw kind';

  return (
    <div className="card max-w-3xl p-5">
      <div className="mb-3 flex items-center justify-between gap-3">
        <div className="min-w-0">
          {isSelf && sponsorOrganization ? (
            <Eyebrow>{isParent ? 'Contactpersoon en ouder' : 'Contactpersoon'}</Eyebrow>
          ) : null}
          <h2 className={`${isSelf && sponsorOrganization ? 'mt-0.5 ' : ''}break-words font-semibold text-gray-900 dark:text-gray-100`}>
            {name}
          </h2>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          {isSelf || person.household_role === 'child' ? (
            <button
              type="button"
              className="btn-secondary gap-2 px-3 py-1.5 text-sm"
              aria-haspopup="dialog"
              aria-expanded={Boolean(profileEditorAnchor)}
              aria-controls={`member-profile-editor-${person.id}`}
              onClick={(event) => setProfileEditorAnchor(profileEditorAnchor ? null : event.currentTarget)}
            >
              <Pencil className="h-4 w-4" aria-hidden="true" />
              Wijzigen
            </button>
          ) : null}
          <span className="inline-block rounded bg-cyan-100 px-2 py-0.5 text-xs font-medium text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300">
            {relationshipLabel}
          </span>
        </div>
      </div>

      <div className="grid gap-x-8 sm:grid-cols-2">
        <Detail icon={Mail} label="E-mail" value={fields.email_1} />
        <Detail icon={Mail} label="E-mail (2e)" value={fields.email_2} />
        <Detail icon={Smartphone} label="Mobiel" value={fields.mobile_1} />
        <Detail icon={Smartphone} label="Mobiel (2e)" value={fields.mobile_2} />
        <Detail icon={Phone} label="Telefoon" value={fields.telephone_1} />
        <Detail icon={Phone} label="Telefoon (2e)" value={fields.telephone_2} />
        <Detail icon={MapPin} label="Adres" value={firstAddress(fields.addresses)} />
        <Detail icon={Calendar} label="Geboortedatum" value={formatFieldDate(fields.birthdate)} />
        <Detail icon={Users} label="Leeftijdsgroep" value={fields.leeftijdsgroep} />
        <Detail icon={IdCard} label="KNVB-ID" value={fields['knvb_id']} />
        <Detail icon={Calendar} label="Lid sinds" value={formatFieldDate(fields['lid_sinds'])} />
        <Detail icon={ShieldCheck} label="VOG afgegeven" value={formatFieldDate(fields['datum_vog'])} />
        {membershipPass ? <MembershipPassActions membershipPass={membershipPass} personId={person.id} /> : null}
      </div>

      <ContributionStatus contribution={person.contribution} />

      {person.can_add_parent ? (
        <button type="button" className="btn-secondary mt-4 gap-2" onClick={() => onAddParent(person.id)}>
          <UserRoundPlus className="h-4 w-4" aria-hidden="true" />
          Andere ouder/verzorger toevoegen
        </button>
      ) : null}

      {isSelf && sponsorOrganization?.can_edit_logo ? (
        <SponsorLogoEditor organization={sponsorOrganization} />
      ) : null}

      {profileEditorAnchor ? (
        <AnchoredPopover
          anchor={profileEditorAnchor}
          id={`member-profile-editor-${person.id}`}
          initialFocusRef={profileEditorCloseRef}
          labelledBy={`member-profile-editor-title-${person.id}`}
          maxWidth={720}
          onClose={closeProfileEditor}
          preferredHeight={640}
        >
          <div className="p-4 sm:p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 id={`member-profile-editor-title-${person.id}`} className="font-semibold text-gray-900 dark:text-gray-100">
                Gegevens van {name} wijzigen
              </h2>
              <button ref={profileEditorCloseRef} type="button" className="btn-tertiary px-2 py-2" aria-label="Sluiten" onClick={closeProfileEditor}>
                <X className="h-5 w-5" aria-hidden="true" />
              </button>
            </div>
            <MemberProfileEditors people={householdPeople} linkedPersonId={linkedPersonId} targetPersonId={person.id} embedded />
          </div>
        </AnchoredPopover>
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
  const addHouseholdParent = useAddHouseholdParent();
  const [parentEditorChildId, setParentEditorChildId] = useState(null);

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
    const children = people.filter((person) => person.household_role === 'child');
    const otherParents = people.filter((person) => person.household_role === 'other_parent');
    return [...self, ...children, ...otherParents];
  }, [people, linkedPersonId]);
  const editablePeople = useMemo(
    () => ordered.filter((person) => person.household_role !== 'other_parent'),
    [ordered],
  );

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
          Je kunt je contactgegevens en het woonadres van je gezin hier aanpassen en de contributiestatus bekijken.
        </p>
      </div>

      {ordered.length === 0 ? (
        <div className="card p-5">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            We konden geen ledengegevens aan je account koppelen. Neem contact op met de ledenadministratie.
          </p>
        </div>
      ) : (
        <>
          {ordered.map((person) => (
            <PersonCard
              key={person.id}
              person={person}
              isParent={currentUser?.is_parent === true}
              householdPeople={editablePeople}
              linkedPersonId={linkedPersonId}
              onAddParent={setParentEditorChildId}
            />
          ))}
          <ParentRelationshipModal
            isOpen={parentEditorChildId !== null}
            onClose={() => setParentEditorChildId(null)}
            onSubmit={(data) => addHouseholdParent.mutateAsync({ childId: parentEditorChildId, data }).then(() => setParentEditorChildId(null))}
            canAddParent
            newOnly
            isLoading={addHouseholdParent.isPending}
            personId={parentEditorChildId}
          />
        </>
      )}
    </div>
  );
}
