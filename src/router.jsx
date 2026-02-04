import { lazy, Suspense } from 'react';
import { createBrowserRouter, Navigate, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import Layout from '@/components/layout/Layout';
import { AlertCircle, Shield } from 'lucide-react';
import App from './App';

// Direct import for Dashboard (no lazy loading)
import Dashboard from '@/pages/Dashboard';

// Lazy-loaded page components
const PeopleList = lazy(() => import('@/pages/People/PeopleList'));
const PersonDetail = lazy(() => import('@/pages/People/PersonDetail'));
const FamilyTree = lazy(() => import('@/pages/People/FamilyTree'));
const TeamsList = lazy(() => import('@/pages/Teams/TeamsList'));
const TeamDetail = lazy(() => import('@/pages/Teams/TeamDetail'));
const CommissiesList = lazy(() => import('@/pages/Commissies/CommissiesList'));
const CommissieDetail = lazy(() => import('@/pages/Commissies/CommissieDetail'));
const DatesList = lazy(() => import('@/pages/Dates/DatesList'));
const TodosList = lazy(() => import('@/pages/Todos/TodosList'));
const FeedbackList = lazy(() => import('@/pages/Feedback/FeedbackList'));
const FeedbackDetail = lazy(() => import('@/pages/Feedback/FeedbackDetail'));
const Settings = lazy(() => import('@/pages/Settings/Settings'));
const VOGList = lazy(() => import('@/pages/VOG/VOGList'));
const ContributieList = lazy(() => import('@/pages/Contributie/ContributieList'));
const DisciplineCasesList = lazy(() => import('@/pages/DisciplineCases/DisciplineCasesList'));
const RelationshipTypes = lazy(() => import('@/pages/Settings/RelationshipTypes'));
const Labels = lazy(() => import('@/pages/Settings/Labels'));
const UserApproval = lazy(() => import('@/pages/Settings/UserApproval'));
const CustomFields = lazy(() => import('@/pages/Settings/CustomFields'));
const FeedbackManagement = lazy(() => import('@/pages/Settings/FeedbackManagement'));
const Login = lazy(() => import('@/pages/Login'));

// Page loading spinner
const PageLoader = () => (
  <div className="flex items-center justify-center min-h-[50vh]">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
  </div>
);

function ApprovalCheck({ children }) {
  const { data: user, isLoading, error } = useCurrentUser();

  // Always render children, hide with CSS while loading
  const showApprovalError = !isLoading && (error || !user || (!user.is_admin && !user.is_approved));

  return (
    <>
      {/* Loading overlay - shown on top while loading */}
      {isLoading && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-50">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
        </div>
      )}

      {/* Approval error screen - shown on top when not approved */}
      {showApprovalError && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-50">
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
      )}

      {/* Always render children - they mount immediately and stay mounted */}
      {children}
    </>
  );
}

function AccessDenied({ navigate }) {
  return (
    <div className="flex items-center justify-center min-h-screen bg-gray-50 dark:bg-gray-900">
      <div className="max-w-md w-full mx-4">
        <div className="card p-8 text-center">
          <div className="flex justify-center mb-4">
            <div className="p-3 bg-red-100 rounded-full dark:bg-red-900">
              <Shield className="w-8 h-8 text-red-600 dark:text-red-400" />
            </div>
          </div>
          <h2 className="text-2xl font-semibold text-gray-900 mb-2 dark:text-gray-100">
            Geen toegang
          </h2>
          <p className="text-gray-600 mb-6 dark:text-gray-400">
            Je hebt geen toestemming om deze pagina te bekijken.
          </p>
          <button
            onClick={() => navigate(-1)}
            className="btn btn-secondary"
          >
            Ga terug
          </button>
        </div>
      </div>
    </div>
  );
}

function FairplayRoute({ children }) {
  const navigate = useNavigate();
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
      </div>
    );
  }

  // User doesn't have fairplay capability
  if (!user?.can_access_fairplay) {
    return <AccessDenied navigate={navigate} />;
  }

  return children;
}

