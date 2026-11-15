export const uid = () => `a${crypto.randomUUID().replaceAll('-', '')}`;
export const formatNumber = (value) => new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 1 }).format(value);
export const percent = (value) => value == null ? 'Niet beschikbaar' : `${formatNumber(value)}%`;
export const errorText = (error) => error?.response?.data?.message || error?.message || 'Er ging iets mis.';
export function publicationLabel(versions, id) {
  const index = versions.findIndex((version) => version.id === id);
  return index < 0 ? 'Versie niet meer beschikbaar' : `Versie ${index + 1} · ${new Date(`${versions[index].date.replace(' ', 'T')}Z`).toLocaleDateString('nl-NL')}`;
}
