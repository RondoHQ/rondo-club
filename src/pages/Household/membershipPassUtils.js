const PASS_PRESENTATIONS = {
  bondslid: {
    eyebrow: 'Bondslid',
    title: 'AWC Ledenpas',
    sponsor: false,
    businessclub: false,
  },
  verenigingslid: {
    eyebrow: 'Verenigingslid',
    title: 'AWC Ledenpas',
    sponsor: false,
    businessclub: false,
  },
  businessclub: {
    eyebrow: 'Sponsor',
    title: 'Businessclub AWC',
    sponsor: true,
    businessclub: true,
  },
  awc_sponsor: {
    eyebrow: 'Sponsor',
    title: 'AWC Sponsor',
    sponsor: true,
    businessclub: false,
  },
};

const FALLBACK_PRESENTATION = {
  eyebrow: 'Ledenpas',
  title: 'AWC Ledenpas',
  sponsor: false,
  businessclub: false,
};

export function getMembershipPassPresentation(passType) {
  return PASS_PRESENTATIONS[passType] || FALLBACK_PRESENTATION;
}

export function buildDigitalPassPath(personId, role = '') {
  const path = `/mijn-gegevens/pas/${personId}`;
  if (!role) return path;

  const params = new URLSearchParams({ role });
  return `${path}?${params.toString()}`;
}
