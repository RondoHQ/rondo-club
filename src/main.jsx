import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider, onlineManager } from '@tanstack/react-query';
import DomErrorBoundary from './components/DomErrorBoundary';
import App from './App';
import './index.css';

// Configure TanStack Query to use browser online/offline events
onlineManager.setEventListener((setOnline) => {
  const handleOnline = () => setOnline(true);
  const handleOffline = () => setOnline(false);

  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  return () => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
  };
});

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      refetchOnWindowFocus: false, // Prevents refetch on tab switch
      refetchOnMount: false, // Prevents refetch when component remounts with cached data
      refetchOnReconnect: false, // Prevents refetch on network reconnection
      retry: 1,
    },
  },
});

const rootElement = document.getElementById('root');

if (rootElement) {
  try {
    ReactDOM.createRoot(rootElement).render(
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </QueryClientProvider>
    );
  } catch {
    rootElement.innerHTML = '<div style="padding: 20px; color: red;"><h1>Error Loading Application</h1><p>Please check the browser console for details.</p></div>';
  }
}
