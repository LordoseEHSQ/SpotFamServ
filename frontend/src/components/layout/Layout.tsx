import { NavLink, useLocation } from 'react-router-dom';
import {
  LayoutDashboard, Users, Speaker, Activity,
  Clock, Settings, Wifi, ChevronRight, SlidersHorizontal, Radio, AudioLines,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';
import { TooltipProvider } from '@/components/ui/tooltip';

interface NavItem {
  label: string;
  href: string;
  icon: React.ElementType;
  badge?: string;
}

interface NavGroup {
  heading?: string;
  items: NavItem[];
}

const navigation: NavGroup[] = [
  {
    items: [
      { label: 'Dashboard', href: '/', icon: LayoutDashboard },
    ],
  },
  {
    heading: 'Verwaltung',
    items: [
      { label: 'Teilnehmer', href: '/profiles', icon: Users },
      { label: 'Lautsprecher & Geräte', href: '/devices', icon: Speaker },
      { label: 'RFID-Leser', href: '/readers', icon: Radio },
    ],
  },
  {
    heading: 'Betrieb',
    items: [
      { label: 'Aktivität & Verlauf', href: '/activity', icon: Activity },
      { label: 'Scan-Protokoll', href: '/scan-logs', icon: Wifi },
      { label: 'Regeln & Hörzeit', href: '/rules', icon: Clock },
    ],
  },
  {
    heading: 'Werkzeuge',
    items: [
      { label: 'Audio-Extractor', href: '/tools/audio-extractor', icon: AudioLines },
    ],
  },
  {
    heading: 'System',
    items: [
      { label: 'Setup-Assistent', href: '/setup', icon: Settings },
      { label: 'Systemeinstellungen', href: '/system', icon: SlidersHorizontal },
    ],
  },
];

function SidebarNavItem({ item }: { item: NavItem }) {
  const location = useLocation();
  const isActive = item.href === '/'
    ? location.pathname === '/'
    : location.pathname.startsWith(item.href);

  return (
    <NavLink
      to={item.href}
      className={cn(
        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
        isActive
          ? 'bg-sidebar-accent text-sidebar-accent-foreground'
          : 'text-sidebar-muted hover:bg-sidebar-accent/60 hover:text-sidebar-foreground'
      )}
    >
      <item.icon className="h-4 w-4 shrink-0" />
      <span className="flex-1 truncate">{item.label}</span>
      {item.badge && (
        <span className="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-sidebar-accent-foreground/10 px-1.5 text-xs text-sidebar-foreground">
          {item.badge}
        </span>
      )}
      {isActive && <ChevronRight className="h-3 w-3 opacity-40" />}
    </NavLink>
  );
}

function Sidebar() {
  return (
    <aside className="flex h-screen w-56 flex-col bg-sidebar border-r border-sidebar-border shrink-0">
      {/* Logo / App Header */}
      <div className="flex h-14 items-center gap-3 px-4 border-b border-sidebar-border">
        <div className="flex h-7 w-7 items-center justify-center rounded-md bg-sidebar-accent-foreground/10">
          <Speaker className="h-4 w-4 text-sidebar-foreground" />
        </div>
        <div className="flex flex-col leading-none">
          <span className="text-sm font-semibold text-sidebar-foreground tracking-tight">SpotFam</span>
          <span className="text-xs text-sidebar-muted">Admin</span>
        </div>
      </div>

      {/* Navigation */}
      <ScrollArea className="flex-1 px-3 py-3">
        <nav className="flex flex-col gap-4">
          {navigation.map((group, i) => (
            <div key={i} className="flex flex-col gap-0.5">
              {group.heading && (
                <p className="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-sidebar-muted">
                  {group.heading}
                </p>
              )}
              {group.items.map((item) => (
                <SidebarNavItem key={item.href} item={item} />
              ))}
            </div>
          ))}
        </nav>
      </ScrollArea>

      {/* Footer */}
      <div className="border-t border-sidebar-border px-4 py-3">
        <p className="text-xs text-sidebar-muted">v{__APP_VERSION__}</p>
      </div>
    </aside>
  );
}

interface LayoutProps {
  children: React.ReactNode;
}

export function Layout({ children }: LayoutProps) {
  const location = useLocation();
  const isLogin = location.pathname === '/login';

  if (isLogin) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        {children}
      </div>
    );
  }

  return (
    <TooltipProvider delayDuration={300}>
      <div className="flex h-screen overflow-hidden bg-background">
        <Sidebar />
        <main className="flex flex-1 flex-col overflow-hidden">
          {children}
        </main>
      </div>
    </TooltipProvider>
  );
}
