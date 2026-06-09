import { useState, useRef, type ChangeEvent } from 'react';
import {
  Cpu, RefreshCw, Zap, CheckCircle2, XCircle, Loader2, AlertTriangle, Usb, ChevronDown, ChevronUp,
  Upload,
} from 'lucide-react';
import {
  useDetectedDevices,
  useArtifacts,
  useCreateFlashJob,
  useFlashJob,
  useUploadArtifact,
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
import { Input } from '@/components/ui/input';
import { cn, formatDateRelative } from '@/lib/utils';
import { evaluateChipMatch } from '@/lib/chipMatch';

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

// ─── Upload-Dialog ────────────────────────────────────────────────────────────

const CHIP_OPTIONS = ['ESP32', 'ESP32-S2', 'ESP32-S3', 'ESP32-C3', 'ESP8266'];
const CHANNEL_OPTIONS = ['stable', 'beta', 'dev'];

function UploadArtifactDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const upload = useUploadArtifact();
  const fileRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [board, setBoard] = useState('esp32-wroom-32');
  const [channel, setChannel] = useState('stable');
  const [version, setVersion] = useState('');
  const [expectedChip, setExpectedChip] = useState('ESP32');

  const reset = () => {
    setFile(null);
    setBoard('spotfam_reader');
    setChannel('stable');
    setVersion('');
    setExpectedChip('ESP32');
    upload.reset();
    if (fileRef.current) fileRef.current.value = '';
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    setFile(e.target.files?.[0] ?? null);
  };

  const handleUpload = () => {
    if (!file || !version.trim()) return;
    upload.mutate(
      { file, board: board.trim(), channel, version: version.trim(), expectedChip },
      {
        onSuccess: () => {
          handleClose();
        },
      },
    );
  };

  const is400 =
    upload.isError && (upload.error as Error & { status?: number }).status === 400;
  const errorDetail = upload.isError
    ? ((upload.error as Error & { body?: { detail?: string } }).body?.detail ??
      (upload.error as Error).message)
    : null;

  return (
    <Dialog open={open} onOpenChange={(v) => !v && handleClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Upload className="h-4 w-4 text-primary" />
            Firmware hochladen
          </DialogTitle>
          <DialogDescription>
            Lade eine .bin-Datei hoch und registriere sie als Firmware-Artefakt.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Datei */}
          <div className="space-y-1.5">
            <Label htmlFor="fw-file">Firmware-Datei (.bin)</Label>
            <Input
              ref={fileRef}
              id="fw-file"
              type="file"
              accept=".bin"
              onChange={handleFileChange}
              disabled={upload.isPending}
            />
            {file && (
              <p className="text-xs text-muted-foreground">
                Ausgewählt: <span className="font-mono">{file.name}</span> ({formatBytes(file.size)})
              </p>
            )}
          </div>

          {/* Board */}
          <div className="space-y-1.5">
            <Label htmlFor="fw-board">Board</Label>
            <Input
              id="fw-board"
              value={board}
              onChange={(e) => setBoard(e.target.value)}
              placeholder="z.B. spotfam_reader"
              disabled={upload.isPending}
            />
          </div>

          {/* Channel */}
          <div className="space-y-1.5">
            <Label>Kanal</Label>
            <Select value={channel} onValueChange={setChannel} disabled={upload.isPending}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CHANNEL_OPTIONS.map((c) => (
                  <SelectItem key={c} value={c}>
                    {c}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Version */}
          <div className="space-y-1.5">
            <Label htmlFor="fw-version">Version</Label>
            <Input
              id="fw-version"
              value={version}
              onChange={(e) => setVersion(e.target.value)}
              placeholder="z.B. 1.2.3"
              disabled={upload.isPending}
            />
          </div>

          {/* Erwarteter Chip */}
          <div className="space-y-1.5">
            <Label>Erwarteter Chip</Label>
            <Select value={expectedChip} onValueChange={setExpectedChip} disabled={upload.isPending}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CHIP_OPTIONS.map((c) => (
                  <SelectItem key={c} value={c}>
                    {c}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Fehleranzeige */}
          {upload.isError && (
            <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              <XCircle className="mt-0.5 h-4 w-4 shrink-0" />
              <span>
                {is400 ? 'Ungültige Eingabe: ' : 'Upload fehlgeschlagen: '}
                {errorDetail}
              </span>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={handleClose} disabled={upload.isPending}>
            Abbrechen
          </Button>
          <Button
            onClick={handleUpload}
            disabled={!file || !version.trim() || !board.trim() || upload.isPending}
          >
            {upload.isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Hochladen…
              </>
            ) : (
              <>
                <Upload className="h-4 w-4" />
                Hochladen
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
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
  // Familien-Abgleich gegen die volle chipDescription (beratend; der Flash-Agent
  // ist das harte Gate). Nur ein echter Mismatch blockiert; 'unknown' nicht.
  const chipMismatch =
    selectedArtifact !== undefined &&
    evaluateChipMatch(selectedArtifact.expectedChip, device) === 'mismatch';
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
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Zap className="h-4 w-4 text-warning" />
            Firmware flashen
          </DialogTitle>
          <DialogDescription>
            Wähle ein Firmware-Artefakt für das ausgewählte Gerät und starte den Flash-Vorgang.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Geräte-Identität als umbruchsicheres Key-Value-Grid */}
          <dl className="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-1 rounded-md border bg-muted/20 px-3 py-2 text-sm">
            <dt className="text-muted-foreground">Port</dt>
            <dd className="min-w-0 break-words font-mono">{device.port}</dd>
            <dt className="text-muted-foreground">Chip</dt>
            <dd className="min-w-0 break-words font-mono">{device.chipDescription || device.chip}</dd>
            <dt className="text-muted-foreground">MAC</dt>
            <dd className="min-w-0 break-words font-mono">{device.mac}</dd>
          </dl>
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
                    {artifacts.map((a) => {
                      const label = `${a.board} · ${a.channel} · v${a.version} · ${a.expectedChip} · ${formatBytes(a.sizeBytes)}`;
                      return (
                        <SelectItem key={a.id} value={a.id}>
                          <span className="block max-w-[22rem] truncate" title={label}>
                            {label}
                          </span>
                        </SelectItem>
                      );
                    })}
                  </SelectContent>
                </Select>
              )}
            </div>
          )}

          {/* Chip-Mismatch-Warnung */}
          {chipMismatch && !activeJobId && (
            <div className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-sm">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
              <span className="min-w-0 break-words">
                <strong>Chip-Mismatch:</strong> Das Artefakt erwartet{' '}
                <span className="font-mono">{selectedArtifact?.expectedChip}</span>, das Gerät
                meldet <span className="font-mono">{device.chipDescription || device.chip}</span>.
                Flashen könnte das Gerät beschädigen.
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

// ─── „Aktuell erkanntes Gerät"-Panel ──────────────────────────────────────────

function CurrentDevicePanel({
  device,
  highlighted,
}: {
  device: DetectedDevice;
  highlighted: boolean;
}) {
  const fields: Array<{ label: string; value: string; mono?: boolean }> = [
    { label: 'Port', value: device.port, mono: true },
    { label: 'Chip', value: device.chipDescription || device.chip, mono: true },
    { label: 'MAC', value: device.mac, mono: true },
    { label: 'Flash-Größe', value: device.flashSize },
    { label: 'Zuletzt gesehen', value: formatDateRelative(device.lastSeenAt) },
  ];

  return (
    <div
      className={cn(
        'rounded-lg border px-4 py-3',
        highlighted ? 'border-primary/40 bg-primary/5' : 'bg-muted/20',
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Usb className="h-4 w-4 shrink-0 text-primary" />
          <span className="text-sm font-medium">Aktuell erkanntes Gerät</span>
        </div>
        <DeviceStatusBadge device={device} />
      </div>
      <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
        {fields.map((f) => (
          <div key={f.label} className="min-w-0">
            <dt className="text-xs text-muted-foreground">{f.label}</dt>
            <dd className={cn('truncate', f.mono && 'font-mono')} title={f.value}>
              {f.value}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

// ─── Hauptseite ───────────────────────────────────────────────────────────────

export function ProvisioningPage() {
  const { data, isLoading, error, refetch, isFetching } = useDetectedDevices();
  const [flashTarget, setFlashTarget] = useState<DetectedDevice | null>(null);
  const [uploadOpen, setUploadOpen] = useState(false);

  const devices = data?.items ?? [];
  // Zuletzt gesehenes Gerät zuerst (ISO-Timestamps sortieren lexikografisch korrekt).
  const primaryDevice =
    devices.length > 0
      ? [...devices].sort((a, b) => (b.lastSeenAt ?? '').localeCompare(a.lastSeenAt ?? ''))[0]
      : null;

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">Firmware-Station</h1>
          <p className="text-sm text-muted-foreground">
            ESP32 per USB flashen
            {devices.length > 0 && (
              <> – {devices.length} Gerät{devices.length !== 1 ? 'e' : ''} erkannt</>
            )}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setUploadOpen(true)}
          >
            <Upload className="h-4 w-4" />
            Firmware hochladen
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => void refetch()}
            disabled={isFetching}
          >
            <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
          </Button>
        </div>
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
          <div className="space-y-4 p-6">
            {primaryDevice && (
              <CurrentDevicePanel device={primaryDevice} highlighted={devices.length === 1} />
            )}
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
                      <span className="text-sm">{device.flashSize}</span>
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
          </div>
        )}
      </div>

      <FlashDialog device={flashTarget} onClose={() => setFlashTarget(null)} />
      <UploadArtifactDialog open={uploadOpen} onClose={() => setUploadOpen(false)} />
    </div>
  );
}
