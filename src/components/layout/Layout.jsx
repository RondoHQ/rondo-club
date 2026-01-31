import { useState, useRef, useEffect } from 'react';
import { Link, NavLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
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
  ChevronDown,
  CheckSquare,
  Command,
  UsersRound,
  MessageSquare,
  User,
  FileCheck,
  Coins
} from 'lucide-react';

// Stadium icon component matching favicon
const StadiumIcon = ({ className }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="currentColor"
    className={className}
  >
    <path
      fillRule="evenodd"
      d="M12 2C6.5 2 2 5.5 2 9v6c0 3.5 4.5 7 10 7s10-3.5 10-7V9c0-3.5-4.5-7-10-7zm0 2c4.4 0 8 2.7 8 5s-3.6 5-8 5-8-2.7-8-5 3.6-5 8-5zm0 4c-2.2 0-4 .9-4 2s1.8 2 4 2 4-.9 4-2-1.8-2-4-2z"
      clipRule="evenodd"
    />
  </svg>
);
import { useAuth } from '@/hooks/useAuth';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useSearch, useDashboard } from '@/hooks/useDashboard';
import { useQuery, useMutation } from '@tanstack/react-query';
import { prmApi, wpApi } from '@/api/client';
import { APP_NAME } from '@/constants/app';
import { useVOGCount } from '@/hooks/useVOGCount';

const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Leden', href: '/people', icon: Users },
  { name: 'Contributie', href: '/contributie', icon: Coins, indent: true },
  { name: 'VOG', href: '/vog', icon: FileCheck, indent: true },
  { name: 'Teams', href: '/teams', icon: Building2 },
  { name: 'Commissies', href: '/commissies', icon: UsersRound },
  { name: 'Datums', href: '/dates', icon: Calendar },
  { name: 'Taken', href: '/todos', icon: CheckSquare },
  { name: 'Feedback', href: '/feedback', icon: MessageSquare },
  { name: 'Instellingen', href: '/settings', icon: Settings },
];

