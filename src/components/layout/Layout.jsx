import { useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { 
  Users, 
  Building2, 
  Calendar, 
  Settings, 
  Menu, 
  X,
  Home,
  LogOut,
  Search
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

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
        <Link to="/" className="text-xl font-bold text-primary-600">
          Personal CRM
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

function Header({ onMenuClick }) {
  const [searchQuery, setSearchQuery] = useState('');
  const location = useLocation();
  
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
      
      {/* Search */}
      <div className="flex-1 max-w-md mx-4 lg:mx-8">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="search"
            placeholder="Search..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="input pl-9"
          />
        </div>
      </div>
      
      {/* User menu placeholder */}
      <div className="flex items-center">
        <div className="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
          <span className="text-sm font-medium text-primary-700">U</span>
        </div>
      </div>
    </header>
  );
}

export default function Layout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  
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
