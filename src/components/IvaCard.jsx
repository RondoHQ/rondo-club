import DocumentStatusBadge from '@/components/DocumentStatusBadge';
import IvaCertificateLink from '@/components/IvaCertificateLink';
import { format } from '@/utils/dateFormat';
import { parseFieldDate } from '@/utils/formatters';

export default function IvaCard({ fieldData, personId, canViewCertificate }) {
  const certificate = fieldData?.iva_certificaat;
  if (!certificate?.id && !certificate?.ID) return null;

  const date = parseFieldDate(fieldData.datum_iva);
  // An approval without a completion date is pending in IvaStatus as well.
  const approved = fieldData.iva_approved === true && date !== null;

  return (
    <section aria-label="IVA / Sociale Hygiëne">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">IVA / Sociale Hygiëne</h3>
        <DocumentStatusBadge status={approved ? 'valid' : 'pending'} />
      </div>
      <dl className="mt-2 text-sm">
        <div className="flex flex-wrap justify-between gap-x-4 gap-y-1">
          <dt className="text-gray-600 dark:text-gray-400">Behaald op</dt>
          <dd className="text-gray-900 dark:text-gray-100">{date ? format(date, 'd MMMM yyyy') : 'Niet geregistreerd'}</dd>
        </div>
      </dl>
      {canViewCertificate && (
        <IvaCertificateLink personId={personId} className="mt-2 text-sm">
          Certificaat bekijken
        </IvaCertificateLink>
      )}
    </section>
  );
}
