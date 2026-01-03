import { useState, useRef, useEffect } from 'react';
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom';
import { 
  Users, 
  Building2, 
  Calendar, 
  Settings, 
  Menu, 
  X,
  Home,
  LogOut,
  Search,
  User,
  Briefcase,
  Calendar as CalendarIcon,
  ChevronDown
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useSearch } from '@/hooks/useDashboard';
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { APP_NAME } from '@/constants/app';

const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'People', href: '/people', icon: Users },
  { name: 'Companies', href: '/companies', icon: Building2 },
  { name: 'Dates', href: '/dates', icon: Calendar },
  { name: 'Settings', href: '/settings', icon: Settings },
];

function Sidebar({ mobile = false, onClose }) {
  const { logoutUrl } = useAuth();
  
  return (
    <div className="flex flex-col h-full bg-white border-r border-gray-200">
      {/* Logo */}
      <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200">
        <Link to="/" className="flex items-center gap-2 text-xl font-bold text-primary-600">
          <Home className="w-5 h-5" />
          {APP_NAME}
        </Link>
        {mobile && (
          <button onClick={onClose} className="p-2 -mr-2">
            <X className="w-5 h-5" />
          </button>
        )}
      </div>
      
      {/* Navigation */}
      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        {navigation.map((item) => (
          <NavLink
            key={item.name}
            to={item.href}
            onClick={mobile ? onClose : undefined}
            className={({ isActive }) =>
              `flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                isActive
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-700 hover:bg-gray-50'
              }`
            }
          >
            <item.icon className="w-5 h-5 mr-3" />
            {item.name}
          </NavLink>
        ))}
      </nav>
      
      {/* Logout */}
      <div className="p-4 border-t border-gray-200">
        <a
          href={logoutUrl}
          className="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <LogOut className="w-5 h-5 mr-3" />
          Log Out
        </a>
      </div>
    </div>
  );
}

