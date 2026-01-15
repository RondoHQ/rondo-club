import { lazy, Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/hooks/useAuth';
import { useVersionCheck } from '@/hooks/useVersionCheck';
import { useTheme } from '@/hooks/useTheme';
import { prmApi } from '@/api/client';
import Layout from '@/components/layout/Layout';
import { AlertCircle, RefreshCw } from 'lucide-react';

// Lazy-loaded page components
const Dashboard = lazy(() => import('@/pages/Dashboard'));
const PeopleList = lazy(() => import('@/pages/People/PeopleList'));
const PersonDetail = lazy(() => import('@/pages/People/PersonDetail'));
const FamilyTree = lazy(() => import('@/pages/People/FamilyTree'));
const CompaniesList = lazy(() => import('@/pages/Companies/CompaniesList'));
const CompanyDetail = lazy(() => import('@/pages/Companies/CompanyDetail'));
const DatesList = lazy(() => import('@/pages/Dates/DatesList'));
const TodosList = lazy(() => import('@/pages/Todos/TodosList'));
const Settings = lazy(() => import('@/pages/Settings/Settings'));
const RelationshipTypes = lazy(() => import('@/pages/Settings/RelationshipTypes'));
const Labels = lazy(() => import('@/pages/Settings/Labels'));
const UserApproval = lazy(() => import('@/pages/Settings/UserApproval'));
const WorkspacesList = lazy(() => import('@/pages/Workspaces/WorkspacesList'));
const WorkspaceDetail = lazy(() => import('@/pages/Workspaces/WorkspaceDetail'));
const WorkspaceSettings = lazy(() => import('@/pages/Workspaces/WorkspaceSettings'));
const WorkspaceInviteAccept = lazy(() => import('@/pages/Workspaces/WorkspaceInviteAccept'));
const Login = lazy(() => import('@/pages/Login'));

// Page loading spinner
const PageLoader = () => (
  <div className="flex items-center justify-center min-h-[50vh]">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
  </div>
);

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
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
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
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
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
    <div className="fixed top-0 left-0 right-0 z-[100] bg-accent-600 text-white py-2 px-4 shadow-lg">
      <div className="max-w-7xl mx-auto flex items-center justify-center gap-4 text-sm">
        <span>
          A new version ({latestVersion}) is available.
        </span>
        <button
          onClick={reload}
          className="inline-flex items-center gap-2 px-3 py-1 bg-white text-accent-600 rounded-md font-medium hover:bg-accent-50 transition-colors"
        >
          <RefreshCw className="w-4 h-4" />
          Reload
        </button>
      </div>
    </div>
  );
}

function App() {
  // Initialize theme on app mount (applies dark class and accent color)
  useTheme();

  return (
    <div className="app-root">
      <UpdateBanner />
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={
          <Suspense fallback={<PageLoader />}>
            <Login />
          </Suspense>
        } />

      {/* Protected routes */}
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <Layout>
              <Suspense fallback={<PageLoader />}>
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
                  <Route path="/settings/labels" element={<Labels />} />
                  <Route path="/settings/user-approval" element={<UserApproval />} />

                  {/* Workspaces routes */}
                  <Route path="/workspaces" element={<WorkspacesList />} />
                  <Route path="/workspaces/:id" element={<WorkspaceDetail />} />
                  <Route path="/workspaces/:id/settings" element={<WorkspaceSettings />} />
                  <Route path="/workspace-invite/:token" element={<WorkspaceInviteAccept />} />

                  {/* Fallback */}
                  <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
              </Suspense>
            </Layout>
          </ProtectedRoute>
        }
      />
      </Routes>
    </div>
  );
}

export default App;
