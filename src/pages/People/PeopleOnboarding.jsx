import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Loader2, Mail, Settings, Users, CheckCircle2, AlertCircle } from 'lucide-react';
import { useFilteredPeople, useSendOnboardingEmail } from '@/hooks/usePeople';
import PersonAvatar from '@/components/PersonAvatar';
import TabButton from '@/components/TabButton';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';

const TAB_LID = 'leden';
const TAB_VRIJWILLIGER = 'vrijwilligers';

const TYPE_PARAM_BY_TAB = {
  [TAB_LID]: 'lid',
  [TAB_VRIJWILLIGER]: 'vrijwilliger',
};

const STATUS_LABELS = {
  sent: 'Verstuurd',
  already_sent: 'Was al verstuurd',
  no_email: 'Geen e-mailadres',
  send_failed: 'Versturen mislukt',
  not_found: 'Persoon niet gevonden',
  invalid_type: 'Ongeldig type',
};

function getSinceDate(person, tab) {
  const acf = person.acf || {};
  if (tab === TAB_VRIJWILLIGER) {
    return acf['vrijwilliger-sinds'] || null;
  }
  return acf['lid-sinds'] || null;
}

function getEmail(person) {
  const acf = person.acf || {};
  return acf.email_1 || acf.email_2 || '';
}

function getDisplayName(person) {
  return [person.first_name, person.infix, person.last_name].filter(Boolean).join(' ').trim();
}

