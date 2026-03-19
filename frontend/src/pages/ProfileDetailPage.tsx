import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft, Save, Trash2, Wand2, RefreshCw,
  User, Music2, Speaker, CreditCard, Clock, Activity, Headphones,
  CheckCircle2, XCircle, AlertCircle, ExternalLink,
} from 'lucide-react';
import { MusicTab } from '@/components/music/MusicTab';
import { useProfile, useUpdateProfile, useDeleteProfile } from '@/hooks/useProfiles';
import { useRfidCards } from '@/hooks/useRfidCards';
import { useActivity } from '@/hooks/useActivity';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ScrollArea } from '@/components/ui/scroll-area';
import { formatDate, formatDateRelative, cn } from '@/lib/utils';
import { type FamilyProfileDto } from '@/api/endpoints/profiles';
import { type ActivitySeverity } from '@/api/endpoints/activity';
import { api } from '@/api/client';

// ─── Tab: Allgemein ──────────────────────────────────────────────────────────

function TabAllgemein({ profile }: { profile: FamilyProfileDto }) {
  const [name, setName] = useState(profile.name);
  const [description, setDescription] = useState(profile.description ?? '');
  const updateProfile = useUpdateProfile(profile.id);
  const dirty = name !== profile.name || description !== (profile.description ?? '');

  const handleSave = async () => {
    await updateProfile.mutateAsync({ name, description: description || null });
  };

  return (
    <div className="space-y-6 max-w-lg">
      <div className="space-y-1.5">
        <Label htmlFor="name">Anzeigename *</Label>
        <Input id="name" value={name} onChange={(e) => setName(e.target.value)} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="desc">Notizen / Beschreibung</Label>
        <Textarea
          id="desc"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={3}
          placeholder="Optional"
        />
      </div>
      <Separator />
      <div className="grid grid-cols-2 gap-4 text-sm">
        <div>
          <p className="text-muted-foreground text-xs mb-0.5">Erstellt am</p>
          <p>{formatDate(profile.created_at)}</p>
        </div>
        <div>
          <p className="text-muted-foreground text-xs mb-0.5">Zuletzt geändert</p>
          <p>{formatDate(profile.updated_at)}</p>
        </div>
        <div>
          <p className="text-muted-foreground text-xs mb-0.5">Interne ID</p>
          <p className="font-mono text-xs text-muted-foreground break-all">{profile.id}</p>
        </div>
      </div>
      {dirty && (
        <Button onClick={handleSave} disabled={!name.trim() || updateProfile.isPending} size="sm">
          <Save className="h-4 w-4" />
          {updateProfile.isPending ? 'Speichert…' : 'Änderungen speichern'}
        </Button>
      )}
    </div>
  );
}

// ─── Tab: Spotify ────────────────────────────────────────────────────────────

