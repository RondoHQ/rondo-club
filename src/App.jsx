import { Routes, Route, Navigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/hooks/useAuth';
import { prmApi } from '@/api/client';
import Layout from '@/components/layout/Layout';
import Dashboard from '@/pages/Dashboard';
import PeopleList from '@/pages/People/PeopleList';
import PersonDetail from '@/pages/People/PersonDetail';
import PersonForm from '@/pages/People/PersonForm';
import WorkHistoryForm from '@/pages/People/WorkHistoryForm';
import ContactDetailForm from '@/pages/People/ContactDetailForm';
import RelationshipForm from '@/pages/People/RelationshipForm';
import CompaniesList from '@/pages/Companies/CompaniesList';
import CompanyDetail from '@/pages/Companies/CompanyDetail';
import CompanyForm from '@/pages/Companies/CompanyForm';
import DatesList from '@/pages/Dates/DatesList';
import DateForm from '@/pages/Dates/DateForm';
import Settings from '@/pages/Settings/Settings';
import RelationshipTypes from '@/pages/Settings/RelationshipTypes';
import Import from '@/pages/Settings/Import';
import Export from '@/pages/Settings/Export';
import UserApproval from '@/pages/Settings/UserApproval';
import FamilyTree from '@/pages/People/FamilyTree';
import Login from '@/pages/Login';
import { AlertCircle } from 'lucide-react';

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

function App() {
  return (
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
                <Route path="/people/new" element={<PersonForm />} />
                <Route path="/people/:personId/contact/new" element={<ContactDetailForm />} />
                <Route path="/people/:personId/contact/:index/edit" element={<ContactDetailForm />} />
                <Route path="/people/:personId/work-history/new" element={<WorkHistoryForm />} />
                <Route path="/people/:personId/work-history/:index/edit" element={<WorkHistoryForm />} />
                <Route path="/people/:personId/relationship/new" element={<RelationshipForm />} />
                <Route path="/people/:personId/relationship/:index/edit" element={<RelationshipForm />} />
                <Route path="/people/:id/family-tree" element={<FamilyTree />} />
                <Route path="/people/:id/edit" element={<PersonForm />} />
                <Route path="/people/:id" element={<PersonDetail />} />
                
                {/* Companies routes */}
                <Route path="/companies" element={<CompaniesList />} />
                <Route path="/companies/new" element={<CompanyForm />} />
                <Route path="/companies/:id" element={<CompanyDetail />} />
                <Route path="/companies/:id/edit" element={<CompanyForm />} />
                
                {/* Dates routes */}
                <Route path="/dates" element={<DatesList />} />
                <Route path="/dates/new" element={<DateForm />} />
                <Route path="/dates/:id/edit" element={<DateForm />} />
                
                {/* Settings */}
                <Route path="/settings" element={<Settings />} />
                <Route path="/settings/relationship-types" element={<RelationshipTypes />} />
                <Route path="/settings/import" element={<Import />} />
                <Route path="/settings/export" element={<Export />} />
                <Route path="/settings/user-approval" element={<UserApproval />} />
                
                {/* Fallback */}
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </Layout>
          </ProtectedRoute>
        }
      />
    </Routes>
  );
}

export default App;
