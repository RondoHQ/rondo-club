import { useMemo } from 'react';

export function useAuth() {
  const config = window.stadionConfig || {};
  
  return useMemo(() => ({
    isLoggedIn: config.isLoggedIn || false,
    userId: config.userId || null,
    loginUrl: config.loginUrl || '/wp-login.php',
    logoutUrl: config.logoutUrl || '/wp-login.php?action=logout',
    isLoading: false,
  }), [config.isLoggedIn, config.userId, config.loginUrl, config.logoutUrl]);
}
