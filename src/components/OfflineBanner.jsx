import { useState, useEffect } from 'react';
import { WifiOff, Wifi } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export function OfflineBanner() {
  const isOnline = useOnlineStatus();
  const [wasOffline, setWasOffline] = useState(false);
  const [showBackOnline, setShowBackOnline] = useState(false);

  useEffect(() => {
    if (!isOnline) {
      // Going offline
      setWasOffline(true);
      setShowBackOnline(false);
    } else if (wasOffline) {
      // Coming back online after being offline
      setShowBackOnline(true);
      setWasOffline(false);

      const timer = setTimeout(() => {
        setShowBackOnline(false);
      }, 2500);

      return () => clearTimeout(timer);
    }
  }, [isOnline, wasOffline]);

  // Don't render if online and not showing "back online" message
  if (isOnline && !showBackOnline) {
    return null;
  }

  return (
    <div
      className={`fixed bottom-0 left-0 right-0 z-50 px-4 py-3 text-sm font-medium text-center transition-colors ${
        isOnline
          ? 'bg-green-600 text-white'
          : 'bg-gray-600 text-gray-100 dark:bg-gray-700'
      }`}
    >
      <div className="flex items-center justify-center gap-2">
        {isOnline ? (
          <>
            <Wifi className="w-4 h-4" />
            <span>Je bent weer online</span>
          </>
        ) : (
          <>
            <WifiOff className="w-4 h-4" />
            <span>Je bent offline</span>
          </>
        )}
      </div>
    </div>
  );
}
