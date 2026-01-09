import { Routes, Route, Navigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/hooks/useAuth';
import { useVersionCheck } from '@/hooks/useVersionCheck';
import { prmApi } from '@/api/client';
import Layout from '@/components/layout/Layout';
import Dashboard from '@/pages/Dashboard';
import PeopleList from '@/pages/People/PeopleList';
import PersonDetail from '@/pages/People/PersonDetail';
import CompaniesList from '@/pages/Companies/CompaniesList';
import CompanyDetail from '@/pages/Companies/CompanyDetail';
import DatesList from '@/pages/Dates/DatesList';
import TodosList from '@/pages/Todos/TodosList';
import Settings from '@/pages/Settings/Settings';
import RelationshipTypes from '@/pages/Settings/RelationshipTypes';
import UserApproval from '@/pages/Settings/UserApproval';
import FamilyTree from '@/pages/People/FamilyTree';
import Login from '@/pages/Login';
import { AlertCircle, RefreshCw } from 'lucide-react';

function ApprovalCheck({ children }) {
  const { data: user, isLoading, error } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    retry: false, // Don't retry on error
  });
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  // If there's an error or no user data, show approval screen as fallback
  // This handles cases where the API call fails
  if (error || !user) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="max-w-md w-full mx-4">
          <div className="card p-8 text-center">
            <div className="flex justify-center mb-4">
              <div className="p-3 bg-yellow-100 rounded-full">
                <AlertCircle className="w-8 h-8 text-yellow-600" />
              </div>
            </div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Account Pending Approval
            </h2>
            <p className="text-gray-600 mb-6">
              Your account is pending approval by an administrator. You will receive an email notification once your account has been approved.
            </p>
            <p className="text-sm text-gray-500">
              If you have any questions, please contact your administrator.
            </p>
          </div>
        </div>
      </div>
    );
  }
  
  // Admins are always approved
  if (user?.is_admin) {
    return children;
  }
  
  // Check approval status
  if (!user.is_approved) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="max-w-md w-full mx-4">
          <div className="card p-8 text-center">
            <div className="flex justify-center mb-4">
              <div className="p-3 bg-yellow-100 rounded-full">
                <AlertCircle className="w-8 h-8 text-yellow-600" />
              </div>
            </div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Account Pending Approval
            </h2>
            <p className="text-gray-600 mb-6">
              Your account is pending approval by an administrator. You will receive an email notification once your account has been approved.
            </p>
            <p className="text-sm text-gray-500">
              If you have any questions, please contact your administrator.
            </p>
          </div>
        </div>
      </div>
    );
  }
  
  return children;
}

function ProtectedRoute({ children }) {
  const { isLoggedIn, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (!isLoggedIn) {
    return <Navigate to="/login" replace />;
  }
  
  return <ApprovalCheck>{children}</ApprovalCheck>;
}

function UpdateBanner() {
  const { hasUpdate, latestVersion, reload } = useVersionCheck();
  
  if (!hasUpdate) return null;
  
  return (
    <div className="fixed top-0 left-0 right-0 z-[100] bg-primary-600 text-white py-2 px-4 shadow-lg">
      <div className="max-w-7xl mx-auto flex items-center justify-center gap-4 text-sm">
        <span>
          A new version ({latestVersion}) is available.
        </span>
        <button
          onClick={reload}
          className="inline-flex items-center gap-2 px-3 py-1 bg-white text-primary-600 rounded-md font-medium hover:bg-primary-50 transition-colors"
        >
          <RefreshCw className="w-4 h-4" />
          Reload
        </button>
      </div>
    </div>
  );
}

function App() {
  return (
    <>
      <UpdateBanner />
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<Login />} />
      
      {/* Protected routes */}
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <Layout>
              <Routes>
                <Route path="/" element={<Dashboard />} />
                
                {/* People routes */}
                <Route path="/people" element={<PeopleList />} />
                <Route path="/people/:id/family-tree" element={<FamilyTree />} />
                <Route path="/people/:id" element={<PersonDetail />} />
                
                {/* Companies routes */}
                <Route path="/companies" element={<CompaniesList />} />
                <Route path="/companies/:id" element={<CompanyDetail />} />
                
                {/* Dates routes */}
                <Route path="/dates" element={<DatesList />} />
                
                {/* Todos routes */}
                <Route path="/todos" element={<TodosList />} />
                
                {/* Settings */}
                <Route path="/settings" element={<Settings />} />
                <Route path="/settings/relationship-types" element={<RelationshipTypes />} />
                <Route path="/settings/user-approval" element={<UserApproval />} />
                
                {/* Fallback */}
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </Layout>
          </ProtectedRoute>
        }
      />
      </Routes>
    </>
  );
}

export default App;
