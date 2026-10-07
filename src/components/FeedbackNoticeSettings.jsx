import { useEffect, useState } from 'react';
import { prmApi } from '@/api/client';
import FeedbackNotice from '@/components/FeedbackNotice';

export default function FeedbackNoticeSettings({ clubConfig, setClubConfig, isLoading }) {
  const [notice, setNotice] = useState(window.rondoConfig?.feedbackNotice || {});
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (clubConfig?.feedback_notice) setNotice(clubConfig.feedback_notice);
  }, [clubConfig]);

  const update = (field, value) => {
    setNotice((current) => ({ ...current, [field]: value }));
    setSaved(false);
  };

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setSaved(false);
    setError('');
    try {
      const { data } = await prmApi.updateClubConfig({ feedback_notice: notice });
      window.rondoConfig.feedbackNotice = data.feedback_notice;
      setClubConfig(data);
      setSaved(true);
    } catch (error) {
      setError(error.response?.data?.message || 'Kan de feedbackmelding niet opslaan. Probeer het opnieuw.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="card p-6" aria-labelledby="feedback-notice-settings-title">
      <h2 id="feedback-notice-settings-title" className="text-lg font-semibold text-brand-gradient mb-2">Melding boven het feedbackformulier</h2>
      <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">Verwijs leden met vragen over hun gegevens of lidmaatschap naar de ledenadministratie.</p>
      <form onSubmit={save}>
        <fieldset className="space-y-4" disabled={isLoading || saving}>
          <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" checked={notice.enabled || false} onChange={(event) => update('enabled', event.target.checked)} />
            Melding tonen
          </label>
          <div>
            <label className="label" htmlFor="feedback-notice-title">Titel van de melding</label>
            <input id="feedback-notice-title" className="input" value={notice.title || ''} required={notice.enabled}
              onChange={(event) => update('title', event.target.value)} />
          </div>
          <div>
            <label className="label" htmlFor="feedback-notice-email">E-mailadres ledenadministratie</label>
            <input id="feedback-notice-email" type="email" className="input" value={notice.email || ''} required={notice.enabled}
              onChange={(event) => update('email', event.target.value)} />
          </div>
          <div>
            <label className="label" htmlFor="feedback-notice-text">Tekst van de melding</label>
            <textarea id="feedback-notice-text" className="input" rows={5} value={notice.text || ''} required={notice.enabled}
              onChange={(event) => update('text', event.target.value)} />
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Gebruik {'{email}'} om het klikbare e-mailadres in de tekst te plaatsen.</p>
          </div>
          <FeedbackNotice notice={notice} />
          {error ? <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p> : null}
          <div className="flex items-center gap-3">
            <button type="submit" className="btn-primary">{saving ? 'Opslaan...' : 'Melding opslaan'}</button>
            {saved ? <span role="status" className="text-sm text-green-600 dark:text-green-400">Opgeslagen</span> : null}
          </div>
        </fieldset>
      </form>
    </section>
  );
}
