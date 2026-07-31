import { useState, useEffect } from 'react';
import { installTracking } from '@/utils/installTracking';
import { useEngagementTracking } from '@/hooks/useEngagementTracking';
import { isIOSSafari, isStandalone } from '@/utils/platform';
import { InstallInstructions } from '@/components/InstallInstructions';

/**
 * IOSInstallModal - iOS Safari install instructions modal
 *
 * Shows step-by-step instructions for adding the app to the home screen, since
 * Safari has no beforeinstallprompt event to hook into. Only for iOS Safari
 * users who aren't already running the installed app, after they've shown some
 * engagement, and subject to the dismissal backoff in installTracking.
 *
 * Members who dismiss this can still install on demand via the "App
 * installeren" button in the sidebar.
 */
export function IOSInstallModal() {
  const [show, setShow] = useState(false);
  const isEngaged = useEngagementTracking({ minPageViews: 3, minNotes: 1 });

  useEffect(() => {
    if (!isEngaged || !isIOSSafari() || isStandalone()) {
      return;
    }

    // Respects the 7-day / 3-dismissal backoff in installTracking.
    if (!installTracking.shouldShowIOSPrompt()) {
      return;
    }

    setShow(true);
  }, [isEngaged]);

  const handleDismiss = () => {
    installTracking.trackIOSDismissal();
    setShow(false);
  };

  return (
    <InstallInstructions
      open={show}
      onClose={handleDismiss}
      platform="ios-safari"
      closeLabel="Misschien later"
    />
  );
}

export default IOSInstallModal;
