/* eslint-disable react-refresh/only-export-components */
import { Suspense } from 'react';
import { createBrowserRouter, Navigate, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import Layout from '@/components/layout/Layout';
import { PageLoadingSpinner, ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { Shield } from 'lucide-react';
import App from './App';

// Direct import for Dashboard (no lazy loading)
import Dashboard from '@/pages/Dashboard';

// Lazy-loaded page components (separate file for fast refresh compatibility)
import {
  PeopleList, PeopleAnniversaries, PeopleOnboarding, PersonDetail, TeamsList, TeamDetail,
  Kaderlijst,
  CommissiesList, CommissieDetail, TodosList,
  FeedbackList, FeedbackDetail, Settings, VOG,
  Contributie, DisciplineCasesList,
  FinanceDashboard, Facturen, FactuurDetail, FactuurNieuw, RelationshipTypes,
  CustomFields, Login, Profile, ProfileIva, ProfileVog,
  MembershipPassScanner,
  ClothingPage,
  VrijwilligersDashboard, VrijwilligersExemptions, VrijwilligersIva, VrijwilligersDiensten,
  VrijwilligersDienstForm, VrijwilligersSjablonen, VrijwilligersSjabloonForm,
  VrijwilligersDataQuality, VrijwilligersRelationshipQuality, Vrijwillig,
  TaakuitlegList, TaakuitlegForm,
} from './lazyPages';

// Page loader for Suspense fallback
function PageLoader() {
  return <ContentLoadingSpinner />;
}

function AccessDenied() {
  const navigate = useNavigate();

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
            className="btn-tertiary"
          >
            Ga terug
          </button>
        </div>
      </div>
    </div>
  );
}

/**
 * Generic capability-based route guard.
 * @param {Object} props
 * @param {Function} props.checkAccess - Function that receives user object and returns boolean
 * @param {React.ReactNode} props.children - Child components to render if access is granted
 */
function CapabilityRoute({ checkAccess, children }) {
  const { data: user, isLoading } = useCurrentUser();

  if (isLoading) {
    return <PageLoadingSpinner />;
  }

  if (!checkAccess(user)) {
    return <AccessDenied />;
  }

  return children;
}

// Specific capability routes using the generic guard
function FairplayRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_fairplay}>
      {children}
    </CapabilityRoute>
  );
}

function VOGRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_vog}>
      {children}
    </CapabilityRoute>
  );
}

function FinancieelRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_financieel}>
      {children}
    </CapabilityRoute>
  );
}

function ToegangscontroleRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_toegangscontrole}>
      {children}
    </CapabilityRoute>
  );
}

function ClothingRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_clothing}>
      {children}
    </CapabilityRoute>
  );
}

function LedenadministratieRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_ledenadministratie}>
      {children}
    </CapabilityRoute>
  );
}

function VrijwilligersRoute({ children }) {
  return (
    <CapabilityRoute checkAccess={(user) => user?.can_access_vrijwilligers}>
      {children}
    </CapabilityRoute>
  );
}

/**
 * Kader-only: any user with at least one staff capability. Plain leden zonder
 * expliciete rechten krijgen geen "Geen toegang"-scherm maar worden meteen
 * doorgestuurd naar hun eigen vrijwillig-aanmelding.
 */
function isKaderUser(user) {
  if (!user) return false;
  return Boolean(
    user.is_admin ||
    user.can_access_fairplay ||
    user.can_access_vog ||
    user.can_access_financieel ||
    user.can_access_toegangscontrole ||
    user.can_access_clothing ||
    user.can_access_ledenadministratie ||
    user.can_access_vrijwilligers
  );
}

function KaderOrVrijwilligRedirect({ children }) {
  const { data: user, isLoading } = useCurrentUser();
  if (isLoading) return <PageLoadingSpinner />;
  if (!isKaderUser(user)) return <Navigate to="/vrijwillig" replace />;
  return children;
}

