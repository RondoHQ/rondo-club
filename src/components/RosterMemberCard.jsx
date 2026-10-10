import { useState } from 'react';
import { ChevronDown, Mail, Phone } from 'lucide-react';
import { SiWhatsapp } from '@icons-pack/react-simple-icons';
import { formatPhoneForTel, isDutchMobilePhone } from '@/utils/formatters';

function whatsappNumber(phone) {
  return formatPhoneForTel(phone).replace(/\D/g, '').replace(/^00/, '');
}

function ContactDetails({ person }) {
  const mobileNumbers = new Set((person.mobile_phones || []).map(whatsappNumber));

  return (
    <div className="min-w-0 space-y-1 text-sm">
      {person.phones.map((phone) => {
        const number = whatsappNumber(phone);
        const isMobile = mobileNumbers.has(number) || isDutchMobilePhone(phone);

        return (
          <div key={phone} className="flex items-center gap-2">
            <a href={`tel:${formatPhoneForTel(phone)}`} className="flex min-h-11 min-w-0 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
              <Phone className="h-4 w-4 shrink-0" aria-hidden="true" />
              <span className="min-w-0 [overflow-wrap:anywhere]">{phone}</span>
            </a>
            {isMobile && number ? (
              <a
                href={`https://wa.me/${number}`}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`Stuur ${person.name} een WhatsApp-bericht op ${phone}`}
                title="WhatsApp"
                className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded text-green-600 hover:bg-green-50 hover:text-green-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 dark:text-green-400 dark:hover:bg-green-950 dark:hover:text-green-300"
              >
                <SiWhatsapp className="h-5 w-5" aria-hidden="true" />
              </a>
            ) : null}
          </div>
        );
      })}
      {person.emails.map((email) => (
        <a key={email} href={`mailto:${email}`} className="flex min-h-11 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
          <Mail className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 [overflow-wrap:anywhere]">{email}</span>
        </a>
      ))}
      {person.emails.length === 0 && person.phones.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400">Geen contactgegevens bekend.</p>
      ) : null}
    </div>
  );
}

function MemberPhoto({ person }) {
  const [failedThumbnail, setFailedThumbnail] = useState(null);
  const nameParts = person.name.trim().split(/\s+/).filter(Boolean);
  const initials = [nameParts[0]?.[0], nameParts.length > 1 ? nameParts.at(-1)?.[0] : ''].join('').toUpperCase() || '?';

  if (person.thumbnail && person.thumbnail !== failedThumbnail) {
    return (
      <img
        src={person.thumbnail}
        alt=""
        width={64}
        height={64}
        loading="lazy"
        onError={() => setFailedThumbnail(person.thumbnail)}
        className="h-16 w-16 shrink-0 rounded-xl bg-gray-100 object-cover dark:bg-gray-700"
      />
    );
  }

  return (
    <span aria-label="Geen foto beschikbaar" className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xl font-semibold text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan">
      {initials}
    </span>
  );
}

function MemberIdentity({ person, children }) {
  return (
    <>
      <MemberPhoto person={person} />
      <span className="min-w-0 flex-1">
        <span className="block font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{person.name}</span>
        {person.roles?.length ? <span className="mt-1 block text-sm text-gray-500 dark:text-gray-400">{person.roles.join(', ')}</span> : null}
        {children}
      </span>
    </>
  );
}

export default function RosterMemberCard({ person, canViewContacts, isStaff = false, contactLabel = isStaff ? 'Staf' : 'Speler', showParents = !isStaff }) {
  if (!canViewContacts) {
    return (
      <article className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <MemberIdentity person={person} />
      </article>
    );
  }

  return (
    <details className="group overflow-hidden rounded-xl border border-gray-200 bg-white open:border-bright-cobalt dark:border-gray-700 dark:bg-gray-800 dark:open:border-electric-cyan">
      <summary className="flex cursor-pointer list-none items-center gap-4 p-4 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-bright-cobalt dark:hover:bg-gray-700/50 dark:focus-visible:outline-electric-cyan [&::-webkit-details-marker]:hidden">
        <MemberIdentity person={person}>
          <span className="mt-1 block text-sm text-bright-cobalt dark:text-electric-cyan">
            <span className="group-open:hidden">Contactgegevens</span>
            <span className="hidden group-open:inline">Contactgegevens sluiten</span>
          </span>
        </MemberIdentity>
        <ChevronDown className="h-5 w-5 shrink-0 text-gray-500 group-open:rotate-180 group-open:text-bright-cobalt dark:text-gray-400 dark:group-open:text-electric-cyan" aria-hidden="true" />
      </summary>
      <div className="grid gap-5 border-t border-gray-200 p-4 sm:grid-cols-2 xl:grid-cols-3 dark:border-gray-700">
        <div className="min-w-0">
          <p className="mb-1 text-xs text-gray-500 dark:text-gray-400">{contactLabel}</p>
          <p className="mb-2 text-sm font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{person.name}</p>
          <ContactDetails person={person} />
        </div>
        {(showParents ? person.parents || [] : []).map((parent) => (
          <div key={parent.id} className="min-w-0 border-t border-gray-200 pt-4 sm:border-0 sm:pt-0 dark:border-gray-700">
            <p className="mb-1 text-xs text-gray-500 dark:text-gray-400">Ouder/verzorger</p>
            <p className="mb-2 text-sm font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{parent.name}</p>
            <ContactDetails person={parent} />
          </div>
        ))}
        {showParents && (person.parents || []).length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen ouders/verzorgers gekoppeld.</p> : null}
      </div>
    </details>
  );
}

