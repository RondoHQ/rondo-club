import { useState, useRef, useEffect, lazy, Suspense } from 'react';
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
  ChevronDown,
  Sparkles,
  CheckSquare,
  Plus,
  Command,
  UsersRound,
  MessageSquare
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { useCreatePerson } from '@/hooks/usePeople';
import { useCreateDate } from '@/hooks/useDates';
import { useCreateTeam } from '@/hooks/useTeams';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useSearch, useDashboard } from '@/hooks/useDashboard';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi, wpApi } from '@/api/client';
import { APP_NAME } from '@/constants/app';
// Lazy load modals to reduce initial bundle size
// These pull in TipTap editor (~370 KB) which should only load when needed
const GlobalTodoModal = lazy(() => import('@/components/Timeline/GlobalTodoModal'));
const PersonEditModal = lazy(() => import('@/components/PersonEditModal'));
const TeamEditModal = lazy(() => import('@/components/TeamEditModal'));
const ImportantDateModal = lazy(() => import('@/components/ImportantDateModal'));
import { usePeople } from '@/hooks/usePeople';

const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'People', href: '/people', icon: Users },
  { name: 'Teams', href: '/teams', icon: Building2 },
  { name: 'Commissies', href: '/commissies', icon: UsersRound },
  { name: 'Dates', href: '/dates', icon: Calendar },
  { name: 'Todos', href: '/todos', icon: CheckSquare },
  { name: 'Workspaces', href: '/workspaces', icon: UsersRound },
  { name: 'Feedback', href: '/feedback', icon: MessageSquare },
  { name: 'Settings', href: '/settings', icon: Settings },
];

