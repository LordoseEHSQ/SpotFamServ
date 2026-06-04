import { useState } from 'react';
import {
  Cpu, RefreshCw, Zap, CheckCircle2, XCircle, Loader2, AlertTriangle, Usb, ChevronDown, ChevronUp,
} from 'lucide-react';
import {
  useDetectedDevices,
  useArtifacts,
  useCreateFlashJob,
  useFlashJob,
} from '@/hooks/useProvisioning';
import type { DetectedDevice, FlashArtifact } from '@/api/endpoints/provisioning';
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

// ─── Hilfsfunktionen ──────────────────────────────────────────────────────────

function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${bytes} B`;
}

// ─── Job-Fortschrittsanzeige ─────────────────────────────────────────────────

function JobProgress({ jobId }: { jobId: string }) {
  const { data: job, isLoading } = useFlashJob(jobId);
  const [detailOpen, setDetailOpen] = useState(false);

  if (isLoading || !job) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="h-3.5 w-3.5 animate-spin" />
        Lade Job-Status…
      </div>
    );
  }

  const statusConfig: Record<string, { label: string; icon: React.ReactNode; variant: 'muted' | 'warning' | 'info' | 'success' | 'destructive' }> = {
    pending: { label: 'Wartend', icon: <Loader2 className="h-3 w-3 animate-spin" />, variant: 'muted' },
    running: { label: `Flasht … ${job.progress}%`, icon: <Loader2 className="h-3 w-3 animate-spin" />, variant: 'info' },
    success: { label: 'Fertig', icon: <CheckCircle2 className="h-3 w-3" />, variant: 'success' },
    failed:  { label: 'Fehlgeschlagen', icon: <XCircle className="h-3 w-3" />, variant: 'destructive' },
  };

  const cfg = statusConfig[job.status] ?? statusConfig.pending;

  return (
    <div className="space-y-1.5">
      <div className="flex items-center gap-2">
        <Badge variant={cfg.variant} className="gap-1">
          {cfg.icon}
          {cfg.label}
        </Badge>
        {job.message && (
          <button
            type="button"
            onClick={() => setDetailOpen((v) => !v)}
            className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-foreground"
          >
            {detailOpen ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
            Details
          </button>
        )}
      </div>
      {(job.status === 'running' || job.status === 'pending') && (
        <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
          <div
            className="h-full rounded-full bg-info transition-all duration-500"
            style={{ width: `${Math.max(job.progress, job.status === 'running' ? 3 : 0)}%` }}
          />
        </div>
      )}
      {detailOpen && job.message && (
        <p className="rounded border bg-muted/30 px-2 py-1 font-mono text-xs text-muted-foreground">
          {job.message}
        </p>
      )}
    </div>
  );
}

// ─── Flash-Dialog ─────────────────────────────────────────────────────────────

function FlashDialog({
  device,
  onClose,
}: {
  device: DetectedDevice | null;
  onClose: () => void;
}) {
  const { data: artifactsData, isLoading: artifactsLoading } = useArtifacts();
  const createJob = useCreateFlashJob();
  const [selectedArtifactId, setSelectedArtifactId] = useState<string>('');
  const [activeJobId, setActiveJobId] = useState<string | null>(null);

  if (!device) return null;

  const artifacts = artifactsData?.items ?? [];
  const selectedArtifact: FlashArtifact | undefined = artifacts.find((a) => a.id === selectedArtifactId);
  const chipMismatch =
    selectedArtifact !== undefined &&
    selectedArtifact.expectedChip.toLowerCase() !== device.chip.toLowerCase();
  const hasActiveJob =
    device.latestJob?.status === 'pending' || device.latestJob?.status === 'running';

  const handleClose = () => {
    setSelectedArtifactId('');
    setActiveJobId(null);
    createJob.reset();
    onClose();
  };

  const handleFlash = () => {
    if (!selectedArtifact) return;
    createJob.mutate(
      { deviceId: device.id, artifactId: selectedArtifact.id },
      {
        onSuccess: (job) => {
          setActiveJobId(job.jobId);
        },
      },
    );
  };

  const is409 =
    createJob.isError &&
    (createJob.error as (Error & { status?: number })).status === 409;

  return (
    <Dialog open={!!device} onOpenChange={(v) => !v && handleClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Zap className="h-4 w-4 text-warning" />
            Firmware flashen
          </DialogTitle>
          <DialogDescription>
            Gerät: <span className="font-mono">{device.port}</span> · {device.chipDescription} · MAC{' '}
            <span className="font-mono">{device.mac}</span>
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Artefakt-Auswahl */}
          {!activeJobId && (
            <div className="space-y-1.5">
              <Label>Firmware-Artefakt</Label>
              {artifactsLoading ? (
                <Skeleton className="h-9 w-full" />
              ) : artifacts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  Keine Artefakte vorhanden. Bitte zuerst eine Firmware hochladen.
                </p>
              ) : (
                <Select value={selectedArtifactId} onValueChange={setSelectedArtifactId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Artefakt wählen…" />
                  </SelectTrigger>
                  <SelectContent>
                    {artifacts.map((a) => (
                      <SelectItem key={a.id} value={a.id}>
                        {a.board} · {a.channel} · v{a.version} · {a.expectedChip} · {formatBytes(a.sizeBytes)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>
          )}

          {/* Chip-Mismatch-Warnung */}
          {chipMismatch && !activeJobId && (
            <div className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-sm">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
              <span>
                <strong>Chip-Mismatch:</strong> Das Artefakt erwartet{' '}
                <span className="font-mono">{selectedArtifact?.expectedChip}</span>, das Gerät
                meldet <span className="font-mono">{device.chip}</span>. Flashen könnte das Gerät
                beschädigen.
              </span>
            </div>
          )}

          {/* Bereits aktiver Job */}
          {hasActiveJob && !activeJobId && (
            <div className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-sm">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
              <span>
                Es läuft bereits ein Flash-Vorgang für dieses Gerät (Job{' '}
                <span className="font-mono">{device.latestJob?.jobId.slice(0, 8)}…</span>
                ).
              </span>
            </div>
          )}

          {/* 409-Fehler */}
          {is409 && !activeJobId && (
            <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
              Es läuft bereits ein Flash-Vorgang für dieses Gerät. Bitte warte, bis der aktuelle
              Job abgeschlossen ist.
            </p>
          )}

          {/* Sonstiger API-Fehler */}
          {createJob.isError && !is409 && (
            <p className="text-sm text-destructive">
              Fehler: {(createJob.error as Error).message}
            </p>
          )}

          {/* Job-Fortschritt */}
          {activeJobId && (
            <div className="space-y-2">
              <p className="text-sm font-medium">Flash-Vorgang läuft</p>
              <JobProgress jobId={activeJobId} />
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={handleClose}>
            {activeJobId ? 'Schließen' : 'Abbrechen'}
          </Button>
          {!activeJobId && (
            <Button
              onClick={handleFlash}
              disabled={
                !selectedArtifactId ||
                chipMismatch ||
                hasActiveJob ||
                createJob.isPending ||
                artifactsLoading
              }
              className={cn(chipMismatch && 'opacity-50')}
            >
              {createJob.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Starte…
                </>
              ) : (
                <>
                  <Zap className="h-4 w-4" />
                  Jetzt flashen
                </>
              )}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Gerätestatus-Badge ───────────────────────────────────────────────────────

function DeviceStatusBadge({ device }: { device: DetectedDevice }) {
  if (device.status === 'flashing') {
    return (
      <Badge variant="info" className="gap-1">
        <Loader2 className="h-3 w-3 animate-spin" />
        Wird geflasht
      </Badge>
    );
  }

  if (device.latestJob) {
    const jobStatus = device.latestJob.status;
    if (jobStatus === 'pending' || jobStatus === 'running') {
      return (
        <Badge variant="info" className="gap-1">
          <Loader2 className="h-3 w-3 animate-spin" />
          Job läuft ({device.latestJob.progress}%)
        </Badge>
      );
    }
    if (jobStatus === 'success') {
      return (
        <Badge variant="success" className="gap-1">
          <CheckCircle2 className="h-3 w-3" />
          Geflasht
        </Badge>
      );
    }
    if (jobStatus === 'failed') {
      return (
        <Badge variant="destructive" className="gap-1">
          <XCircle className="h-3 w-3" />
          Fehlgeschlagen
        </Badge>
      );
    }
  }

  return <Badge variant="muted">Bereit</Badge>;
}

// ─── Hauptseite ───────────────────────────────────────────────────────────────

export function ProvisioningPage() {
  const { data, isLoading, error, refetch, isFetching } = useDetectedDevices();
  const [flashTarget, setFlashTarget] = useState<DetectedDevice | null>(null);

  const devices = data?.items ?? [];

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">Reader-Station</h1>
          <p className="text-sm text-muted-foreground">
            {devices.length === 0
              ? 'Kein Gerät erkannt'
              : `${devices.length} Gerät${devices.length !== 1 ? 'e' : ''} erkannt`}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => void refetch()}
          disabled={isFetching}
        >
          <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
        </Button>
      </div>

      {/* Hinweisband */}
      <div className="px-6 py-4 border-b shrink-0">
        <div className="rounded-md border bg-muted/20 px-4 py-3 text-sm text-muted-foreground flex gap-2">
          <Usb className="h-4 w-4 mt-0.5 shrink-0" />
          <span>
            Verbinde einen ESP32 per USB mit dem Pi. Er erscheint hier automatisch, sobald der
            Flash-Agent ihn erkennt. Wähle dann ein Firmware-Artefakt und starte den Flash-Vorgang.
            Der Fortschritt wird live angezeigt.
          </span>
        </div>
      </div>

      {/* Gerätetabelle */}
      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive">Fehler beim Laden der Gerätliste.</p>
            <Button
              variant="outline"
              size="sm"
              className="mt-3"
              onClick={() => void refetch()}
            >
              Erneut versuchen
            </Button>
          </div>
        ) : devices.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-center gap-3">
            <Cpu className="h-8 w-8 text-muted-foreground" />
            <div>
              <p className="text-sm font-medium">Kein Gerät erkannt</p>
              <p className="text-sm text-muted-foreground mt-1">
                ESP per USB an den Pi stecken – der Flash-Agent erkennt ihn automatisch.
              </p>
            </div>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Port</TableHead>
                <TableHead>Chip / Beschreibung</TableHead>
                <TableHead>MAC-Adresse</TableHead>
                <TableHead>Flash-Größe</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-32" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {devices.map((device) => {
                const hasActiveJob =
                  device.latestJob?.status === 'pending' ||
                  device.latestJob?.status === 'running';

                return (
                  <TableRow key={device.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Cpu className="h-4 w-4 text-muted-foreground shrink-0" />
                        <span className="font-mono text-sm">{device.port}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div>
                        <span className="font-mono text-sm">{device.chip}</span>
                        {device.chipDescription && device.chipDescription !== device.chip && (
                          <span className="ml-1.5 text-xs text-muted-foreground">
                            ({device.chipDescription})
                          </span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="font-mono text-sm">{device.mac}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{formatBytes(device.flashSize)}</span>
                    </TableCell>
                    <TableCell>
                      <DeviceStatusBadge device={device} />
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setFlashTarget(device)}
                        disabled={hasActiveJob}
                        title={
                          hasActiveJob
                            ? 'Warte auf Abschluss des laufenden Flash-Vorgangs'
                            : 'Firmware flashen'
                        }
                      >
                        <Zap className="h-3.5 w-3.5" />
                        Flashen
                      </Button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </div>

      <FlashDialog device={flashTarget} onClose={() => setFlashTarget(null)} />
    </div>
  );
}
