export function getClubName(fallback = 'de club') {
  return globalThis.window?.rondoConfig?.clubName?.trim() || fallback;
}

export function getSponsorRoleLabels(clubName = getClubName('')) {
  const name = clubName.trim();
  return {
    businessclub: `Businessclub ${name}`.trim(),
    awc_sponsor: `Sponsor ${name}`.trim(),
  };
}