function TabSpotify({ profile }: { profile: FamilyProfileDto }) {
  const [validating, setValidating] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);
  const [validateResult, setValidateResult] = useState<{ valid: boolean; message?: string } | null>(null);
  const [showDisconnectDialog, setShowDisconnectDialog] = useState(false);

  const getAuthUrl = async () => {
    try {
      const res = await api.get<{ authorization_url: string }>(
        `/profiles/${profile.id}/spotify/authorization-url`
      );
      window.location.href = res.authorization_url;
    } catch {
      alert('Fehler beim Abrufen der Spotify-URL.');
    }
  };

  const validate = async () => {
    setValidating(true);
    try {
      const res = await api.post<{ valid: boolean; display_name?: string }>(
        `/profiles/${profile.id}/spotify/validate`, {}
      );
      setValidateResult({ valid: res.valid, message: res.display_name });
    } catch {
      setValidateResult({ valid: false, message: 'Validierung fehlgeschlagen.' });
    } finally {
      setValidating(false);
    }
  };

  const disconnect = async () => {
    setDisconnecting(true);
    try {
      await api.delete(`/profiles/${profile.id}/spotify/disconnect`);
      window.location.reload();
    } catch {
      alert('Fehler beim Trennen der Spotify-Verbindung.');
    } finally {
      setDisconnecting(false);
      setShowDisconnectDialog(false);
    }
  };

  const status = profile.spotify_status ?? 'not_connected';

  return (
    <div className="space-y-5 max-w-lg">
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">Verbindungsstatus</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex items-center gap-3">
            {status === 'connected' ? (
              <CheckCircle2 className="h-5 w-5 text-success shrink-0" />
            ) : status === 'expired' ? (
              <AlertCircle className="h-5 w-5 text-warning shrink-0" />
            ) : (
              <XCircle className="h-5 w-5 text-muted-foreground shrink-0" />
            )}
            <div>
              <p className="text-sm font-medium">
                {status === 'connected' ? 'Verbunden' : status === 'expired' ? 'Token abgelaufen' : 'Nicht verbunden'}
              </p>
              {profile.spotify_user_display_name && (
                <p className="text-xs text-muted-foreground">{profile.spotify_user_display_name}</p>
              )}
            </div>
          </div>

          {(status === 'not_connected') && (
            <div className="rounded-md border border-muted bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
              <p className="font-medium text-foreground mb-0.5">Spotify nicht verbunden</p>
              <p>Klicke auf „Mit Spotify verbinden". Spotify zeigt dann einen Autorisierungsdialog – alle benötigten Berechtigungen werden dabei erteilt.</p>
            </div>
          )}

          {(status === 'connected' || status === 'expired') && (
            <div className="rounded-md border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 px-3 py-2.5 text-sm text-amber-800 dark:text-amber-300">
              <p className="font-medium mb-0.5">Fehlende Berechtigungen? Neu verbinden</p>
              <p>Falls Aktionen wie Playlist-Erstellen mit „Fehlende Berechtigung" fehlschlagen: Klicke auf <strong>„Trennen"</strong> und danach <strong>„Mit Spotify verbinden"</strong>. Spotify fordert dann die vollständigen Berechtigungen neu an.</p>
            </div>
          )}

          {validateResult && (
            <div className={cn(
              'rounded-md border px-3 py-2 text-sm',
              validateResult.valid ? 'border-success/30 bg-success/5 text-success' : 'border-destructive/30 bg-destructive/5 text-destructive'
            )}>
              {validateResult.valid ? `✓ Gültig – ${validateResult.message ?? 'Verbindung OK'}` : `✗ ${validateResult.message}`}
            </div>
          )}

          <div className="flex gap-2 flex-wrap pt-1">
            {status === 'not_connected' ? (
              <Button size="sm" onClick={getAuthUrl}>
                <Music2 className="h-4 w-4" />
                Mit Spotify verbinden
              </Button>
            ) : (
              <>
                <Button size="sm" variant="outline" onClick={validate} disabled={validating}>
                  <RefreshCw className={cn('h-4 w-4', validating && 'animate-spin')} />
                  {validating ? 'Prüfe…' : 'Verbindung prüfen'}
                </Button>
                <Button size="sm" variant="outline" onClick={getAuthUrl}>
                  <ExternalLink className="h-4 w-4" />
                  Neu verbinden
                </Button>
                <Button
                  size="sm"
                  variant="destructive"
                  onClick={() => setShowDisconnectDialog(true)}
                  disabled={disconnecting}
                >
                  <XCircle className="h-4 w-4" />
                  Trennen
                </Button>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      <AlertDialog open={showDisconnectDialog} onOpenChange={setShowDisconnectDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Spotify-Verbindung trennen?</AlertDialogTitle>
            <AlertDialogDescription>
              Das gespeicherte Spotify-Token für <strong>{profile.name}</strong> wird gelöscht. Musik-Funktionen stehen nicht mehr zur Verfügung bis die Verbindung neu autorisiert wird.
              <br /><br />
              Nach dem Trennen: Klicke auf „Mit Spotify verbinden" um alle Berechtigungen neu zu erteilen.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              onClick={disconnect}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {disconnecting ? 'Trennt…' : 'Verbindung trennen'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// ─── Tab: Lautsprecher ───────────────────────────────────────────────────────

function TabLautsprecher({ profile }: { profile: FamilyProfileDto }) {
  const [devices, setDevices] = useState<{ id: string; name: string; type: string; is_active: boolean }[]>([]);
  const [loading, setLoading] = useState(false);

  const loadDevices = async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: typeof devices }>(`/profiles/${profile.id}/spotify/devices`);
      setDevices(res.items);
    } catch {
      setDevices([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-5 max-w-lg">
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">Zugeordneter Standardlautsprecher</CardTitle>
          <CardDescription>Wird bei RFID-Scan automatisch genutzt.</CardDescription>
        </CardHeader>
        <CardContent>
          {profile.default_device_name ? (
            <div className="flex items-center gap-3">
              <Speaker className="h-5 w-5 text-muted-foreground shrink-0" />
              <div>
                <p className="text-sm font-medium">{profile.default_device_name}</p>
                <p className="text-xs text-muted-foreground font-mono">{profile.default_spotify_device_id}</p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">Kein Standardlautsprecher konfiguriert.</p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">Verfügbare Spotify-Geräte</CardTitle>
          <CardDescription>Aktuell über Spotify-API erkannte Geräte.</CardDescription>
        </CardHeader>
        <CardContent>
          <Button size="sm" variant="outline" onClick={loadDevices} disabled={loading}>
            <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
            {loading ? 'Lädt…' : 'Geräte abrufen'}
          </Button>
          {devices.length > 0 && (
            <div className="mt-3 space-y-2">
              {devices.map((d) => (
                <div key={d.id} className="flex items-center gap-3 rounded-md border px-3 py-2">
                  <Speaker className="h-4 w-4 text-muted-foreground shrink-0" />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{d.name}</p>
                    <p className="text-xs text-muted-foreground">{d.type}</p>
                  </div>
                  {d.is_active && <Badge variant="success" className="text-xs">Aktiv</Badge>}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

// ─── Tab: RFID-Karten ────────────────────────────────────────────────────────

function TabRfid({ profileId }: { profileId: string }) {
  const { data, isLoading } = useRfidCards(profileId);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{data?.items.length ?? 0} Karten zugeordnet</p>
      </div>
      {isLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : !data?.items.length ? (
        <Card>
          <CardContent className="py-8 text-center text-sm text-muted-foreground">
            Noch keine RFID-Karten zugeordnet.
          </CardContent>
        </Card>
      ) : (
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead>Karten-UID</TableHead>
              <TableHead>Label</TableHead>
              <TableHead>Erstellt</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.items.map((card) => (
              <TableRow key={card.id} className="cursor-default hover:bg-muted/30">
                <TableCell>
                  <span className="font-mono text-xs">{card.card_uid}</span>
                </TableCell>
                <TableCell>{card.label ?? <span className="text-muted-foreground">—</span>}</TableCell>
                <TableCell className="text-muted-foreground text-sm">{formatDate((card as any).created_at)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  );
}

// ─── Tab: Hörzeit ────────────────────────────────────────────────────────────

function TabHoerzeit() {
  return (
    <Card>
      <CardContent className="py-8 text-center">
        <Clock className="h-8 w-8 text-muted-foreground mx-auto mb-3" />
        <p className="text-sm text-muted-foreground">Hörzeit-Regeln sind in dieser MVP-Version noch nicht konfigurierbar.</p>
        <p className="text-xs text-muted-foreground mt-1">Geplant für eine zukünftige Version.</p>
      </CardContent>
    </Card>
  );
}

// ─── Tab: Aktivität ──────────────────────────────────────────────────────────

function SeverityIcon({ severity }: { severity: ActivitySeverity }) {
  if (severity === 'error' || severity === 'critical')
    return <XCircle className="h-4 w-4 text-destructive shrink-0" />;
  if (severity === 'warning')
    return <AlertCircle className="h-4 w-4 text-warning shrink-0" />;
  return <CheckCircle2 className="h-4 w-4 text-muted-foreground shrink-0" />;
}

function TabAktivitaet({ profileId }: { profileId: string }) {
  const { data, isLoading } = useActivity({ profile_id: profileId, limit: 50 });

  return (
    <div className="space-y-1">
      {isLoading ? (
        <div className="space-y-2">
          {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
        </div>
      ) : !data?.items.length ? (
        <Card>
          <CardContent className="py-8 text-center text-sm text-muted-foreground">
            Noch keine Aktivitäten aufgezeichnet.
          </CardContent>
        </Card>
      ) : (
        <ScrollArea className="h-[400px]">
          <div className="space-y-1 pr-4">
            {data.items.map((entry) => (
              <div key={entry.id} className="flex items-start gap-3 rounded-md px-3 py-2.5 hover:bg-muted/40">
                <SeverityIcon severity={entry.severity} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm leading-snug">{entry.message}</p>
                  <p className="text-xs text-muted-foreground mt-0.5">{formatDateRelative(entry.occurred_at)}</p>
                </div>
              </div>
            ))}
          </div>
        </ScrollArea>
      )}
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export function ProfileDetailPage() {
  const { profileId } = useParams<{ profileId: string }>();
  const navigate = useNavigate();
  const { data: profile, isLoading, error } = useProfile(profileId!);
  const deleteProfile = useDeleteProfile();
  const [deleteOpen, setDeleteOpen] = useState(false);

  const handleDelete = async () => {
    await deleteProfile.mutateAsync(profileId!);
    navigate('/profiles');
  };

  if (isLoading) {
    return (
      <div className="p-8 space-y-4">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-4 w-48" />
        <Skeleton className="h-[400px] w-full" />
      </div>
    );
  }

  if (error || !profile) {
    return (
      <div className="flex flex-col items-center justify-center h-full">
        <p className="text-sm text-destructive">Teilnehmer nicht gefunden.</p>
        <Button variant="outline" size="sm" className="mt-3" onClick={() => navigate('/profiles')}>
          Zurück zur Liste
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center gap-4 border-b px-6 py-4 shrink-0">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate('/profiles')}>
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h1 className="text-lg font-semibold tracking-tight truncate">{profile.name}</h1>
            <Badge variant={profile.status === 'active' ? 'success' : 'muted'}>
              {profile.status === 'active' ? 'Aktiv' : 'Inaktiv'}
            </Badge>
          </div>
          <p className="text-sm text-muted-foreground">Teilnehmer-Datensatz</p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate(`/profiles/${profileId}/setup`)}
          >
            <Wand2 className="h-4 w-4" />
            Setup
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="text-destructive border-destructive/30 hover:bg-destructive/10"
            onClick={() => setDeleteOpen(true)}
          >
            <Trash2 className="h-4 w-4" />
            Löschen
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex-1 overflow-hidden flex flex-col">
        <Tabs defaultValue="allgemein" className="flex flex-col h-full">
          <div className="px-6 border-b shrink-0">
            <TabsList className="gap-0">
              <TabsTrigger value="allgemein">
                <User className="h-3.5 w-3.5 mr-1.5" />
                Allgemein
              </TabsTrigger>
              <TabsTrigger value="spotify">
                <Music2 className="h-3.5 w-3.5 mr-1.5" />
                Spotify
              </TabsTrigger>
              <TabsTrigger value="lautsprecher">
                <Speaker className="h-3.5 w-3.5 mr-1.5" />
                Lautsprecher
              </TabsTrigger>
              <TabsTrigger value="rfid">
                <CreditCard className="h-3.5 w-3.5 mr-1.5" />
                RFID-Karten
              </TabsTrigger>
              <TabsTrigger value="hoerzeit">
                <Clock className="h-3.5 w-3.5 mr-1.5" />
                Hörzeit
              </TabsTrigger>
              <TabsTrigger value="aktivitaet">
                <Activity className="h-3.5 w-3.5 mr-1.5" />
                Aktivität
              </TabsTrigger>
              <TabsTrigger value="musik">
                <Headphones className="h-3.5 w-3.5 mr-1.5" />
                Musik
              </TabsTrigger>
            </TabsList>
          </div>

          <ScrollArea className="flex-1">
            <div className="p-6">
              <TabsContent value="allgemein">
                <TabAllgemein profile={profile} />
              </TabsContent>
              <TabsContent value="spotify">
                <TabSpotify profile={profile} />
              </TabsContent>
              <TabsContent value="lautsprecher">
                <TabLautsprecher profile={profile} />
              </TabsContent>
              <TabsContent value="rfid">
                <TabRfid profileId={profile.id} />
              </TabsContent>
              <TabsContent value="hoerzeit">
                <TabHoerzeit />
              </TabsContent>
              <TabsContent value="aktivitaet">
                <TabAktivitaet profileId={profile.id} />
              </TabsContent>
              <TabsContent value="musik">
                <MusicTab profile={profile} />
              </TabsContent>
            </div>
          </ScrollArea>
        </Tabs>
      </div>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Teilnehmer löschen?</AlertDialogTitle>
            <AlertDialogDescription>
              <strong>{profile.name}</strong> wird dauerhaft gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={handleDelete}
            >
              Endgültig löschen
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
