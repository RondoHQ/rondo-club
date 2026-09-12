import { Award, Check, Clock } from 'lucide-react';
import IvaCertificateLink from '@/components/IvaCertificateLink';
import { format } from '@/utils/dateFormat';
import { parseFieldDate } from '@/utils/formatters';

export default function IvaCard({ fieldData, personId, canViewCertificate }) {
  const certificate = fieldData?.iva_certificaat;
  if (!certificate?.id && !certificate?.ID) return null;

  const date = parseFieldDate(fieldData.datum_iva);
  // An approval without a completion date is pending in IvaStatus as well.
  const approved = fieldData.iva_approved === true && date !== null;
  const StatusIcon = approved ? Check : Clock;

  return (
    <section className="card p-6" aria-label="IVA / Sociale Hygiëne">
      <div className="flex items-center gap-2 mb-4">
        <Award className="w-5 h-5 text-bright-cobalt shrink-0" aria-hidden="true" />
        <h2 className="font-semibold text-brand-gradient">IVA / Sociale Hygiëne</h2>
      </div>
      <span className={`inline-flex items-center gap-1.5 rounded px-2 py-1 text-sm font-medium ${approved
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'}`}>
        <StatusIcon className="w-4 h-4 shrink-0" aria-hidden="true" />
        {approved ? 'Goedgekeurd' : 'Wacht op beoordeling'}
      </span>
      <dl className="mt-4 text-sm">
        <div className="flex flex-wrap justify-between gap-x-4 gap-y-1">
          <dt className="text-gray-600 dark:text-gray-400">Behaald op</dt>
          <dd className="text-gray-900 dark:text-gray-100">{date ? format(date, 'd MMMM yyyy') : 'Niet geregistreerd'}</dd>
        </div>
      </dl>
      {canViewCertificate && (
        <IvaCertificateLink personId={personId} className="mt-4 text-sm">
          Certificaat bekijken
        </IvaCertificateLink>
      )}
    </section>
  );
}
