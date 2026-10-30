import { hasSponsorRole } from '../../utils/formatters.js';
import { format, parseYmd, isValid } from '../../utils/dateFormat.js';

// Helper export function to get first email from fixed fields
export function getFirstEmail(person) {
  return person.fields?.email_1 || person.fields?.email_2 || null;
}

// Helper export function to get first phone from fixed fields
export function getFirstPhone(person) {
  return person.fields?.mobile_1 || person.fields?.telephone_1 || person.fields?.mobile_2 || person.fields?.telephone_2 || null;
}

// Primary address is the first row of the native field `addresses` repeater.
export function getPrimaryAddress(person) {
  const addresses = person.fields?.addresses;
  return Array.isArray(addresses) && addresses.length > 0 ? addresses[0] : null;
}

export function formatStreetLine(address) {
  if (!address) return '';
  const street = address.street_name || '';
  const number = [address.house_number, address.house_number_addition].filter(Boolean).join('');
  return [street, number].filter(Boolean).join(' ').trim();
}

export function formatBirthdateDisplay(birthdate) {
  if (!birthdate) return '-';
  const parsed = parseYmd(birthdate);
  if (!isValid(parsed)) return '-';
  return format(parsed, 'd MMM yyyy');
}

export function getMembershipTypeLabel(person) {
  const fields = person.fields || {};
  const sponsorSuffix = (person.characteristics?.sponsor || hasSponsorRole(fields)) ? ' + sponsorcontact' : '';

  if (fields.person_type === 'contact') return `Contact${sponsorSuffix}`;

  const sportlinkType = String(fields['type_lid'] || '').trim().toLowerCase();
  if (sportlinkType.includes('verenigingslid')) return `Verenigingslid${sponsorSuffix}`;
  if (sportlinkType.includes('bondslid')) return `Bondslid${sponsorSuffix}`;
  if (sportlinkType.includes('ouder')) return `Ouder${sponsorSuffix}`;

  const isParent = fields.isparent === true || fields.isparent === 1 || fields.isparent === '1';
  if (isParent || !fields['knvb_id']) return `Ouder${sponsorSuffix}`;

  return `Bondslid${sponsorSuffix}`;
}

export function getCharacteristicBadges(characteristics) {
  if (!characteristics) return [];
  return [
    characteristics.contact ? { label: 'Contact', tone: 'contact' } : {
      label: characteristics.playing_member ? 'Spelend lid' : 'Niet-spelend lid',
      tone: characteristics.playing_member ? 'playing' : 'notPlaying',
    },
    { label: characteristics.knvb_known ? 'KNVB bekend' : 'KNVB onbekend', tone: characteristics.knvb_known ? 'knvb' : 'noKnvb' },
    characteristics.parent && { label: 'Ouder/verzorger', tone: 'parent' },
    characteristics.volunteer && { label: 'Vrijwilliger', tone: 'volunteer' },
    characteristics.sponsor && { label: 'Sponsorcontact', tone: 'sponsor' },
  ].filter(Boolean);
}
