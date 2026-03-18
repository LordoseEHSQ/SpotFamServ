import { useState } from 'react';
import {
  Speaker, RefreshCw, Search, AlertTriangle,
  CheckCircle2, Wifi, WifiOff, Lock,
  UserCheck, Unlink, Zap,
} from 'lucide-react';
import { useDevices, useTriggerDiscovery, useLatestDiscoveryRun, useAssignDevice } from '@/hooks/useDevices';
import { useProfiles } from '@/hooks/useProfiles';
import { type SpotifyDeviceDto, type AssignmentMode } from '@/api/endpoints/devices';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateRelative, cn } from '@/lib/utils';

// ─── Status-Badges ────────────────────────────────────────────────────────────

function AvailabilityBadge({ available }: { available: boolean }) {
  return available
    ? <Badge variant="success"><Wifi className="h-3 w-3 mr-1" />Verfügbar</Badge>
    : <Badge variant="muted"><WifiOff className="h-3 w-3 mr-1" />Nicht verfügbar</Badge>;
}

function AssignmentBadge({ mode, profileName }: { mode: AssignmentMode; profileName?: string | null }) {
  if (mode === 'assigned' && profileName)
    return <Badge variant="info"><UserCheck className="h-3 w-3 mr-1" />{profileName}</Badge>;
  if (mode === 'reserved')
    return <Badge variant="warning"><Lock className="h-3 w-3 mr-1" />Reserviert</Badge>;
  if (mode === 'locked')
    return <Badge variant="destructive"><Lock className="h-3 w-3 mr-1" />Gesperrt</Badge>;
  if (mode === 'shared')
    return <Badge variant="secondary">Geteilt</Badge>;
  return <Badge variant="muted"><Unlink className="h-3 w-3 mr-1" />Frei</Badge>;
}

// ─── Discovery Panel ─────────────────────────────────────────────────────────

function DiscoveryPanel() {
  const { data: run, isLoading } = useLatestDiscoveryRun();
  const triggerDiscovery = useTriggerDiscovery();

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-sm">Geräteerkennung (Discovery)</CardTitle>
            <CardDescription className="text-xs mt-0.5">
              Geräte werden über die Spotify Web API abgerufen – kein Netzwerk-Scan.
            </CardDescription>
          </div>
          <Button
            size="sm"
            variant="outline"
            onClick={() => triggerDiscovery.mutate(undefined)}
            disabled={triggerDiscovery.isPending}
          >
            <Zap className={cn('h-4 w-4', triggerDiscovery.isPending && 'animate-pulse')} />
            {triggerDiscovery.isPending ? 'Suche läuft…' : 'Jetzt suchen'}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="h-16 w-full" />
        ) : !run ? (
          <p className="text-sm text-muted-foreground">Noch keine Discovery durchgeführt.</p>
        ) : (
          <div className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4 text-sm">
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Gestartet</p>
              <p className="font-medium">{formatDateRelative(run.started_at)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Status</p>
              <p className="font-medium capitalize">{run.result_status ?? 'Läuft…'}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Gefunden</p>
              <p className="font-medium">{run.devices_found_count}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Verfügbar</p>
              <p className="font-medium">{run.devices_available_count}</p>
            </div>
            {run.devices_new_count > 0 && (
              <div>
                <p className="text-xs text-muted-foreground mb-0.5">Neu erkannt</p>
                <p className="font-medium text-success">{run.devices_new_count}</p>
              </div>
            )}
            {run.error_message && (
              <div className="col-span-2">
                <p className="text-xs text-muted-foreground mb-0.5">Hinweis</p>
                <p className="text-destructive text-xs">{run.error_message}</p>
              </div>
            )}
          </div>
        )}
        <div className="mt-3 pt-3 border-t">
          <p className="text-xs text-muted-foreground">
            <AlertTriangle className="h-3 w-3 inline mr-1 text-warning" />
            Die Erkennung fragt ausschließlich die Spotify Web API ab (/me/player/devices).
            Es werden nur Geräte gefunden, bei denen Spotify aktiv läuft.
          </p>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Device Assignment Dialog ────────────────────────────────────────────────

interface AssignDialogProps {
  device: SpotifyDeviceDto | null;
  onClose: () => void;
}

