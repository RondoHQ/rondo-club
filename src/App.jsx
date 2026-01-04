import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
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
import FamilyTree from '@/pages/People/FamilyTree';
import Login from '@/pages/Login';

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
  
  return children;
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