function Sidebar({ mobile = false, onClose, stats }) {
  const { logoutUrl } = useAuth();

  // Map navigation items to their counts
  const getCounts = (name) => {
    if (!stats) return null;
    switch (name) {
      case 'People': return stats.total_people;
      case 'Organizations': return stats.total_teams;
      case 'Dates': return stats.total_dates;
      default: return null;
    }
  };

  return (
    <div className="flex flex-col h-full bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700">
      {/* Logo */}
      <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <Link to="/" className="flex items-center gap-2 text-xl font-bold text-accent-600 dark:text-accent-400">
          <Sparkles className="w-5 h-5" />
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
                `flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
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
        aria-label="User menu"
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
              <span className="hidden md:inline">Edit profile</span>
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
                <span className="hidden md:inline">WordPress admin</span>
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
              placeholder="Search people & organizations..."
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
                <p className="text-sm">Type at least 2 characters to search</p>
              </div>
            ) : isSearchLoading ? (
              <div className="px-4 py-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 mx-auto"></div>
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">Searching...</p>
              </div>
            ) : hasResults ? (
              <div className="py-2">
                {/* People results */}
                {safeResults.people && safeResults.people.length > 0 && (
                  <div className="px-2">
                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide dark:text-gray-400">
                      People
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
                      Organizations
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
                <p className="text-sm">No results found for "{searchQuery}"</p>
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

function QuickAddMenu({ onAddTodo, onAddPerson, onAddTeam, onAddDate }) {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef(null);
  
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
  
  const handleAddTodo = () => {
    setIsOpen(false);
    onAddTodo();
  };
  
  const handleAddPerson = () => {
    setIsOpen(false);
    onAddPerson();
  };
  
  const handleAddTeam = () => {
    setIsOpen(false);
    onAddTeam();
  };
  
  const handleAddDate = () => {
    setIsOpen(false);
    onAddDate();
  };
  
  return (
    <div className="relative" ref={menuRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center justify-center w-9 h-9 rounded-lg bg-accent-600 hover:bg-accent-700 text-white transition-colors"
        aria-label="Quick add"
        title="Quick add"
      >
        <Plus className="w-5 h-5" />
      </button>
      
      {isOpen && (
        <div className="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 dark:bg-gray-800 dark:border-gray-700">
          <div className="py-1">
            <button
              onClick={handleAddPerson}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <User className="w-4 h-4 mr-3 text-gray-400" />
              New Person
            </button>
            <button
              onClick={handleAddTeam}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Building2 className="w-4 h-4 mr-3 text-gray-400" />
              New Organization
            </button>
            <button
              onClick={handleAddTodo}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <CheckSquare className="w-4 h-4 mr-3 text-gray-400" />
              New Todo
            </button>
            <button
              onClick={handleAddDate}
              className="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left dark:text-gray-200 dark:hover:bg-gray-700"
            >
              <Calendar className="w-4 h-4 mr-3 text-gray-400" />
              New Date
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function Header({ onMenuClick, onAddTodo, onAddPerson, onAddTeam, onAddDate, onOpenSearch }) {
  const location = useLocation();
  const navigate = useNavigate();

  // Get page title from location
  const getPageTitle = () => {
    const path = location.pathname;
    if (path === '/') return 'Dashboard';
    if (path.startsWith('/people')) return 'People';
    if (path.startsWith('/teams')) return 'Teams';
    if (path.startsWith('/commissies')) return 'Commissies';
    if (path.startsWith('/dates')) return 'Important Dates';
    if (path.startsWith('/todos')) return 'Todos';
    if (path.startsWith('/workspaces')) return 'Workspaces';
    if (path.startsWith('/settings')) return 'Settings';
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
      </h1>

      {/* Dashboard customize button */}
      {isDashboard && (
        <button
          onClick={handleCustomizeClick}
          className="ml-3 flex items-center gap-1.5 px-2 py-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
        >
          <Settings className="w-4 h-4" />
          <span className="hidden sm:inline">Customize</span>
        </button>
      )}

      {/* Spacer */}
      <div className="flex-1" />

      {/* Search button */}
      <button
        onClick={onOpenSearch}
        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors dark:border-gray-600 dark:hover:bg-gray-700 dark:text-gray-400"
        aria-label="Search"
        title="Search (Cmd+K)"
      >
        <Search className="w-4 h-4" />
        <span className="hidden sm:inline text-sm">Search...</span>
        <kbd className="hidden sm:flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 rounded text-xs text-gray-500 font-mono dark:bg-gray-700 dark:text-gray-400">
          <Command className="w-3 h-3" />K
        </kbd>
      </button>
      
      {/* Quick Add menu */}
      <div className="ml-2">
        <QuickAddMenu 
          onAddTodo={onAddTodo} 
          onAddPerson={onAddPerson}
          onAddTeam={onAddTeam}
          onAddDate={onAddDate}
        />
      </div>
      
      {/* User menu - right aligned */}
      <div className="ml-2">
        <UserMenu />
      </div>
    </header>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showSearchModal, setShowSearchModal] = useState(false);
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [showTeamModal, setShowTeamModal] = useState(false);
  const [showDateModal, setShowDateModal] = useState(false);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);
  const [isCreatingTeam, setIsCreatingTeam] = useState(false);
  const [isCreatingDate, setIsCreatingDate] = useState(false);
  
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  
  // Fetch people for date modal
  const { data: allPeople = [], isLoading: isPeopleLoading } = usePeople();

  // Fetch dashboard stats for navigation counts
  const { data: dashboardData } = useDashboard();
  const stats = dashboardData?.stats;

  // Update document title based on route
  useRouteTitle();
  
  // Create person mutation (using shared hook)
  const createPersonMutation = useCreatePerson({
    onSuccess: (result) => {
      setShowPersonModal(false);
      navigate(`/people/${result.id}`);
    },
  });
  
  // Create team mutation
  const createTeamMutation = useCreateTeam({
    onSuccess: (result) => {
      setShowTeamModal(false);
      navigate(`/teams/${result.id}`);
    },
  });
  
  // Create date mutation
  const createDateMutation = useCreateDate({
    onSuccess: () => setShowDateModal(false),
  });
  
  // Handle creating person
  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };
  
  // Handle creating team
  const handleCreateTeam = async (data) => {
    setIsCreatingTeam(true);
    try {
      await createTeamMutation.mutateAsync(data);
    } finally {
      setIsCreatingTeam(false);
    }
  };
  
  // Handle creating date
  const handleCreateDate = async (data) => {
    setIsCreatingDate(true);
    try {
      await createDateMutation.mutateAsync(data);
    } finally {
      setIsCreatingDate(false);
    }
  };
  
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
          onAddTodo={() => setShowTodoModal(true)}
          onAddPerson={() => setShowPersonModal(true)}
          onAddTeam={() => setShowTeamModal(true)}
          onAddDate={() => setShowDateModal(true)}
          onOpenSearch={() => setShowSearchModal(true)}
        />

        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          {children}
        </main>
      </div>

      {/* Search Modal */}
      <SearchModal
        isOpen={showSearchModal}
        onClose={() => setShowSearchModal(false)}
      />

      {/* Global Todo Modal (lazy loaded) */}
      <Suspense fallback={null}>
        <GlobalTodoModal
          isOpen={showTodoModal}
          onClose={() => setShowTodoModal(false)}
        />
      </Suspense>

      {/* Person Modal (lazy loaded) */}
      <Suspense fallback={null}>
        <PersonEditModal
          isOpen={showPersonModal}
          onClose={() => setShowPersonModal(false)}
          onSubmit={handleCreatePerson}
          isLoading={isCreatingPerson}
        />
      </Suspense>

      {/* Team Modal (lazy loaded) */}
      <Suspense fallback={null}>
        <TeamEditModal
          isOpen={showTeamModal}
          onClose={() => setShowTeamModal(false)}
          onSubmit={handleCreateTeam}
          isLoading={isCreatingTeam}
        />
      </Suspense>

      {/* Date Modal (lazy loaded) */}
      <Suspense fallback={null}>
        <ImportantDateModal
          isOpen={showDateModal}
          onClose={() => setShowDateModal(false)}
          onSubmit={handleCreateDate}
          isLoading={isCreatingDate}
          allPeople={allPeople}
          isPeopleLoading={isPeopleLoading}
        />
      </Suspense>
    </div>
  );
}
