import { useNavigate } from 'react-router-dom';
import { Users, Speaker, CreditCard, Activity, CheckCircle2, AlertCircle, XCircle, ArrowRight } from 'lucide-react';
import { useProfiles } from '@/hooks/useProfiles';
import { useDevices, useLatestDiscoveryRun } from '@/hooks/useDevices';
import { useActivity } from '@/hooks/useActivity';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateRelative, cn } from '@/lib/utils';
import { type ActivitySeverity } from '@/api/endpoints/activity';

function StatCard({
  title, value, sub, icon: Icon, href, loading,
}: {
  title: string;
  value: number | string;
  sub?: string;
  icon: React.ElementType;
  href: string;
  loading?: boolean;
}) {
  const navigate = useNavigate();
  return (
    <Card
      className="cursor-pointer hover:shadow-md transition-shadow"
      onClick={() => navigate(href)}
    >
      <CardContent className="p-5">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-xs text-muted-foreground font-medium uppercase tracking-wide">{title}</p>
            {loading ? (
              <Skeleton className="h-8 w-12 mt-1" />
            ) : (
              <p className="text-3xl font-bold tracking-tight mt-1">{value}</p>
            )}
            {sub && <p className="text-xs text-muted-foreground mt-1">{sub}</p>}
          </div>
          <div className="rounded-md bg-muted p-2">
            <Icon className="h-5 w-5 text-muted-foreground" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function SeverityIcon({ severity }: { severity: ActivitySeverity }) {
  if (severity === 'critical' || severity === 'error')
    return <XCircle className="h-4 w-4 text-destructive shrink-0" />;
  if (severity === 'warning')
    return <AlertCircle className="h-4 w-4 text-warning shrink-0" />;
  return <CheckCircle2 className="h-4 w-4 text-muted-foreground shrink-0" />;
}

export function DashboardPage() {
  const navigate = useNavigate();
  const { data: profiles, isLoading: loadingProfiles } = useProfiles();
  const { data: devices, isLoading: loadingDevices } = useDevices();
  const { data: latestRun } = useLatestDiscoveryRun();
  const { data: activity, isLoading: loadingActivity } = useActivity({ limit: 10 });

  const connectedProfiles = profiles?.items.filter((p) => p.spotify_status === 'connected').length ?? 0;
  const availableDevices = devices?.items.filter((d) => d.is_available).length ?? 0;

  return (
    <div className="flex flex-col h-full overflow-auto">
      <div className="border-b px-6 py-4 shrink-0">
        <h1 className="text-lg font-semibold tracking-tight">Dashboard</h1>
        <p className="text-sm text-muted-foreground">Systemübersicht</p>
      </div>

      <div className="p-6 space-y-6">
        {/* Stats */}
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <StatCard
            title="Teilnehmer"
            value={profiles?.items.length ?? 0}
            sub={`${connectedProfiles} mit Spotify verbunden`}
            icon={Users}
            href="/profiles"
            loading={loadingProfiles}
          />
          <StatCard
            title="Geräte"
            value={devices?.total ?? 0}
            sub={`${availableDevices} verfügbar`}
            icon={Speaker}
            href="/devices"
            loading={loadingDevices}
          />
          <StatCard
            title="Aktivitäten"
            value={activity?.total ?? 0}
            sub="letzte 100 Einträge"
            icon={Activity}
            href="/activity"
            loading={loadingActivity}
          />
          <StatCard
            title="RFID-Karten"
            value="—"
            sub="Karten über Teilnehmer"
            icon={CreditCard}
            href="/cards"
          />
        </div>

        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {/* Teilnehmer Status */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">Teilnehmer-Status</CardTitle>
                <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => navigate('/profiles')}>
                  Alle anzeigen <ArrowRight className="h-3 w-3 ml-1" />
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {loadingProfiles ? (
                <div className="space-y-2">
                  {[1, 2].map((i) => <Skeleton key={i} className="h-10" />)}
                </div>
              ) : !profiles?.items.length ? (
                <p className="text-sm text-muted-foreground text-center py-4">
                  Keine Teilnehmer vorhanden.
                </p>
              ) : (
                <div className="space-y-1">
                  {profiles.items.slice(0, 5).map((p) => (
                    <div
                      key={p.id}
                      className="flex items-center gap-3 rounded-md px-3 py-2 hover:bg-muted/40 cursor-pointer"
                      onClick={() => navigate(`/profiles/${p.id}`)}
                    >
                      <div className={cn(
                        'h-2 w-2 rounded-full shrink-0',
                        p.spotify_status === 'connected' ? 'bg-success' :
                        p.spotify_status === 'expired' ? 'bg-warning' : 'bg-muted-foreground'
                      )} />
                      <span className="flex-1 text-sm font-medium">{p.name}</span>
                      {p.spotify_status === 'connected' ? (
                        <Badge variant="success" className="text-xs">Spotify OK</Badge>
                      ) : p.spotify_status === 'expired' ? (
                        <Badge variant="warning" className="text-xs">Abgelaufen</Badge>
                      ) : (
                        <Badge variant="muted" className="text-xs">Nicht verbunden</Badge>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Letzte Aktivitäten */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm">Letzte Aktivitäten</CardTitle>
                <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => navigate('/activity')}>
                  Alle anzeigen <ArrowRight className="h-3 w-3 ml-1" />
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {loadingActivity ? (
                <div className="space-y-2">
                  {[1, 2, 3].map((i) => <Skeleton key={i} className="h-10" />)}
                </div>
              ) : !activity?.items.length ? (
                <p className="text-sm text-muted-foreground text-center py-4">
                  Noch keine Aktivitäten vorhanden.
                </p>
              ) : (
                <div className="space-y-1">
                  {activity.items.slice(0, 6).map((entry) => (
                    <div key={entry.id} className="flex items-start gap-2.5 py-1.5">
                      <SeverityIcon severity={entry.severity} />
                      <div className="flex-1 min-w-0">
                        <p className="text-xs leading-snug truncate">{entry.message}</p>
                        <p className="text-xs text-muted-foreground">{formatDateRelative(entry.occurred_at)}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Discovery Status */}
        {latestRun && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-sm">Letzter Discovery-Lauf</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-6 text-sm flex-wrap">
                <div>
                  <p className="text-xs text-muted-foreground">Durchgeführt</p>
                  <p className="font-medium">{formatDateRelative(latestRun.started_at)}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Gefunden</p>
                  <p className="font-medium">{latestRun.devices_found_count} Gerät(e)</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Verfügbar</p>
                  <p className="font-medium">{latestRun.devices_available_count}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Status</p>
                  <p className="font-medium capitalize">{latestRun.result_status}</p>
                </div>
                <Button variant="outline" size="sm" className="ml-auto" onClick={() => navigate('/devices')}>
                  Geräteverwaltung <ArrowRight className="h-3 w-3 ml-1" />
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
