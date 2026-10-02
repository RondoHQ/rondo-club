import { useEffect, useState } from 'react';
import { prmApi } from '@/api/client';

export default function FeedbackScreenshot({ feedbackId }) {
  const [image, setImage] = useState(null);
  const [errorId, setErrorId] = useState(null);

  useEffect(() => {
    let active = true;
    let objectUrl;
    prmApi.getFeedbackScreenshot(feedbackId).then(({ data }) => {
      if (!active) return;
      objectUrl = URL.createObjectURL(data);
      setImage({ id: feedbackId, url: objectUrl });
    }).catch(() => {
      if (active) setErrorId(feedbackId);
    });
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [feedbackId]);

  return (
    <div className="card p-6">
      <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Screenshot</h2>
      {errorId === feedbackId ? <p role="alert">De screenshot kon niet worden geladen. Ververs de pagina om het opnieuw te proberen.</p>
        : image?.id === feedbackId ? (
          <a href={image.url} target="_blank" rel="noopener noreferrer" aria-label="Screenshot op volledige grootte openen">
            <img src={image.url} alt="Screenshot bij deze feedback" className="max-h-96 max-w-full rounded border border-gray-200 dark:border-gray-700" />
          </a>
        ) : <p role="status">Screenshot laden...</p>}
    </div>
  );
}