function Sidebar({ mobile = false, onClose, stats }) {
  const { logoutUrl } = useAuth();
  const { count: vogCount } = useVOGCount();

  // Map navigation items to their counts
  const getCounts = (name) => {
    if (!stats) return null;
    switch (name) {
      case 'Leden': return stats.total_people;
      case 'Teams': return stats.total_teams;
      case 'Datums': return stats.total_dates;
      case 'VOG': return vogCount > 0 ? vogCount : null;
      default: return null;
    }
  };

  return (
    <div className="flex flex-col h-full bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700">
      {/* Logo */}
      <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <Link to="/" className="flex items-center gap-2 text-xl font-bold text-accent-600 dark:text-accent-400">
          <StadiumIcon className="w-5 h-5" />
          {APP_NAME}
        </Link>
        {mobile && (
          <button onClick={onClose} className="p-2 -mr-2 dark:text-gray-300">
            <X className="w-5 h-5" />
          </button>
        )}
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        {navigation.map((item) => {
          const count = getCounts(item.name);
          return (
            <NavLink
              key={item.name}
              to={item.href}
              onClick={mobile ? onClose : undefined}
              className={({ isActive }) =>
                `flex items-center py-2 text-sm font-medium rounded-lg transition-colors ${
                  item.indent ? 'pl-8 pr-3' : 'px-3'
                } ${
                  isActive
                    ? 'bg-accent-50 text-accent-700 dark:bg-gray-700 dark:text-accent-300'
                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700'
                }`
              }
            >
              <item.icon className="w-5 h-5 mr-3" />
              {item.name}
              {count !== null && count > 0 && (
                <span className="ml-auto text-xs text-gray-400 dark:text-gray-500">{count}</span>
              )}
            </NavLink>
          );
        })}
      </nav>

      {/* Logout */}
      <div className="p-4 border-t border-gray-200 dark:border-gray-700">
        <a
          href={logoutUrl}
          className="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700"
        >
          <LogOut className="w-5 h-5 mr-3" />
          Uitloggen
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
        <div className="w-8 h-8 bg-gray-200 rounded-full animate-pulse dark:bg-gray-700"></div>
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
        className="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors dark:hover:bg-gray-700"
        aria-label="Gebruikersmenu"
      >
        {user.avatar_url ? (
          <img
            src={user.avatar_url}
            alt={user.name}
            className="w-8 h-8 rounded-full object-cover"
          />
        ) : (
          <div className="w-8 h-8 bg-accent-100 rounded-full flex items-center justify-center dark:bg-accent-900">
            <span className="text-sm font-medium text-accent-700 dark:text-accent-300">{initials}</span>
          </div>
        )}
        <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform dark:text-gray-400 ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 dark:bg-gray-800 dark:border-gray-700">
          <div className="py-1">
            <a
              href={user.profile_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700"
              onClick={() => setIsOpen(false)}
            >
              <User className="w-4 h-4 mr-2" />
              <span className="hidden md:inline">Profiel bewerken</span>
            </a>
            {user.is_admin && (
              <a
                href={user.admin_url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700"
                onClick={() => setIsOpen(false)}
              >
                <Settings className="w-4 h-4 mr-2" />
                <span className="hidden md:inline">WordPress beheer</span>
              </a>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function SearchModal({ isOpen, onClose }) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const navigate = useNavigate();
  const inputRef = useRef(null);
  
  // Use search hook
  const trimmedQuery = searchQuery.trim();
  const { data: searchResults, isLoading: isSearchLoading } = useSearch(trimmedQuery);
  
  // Safe results
  const safeResults = searchResults || { people: [], teams: [] };
  const allResults = [
    ...(safeResults.people || []).map(p => ({ ...p, type: 'person' })),
    ...(safeResults.teams || []).map(c => ({ ...c, type: 'team' })),
  ];
  const hasResults = allResults.length > 0;
  const showResults = searchQuery.trim().length >= 2;
  
  // Reset state when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      setSearchQuery('');
      setSelectedIndex(0);
      // Focus input after a short delay to ensure modal is rendered
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [isOpen]);
  
  // Reset selection when results change
  useEffect(() => {
    setSelectedIndex(0);
  }, [searchResults]);
  
  // Handle keyboard navigation
  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      onClose();
      return;
    }
    
    if (!showResults || !hasResults) return;
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex(prev => Math.min(prev + 1, allResults.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex(prev => Math.max(prev - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const selected = allResults[selectedIndex];
      if (selected) {
        handleResultClick(selected.type, selected.id);
      }
    }
  };
  
  // Handle result click
  const handleResultClick = (type, id) => {
    onClose();
    if (type === 'person') {
      navigate(`/people/${id}`);
    } else if (type === 'team') {
      navigate(`/teams/${id}`);
    }
  };
  
  if (!isOpen) return null;
  
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />
      
      {/* Modal */}
      <div className="relative min-h-screen flex items-start justify-center pt-[15vh] px-4">
        <div className="relative w-full max-w-xl bg-white rounded-xl shadow-2xl overflow-hidden dark:bg-gray-800">
          {/* Search input */}
          <div className="flex items-center px-4 border-b border-gray-200 dark:border-gray-700">
            <Search className="w-5 h-5 text-gray-400 flex-shrink-0" />
            <input
              ref={inputRef}
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Zoek leden en teams..."
              className="flex-1 px-4 py-4 text-lg outline-none placeholder:text-gray-400 bg-transparent dark:text-gray-100"
              autoComplete="off"
            />
            <div className="flex items-center gap-1 text-xs text-gray-400">
              <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">esc</kbd>
              <span>to close</span>
            </div>
          </div>
          
          {/* Results */}
          <div className="max-h-96 overflow-y-auto">
            {!showResults ? (
              <div className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <Search className="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                <p className="text-sm">Typ minimaal 2 tekens om te zoeken</p>
              </div>
            ) : isSearchLoading ? (
              <div className="px-4 py-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 mx-auto"></div>
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">Zoeken...</p>
              </div>
            ) : hasResults ? (
              <div className="py-2">
                {/* People results */}
                {safeResults.people && safeResults.people.length > 0 && (
                  <div className="px-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      Leden
                    </div>
                    {safeResults.people.map((person, index) => {
                      const globalIndex = index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={person.id}
                          onClick={() => handleResultClick('person', person.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-accent-50 text-accent-900 dark:bg-accent-700 dark:text-white' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
                          }`}
                        >
                          {person.thumbnail ? (
                            <img
                              src={person.thumbnail}
                              alt={person.name}
                              className="w-8 h-8 rounded-full object-cover"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center dark:bg-gray-700">
                              <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {person.name}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">Enter</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}

                {/* Organizations results */}
                {safeResults.teams && safeResults.teams.length > 0 && (
                  <div className="px-2 mt-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      Teams
                    </div>
                    {safeResults.teams.map((team, index) => {
                      const globalIndex = (safeResults.people?.length || 0) + index;
                      const isSelected = selectedIndex === globalIndex;
                      return (
                        <button
                          key={team.id}
                          onClick={() => handleResultClick('team', team.id)}
                          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-left ${
                            isSelected ? 'bg-accent-50 text-accent-900 dark:bg-accent-700 dark:text-white' : 'hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-200'
                          }`}
                        >
                          {team.thumbnail ? (
                            <img
                              src={team.thumbnail}
                              alt={team.name}
                              className="w-8 h-8 rounded object-contain dark:bg-gray-700"
                            />
                          ) : (
                            <div className="w-8 h-8 bg-gray-200 rounded flex items-center justify-center dark:bg-gray-700">
                              <Building2 className="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            </div>
                          )}
                          <span className="text-sm font-medium flex-1 truncate">
                            {team.name}
                          </span>
                          {isSelected && (
                            <kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">Enter</kbd>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            ) : (
              <div className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <p className="text-sm">Geen resultaten gevonden voor "{searchQuery}"</p>
              </div>
            )}
          </div>
          
          {/* Footer */}
          <div className="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
            <div className="flex items-center gap-4">
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">up</kbd>
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">down</kbd>
                <span>to navigate</span>
              </span>
              <span className="flex items-center gap-1">
                <kbd className="px-1.5 py-0.5 bg-white border border-gray-200 rounded font-mono dark:bg-gray-800 dark:border-gray-600">enter</kbd>
                <span>to select</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Header({ onMenuClick, onOpenSearch }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const filteredCount = searchParams.get('filteredCount');

  // Get page title from location
  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path.startsWith('/people')) return 'Leden';
    if (path.startsWith('/contributie')) return 'Contributie';
    if (path.startsWith('/vog')) return 'VOG';
    if (path.startsWith('/teams')) return 'Teams';
    if (path.startsWith('/commissies')) return 'Commissies';
    if (path.startsWith('/dates')) return 'Datums';
    if (path.startsWith('/todos')) return 'Taken';
    if (path.startsWith('/settings')) return 'Instellingen';
    return '';
  };

  const isDashboard = location.pathname === '/';

  const handleCustomizeClick = () => {
    navigate('/?customize=true');
  };

  return (
    <header className="sticky top-0 z-30 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6 dark:bg-gray-800 dark:border-gray-700">
      {/* Mobile menu button */}
      <button
        onClick={onMenuClick}
        className="p-2 -ml-2 lg:hidden dark:text-gray-300"
      >
        <Menu className="w-5 h-5" />
      </button>

      {/* Page title */}
      <h1 className="ml-2 text-lg font-semibold lg:ml-0 dark:text-gray-100">
        {getPageTitle()}
        {filteredCount && (
          <span className="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
            ({filteredCount})
          </span>
        )}
      </h1>

      {/* Dashboard customize button */}
      {isDashboard && (
        <button
          onClick={handleCustomizeClick}
          className="ml-3 flex items-center gap-1.5 px-2 py-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
        >
          <Settings className="w-4 h-4" />
          <span className="hidden sm:inline">Aanpassen</span>
        </button>
      )}

      {/* Spacer */}
      <div className="flex-1" />

      {/* Search button */}
      <button
        onClick={onOpenSearch}
        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors dark:border-gray-600 dark:hover:bg-gray-700 dark:text-gray-400"
        aria-label="Zoeken"
        title="Zoeken (Cmd+K)"
      >
        <Search className="w-4 h-4" />
        <span className="hidden sm:inline text-sm">Zoek...</span>
        <kbd className="hidden sm:flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">
          <Command className="w-3 h-3" />K
        </kbd>
      </button>
      
      {/* User menu - right aligned */}
      <div className="ml-2">
        <UserMenu />
      </div>
    </header>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [showSearchModal, setShowSearchModal] = useState(false);
  const mainRef = useRef(null);
  const location = useLocation();

  // Fetch dashboard stats for navigation counts
  const { data: dashboardData } = useDashboard();
  const stats = dashboardData?.stats;

  // Update document title based on route
  useRouteTitle();

  // Focus main element on route change for keyboard scrolling
  useEffect(() => {
    // Small delay to ensure content is rendered
    const timer = setTimeout(() => {
      mainRef.current?.focus();
    }, 0);
    return () => clearTimeout(timer);
  }, [location.pathname]);

  // Handle Cmd+K keyboard shortcut
  useEffect(() => {
    const handleKeyDown = (e) => {
      // Cmd+K (Mac) or Ctrl+K (Windows/Linux)
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setShowSearchModal(true);
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, []);
  
  return (
    <div className="flex h-screen bg-gray-50 dark:bg-gray-900">
      {/* Desktop sidebar */}
      <div className="hidden lg:flex lg:w-64 lg:flex-col">
        <Sidebar stats={stats} />
      </div>

      {/* Mobile sidebar */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-gray-600 bg-opacity-75 dark:bg-black dark:bg-opacity-50"
            onClick={() => setSidebarOpen(false)}
          />

          {/* Sidebar */}
          <div className="fixed inset-y-0 left-0 flex flex-col w-64 bg-white dark:bg-gray-800">
            <Sidebar mobile onClose={() => setSidebarOpen(false)} stats={stats} />
          </div>
        </div>
      )}

      {/* Main content */}
      <div className="flex flex-col flex-1 min-w-0">
        <Header
          onMenuClick={() => setSidebarOpen(true)}
          onOpenSearch={() => setShowSearchModal(true)}
        />

        <main ref={mainRef} tabIndex={-1} className="flex-1 overflow-y-auto p-4 lg:p-6 [overscroll-behavior-y:none] focus:outline-none">
          {children}
        </main>
      </div>

      {/* Search Modal */}
      <SearchModal
        isOpen={showSearchModal}
        onClose={() => setShowSearchModal(false)}
      />
    </div>
  );
}