function ProtectedRoute({ children }) {
  const { isLoggedIn, isLoading } = useAuth();

  if (isLoading) {
    return <PageLoadingSpinner />;
  }

  if (!isLoggedIn) {
    return <Navigate to="/login" replace />;
  }

  return children;
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
          // Dashboard — plain leden zonder kader-rol redirecten naar /vrijwillig
          { index: true, element: <KaderOrVrijwilligRedirect><Dashboard /></KaderOrVrijwilligRedirect> },

          // People routes — kader only (plain leden zien hun eigen gegevens via /profile of /vrijwillig)
          { path: 'people', element: <KaderOrVrijwilligRedirect><PeopleList /></KaderOrVrijwilligRedirect> },
          { path: 'people/jubilarissen', element: <KaderOrVrijwilligRedirect><PeopleAnniversaries /></KaderOrVrijwilligRedirect> },
          {
            path: 'people/onboarding',
            element: (
              <LedenadministratieRoute>
                <PeopleOnboarding />
              </LedenadministratieRoute>
            ),
          },
          { path: 'people/:id', element: <KaderOrVrijwilligRedirect><PersonDetail /></KaderOrVrijwilligRedirect> },

          // VOG routes - requires VOG capability
          // Canonical lives under /vrijwilligers/vog; legacy /vog kept for back-compat.
          {
            path: 'vog',
            element: (
              <VOGRoute>
                <VOG />
              </VOGRoute>
            ),
          },
          {
            path: 'vog/:tab',
            element: (
              <VOGRoute>
                <VOG />
              </VOGRoute>
            ),
          },
          {
            path: 'vog/instellingen',
            element: <Navigate to="/settings/vog" replace />,
          },

          // Vrijwilligers section - requires vrijwilligers capability
          {
            path: 'vrijwilligers',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersDashboard />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/vog',
            element: (
              <VOGRoute>
                <VOG />
              </VOGRoute>
            ),
          },
          {
            path: 'vrijwilligers/vog/:tab',
            element: (
              <VOGRoute>
                <VOG />
              </VOGRoute>
            ),
          },
          {
            path: 'vrijwilligers/iva',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersIva />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/diensten',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersDiensten />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/diensten/nieuw',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersDienstForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/diensten/:id',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersDienstForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/sjablonen',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersSjablonen />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/sjablonen/nieuw',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersSjabloonForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/sjablonen/:id',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersSjabloonForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/vrijstellingen',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersExemptions />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/taakuitleg',
            element: (
              <VrijwilligersRoute>
                <TaakuitlegList />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/taakuitleg/nieuw',
            element: (
              <VrijwilligersRoute>
                <TaakuitlegForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/taakuitleg/:id',
            element: (
              <VrijwilligersRoute>
                <TaakuitlegForm />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/datakwaliteit/:category',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersDataQuality />
              </VrijwilligersRoute>
            ),
          },
          {
            path: 'vrijwilligers/relatie-check',
            element: (
              <VrijwilligersRoute>
                <VrijwilligersRelationshipQuality />
              </VrijwilligersRoute>
            ),
          },

          // Member-facing surface (#4) — any logged-in member can use this,
          // capability gating happens server-side based on linked-person eligibility.
          { path: 'vrijwillig', element: <Vrijwillig /> },
          // Legacy: /vrijwillig/profiel is verplaatst naar /profile/iva.
          { path: 'vrijwillig/profiel', element: <Navigate to="/profile/iva" replace /> },

          // Finance routes - requires financieel capability
          {
            path: 'financien',
            element: (
              <FinancieelRoute>
                <FinanceDashboard />
              </FinancieelRoute>
            ),
          },
          {
            path: 'financien/contributie',
            element: (
              <FinancieelRoute>
                <Contributie />
              </FinancieelRoute>
            ),
          },
          {
            path: 'financien/contributie/overzicht',
            element: <Navigate to="/financien" replace />,
          },
          {
            path: 'financien/contributie/:tab',
            element: (
              <FinancieelRoute>
                <Contributie />
              </FinancieelRoute>
            ),
          },
          { path: 'financien/instellingen', element: <Navigate to="/settings/financieel" replace /> },
          {
            path: 'financien/facturen/nieuw',
            element: (
              <FinancieelRoute>
                <FactuurNieuw />
              </FinancieelRoute>
            ),
          },
          {
            path: 'financien/facturen',
            element: (
              <FinancieelRoute>
                <Facturen />
              </FinancieelRoute>
            ),
          },
          {
            path: 'financien/facturen/:id',
            element: (
              <FinancieelRoute>
                <FactuurDetail />
              </FinancieelRoute>
            ),
          },

          // Old contributie routes - redirect to new location
          { path: 'contributie', element: <Navigate to="/financien/contributie" replace /> },
          { path: 'contributie/:tab', element: <Navigate to="/financien/contributie" replace /> },

          // Discipline Cases route - requires fairplay capability
          {
            path: 'tuchtzaken',
            element: (
              <FairplayRoute>
                <DisciplineCasesList />
              </FairplayRoute>
            ),
          },

          // Teams routes — kader only
          { path: 'teams', element: <KaderOrVrijwilligRedirect><TeamsList /></KaderOrVrijwilligRedirect> },
          { path: 'teams/:id', element: <KaderOrVrijwilligRedirect><TeamDetail /></KaderOrVrijwilligRedirect> },
          { path: 'kaderlijst', element: <KaderOrVrijwilligRedirect><Kaderlijst /></KaderOrVrijwilligRedirect> },
          {
            path: 'kleding/:tab',
            element: (
              <ClothingRoute>
                <ClothingPage />
              </ClothingRoute>
            ),
          },
          {
            path: 'kleding',
            element: (
              <ClothingRoute>
                <ClothingPage />
              </ClothingRoute>
            ),
          },

          // Commissies routes — kader only
          { path: 'commissies', element: <KaderOrVrijwilligRedirect><CommissiesList /></KaderOrVrijwilligRedirect> },
          { path: 'commissies/:id', element: <KaderOrVrijwilligRedirect><CommissieDetail /></KaderOrVrijwilligRedirect> },

          // Todos routes — kader only
          { path: 'todos', element: <KaderOrVrijwilligRedirect><TodosList /></KaderOrVrijwilligRedirect> },

          // Feedback routes — kader only
          { path: 'feedback', element: <KaderOrVrijwilligRedirect><FeedbackList /></KaderOrVrijwilligRedirect> },
          { path: 'feedback/:id', element: <KaderOrVrijwilligRedirect><FeedbackDetail /></KaderOrVrijwilligRedirect> },

          // Settings routes — kader only
          { path: 'settings/notifications', element: <Navigate to="/profile" replace /> },
          { path: 'settings', element: <KaderOrVrijwilligRedirect><Settings /></KaderOrVrijwilligRedirect> },
          { path: 'settings/:tab', element: <KaderOrVrijwilligRedirect><Settings /></KaderOrVrijwilligRedirect> },
          { path: 'settings/:tab/:subtab', element: <KaderOrVrijwilligRedirect><Settings /></KaderOrVrijwilligRedirect> },
          { path: 'settings/relationship-types', element: <KaderOrVrijwilligRedirect><RelationshipTypes /></KaderOrVrijwilligRedirect> },
          { path: 'settings/custom-fields', element: <KaderOrVrijwilligRedirect><CustomFields /></KaderOrVrijwilligRedirect> },
          { path: 'settings/feedback', element: <Navigate to="/feedback" replace /> },

          // Profile routes — Profile zelf is voor iedereen, subpagina's voor certificaten
          { path: 'profile', element: <Profile /> },
          { path: 'profile/vog', element: <ProfileVog /> },
          { path: 'profile/iva', element: <ProfileIva /> },

          // Membership pass scanner
          {
            path: 'lidpas-scanner',
            element: (
              <ToegangscontroleRoute>
                <MembershipPassScanner />
              </ToegangscontroleRoute>
            ),
          },

          // Fallback to dashboard
          { path: '*', element: <Navigate to="/" replace /> },
        ],
      },
    ],
  },
]);

export default router;
