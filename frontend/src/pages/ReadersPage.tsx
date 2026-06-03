import { useEffect, useMemo, useState } from 'react';
import { Radio, RefreshCw, Speaker, MapPin, Unlink, Info, Plus, Copy, KeyRound } from 'lucide-react';
import {
  useReaders,
  useSetReaderBox,
  useClearReaderBox,
  useCreateReaderClaim,
  useReaderClaimStatus,
} from '@/hooks/useReaders';
import { useDevices } from '@/hooks/useDevices';
import type { ReaderClaimResponse, ReaderDto } from '@/api/endpoints/readers';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// ─── Box-Zuweisungs-Dialog ──────────────────────────────────────────────────

function AssignBoxDialog({ reader, onClose }: { reader: ReaderDto | null; onClose: () => void }) {
  const { data: devicesData, isLoading } = useDevices();
  const setBox = useSetReaderBox();
  const [deviceId, setDeviceId] = useState<string>(reader?.default_spotify_device_id ?? '');

  if (!reader) return null;

  const devices = devicesData?.items ?? [];

  const handleSave = () => {
    const device = devices.find((d) => d.spotify_device_id === deviceId);
    if (!device) return;
    setBox.mutate(
      { readerId: reader.reader_id, deviceId: device.spotify_device_id, deviceName: device.spotify_device_name },
      { onSuccess: onClose },
    );
  };

  return (
    <Dialog open={!!reader} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Box zuweisen</DialogTitle>
          <DialogDescription>
            Leser <span className="font-mono">{reader.reader_id}</span> – wähle den Lautsprecher,
            der bei einem Scan an diesem Leser spielen soll.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Lautsprecher (Spotify-Connect-Gerät)</Label>
            {isLoading ? (
              <Skeleton className="h-9 w-full" />
            ) : devices.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Keine Geräte bekannt. Führe zuerst unter „Lautsprecher &amp; Geräte" eine
                Geräteerkennung durch.
              </p>
            ) : (
              <Select value={deviceId} onValueChange={setDeviceId}>
                <SelectTrigger>
                  <SelectValue placeholder="Lautsprecher wählen…" />
                </SelectTrigger>
                <SelectContent>
                  {devices.map((d) => (
                    <SelectItem key={d.id} value={d.spotify_device_id}>
                      {d.spotify_device_name}{!d.is_available ? ' (offline)' : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>
          {setBox.isError && (
            <p className="text-sm text-destructive">{(setBox.error as Error).message}</p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Abbrechen</Button>
          <Button onClick={handleSave} disabled={!deviceId || setBox.isPending}>
            {setBox.isPending ? 'Speichert…' : 'Box zuweisen'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Reader-Provisioning-Dialog ──────────────────────────────────────────────

function AddReaderDialog({
  open,
  onClose,
  onClaimed,
}: {
  open: boolean;
  onClose: () => void;
  onClaimed: () => void;
}) {
  const createClaim = useCreateReaderClaim();
  const [readerName, setReaderName] = useState('');
  const [claim, setClaim] = useState<ReaderClaimResponse | null>(null);
  const { data: status } = useReaderClaimStatus(claim?.claim_code ?? null);

  const payload = useMemo(() => {
    if (!claim) return '';
    return JSON.stringify({
      backend_url: claim.backend_url,
      claim_code: claim.claim_code,
    }, null, 2);
  }, [claim]);

  useEffect(() => {
    if (status?.status === 'claimed') {
      onClaimed();
    }
  }, [status?.status, onClaimed]);

  const handleCreate = () => {
    createClaim.mutate(
      { reader_name: readerName.trim() || null, fw_channel: 'stable' },
      { onSuccess: setClaim },
    );
  };

  const handleClose = () => {
    setClaim(null);
    setReaderName('');
    createClaim.reset();
    onClose();
  };

  const copyPayload = async () => {
    if (payload) {
      await navigator.clipboard.writeText(payload);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !v && handleClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Reader hinzufügen</DialogTitle>
          <DialogDescription>
            Erzeuge einen kurzlebigen Claim-Code für das ESP32-Captive-Portal. Der API-Key
            wird erst beim Einlösen erzeugt und hier nicht dauerhaft angezeigt.
          </DialogDescription>
        </DialogHeader>

        {!claim ? (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Reader-Name (optional)</Label>
              <Input
                value={readerName}
                onChange={(e) => setReaderName(e.target.value)}
                placeholder="z. B. Küche"
              />
            </div>
            {createClaim.isError && (
              <p className="text-sm text-destructive">{(createClaim.error as Error).message}</p>
            )}
            <DialogFooter>
              <Button variant="outline" onClick={handleClose}>Abbrechen</Button>
              <Button onClick={handleCreate} disabled={createClaim.isPending}>
                {createClaim.isPending ? 'Erzeuge…' : 'Claim-Code erzeugen'}
              </Button>
            </DialogFooter>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="rounded-md border bg-muted/20 p-4">
              <p className="text-xs uppercase text-muted-foreground mb-1">Claim-Code</p>
              <div className="font-mono text-3xl tracking-[0.25em]">{claim.claim_code}</div>
              <p className="mt-2 text-xs text-muted-foreground">
                Gültig bis {new Date(claim.expires_at).toLocaleTimeString()} · Kanal {claim.fw_channel}
              </p>
            </div>

            <div className="space-y-1.5">
              <Label>Payload fürs Captive Portal</Label>
              <pre className="rounded-md border bg-muted/30 p-3 text-xs overflow-auto">{payload}</pre>
              <Button variant="outline" size="sm" onClick={copyPayload}>
                <Copy className="h-3.5 w-3.5" />
                Payload kopieren
              </Button>
            </div>

            <div className="rounded-md border px-3 py-2 text-sm flex items-center gap-2">
              <KeyRound className="h-4 w-4 text-muted-foreground" />
              {status?.status === 'claimed' ? (
                <span>Reader wurde provisioniert: <span className="font-mono">{status.reader_id}</span></span>
              ) : status?.status === 'expired' ? (
                <span className="text-destructive">Claim ist abgelaufen. Erzeuge einen neuen Code.</span>
              ) : (
                <span className="text-muted-foreground">Warte auf Einlösung durch den ESP…</span>
              )}
            </div>

            <DialogFooter>
              <Button onClick={handleClose}>
                {status?.status === 'claimed' ? 'Schließen' : 'Abbrechen'}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

// ─── Hauptseite ─────────────────────────────────────────────────────────────

export function ReadersPage() {
  const { data, isLoading, error, refetch, isFetching } = useReaders();
  const clearBox = useClearReaderBox();
  const [assignTarget, setAssignTarget] = useState<ReaderDto | null>(null);
  const [addDialogOpen, setAddDialogOpen] = useState(false);

  const readers = data?.items ?? [];

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">RFID-Leser</h1>
          <p className="text-sm text-muted-foreground">{readers.length} bekannte Leser</p>
        </div>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={() => setAddDialogOpen(true)}>
            <Plus className="h-4 w-4" />
            Reader hinzufügen
          </Button>
          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
          </Button>
        </div>
      </div>

      <div className="px-6 py-4 border-b shrink-0">
        <div className="rounded-md border bg-muted/20 px-4 py-3 text-sm text-muted-foreground flex gap-2">
          <Info className="h-4 w-4 mt-0.5 shrink-0 text-info" />
          <span>
            ESP-Reader werden über „Reader hinzufügen" mit einem kurzlebigen Claim-Code provisioniert.
            Pi-/Bestandsleser können weiterhin beim ersten Scan erscheinen. Weist du einem Leser eine
            Box zu, spielt jeder Scan an diesem Leser auf <strong>dieser</strong> Box. Ohne Zuweisung
            gilt der Standard-Lautsprecher des Karten-Profils.
          </span>
        </div>
      </div>

      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive">Fehler beim Laden der Leser.</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>Erneut versuchen</Button>
          </div>
        ) : readers.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <Radio className="h-8 w-8 text-muted-foreground mb-3" />
            <p className="text-sm text-muted-foreground">
              Noch keine Leser. Ein Leser erscheint hier nach seinem ersten Scan.
            </p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Leser-ID</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Zugewiesene Box</TableHead>
                <TableHead className="w-48" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {readers.map((reader) => (
                <TableRow key={reader.reader_id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Radio className="h-4 w-4 text-muted-foreground shrink-0" />
                      <span className="font-mono text-sm">{reader.reader_id}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-muted-foreground">{reader.name ?? '—'}</span>
                  </TableCell>
                  <TableCell>
                    {reader.has_api_key ? (
                      <Badge variant="success"><KeyRound className="h-3 w-3 mr-1" />Provisioniert</Badge>
                    ) : (
                      <Badge variant="muted">Legacy/Fallback</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    {reader.default_device_name ? (
                      <Badge variant="info"><Speaker className="h-3 w-3 mr-1" />{reader.default_device_name}</Badge>
                    ) : (
                      <Badge variant="muted">Profil-Standard</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-1.5">
                      <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => setAssignTarget(reader)}>
                        <MapPin className="h-3.5 w-3.5" />
                        Box zuweisen
                      </Button>
                      {reader.default_spotify_device_id && (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-7 text-xs text-destructive"
                          onClick={() => clearBox.mutate(reader.reader_id)}
                          disabled={clearBox.isPending}
                        >
                          <Unlink className="h-3.5 w-3.5" />
                          Entfernen
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      <AssignBoxDialog reader={assignTarget} onClose={() => setAssignTarget(null)} />
      <AddReaderDialog
        open={addDialogOpen}
        onClose={() => setAddDialogOpen(false)}
        onClaimed={() => void refetch()}
      />
    </div>
  );
}
