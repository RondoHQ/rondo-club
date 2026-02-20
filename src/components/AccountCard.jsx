import { useState } from 'react';
import { UserPlus, Check, Loader2, AlertCircle } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { format } from '@/utils/dateFormat';

/**
 * AccountCard - Admin-only provisioning status card for person detail page.
 * Shows whether a WordPress account has been created for this person,
 * and provides a button to trigger provisioning if not yet done.
 *
 * Props:
 *   personId  - string or number, the person post ID
 *   personData - full person object from usePerson (includes acf, linked_user_id, welcome_email_sent_at)
 */
export default function AccountCard({ personId, personData }) {
  const config = window.rondoConfig || {};
  const queryClient = useQueryClient();
  const [provisioning, setProvisioning] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  // Only admins see this card
  if (!config.isAdmin) {
    return null;
  }

  const linkedUserId = personData?.linked_user_id;
  const welcomeEmailSentAt = personData?.welcome_email_sent_at;

  // Check if person has an email address
  const hasEmail = personData?.acf?.contact_info?.some(
    (contact) => contact.contact_type === 'email' && contact.contact_value?.trim()
  );

  const handleProvision = async () => {
    setProvisioning(true);
    setResult(null);
    setError(null);
    try {
      const res = await prmApi.provisionUser(personId);
      setResult(res.data?.message || 'Account aangemaakt.');
      // Invalidate person detail query so linked_user_id and welcome_email_sent_at refresh
      queryClient.invalidateQueries({ queryKey: ['people', 'detail', String(personId)] });
    } catch (err) {
      setError(err.response?.data?.message || 'Kon account niet aanmaken');
    } finally {
      setProvisioning(false);
    }
  };

  return (
    <div className="card p-6">
      <h2 className="font-semibold text-brand-gradient mb-3">Account</h2>

      {linkedUserId ? (
        /* Already provisioned */
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
            <Check className="w-5 h-5 flex-shrink-0" />
            <span className="text-sm font-medium">Account aangemaakt</span>
          </div>
          {welcomeEmailSentAt && (
            <p className="text-xs text-gray-500 dark:text-gray-400 pl-7">
              Welkomstmail verstuurd op {format(new Date(welcomeEmailSentAt), 'd MMM yyyy')}
            </p>
          )}
        </div>
      ) : (
        /* Not yet provisioned */
        <div className="space-y-3">
          {result ? (
            <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
              <Check className="w-5 h-5 flex-shrink-0" />
              <span className="text-sm">{result}</span>
            </div>
          ) : null}

          {error && (
            <div className="flex items-start gap-2 text-red-600 dark:text-red-400">
              <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
              <span className="text-sm">{error}</span>
            </div>
          )}

          {!result && (
            <>
              <button
                onClick={handleProvision}
                disabled={provisioning || !hasEmail}
                className={`btn-primary flex items-center gap-2 ${
                  !hasEmail ? 'opacity-50 cursor-not-allowed' : ''
                }`}
              >
                {provisioning ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Bezig met aanmaken...</span>
                  </>
                ) : (
                  <>
                    <UserPlus className="w-4 h-4" />
                    <span>Maak account aan</span>
                  </>
                )}
              </button>

              {!hasEmail && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Voeg eerst een e-mailadres toe aan deze persoon.
                </p>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