function UserMenu() {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef(null);
  
  const { data: user, isLoading } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
  });
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [isOpen]);
  
  if (isLoading || !user) {
    return (
      <div className="flex items-center ml-auto">
        <div className="w-8 h-8 bg-gray-200 rounded-full animate-pulse"></div>
      </div>
    );
  }
  
  const initials = user.name
    ?.split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?';
  
  return (
    <div className="relative ml-auto" ref={menuRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors"
        aria-label="User menu"
      >
        {user.avatar_url ? (
          <img
            src={user.avatar_url}
            alt={user.name}
            className="w-8 h-8 rounded-full object-cover"
          />
        ) : (
          <div className="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
            <span className="text-sm font-medium text-primary-700">{initials}</span>
          </div>
        )}
        <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>
      
      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <div className="py-1">
            <a
              href={user.profile_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
              onClick={() => setIsOpen(false)}
            >
              <User className="w-4 h-4 mr-2" />
              Edit profile
            </a>
            {user.is_admin && (
              <a
                href={user.admin_url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                onClick={() => setIsOpen(false)}
              >
                <Settings className="w-4 h-4 mr-2" />
                WordPress admin
              </a>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function Header({ onMenuClick }) {
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearchFocused, setIsSearchFocused] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const searchRef = useRef(null);
  const dropdownRef = useRef(null);
  
  // Use search hook - only search if query is 2+ characters
  // Always pass a string to maintain consistent hook calls
  const trimmedQuery = searchQuery.trim();
  const { data: searchResults, isLoading: isSearchLoading } = useSearch(trimmedQuery);
  
  // Get page title from location
  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path.startsWith('/people')) return 'People';
    if (path.startsWith('/companies')) return 'Companies';
    if (path.startsWith('/dates')) return 'Important Dates';
    if (path.startsWith('/settings')) return 'Settings';
    return '';
  };
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        searchRef.current &&
        !searchRef.current.contains(event.target)
      ) {
        setIsSearchFocused(false);
      }
    };
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);
  
  // Handle search result click
  const handleResultClick = (type, id) => {
    setIsSearchFocused(false);
    setSearchQuery('');
    if (type === 'person') {
      navigate(`/people/${id}`);
    } else if (type === 'company') {
      navigate(`/companies/${id}`);
    } else if (type === 'date') {
      navigate(`/dates/${id}/edit`);
    }
  };
  
  // Check if there are any results - ensure searchResults is an object
  const safeResults = searchResults || { people: [], companies: [], dates: [] };
  const hasResults = (
    (safeResults.people && safeResults.people.length > 0) ||
    (safeResults.companies && safeResults.companies.length > 0) ||
    (safeResults.dates && safeResults.dates.length > 0)
  );
  
  const showDropdown = isSearchFocused && searchQuery.trim().length >= 2;
  
  return (
    <header className="sticky top-0 z-10 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6">
      {/* Mobile menu button */}
      <button
        onClick={onMenuClick}
        className="p-2 -ml-2 lg:hidden"
      >
        <Menu className="w-5 h-5" />
      </button>
      
      {/* Page title */}
      <h1 className="ml-2 text-lg font-semibold lg:ml-0">
        {getPageTitle()}
      </h1>
      
      {/* Search - centered */}
      <div className="flex-1 flex justify-center max-w-2xl mx-4 lg:mx-8">
        <div className="relative w-full" ref={searchRef}>
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="search"
            placeholder="Search people, companies, dates..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onFocus={() => setIsSearchFocused(true)}
            className="input pl-9 w-full"
          />
          
          {/* Search results dropdown */}
          {showDropdown && (
            <div
              ref={dropdownRef}
              className="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-96 overflow-y-auto z-50"
            >
              {isSearchLoading ? (
                <div className="p-4 text-center text-gray-500">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600 mx-auto"></div>
                  <p className="mt-2 text-sm">Searching...</p>
                </div>
              ) : hasResults ? (
                <div className="py-2">
                  {/* People results */}
                  {safeResults.people && safeResults.people.length > 0 && (
                    <div className="px-3 py-2">
                      <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 px-2">
                        People
                      </div>
                      {safeResults.people.map((person) => (
                        <button
                          key={person.id}
                          onClick={() => handleResultClick('person', person.id)}
                          className="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 transition-colors text-left"
                        >
                          <User className="w-4 h-4 text-gray-400 flex-shrink-0" />
                          <span className="text-sm font-medium text-gray-900 flex-1 truncate">
                            {person.name}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}
                  
                  {/* Companies results */}
                  {safeResults.companies && safeResults.companies.length > 0 && (
                    <div className="px-3 py-2 border-t border-gray-100">
                      <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 px-2">
                        Companies
                      </div>
                      {safeResults.companies.map((company) => (
                        <button
                          key={company.id}
                          onClick={() => handleResultClick('company', company.id)}
                          className="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 transition-colors text-left"
                        >
                          <Briefcase className="w-4 h-4 text-gray-400 flex-shrink-0" />
                          <span className="text-sm font-medium text-gray-900 flex-1 truncate">
                            {company.name}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}
                  
                  {/* Dates results */}
                  {safeResults.dates && safeResults.dates.length > 0 && (
                    <div className="px-3 py-2 border-t border-gray-100">
                      <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 px-2">
                        Dates
                      </div>
                      {safeResults.dates.map((date) => (
                        <button
                          key={date.id}
                          onClick={() => handleResultClick('date', date.id)}
                          className="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 transition-colors text-left"
                        >
                          <CalendarIcon className="w-4 h-4 text-gray-400 flex-shrink-0" />
                          <span className="text-sm font-medium text-gray-900 flex-1 truncate">
                            {date.title}
                          </span>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div className="p-4 text-center text-gray-500">
                  <p className="text-sm">No results found</p>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
      
      {/* User menu - right aligned */}
      <UserMenu />
    </header>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  
  // Update document title based on route
  useRouteTitle();
  
  return (
    <div className="flex h-screen bg-gray-50">
      {/* Desktop sidebar */}
      <div className="hidden lg:flex lg:w-64 lg:flex-col">
        <Sidebar />
      </div>
      
      {/* Mobile sidebar */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-gray-600 bg-opacity-75"
            onClick={() => setSidebarOpen(false)}
          />
          
          {/* Sidebar */}
          <div className="fixed inset-y-0 left-0 flex flex-col w-64 bg-white">
            <Sidebar mobile onClose={() => setSidebarOpen(false)} />
          </div>
        </div>
      )}
      
      {/* Main content */}
      <div className="flex flex-col flex-1 min-w-0">
        <Header onMenuClick={() => setSidebarOpen(true)} />
        
        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
