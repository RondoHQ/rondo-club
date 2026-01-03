import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

const rootElement = document.getElementById('root');

if (!rootElement) {
  console.error('Root element not found! Make sure there is a <div id="root"></div> in your HTML.');
} else {
  try {
    ReactDOM.createRoot(rootElement).render(
      <React.StrictMode>
        <QueryClientProvider client={queryClient}>
          <BrowserRouter>
            <App />
          </BrowserRouter>
        </QueryClientProvider>
      </React.StrictMode>
    );
  } catch (error) {
    console.error('Error mounting React app:', error);
    rootElement.innerHTML = '<div style="padding: 20px; color: red;"><h1>Error Loading Application</h1><p>Please check the browser console for details.</p></div>';
  }
}