function AssignDialog({ device, onClose }: AssignDialogProps) {
  const { data: profilesData } = useProfiles();
  const assignDevice = useAssignDevice();
  const [profileId, setProfileId] = useState<string>(device?.assigned_family_profile_id ?? 'none');
  const [mode, setMode] = useState<AssignmentMode>(device?.assignment_mode ?? 'unassigned');
  const [note, setNote] = useState(device?.assignment_note ?? '');
  const [conflictConfirm, setConflictConfirm] = useState(false);

  if (!device) return null;

  const hasConflict = device.assignment_mode === 'assigned' && device.assigned_family_profile_id !== null;
  const isChangingOwner = hasConflict && profileId !== device.assigned_family_profile_id && profileId !== 'none';

  const handleSave = () => {
    if (isChangingOwner && !conflictConfirm) {
      setConflictConfirm(true);
      return;
    }
    assignDevice.mutate({
      id: device.id,
      data: {
        family_profile_id: profileId === 'none' ? null : profileId,
        assignment_mode: mode,
        assignment_note: note || undefined,
        force: conflictConfirm,
      },
    }, { onSuccess: onClose });
  };

  return (
    <>
      <Dialog open={!conflictConfirm} onOpenChange={(v) => !v && onClose()}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Gerät zuweisen</DialogTitle>
            <DialogDescription>{device.spotify_device_name}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {hasConflict && (
              <div className="rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-sm text-warning-foreground">
                <AlertTriangle className="h-4 w-4 inline mr-1.5 text-warning" />
                Dieses Gerät ist bereits <strong>{device.assigned_profile_name}</strong> zugeordnet.
                Eine Übernahme erfordert eine explizite Bestätigung.
              </div>
            )}
            <div className="space-y-1.5">
              <Label>Teilnehmer</Label>
              <Select value={profileId} onValueChange={setProfileId}>
                <SelectTrigger>
                  <SelectValue placeholder="Keinem Teilnehmer zuordnen" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">— Nicht zugeordnet —</SelectItem>
                  {profilesData?.items.map((p) => (
                    <SelectItem key={p.id} value={p.id}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Zuweisungsmodus</Label>
              <Select value={mode} onValueChange={(v) => setMode(v as AssignmentMode)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="unassigned">Frei (nicht zugeordnet)</SelectItem>
                  <SelectItem value="assigned">Fest zugeordnet</SelectItem>
                  <SelectItem value="reserved">Reserviert</SelectItem>
                  <SelectItem value="locked">Gesperrt</SelectItem>
                  <SelectItem value="shared">Geteilt</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Notiz (optional)</Label>
              <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={onClose}>Abbrechen</Button>
            <Button onClick={handleSave} disabled={assignDevice.isPending}>
              {assignDevice.isPending ? 'Speichert…' : 'Zuweisung speichern'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={conflictConfirm} onOpenChange={setConflictConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Gerät übernehmen?</AlertDialogTitle>
            <AlertDialogDescription>
              Das Gerät <strong>{device.spotify_device_name}</strong> ist derzeit{' '}
              <strong>{device.assigned_profile_name}</strong> zugeordnet.
              Möchtest du die Zuordnung wirklich ändern?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setConflictConfirm(false)}>Abbrechen</AlertDialogCancel>
            <AlertDialogAction onClick={handleSave}>Ja, Zuordnung übernehmen</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Device Detail Panel ─────────────────────────────────────────────────────

function DeviceDetail({ device, onAssign }: { device: SpotifyDeviceDto; onAssign: () => void }) {
  return (
    <div className="p-6 space-y-5">
      <div>
        <h3 className="font-semibold text-base">{device.spotify_device_name}</h3>
        <p className="text-sm text-muted-foreground">{device.device_type ?? 'Unbekannter Typ'}</p>
      </div>

      <Tabs defaultValue="uebersicht">
        <TabsList>
          <TabsTrigger value="uebersicht">Übersicht</TabsTrigger>
          <TabsTrigger value="erkennung">Erkennung</TabsTrigger>
          <TabsTrigger value="governance">Governance</TabsTrigger>
        </TabsList>

        <TabsContent value="uebersicht" className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Spotify Device ID</p>
              <p className="font-mono text-xs break-all">{device.spotify_device_id}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Verfügbarkeit</p>
              <AvailabilityBadge available={device.is_available} />
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Zuletzt gesehen</p>
              <p>{formatDateRelative(device.last_seen_at)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground mb-0.5">Registriert</p>
              <p>{formatDate(device.created_at)}</p>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="erkennung" className="space-y-3">
          <div className="rounded-md border px-4 py-3 space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Discovery-Status</span>
              <span className="font-medium capitalize">{device.discovery_status}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Zuletzt gesehen</span>
              <span>{formatDateRelative(device.last_seen_at)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Aktuell verfügbar</span>
              <span>{device.is_available ? 'Ja' : 'Nein'}</span>
            </div>
          </div>
          <p className="text-xs text-muted-foreground">
            Hinweis: Ein Gerät ist nur verfügbar, wenn Spotify darauf aktiv läuft und die Spotify-API
            es im letzten Abruf gemeldet hat.
          </p>
        </TabsContent>

        <TabsContent value="governance" className="space-y-4">
          <div className="rounded-md border px-4 py-3 space-y-2 text-sm">
            <div className="flex justify-between items-center">
              <span className="text-muted-foreground">Zuweisungsstatus</span>
              <AssignmentBadge mode={device.assignment_mode} profileName={device.assigned_profile_name} />
            </div>
            {device.assignment_updated_at && (
              <div className="flex justify-between">
                <span className="text-muted-foreground">Zuletzt geändert</span>
                <span>{formatDateRelative(device.assignment_updated_at)}</span>
              </div>
            )}
            {device.assignment_note && (
              <div>
                <p className="text-muted-foreground mb-0.5">Notiz</p>
                <p className="text-xs bg-muted px-2 py-1.5 rounded">{device.assignment_note}</p>
              </div>
            )}
          </div>
          <Button size="sm" onClick={onAssign}>
            <UserCheck className="h-4 w-4" />
            Zuweisung bearbeiten
          </Button>
        </TabsContent>
      </Tabs>
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export function DevicesPage() {
  const { data, isLoading, error, refetch, isFetching } = useDevices();
  const [search, setSearch] = useState('');
  const [selectedDevice, setSelectedDevice] = useState<SpotifyDeviceDto | null>(null);
  const [assignTarget, setAssignTarget] = useState<SpotifyDeviceDto | null>(null);

  const filtered = (data?.items ?? []).filter((d) =>
    d.spotify_device_name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">Lautsprecher & Geräte</h1>
          <p className="text-sm text-muted-foreground">
            {data?.total ?? 0} bekannte Geräte
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
        </Button>
      </div>

      {/* Discovery Panel */}
      <div className="px-6 py-4 border-b shrink-0">
        <DiscoveryPanel />
      </div>

      {/* Toolbar */}
      <div className="flex items-center gap-3 px-6 py-3 border-b shrink-0 bg-muted/20">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Gerät suchen…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-8 h-8 text-sm"
          />
        </div>
      </div>

      {/* Content: Table + Detail */}
      <div className="flex flex-1 overflow-hidden">
        {/* Table */}
        <div className={cn('overflow-auto', selectedDevice ? 'w-1/2 border-r' : 'flex-1')}>
          {isLoading ? (
            <div className="p-6 space-y-3">
              {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : error ? (
            <div className="flex flex-col items-center justify-center h-64 text-center">
              <p className="text-sm text-destructive">Fehler beim Laden der Geräte.</p>
              <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>Erneut versuchen</Button>
            </div>
          ) : filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-64 text-center">
              <Speaker className="h-8 w-8 text-muted-foreground mb-3" />
              <p className="text-sm text-muted-foreground">
                {search ? 'Keine Geräte gefunden.' : 'Noch keine Geräte bekannt. Führe eine Geräteerkennung durch.'}
              </p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead>Gerätename</TableHead>
                  <TableHead>Typ</TableHead>
                  <TableHead>Verfügbarkeit</TableHead>
                  <TableHead>Zuweisung</TableHead>
                  <TableHead>Zuletzt gesehen</TableHead>
                  <TableHead className="w-10" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((device) => (
                  <TableRow
                    key={device.id}
                    onClick={() => setSelectedDevice(device)}
                    data-state={selectedDevice?.id === device.id ? 'selected' : undefined}
                  >
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Speaker className="h-4 w-4 text-muted-foreground shrink-0" />
                        <span className="font-medium">{device.spotify_device_name}</span>
                        {device.assignment_mode === 'assigned' && device.assigned_family_profile_id && (
                          <CheckCircle2 className="h-3.5 w-3.5 text-success shrink-0" />
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-muted-foreground">{device.device_type ?? '—'}</span>
                    </TableCell>
                    <TableCell>
                      <AvailabilityBadge available={device.is_available} />
                    </TableCell>
                    <TableCell>
                      <AssignmentBadge mode={device.assignment_mode} profileName={device.assigned_profile_name} />
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-muted-foreground">{formatDateRelative(device.last_seen_at)}</span>
                    </TableCell>
                    <TableCell onClick={(e) => e.stopPropagation()}>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setAssignTarget(device)}
                      >
                        <UserCheck className="h-3.5 w-3.5" />
                        Zuweisen
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>

        {/* Detail Panel */}
        {selectedDevice && (
          <div className="w-1/2 overflow-auto">
            <DeviceDetail
              device={selectedDevice}
              onAssign={() => setAssignTarget(selectedDevice)}
            />
          </div>
        )}
      </div>

      <AssignDialog
        device={assignTarget}
        onClose={() => setAssignTarget(null)}
      />
    </div>
  );
}
