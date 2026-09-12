import { FileCheck } from 'lucide-react';
import IvaCard from '@/components/IvaCard';
import VOGCard from '@/components/VOGCard';

export default function DocumentsCard({ fieldData, personId, canAccessVog, canViewCertificate, onUpdateField, isUpdating }) {
  const hasIva = !!(fieldData?.iva_certificaat?.id || fieldData?.iva_certificaat?.ID);
  const showVog = canAccessVog && (fieldData?.huidig_vrijwilliger === true || fieldData?.huidig_vrijwilliger === '1');
  if (!hasIva && !showVog) return null;

  return (
    <section className="card p-6" aria-label="Documenten">
      <h2 className="mb-4 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100">
        <FileCheck className="w-5 h-5 shrink-0 text-bright-cobalt" aria-hidden="true" />Documenten
      </h2>
      <div className="divide-y divide-gray-200 dark:divide-gray-700 [&>section+section]:pt-4 [&>section+section]:mt-4">
        {showVog && <VOGCard key={personId} fieldData={fieldData} personId={personId} onUpdateField={onUpdateField} isUpdating={isUpdating} />}
        {hasIva && <IvaCard fieldData={fieldData} personId={personId} canViewCertificate={canViewCertificate} />}
      </div>
    </section>
  );
}
