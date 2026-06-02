import { useState } from 'react';
import { Radio, RefreshCw, Speaker, MapPin, Unlink, Info } from 'lucide-react';
import { useReaders, useSetReaderBox, useClearReaderBox } from '@/hooks/useReaders';
import { useDevices } from '@/hooks/useDevices';
import type { ReaderDto } from '@/api/endpoints/readers';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
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

// ─── Hauptseite ─────────────────────────────────────────────────────────────

export function ReadersPage() {
  const { data, isLoading, error, refetch, isFetching } = useReaders();
  const clearBox = useClearReaderBox();
  const [assignTarget, setAssignTarget] = useState<ReaderDto | null>(null);

  const readers = data?.items ?? [];

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">RFID-Leser</h1>
          <p className="text-sm text-muted-foreground">{readers.length} bekannte Leser</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
        </Button>
      </div>

      <div className="px-6 py-4 border-b shrink-0">
        <div className="rounded-md border bg-muted/20 px-4 py-3 text-sm text-muted-foreground flex gap-2">
          <Info className="h-4 w-4 mt-0.5 shrink-0 text-info" />
          <span>
            Leser registrieren sich beim ersten Scan automatisch. Weist du einem Leser eine Box zu,
            spielt jeder Scan an diesem Leser auf <strong>dieser</strong> Box (Multi-Raum). Ohne
            Zuweisung gilt der Standard-Lautsprecher des Karten-Profils.
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
    </div>
  );
}
