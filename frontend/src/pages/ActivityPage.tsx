import { useState } from 'react';
import { CheckCircle2, XCircle, AlertCircle, Info, RefreshCw, Filter } from 'lucide-react';
import { useActivity } from '@/hooks/useActivity';
import { type ActivitySeverity, type ActivityLogEntryDto } from '@/api/endpoints/activity';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { formatDateRelative, cn } from '@/lib/utils';

const ACTIVITY_LABELS: Record<string, string> = {
  rfid_scan: 'RFID-Scan',
  playback_started: 'Wiedergabe gestartet',
  playback_blocked: 'Wiedergabe blockiert',
  playback_failed: 'Wiedergabe fehlgeschlagen',
  spotify_connected: 'Spotify verbunden',
  spotify_disconnected: 'Spotify getrennt',
  spotify_validated: 'Spotify validiert',
  spotify_token_refreshed: 'Token erneuert',
  device_assigned: 'Gerät zugewiesen',
  device_unassigned: 'Gerät freigegeben',
  device_discovered: 'Gerät erkannt',
  device_conflict: 'Gerätkonflikt',
  device_not_available: 'Gerät nicht verfügbar',
  rule_limit_reached: 'Limit erreicht',
  setup_completed: 'Setup abgeschlossen',
  system: 'Systemereignis',
};

function SeverityIcon({ severity, className }: { severity: ActivitySeverity; className?: string }) {
  if (severity === 'critical' || severity === 'error')
    return <XCircle className={cn('h-4 w-4 text-destructive', className)} />;
  if (severity === 'warning')
    return <AlertCircle className={cn('h-4 w-4 text-warning', className)} />;
  if (severity === 'debug')
    return <Info className={cn('h-4 w-4 text-muted-foreground', className)} />;
  return <CheckCircle2 className={cn('h-4 w-4 text-muted-foreground', className)} />;
}

function SeverityBadge({ severity }: { severity: ActivitySeverity }) {
  if (severity === 'critical') return <Badge variant="destructive">Kritisch</Badge>;
  if (severity === 'error') return <Badge variant="destructive">Fehler</Badge>;
  if (severity === 'warning') return <Badge variant="warning">Warnung</Badge>;
  if (severity === 'debug') return <Badge variant="muted">Debug</Badge>;
  return null;
}

function ActivityEntry({ entry }: { entry: ActivityLogEntryDto }) {
  return (
    <div className="flex items-start gap-3 px-4 py-3 border-b last:border-0 hover:bg-muted/30 transition-colors">
      <SeverityIcon severity={entry.severity} className="mt-0.5 shrink-0" />
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <p className="text-sm leading-snug">{entry.message}</p>
          <SeverityBadge severity={entry.severity} />
        </div>
        <div className="flex items-center gap-3 mt-1 flex-wrap">
          <span className="text-xs text-muted-foreground">
            {formatDateRelative(entry.occurred_at)}
          </span>
          {entry.profile_name && (
            <span className="text-xs text-muted-foreground bg-muted rounded px-1.5 py-0.5">
              {entry.profile_name}
            </span>
          )}
          <span className="text-xs text-muted-foreground">
            {ACTIVITY_LABELS[entry.activity_type] ?? entry.activity_type}
          </span>
        </div>
      </div>
    </div>
  );
}

export function ActivityPage() {
  const [severity, setSeverity] = useState<ActivitySeverity | 'all'>('all');
  const { data, isLoading, error, refetch, isFetching } = useActivity({
    limit: 100,
    severity: severity === 'all' ? undefined : severity,
  });

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">Aktivität & Verlauf</h1>
          <p className="text-sm text-muted-foreground">
            Systemereignisse und Teilnehmer-Aktivitäten
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
        </Button>
      </div>

      <div className="flex items-center gap-3 px-6 py-3 border-b shrink-0 bg-muted/20">
        <Filter className="h-4 w-4 text-muted-foreground" />
        <Select value={severity} onValueChange={(v) => setSeverity(v as ActivitySeverity | 'all')}>
          <SelectTrigger className="h-8 w-40 text-sm">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Alle Ereignisse</SelectItem>
            <SelectItem value="info">Info</SelectItem>
            <SelectItem value="warning">Warnungen</SelectItem>
            <SelectItem value="error">Fehler</SelectItem>
            <SelectItem value="critical">Kritisch</SelectItem>
          </SelectContent>
        </Select>
        {data && (
          <span className="text-xs text-muted-foreground ml-auto">
            {data.total} Einträge
          </span>
        )}
      </div>

      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-2">
            {[1, 2, 3, 4, 5].map((i) => <Skeleton key={i} className="h-14 w-full" />)}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive">Aktivitäts-Log nicht verfügbar.</p>
            <p className="text-xs text-muted-foreground mt-1">
              Das Backend-Modul ist möglicherweise noch nicht implementiert.
            </p>
          </div>
        ) : !data?.items.length ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <CheckCircle2 className="h-8 w-8 text-muted-foreground mb-3" />
            <p className="text-sm text-muted-foreground">Keine Aktivitäten vorhanden.</p>
          </div>
        ) : (
          <div className="divide-y">
            {data.items.map((entry) => (
              <ActivityEntry key={entry.id} entry={entry} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