export default function PeopleOnboarding() {
  useDocumentTitle('Onboarding');

  const [searchParams, setSearchParams] = useSearchParams();
  const initialTab = searchParams.get('tab') === TAB_VRIJWILLIGER ? TAB_VRIJWILLIGER : TAB_LID;
  const [activeTab, setActiveTab] = useState(initialTab);
  const [selected, setSelected] = useState(new Set());
  const [resultBanner, setResultBanner] = useState(null);

  const filterKey = activeTab === TAB_VRIJWILLIGER ? 'onboardingNewVolunteers' : 'onboardingNewMembers';
  const filters = useMemo(
    () => ({ [filterKey]: '1', perPage: 100, orderby: 'first_name', order: 'asc' }),
    [filterKey]
  );

  const { data, isLoading, isFetching, error } = useFilteredPeople(filters, {
    staleTime: 30_000,
  });
  const people = useMemo(() => data?.people || [], [data]);
  const total = data?.total ?? people.length;

  const sendMutation = useSendOnboardingEmail();
  const isSending = sendMutation.isPending;

  const switchTab = (next) => {
    if (next === activeTab) return;
    setActiveTab(next);
    setSelected(new Set());
    setResultBanner(null);
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('tab', next);
    setSearchParams(nextParams, { replace: true });
  };

  const toggleSelect = (personId) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(personId)) next.delete(personId);
      else next.add(personId);
      return next;
    });
  };

  const eligibleIds = useMemo(
    () => people.filter((p) => Boolean(getEmail(p))).map((p) => p.id),
    [people]
  );
  const allSelected = eligibleIds.length > 0 && eligibleIds.every((id) => selected.has(id));
  const toggleAll = () => {
    setSelected(allSelected ? new Set() : new Set(eligibleIds));
  };

  const runSend = async (personIds) => {
    if (personIds.length === 0 || isSending) return;
    const type = TYPE_PARAM_BY_TAB[activeTab];
    const label = activeTab === TAB_VRIJWILLIGER ? 'nieuwe vrijwilligers' : 'nieuwe leden';
    const confirmed = window.confirm(
      personIds.length === 1
        ? `Onboarding-mail versturen naar deze ${label === 'nieuwe vrijwilligers' ? 'vrijwilliger' : 'lid'}?`
        : `Onboarding-mail versturen naar ${personIds.length} ${label}?`
    );
    if (!confirmed) return;

    setResultBanner(null);
    try {
      const response = await sendMutation.mutateAsync({ personIds, type });
      const counts = response.data?.counts || {};
      setResultBanner({
        kind: counts.send_failed > 0 ? 'partial' : 'success',
        counts,
      });
      setSelected(new Set());
    } catch (err) {
      setResultBanner({
        kind: 'error',
        message: err?.response?.data?.message || 'Versturen mislukt. Probeer het opnieuw.',
      });
    }
  };

  const sendOne = (personId) => runSend([personId]);
  const sendSelected = () => runSend(Array.from(selected));

  const dateColumnLabel = activeTab === TAB_VRIJWILLIGER ? 'Vrijwilliger sinds' : 'Lid sinds';
  const periodLabel = activeTab === TAB_VRIJWILLIGER
    ? 'Nieuwe vrijwilligers van de afgelopen 60 dagen die nog geen onboarding-mail hebben ontvangen.'
    : 'Nieuwe leden van de afgelopen 30 dagen die nog geen onboarding-mail hebben ontvangen.';

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex items-end gap-6 border-b border-gray-200 dark:border-gray-700">
          <TabButton
            label="Nieuwe leden"
            isActive={activeTab === TAB_LID}
            onClick={() => switchTab(TAB_LID)}
            count={activeTab === TAB_LID ? total : undefined}
          />
          <TabButton
            label="Nieuwe vrijwilligers"
            isActive={activeTab === TAB_VRIJWILLIGER}
            onClick={() => switchTab(TAB_VRIJWILLIGER)}
            count={activeTab === TAB_VRIJWILLIGER ? total : undefined}
          />
        </div>
        <Link
          to="/settings/admin/welkomstmail"
          className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          <Settings className="w-4 h-4" />
          E-mailteksten beheren
        </Link>
      </div>

      <p className="text-sm text-gray-500 dark:text-gray-400">{periodLabel}</p>

      {resultBanner && <ResultBanner banner={resultBanner} onDismiss={() => setResultBanner(null)} />}

      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-400">
          Kon de lijst niet laden. Probeer de pagina te herladen.
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
        </div>
      ) : people.length === 0 ? (
        <EmptyState tab={activeTab} />
      ) : (
        <div className="card overflow-hidden">
          <div className="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <input
                type="checkbox"
                checked={allSelected}
                onChange={toggleAll}
                className="h-4 w-4 rounded border-gray-300 text-electric-cyan focus:ring-electric-cyan"
              />
              {selected.size > 0
                ? `${selected.size} geselecteerd`
                : `Selecteer iedereen (${eligibleIds.length})`}
            </label>
            <button
              type="button"
              onClick={sendSelected}
              disabled={selected.size === 0 || isSending}
              className="btn-primary gap-2 disabled:opacity-50"
            >
              {isSending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Mail className="w-4 h-4" />}
              Verstuur geselecteerde
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                  <th className="w-10 px-4 py-2"></th>
                  <th className="px-4 py-2 font-medium">Naam</th>
                  <th className="px-4 py-2 font-medium">{dateColumnLabel}</th>
                  <th className="px-4 py-2 font-medium">E-mail</th>
                  <th className="px-4 py-2 font-medium text-right">Actie</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {people.map((person) => {
                  const email = getEmail(person);
                  const since = getSinceDate(person, activeTab);
                  const hasEmail = Boolean(email);
                  const displayName = getDisplayName(person);
                  return (
                    <tr key={person.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/40">
                      <td className="px-4 py-2">
                        <input
                          type="checkbox"
                          checked={selected.has(person.id)}
                          onChange={() => toggleSelect(person.id)}
                          disabled={!hasEmail}
                          className="h-4 w-4 rounded border-gray-300 text-electric-cyan focus:ring-electric-cyan disabled:opacity-40"
                          aria-label={`Selecteer ${displayName}`}
                        />
                      </td>
                      <td className="px-4 py-2">
                        <Link to={`/people/${person.id}`} className="flex items-center gap-3 min-w-0">
                          <PersonAvatar
                            thumbnail={person.thumbnail}
                            name={displayName}
                            firstName={person.first_name}
                            size="sm"
                          />
                          <span className="font-medium text-gray-900 truncate dark:text-gray-100">{displayName}</span>
                        </Link>
                      </td>
                      <td className="px-4 py-2 text-gray-600 dark:text-gray-300">
                        {since ? format(new Date(since), 'd MMMM yyyy') : '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-600 dark:text-gray-300">
                        {hasEmail ? (
                          <a href={`mailto:${email}`} className="hover:underline">{email}</a>
                        ) : (
                          <span className="italic text-gray-400">Geen e-mailadres</span>
                        )}
                      </td>
                      <td className="px-4 py-2 text-right">
                        <button
                          type="button"
                          onClick={() => sendOne(person.id)}
                          disabled={!hasEmail || isSending}
                          className="btn-tertiary gap-1.5 disabled:opacity-50"
                        >
                          {isSending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Mail className="w-3.5 h-3.5" />}
                          Verstuur
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {isFetching && !isLoading && (
            <div className="border-t border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
              Bijwerken…
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function EmptyState({ tab }) {
  const label = tab === TAB_VRIJWILLIGER ? 'nieuwe vrijwilligers' : 'nieuwe leden';
  return (
    <div className="card flex flex-col items-center gap-3 py-12 text-center text-gray-500 dark:text-gray-400">
      <Users className="w-10 h-10 text-gray-300 dark:text-gray-600" />
      <p className="text-sm">Geen {label} om te onboarden.</p>
    </div>
  );
}

function ResultBanner({ banner, onDismiss }) {
  if (banner.kind === 'error') {
    return (
      <div className="flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-400">
        <span className="flex items-center gap-2">
          <AlertCircle className="w-4 h-4" />
          {banner.message}
        </span>
        <button type="button" onClick={onDismiss} className="text-xs underline">Sluiten</button>
      </div>
    );
  }

  const counts = banner.counts || {};
  const pieces = [];
  if (counts.sent) pieces.push(`${counts.sent} ${STATUS_LABELS.sent.toLowerCase()}`);
  if (counts.already_sent) pieces.push(`${counts.already_sent} ${STATUS_LABELS.already_sent.toLowerCase()}`);
  if (counts.no_email) pieces.push(`${counts.no_email} zonder e-mailadres`);
  if (counts.send_failed) pieces.push(`${counts.send_failed} mislukt`);
  if (counts.not_found) pieces.push(`${counts.not_found} niet gevonden`);

  const isSuccess = banner.kind === 'success';
  const classes = isSuccess
    ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-700/40 dark:bg-green-900/20 dark:text-green-400'
    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-400';

  return (
    <div className={`flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm ${classes}`}>
      <span className="flex items-center gap-2">
        <CheckCircle2 className="w-4 h-4" />
        {pieces.length > 0 ? pieces.join(' · ') : 'Klaar.'}
      </span>
      <button type="button" onClick={onDismiss} className="text-xs underline">Sluiten</button>
    </div>
  );
}
