import { useEffect } from 'react';
import { useAuth } from '@/hooks/useAuth';

export default function Login() {
  const { loginUrl, isLoggedIn } = useAuth();
  
  useEffect(() => {
    // If not logged in, redirect to WordPress login
    if (!isLoggedIn) {
      window.location.href = loginUrl;
    }
  }, [isLoggedIn, loginUrl]);
  
  return (
    <div className="flex items-center justify-center min-h-screen bg-gray-50">
      <div className="text-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto mb-4"></div>
        <p className="text-gray-600">Redirecting to login...</p>
      </div>
    </div>
  );
}
