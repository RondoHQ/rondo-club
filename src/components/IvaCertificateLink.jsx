import { useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { prmApi } from '@/api/client';

export default function IvaCertificateLink({ personId, children, className = '' }) {
  const [isOpening, setIsOpening] = useState(false);
  const [error, setError] = useState('');

  const handleOpen = async () => {
    if (isOpening) return;

    // Open the tab synchronously so browsers still treat it as user-initiated.
    // The actual file must be fetched through Axios to include the REST nonce.
    const previewWindow = window.open('', '_blank');
    if (previewWindow) {
      previewWindow.document.title = 'Bewijsstuk laden…';
      previewWindow.document.body.textContent = 'Bewijsstuk laden…';
      previewWindow.opener = null;
    }

    setIsOpening(true);
    setError('');

    try {
      const response = await prmApi.getIvaCertificate(personId);
      const objectUrl = URL.createObjectURL(response.data);

      if (previewWindow) {
        previewWindow.location.replace(objectUrl);
      } else {
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'iva-certificaat';
        document.body.appendChild(link);
        link.click();
        link.remove();
      }

      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
    } catch (requestError) {
      previewWindow?.close();
      setError(requestError?.response?.data?.message || 'Kon certificaat niet openen.');
    } finally {
      setIsOpening(false);
    }
  };

  return (
    <div className={className}>
      <button
        type="button"
        onClick={handleOpen}
        disabled={isOpening}
        className="inline-flex items-center gap-1 text-bright-cobalt hover:underline disabled:cursor-wait disabled:opacity-60 dark:text-electric-cyan"
      >
        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
        {isOpening ? 'Laden…' : children}
      </button>
      {error && <p className="mt-1 text-xs text-red-700 dark:text-red-300" role="alert">{error}</p>}
    </div>
  );
}