function VOGRoute({ children }) {
  const navigate = useNavigate();
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
      </div>
    );
  }

  // User doesn't have VOG capability
  if (!user?.can_access_vog) {
    return <AccessDenied navigate={navigate} />;
  }

  return children;
}

function RestrictedRoute({ children }) {
  const navigate = useNavigate();
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
      </div>
    );
  }

  // Restricted users (VOG or Fair Play without admin) cannot access
  const isRestricted = !user?.is_admin &&
    (user?.can_access_vog || user?.can_access_fairplay);

  if (isRestricted) {
    return <AccessDenied navigate={navigate} />;
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

// Layout route component using Outlet for nested routes
function ProtectedLayout() {
  return (
    <ProtectedRoute>
      <Layout>
        <Suspense fallback={<PageLoader />}>
          <Outlet />
        </Suspense>
      </Layout>
    </ProtectedRoute>
  );
}

// Router configuration at MODULE SCOPE - critical for preventing remounts
const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      // Public route: Login
      {
        path: 'login',
        element: (
          <Suspense fallback={<PageLoader />}>
            <Login />
          </Suspense>
        ),
      },
      // Protected routes with layout
      {
        element: <ProtectedLayout />,
        children: [
          // Dashboard
          { index: true, element: <Dashboard /> },

          // People routes
          { path: 'people', element: <PeopleList /> },
          { path: 'people/:id/family-tree', element: <FamilyTree /> },
          { path: 'people/:id', element: <PersonDetail /> },

          // VOG route - requires VOG capability
          {
            path: 'vog',
            element: (
              <VOGRoute>
                <VOGList />
              </VOGRoute>
            ),
          },

          // Contributie route - restricted from VOG/Fair Play users
          {
            path: 'contributie',
            element: (
              <RestrictedRoute>
                <ContributieList />
              </RestrictedRoute>
            ),
          },

          // Discipline Cases route - requires fairplay capability
          {
            path: 'tuchtzaken',
            element: (
              <FairplayRoute>
                <DisciplineCasesList />
              </FairplayRoute>
            ),
          },

          // Teams routes - requires fairplay capability
          {
            path: 'teams',
            element: (
              <FairplayRoute>
                <TeamsList />
              </FairplayRoute>
            ),
          },
          {
            path: 'teams/:id',
            element: (
              <FairplayRoute>
                <TeamDetail />
              </FairplayRoute>
            ),
          },

          // Commissies routes
          { path: 'commissies', element: <CommissiesList /> },
          { path: 'commissies/:id', element: <CommissieDetail /> },

          // Dates routes - restricted from VOG/Fair Play users
          {
            path: 'dates',
            element: (
              <RestrictedRoute>
                <DatesList />
              </RestrictedRoute>
            ),
          },

          // Todos routes - restricted from VOG/Fair Play users
          {
            path: 'todos',
            element: (
              <RestrictedRoute>
                <TodosList />
              </RestrictedRoute>
            ),
          },

          // Feedback routes - restricted from VOG/Fair Play users
          {
            path: 'feedback',
            element: (
              <RestrictedRoute>
                <FeedbackList />
              </RestrictedRoute>
            ),
          },
          {
            path: 'feedback/:id',
            element: (
              <RestrictedRoute>
                <FeedbackDetail />
              </RestrictedRoute>
            ),
          },

          // Settings routes
          { path: 'settings', element: <Settings /> },
          { path: 'settings/:tab', element: <Settings /> },
          { path: 'settings/:tab/:subtab', element: <Settings /> },
          { path: 'settings/relationship-types', element: <RelationshipTypes /> },
          { path: 'settings/labels', element: <Labels /> },
          { path: 'settings/user-approval', element: <UserApproval /> },
          { path: 'settings/custom-fields', element: <CustomFields /> },
          { path: 'settings/feedback', element: <FeedbackManagement /> },

          // Fallback to dashboard
          { path: '*', element: <Navigate to="/" replace /> },
        ],
      },
    ],
  },
]);

export default router;
