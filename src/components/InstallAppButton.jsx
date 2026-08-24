import { useState } from 'react';
import { Download } from 'lucide-react';
import { useInstallPrompt } from '@/hooks/useInstallPrompt';
import { InstallInstructions } from '@/components/InstallInstructions';
import { isStandalone } from '@/utils/platform';

/**
 * InstallAppButton - always-available way to install the app.
 *
 * This sits in the sidebar and is the install affordance that works on every
 * platform, including iOS where no install API exists.
 *
 * On Chromium it fires the real native prompt when one was captured; otherwise
 * it explains where the browser hides the option.
 */
export function InstallAppButton() {
  const { hasNativePrompt, promptInstall, isInstalled } = useInstallPrompt();
  const [showInstructions, setShowInstructions] = useState(false);

  // Nothing to offer once the app is installed.
  if (isInstalled || isStandalone()) {
    return null;
  }

  const handleClick = async () => {
    if (hasNativePrompt) {
      const { outcome } = await promptInstall();

      // 'unavailable' means the captured prompt went stale; fall back to
      // instructions rather than leaving the click doing nothing.
      if (outcome !== 'unavailable') {
        return;
      }
    }

    setShowInstructions(true);
  };

  return (
    <>
      <button
        type="button"
        onClick={handleClick}
        className="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700"
      >
        <Download className="w-5 h-5 mr-3" />
        App installeren
      </button>

      <InstallInstructions
        open={showInstructions}
        onClose={() => setShowInstructions(false)}
      />
    </>
  );
}

export default InstallAppButton;
