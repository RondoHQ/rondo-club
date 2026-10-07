import { getClubName, getSponsorRoleLabels } from '../../utils/clubLabels.js';

const PASS_PRESENTATIONS = {
  bondslid: {
    eyebrow: 'Bondslid',
    sponsor: false,
    businessclub: false,
  },
  verenigingslid: {
    eyebrow: 'Verenigingslid',
    sponsor: false,
    businessclub: false,
  },
  businessclub: {
    eyebrow: 'Sponsor',
    sponsor: true,
    businessclub: true,
  },
  awc_sponsor: {
    eyebrow: 'Sponsor',
    sponsor: true,
    businessclub: false,
  },
};

const FALLBACK_PRESENTATION = {
  eyebrow: 'Ledenpas',
  sponsor: false,
  businessclub: false,
};

export function getMembershipPassPresentation(passType) {
  const presentation = PASS_PRESENTATIONS[passType] || FALLBACK_PRESENTATION;
  const title = presentation.sponsor
    ? getSponsorRoleLabels()[passType]
    : `${getClubName('')} Ledenpas`.trim();
  return { ...presentation, title };
}

export function buildDigitalPassPath(personId, role = '') {
  const path = `/mijn-gegevens/pas/${personId}`;
  if (!role) return path;

  const params = new URLSearchParams({ role });
  return `${path}?${params.toString()}`;
}

// Keep web and native pass backgrounds aligned with the server's Wallet styling.
export function getMembershipPassBackground(passType, backgroundColor) {
  if (typeof backgroundColor === 'string' && /^#[a-f0-9]{6}$/i.test(backgroundColor)) return backgroundColor;
  return getMembershipPassPresentation(passType).sponsor ? '#ffffff' : '#006935';
}
