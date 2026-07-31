import { Share, Plus, X, MoreVertical, MonitorDown } from 'lucide-react';
import { getAppName } from '@/constants/app';
import { getInstallPlatform } from '@/utils/platform';

/**
 * Per-platform install steps.
 *
 * Only Chromium browsers expose beforeinstallprompt, so on everything else the
 * best we can do is describe the browser's own menu accurately.
 *
 * @param {string} appName Name this deployment is known by.
 * @returns {Object<string, {intro: string, steps: Array}>}
 */
function instructionsFor(appName) {
  return {
    'ios-safari': {
      intro: `Voor snellere toegang kun je ${appName} op je beginscherm zetten:`,
      steps: [
        {
          text: 'Tik op het Deel-icoon',
          badge: <Share className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />,
        },
        {
          text: 'Scroll omlaag en kies "Zet op beginscherm"',
          badge: (
            <span className="inline-flex items-center gap-2 text-electric-cyan dark:text-electric-cyan">
              <Plus className="w-4 h-4" />
              <span className="text-sm font-medium">Zet op beginscherm</span>
            </span>
          ),
        },
        { text: 'Tik op "Voeg toe"' },
      ],
    },
    'ios-other': {
      intro: `Op de iPhone en iPad kan alleen Safari apps op het beginscherm zetten. Open ${appName} in Safari en probeer het opnieuw.`,
      steps: [
        { text: 'Open deze pagina in Safari' },
        {
          text: 'Tik op het Deel-icoon',
          badge: <Share className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />,
        },
        { text: 'Kies "Zet op beginscherm" en tik op "Voeg toe"' },
      ],
    },
    android: {
      intro: `Voor snellere toegang kun je ${appName} installeren op je startscherm:`,
      steps: [
        {
          text: 'Tik op het menu rechtsboven in je browser',
          badge: <MoreVertical className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />,
        },
        { text: 'Kies "App installeren" of "Toevoegen aan startscherm"' },
        { text: 'Bevestig met "Installeren"' },
      ],
    },
    desktop: {
      intro: `Je kunt ${appName} als app installeren, met een eigen venster en een icoon in je dock of taakbalk:`,
      steps: [
        {
          text: 'Klik op het installatie-icoon rechts in de adresbalk',
          badge: <MonitorDown className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />,
        },
        {
          text: `Zie je dat niet? Open het browsermenu en kies "${appName} installeren"`,
        },
        { text: 'Bevestig met "Installeren"' },
      ],
    },
  };
}

/**
 * Modal with platform-specific "add to home screen" instructions.
 *
 * Used both by the automatic iOS prompt and by the always-available install
 * button in the sidebar, so members who dismissed the prompt — or never saw
 * it — still have a way in.
 *
 * @param {Object} props Component props.
 * @param {boolean} props.open Whether the modal is visible.
 * @param {Function} props.onClose Called when the member closes the modal.
 * @param {string} [props.platform] Platform override; detected when omitted.
 * @param {string} [props.closeLabel] Label for the dismiss button.
 */
export function InstallInstructions({ open, onClose, platform, closeLabel = 'Sluiten' }) {
  if (!open) return null;

  const appName = getAppName();
  const key = platform || getInstallPlatform();
  const { intro, steps } = instructionsFor(appName)[key];

  return (
    <div
      className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-labelledby="install-instructions-title"
    >
      <div className="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl p-6 max-w-sm w-full mx-4 sm:mx-0 shadow-lg">
        <div className="flex justify-between items-start mb-4">
          <h3
            id="install-instructions-title"
            className="text-lg font-semibold text-gray-900 dark:text-gray-100"
          >
            Installeer {appName}
          </h3>
          <button
            onClick={onClose}
            className="p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            aria-label="Sluiten"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">{intro}</p>

        <ol className="space-y-4">
          {steps.map((step, index) => (
            <li key={step.text} className="flex items-start gap-3">
              <div className="flex-shrink-0 w-6 h-6 rounded-full bg-cyan-100 dark:bg-obsidian text-electric-cyan dark:text-electric-cyan flex items-center justify-center text-sm font-medium">
                {index + 1}
              </div>
              <div className="flex-1 text-sm">
                <p className={`text-gray-900 dark:text-gray-100${step.badge ? ' mb-2' : ''}`}>
                  {step.text}
                </p>
                {step.badge && (
                  <div className="inline-flex items-center justify-center px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-md">
                    {step.badge}
                  </div>
                )}
              </div>
            </li>
          ))}
        </ol>

        <button
          onClick={onClose}
          className="mt-6 w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
        >
          {closeLabel}
        </button>
      </div>
    </div>
  );
}

export default InstallInstructions;
