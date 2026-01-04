import MonicaImport from '@/components/import/MonicaImport';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function Import() {
  useDocumentTitle('Import - Settings');

  return (
    <div className="max-w-2xl mx-auto">
      <div className="card p-6">
        <MonicaImport />
      </div>
    </div>
  );
}

