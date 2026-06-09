import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Radio, RefreshCw, Speaker, MapPin, Unlink, Plus, Copy, KeyRound,
  Cpu, ChevronDown, ChevronUp, CheckCircle2, Loader2,
  AlertCircle, ArrowRight, Zap, Info, Trash2, Usb,
} from 'lucide-react';
import {
  useReaders,
  useSetReaderBox,
  useClearReaderBox,
  useCreateReaderClaim,
  useReaderClaimStatus,
  useRotateReaderApiKey,
  useDeleteReader,
} from '@/hooks/useReaders';
import {
  useDetectedDevices,
  useArtifacts,
  useCreateFlashJob,
  useFlashJob,
} from '@/hooks/useProvisioning';
import { useDevices } from '@/hooks/useDevices';
import type { ReaderClaimResponse, ReaderDto, RotateApiKeyResponse } from '@/api/endpoints/readers';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
import { cn, formatDateRelative } from '@/lib/utils';
import { readerStatusBadge } from '@/lib/readerStatus';

// ─── Outcome-Labels und -Hilfen ──────────────────────────────────────────────

const OUTCOME_LABELS: Record<string, { label: string; hint?: string; variant: 'success' | 'warning' | 'destructive' | 'muted' | 'info' }> = {
  success:          { label: 'Erfolg',                      variant: 'success' },
  debounced:        { label: 'Doppelscan ignoriert',         variant: 'muted',       hint: 'Karte wurde innerhalb von 5 s erneut aufgelegt. Normal.' },
  unknown_card:     { label: 'Karte unbekannt',              variant: 'warning',     hint: 'Diese Karte ist keinem Profil zugeordnet. Karte in der Karten-Verwaltung hinzufügen.' },
  no_binding:       { label: 'Keine Playlist',               variant: 'warning',     hint: 'Die Karte ist einem Profil zugeordnet, hat aber keine Playlist-Bindung.' },
  no_device:        { label: 'Kein Spotify-Gerät',           variant: 'destructive', hint: 'Kein Wiedergabegerät ausgewählt oder Spotify läuft nicht. Unter „Lautsprecher" ein Gerät starten und diesem Reader zuweisen.' },
  token_invalid:    { label: 'Spotify nicht verbunden',      variant: 'destructive', hint: 'Spotify-Token abgelaufen oder ungültig. Im Setup-Wizard neu verbinden.' },
  playback_failed:  { label: 'Wiedergabe fehlgeschlagen',    variant: 'destructive', hint: 'Spotify hat die Wiedergabe abgelehnt. Prüfe ob Spotify Premium aktiv ist.' },
  invalid_request:  { label: 'Ungültige Anfrage',            variant: 'muted' },
  unknown_reader:   { label: 'Unbekannter Leser',            variant: 'muted' },
  no_session:       { label: 'Keine aktive Session',         variant: 'muted' },
};

function outcomeInfo(outcome: string) {
  return OUTCOME_LABELS[outcome] ?? { label: outcome, variant: 'muted' as const };
}

// ─── Box-Zuweisungs-Dialog ───────────────────────────────────────────────────

function AssignBoxDialog({ reader, onClose }: { reader: ReaderDto | null; onClose: () => void }) {
  const { data: devicesData, isLoading } = useDevices();
  const setBox = useSetReaderBox();
  const [deviceId, setDeviceId] = useState<string>(reader?.default_spotify_device_id ?? '');

  if (!reader) return null;
  const devices = devicesData?.items ?? [];

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
                <SelectTrigger><SelectValue placeholder="Lautsprecher wählen…" /></SelectTrigger>
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
          <Button onClick={() => {
            const device = devices.find((d) => d.spotify_device_id === deviceId);
            if (!device) return;
            setBox.mutate({ readerId: reader.reader_id, deviceId: device.spotify_device_id, deviceName: device.spotify_device_name }, { onSuccess: onClose });
          }} disabled={!deviceId || setBox.isPending}>
            {setBox.isPending ? 'Speichert…' : 'Box zuweisen'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Onboarding-Wizard ───────────────────────────────────────────────────────

type WizardStep = 'claim' | 'flash' | 'portal' | 'waiting' | 'done';

function AddReaderWizard({
  open,
  onClose,
  onClaimed,
}: {
  open: boolean;
  onClose: () => void;
  onClaimed: () => void;
}) {
  const createClaim = useCreateReaderClaim();
  const [step, setStep] = useState<WizardStep>('claim');
  const [readerName, setReaderName] = useState('');
  const [fwChannel, setFwChannel] = useState('stable');
  const [claim, setClaim] = useState<ReaderClaimResponse | null>(null);
  const { data: status } = useReaderClaimStatus(claim?.claim_code ?? null);

  // USB-Flash (optional)
  const { data: devicesData } = useDetectedDevices();
  const { data: artifactsData } = useArtifacts();
  const createJob = useCreateFlashJob();
  const [selectedDeviceId, setSelectedDeviceId] = useState('');
  const [selectedArtifactId, setSelectedArtifactId] = useState('');
  const [activeJobId, setActiveJobId] = useState<string | null>(null);
  const { data: job } = useFlashJob(activeJobId);

  const devices = devicesData?.items ?? [];
  const artifacts = (artifactsData?.items ?? []).filter((a) => a.board === 'esp32-wroom-32');

  useEffect(() => {
    if (status?.status === 'claimed') {
      setStep('done');
      onClaimed();
    }
  }, [status?.status, onClaimed]);

  const payload = useMemo(() => {
    if (!claim) return '';
    return JSON.stringify({ backend_url: claim.backend_url, claim_code: claim.claim_code }, null, 2);
  }, [claim]);

  const handleReset = () => {
    setStep('claim');
    setReaderName('');
    setFwChannel('stable');
    setClaim(null);
    createClaim.reset();
    setSelectedDeviceId('');
    setSelectedArtifactId('');
    setActiveJobId(null);
  };

  const handleClose = () => {
    handleReset();
    onClose();
  };

  const handleCreateClaim = () => {
    createClaim.mutate({ reader_name: readerName.trim() || null, fw_channel: fwChannel }, {
      onSuccess: (c) => { setClaim(c); setStep('flash'); },
    });
  };

  const handleFlash = () => {
    if (!selectedDeviceId || !selectedArtifactId) return;
    createJob.mutate({ deviceId: selectedDeviceId, artifactId: selectedArtifactId }, {
      onSuccess: (j) => { setActiveJobId(j.jobId); },
    });
  };

  const flashDone = job?.status === 'success';
  const flashFailed = job?.status === 'failed';

  return (
    <Dialog open={open} onOpenChange={(v) => !v && handleClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Neuen Reader einrichten</DialogTitle>
          <DialogDescription>
            {step === 'claim' && 'Schritt 1 von 4: Claim-Code und Reader-Name'}
            {step === 'flash' && 'Schritt 2 von 4: Firmware flashen (optional)'}
            {step === 'portal' && 'Schritt 3 von 4: Captive Portal befüllen'}
            {step === 'waiting' && 'Schritt 4 von 4: Warte auf ESP…'}
            {step === 'done' && 'Reader erfolgreich provisioniert!'}
          </DialogDescription>
        </DialogHeader>

        {/* ── Schritt 1: Claim ───────────────────────────── */}
        {step === 'claim' && (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Reader-Name (optional)</Label>
              <Input value={readerName} onChange={(e) => setReaderName(e.target.value)} placeholder="z. B. Küche" />
            </div>
            <div className="space-y-1.5">
              <Label>Firmware-Kanal</Label>
              <Select value={fwChannel} onValueChange={setFwChannel}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="stable">stable</SelectItem>
                  <SelectItem value="beta">beta</SelectItem>
                </SelectContent>
              </Select>
            </div>
            {createClaim.isError && (
              <p className="text-sm text-destructive">{(createClaim.error as Error).message}</p>
            )}
            <DialogFooter>
              <Button variant="outline" onClick={handleClose}>Abbrechen</Button>
              <Button onClick={handleCreateClaim} disabled={createClaim.isPending}>
                {createClaim.isPending ? <><Loader2 className="h-4 w-4 animate-spin" /> Erzeuge…</> : <>Weiter <ArrowRight className="h-4 w-4" /></>}
              </Button>
            </DialogFooter>
          </div>
        )}

        {/* ── Schritt 2: Optional Flash ──────────────────── */}
        {step === 'flash' && (
          <div className="space-y-4">
            <div className="rounded-md border bg-muted/20 p-3">
              <p className="text-xs text-muted-foreground mb-1">Claim-Code</p>
              <div className="font-mono text-2xl tracking-[0.25em]">{claim?.claim_code}</div>
              <p className="text-xs text-muted-foreground mt-1">Kanal: {claim?.fw_channel}</p>
            </div>

            <div className="rounded-md border bg-blue-50 dark:bg-blue-950/20 px-3 py-2 text-sm flex gap-2">
              <Info className="h-4 w-4 mt-0.5 shrink-0 text-blue-600" />
              <span>Wenn der ESP bereits geflasht ist, diesen Schritt überspringen und direkt zum Portal gehen.</span>
            </div>

            {devices.length === 0 ? (
              <p className="text-sm text-muted-foreground">Kein ESP via USB erkannt. Verbinde den ESP per USB mit dem Pi oder überspringe diesen Schritt.</p>
            ) : (
              <div className="space-y-3">
                <div className="space-y-1.5">
                  <Label>USB-Gerät</Label>
                  <Select value={selectedDeviceId} onValueChange={setSelectedDeviceId}>
                    <SelectTrigger><SelectValue placeholder="Gerät wählen…" /></SelectTrigger>
                    <SelectContent>
                      {devices.map((d) => (
                        <SelectItem key={d.id} value={d.id}>
                          {d.chip} · {d.port} · {d.mac}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Firmware-Artefakt</Label>
                  {artifacts.length === 0 ? (
                    <p className="text-xs text-muted-foreground">Keine Artefakte für esp32-wroom-32. Lade unter Flash-Station eine Firmware hoch.</p>
                  ) : (
                    <Select value={selectedArtifactId} onValueChange={setSelectedArtifactId}>
                      <SelectTrigger><SelectValue placeholder="Artefakt wählen…" /></SelectTrigger>
                      <SelectContent>
                        {artifacts.map((a) => (
                          <SelectItem key={a.id} value={a.id}>
                            {a.version} · {a.channel}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </div>
                {activeJobId && job && (
                  <div className="flex items-center gap-2 text-sm">
                    {(job.status === 'pending' || job.status === 'running') && <Loader2 className="h-4 w-4 animate-spin text-info" />}
                    {flashDone && <CheckCircle2 className="h-4 w-4 text-success" />}
                    {flashFailed && <AlertCircle className="h-4 w-4 text-destructive" />}
                    <span>{job.status === 'running' ? `Flasht… ${job.progress}%` : job.status === 'success' ? 'Fertig' : job.status === 'failed' ? 'Fehler' : 'Wartend'}</span>
                    {job.message && <span className="text-muted-foreground truncate max-w-xs">{job.message}</span>}
                  </div>
                )}
                {createJob.isError && (
                  <p className="text-sm text-destructive">{(createJob.error as Error).message}</p>
                )}
              </div>
            )}

            <DialogFooter className="gap-2 flex-wrap">
              <Button variant="outline" onClick={handleClose}>Abbrechen</Button>
              {!activeJobId && devices.length > 0 && (
                <Button variant="outline" onClick={handleFlash} disabled={!selectedDeviceId || !selectedArtifactId || createJob.isPending}>
                  <Zap className="h-4 w-4" />
                  {createJob.isPending ? 'Startet…' : 'Jetzt flashen'}
                </Button>
              )}
              <Button onClick={() => setStep('portal')}>
                {flashDone ? 'Weiter zum Portal' : 'Überspringen'} <ArrowRight className="h-4 w-4" />
              </Button>
            </DialogFooter>
          </div>
        )}

        {/* ── Schritt 3: Portal ──────────────────────────── */}
        {step === 'portal' && claim && (
          <div className="space-y-4">
            <ol className="list-decimal list-inside space-y-2 text-sm">
              <li>Verbinde dein Handy/Laptop mit dem WLAN <span className="font-mono bg-muted px-1 rounded">SpotFam-Reader-XXXXXX</span></li>
              <li>Öffne <span className="font-mono bg-muted px-1 rounded">http://192.168.4.1</span> im Browser</li>
              <li>
                Trage ein:
                <ul className="list-disc list-inside ml-4 mt-1 space-y-0.5">
                  <li><strong>Backend URL:</strong> <span className="font-mono">{claim.backend_url}</span></li>
                  <li><strong>Claim-Code:</strong> <span className="font-mono text-lg tracking-widest">{claim.claim_code}</span></li>
                  <li><strong>WLAN SSID + Passwort</strong> des Heimnetzwerks</li>
                </ul>
              </li>
              <li>Klicke „Speichern und neu starten"</li>
            </ol>
            <div className="space-y-1.5">
              <Label>Payload (alternativ als JSON kopieren)</Label>
              <pre className="rounded-md border bg-muted/30 p-3 text-xs overflow-auto">{payload}</pre>
              <Button variant="outline" size="sm" onClick={() => void navigator.clipboard.writeText(payload)}>
                <Copy className="h-3.5 w-3.5" /> Payload kopieren
              </Button>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={handleClose}>Abbrechen</Button>
              <Button onClick={() => setStep('waiting')}>Fertig – warte auf ESP <ArrowRight className="h-4 w-4" /></Button>
            </DialogFooter>
          </div>
        )}

        {/* ── Schritt 4: Warten ──────────────────────────── */}
        {step === 'waiting' && claim && (
          <div className="space-y-4">
            <div className="flex flex-col items-center gap-3 py-6 text-center">
              {status?.status !== 'expired' ? (
                <>
                  <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">Warte auf Einlösung durch den ESP…</p>
                  <p className="text-xs text-muted-foreground">Gültig bis {new Date(claim.expires_at).toLocaleTimeString('de-DE')}</p>
                </>
              ) : (
                <>
                  <AlertCircle className="h-8 w-8 text-destructive" />
                  <p className="text-sm text-destructive">Claim abgelaufen.</p>
                  <Button size="sm" onClick={() => { handleReset(); setStep('claim'); }}>Neu starten</Button>
                </>
              )}
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={handleClose}>Abbrechen</Button>
            </DialogFooter>
          </div>
        )}

        {/* ── Schritt 5: Fertig ──────────────────────────── */}
        {step === 'done' && (
          <div className="space-y-4">
            <div className="flex flex-col items-center gap-3 py-6 text-center">
              <CheckCircle2 className="h-10 w-10 text-success" />
              <p className="font-medium">Reader provisioniert!</p>
              <p className="text-sm text-muted-foreground">Reader-ID: <span className="font-mono">{status?.reader_id}</span></p>
              <p className="text-sm text-muted-foreground">Lege jetzt eine Karte auf den Reader. Falls der Scan fehlschlägt, prüfe den Status in der Tabelle.</p>
            </div>
            <DialogFooter>
              <Button onClick={handleClose}>Schließen</Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

// ─── Reader-Detail-Zeile ─────────────────────────────────────────────────────

function ReaderRow({
  reader,
  onAssign,
  onClear,
  onRotateKey,
  onDelete,
}: {
  reader: ReaderDto;
  onAssign: (r: ReaderDto) => void;
  onClear: (readerId: string) => void;
  onRotateKey: (r: ReaderDto) => void;
  onDelete: (r: ReaderDto) => void;
}) {
  const navigate = useNavigate();
  const [expanded, setExpanded] = useState(false);
  const statusBadge = readerStatusBadge(reader.has_api_key, reader.minutes_since_seen);
  const lastScan = reader.last_scan;
  const outcome = lastScan ? outcomeInfo(lastScan.outcome) : null;

  return (
    <>
      <TableRow
        className="cursor-pointer hover:bg-muted/30"
        onClick={() => setExpanded((v) => !v)}
      >
        <TableCell>
          <div className="flex items-center gap-2">
            <div>
              <div className="flex items-center gap-1.5">
                <span className="font-mono text-sm">{reader.reader_id}</span>
                {reader.has_api_key
                  ? <Badge variant="success" className="gap-1 text-[10px] px-1"><KeyRound className="h-2.5 w-2.5" />ESP</Badge>
                  : <Badge variant="muted" className="text-[10px] px-1">Legacy</Badge>}
              </div>
              <div className="text-xs text-muted-foreground">{reader.name ?? '—'}</div>
            </div>
          </div>
        </TableCell>

        <TableCell>
          <div className="text-xs space-y-0.5">
            <Badge variant={statusBadge.variant} className="text-[10px] px-1.5">
              {statusBadge.text}
            </Badge>
            {reader.firmware_version && (
              <div className="flex items-center gap-1 text-muted-foreground mt-0.5">
                <Cpu className="h-3 w-3" />
                <span>{reader.firmware_version}</span>
                {reader.fw_channel && <span>· {reader.fw_channel}</span>}
              </div>
            )}
          </div>
        </TableCell>

        <TableCell>
          {lastScan && outcome ? (
            <div className="space-y-0.5">
              <Badge variant={outcome.variant} className="text-xs">
                {outcome.label}
              </Badge>
              <div className="text-xs text-muted-foreground font-mono">{lastScan.card_uid_raw}</div>
              <div className="text-xs text-muted-foreground">{formatDateRelative(lastScan.created_at)}</div>
            </div>
          ) : (
            <span className="text-xs text-muted-foreground">—</span>
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
          <div className="flex justify-end items-center gap-1.5">
            <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={(e) => { e.stopPropagation(); onAssign(reader); }}>
              <MapPin className="h-3.5 w-3.5" />Box
            </Button>
            {reader.default_spotify_device_id && (
              <Button variant="ghost" size="sm" className="h-7 text-xs text-destructive" onClick={(e) => { e.stopPropagation(); onClear(reader.reader_id); }}>
                <Unlink className="h-3.5 w-3.5" />
              </Button>
            )}
            <Button variant="ghost" size="sm" className="h-7 px-1.5 text-xs" title="API-Key rotieren" onClick={(e) => { e.stopPropagation(); onRotateKey(reader); }}>
              <KeyRound className="h-3.5 w-3.5" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="h-7 text-muted-foreground hover:text-destructive"
              title="Reader löschen"
              onClick={(e) => { e.stopPropagation(); onDelete(reader); }}
            >
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
            <Button variant="ghost" size="sm" className="h-7 px-1" onClick={(e) => { e.stopPropagation(); setExpanded((v) => !v); }}>
              {expanded ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
            </Button>
          </div>
        </TableCell>
      </TableRow>

      {expanded && (
        <TableRow className="bg-muted/10 hover:bg-muted/10">
          <TableCell colSpan={5} className="py-3 px-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              {/* Status & Firmware */}
              <div className="space-y-1">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Reader-Info</p>
                <div className="space-y-0.5">
                  <div><span className="text-muted-foreground">ID:</span> <span className="font-mono">{reader.reader_id}</span></div>
                  <div><span className="text-muted-foreground">UUID:</span> <span className="font-mono text-xs">{reader.id ?? '—'}</span></div>
                  <div><span className="text-muted-foreground">Firmware:</span> {reader.firmware_version ?? '—'}</div>
                  <div><span className="text-muted-foreground">Board:</span> {reader.board ?? '—'}</div>
                  <div><span className="text-muted-foreground">Kanal:</span> {reader.fw_channel ?? '—'}</div>
                  <div><span className="text-muted-foreground">Zuletzt gesehen:</span> {formatDateRelative(reader.last_seen_at)}</div>
                  {reader.last_ip !== null && (
                    <div><span className="text-muted-foreground">Letzte IP:</span> <span className="font-mono">{reader.last_ip}</span></div>
                  )}
                </div>
                <button
                  className="mt-2 text-xs text-primary hover:underline flex items-center gap-1"
                  onClick={(e) => { e.stopPropagation(); navigate('/provisioning'); }}
                >
                  Firmware flashen <ArrowRight className="h-3 w-3" />
                </button>
              </div>

              {/* Letzter Scan + Hinweis */}
              <div className="space-y-1">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Letzter Scan</p>
                {lastScan && outcome ? (
                  <div className="space-y-1">
                    <Badge variant={outcome.variant}>{outcome.label}</Badge>
                    <div className="text-xs font-mono text-muted-foreground">{lastScan.card_uid_raw}</div>
                    <div className="text-xs text-muted-foreground">{formatDateRelative(lastScan.created_at)}</div>
                    {lastScan.message && (
                      <div className="rounded border bg-muted/30 px-2 py-1 text-xs text-muted-foreground font-mono">{lastScan.message}</div>
                    )}
                    {outcome.hint && (
                      <div className="rounded-md border border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800 px-3 py-2 text-xs flex gap-2">
                        <AlertCircle className="h-3.5 w-3.5 shrink-0 text-amber-600 mt-0.5" />
                        <span>{outcome.hint}</span>
                      </div>
                    )}
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">Noch kein Scan</p>
                )}
              </div>
            </div>
          </TableCell>
        </TableRow>
      )}
    </>
  );
}

// ─── Hauptseite ──────────────────────────────────────────────────────────────

export function ReadersPage() {
  const { data, isLoading, error, refetch, isFetching } = useReaders();
  const { data: devicesData } = useDetectedDevices();
  const clearBox = useClearReaderBox();
  const rotateApiKey = useRotateReaderApiKey();
  const deleteReader = useDeleteReader();
  const [assignTarget, setAssignTarget] = useState<ReaderDto | null>(null);
  const [addDialogOpen, setAddDialogOpen] = useState(false);
  const [rotatingReader, setRotatingReader] = useState<ReaderDto | null>(null);
  const [rotatedKey, setRotatedKey] = useState<RotateApiKeyResponse | null>(null);
  const [deletingReader, setDeletingReader] = useState<ReaderDto | null>(null);
  const navigate = useNavigate();

  const readers = data?.items ?? [];
  const noUsbDevices = devicesData !== undefined && devicesData.items.length === 0;

  return (
    <div className="flex flex-col h-full">
      {noUsbDevices && (
        <div className="flex items-center gap-2 px-6 py-2.5 bg-amber-50 dark:bg-amber-950/20 border-b border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-300 shrink-0">
          <Usb className="h-4 w-4 shrink-0" />
          <span>
            Kein USB-Gerät erkannt – ESP per USB am Pi anschließen, dann{' '}
            <button
              className="underline underline-offset-2 hover:text-amber-900 dark:hover:text-amber-200"
              onClick={() => navigate('/provisioning')}
            >
              Firmware-Station öffnen
            </button>
            .
          </span>
        </div>
      )}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">RFID-Leser</h1>
          <p className="text-sm text-muted-foreground">{readers.length} bekannte Leser</p>
        </div>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={() => setAddDialogOpen(true)}>
            <Plus className="h-4 w-4" />
            Reader einrichten
          </Button>
          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
          </Button>
        </div>
      </div>

      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3].map((i) => <Skeleton key={i} className="h-16 w-full" />)}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive">Fehler beim Laden der Leser.</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>Erneut versuchen</Button>
          </div>
        ) : readers.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-center gap-3">
            <Radio className="h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              Noch keine Leser. Klicke auf „Reader einrichten", um einen neuen ESP32-Reader hinzuzufügen.
            </p>
            <Button size="sm" onClick={() => setAddDialogOpen(true)}>
              <Plus className="h-4 w-4" />Reader einrichten
            </Button>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Reader</TableHead>
                <TableHead>Status / Firmware</TableHead>
                <TableHead>Letzter Scan</TableHead>
                <TableHead>Zugewiesene Box</TableHead>
                <TableHead className="w-36" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {readers.map((reader) => (
                <ReaderRow
                  key={reader.reader_id}
                  reader={reader}
                  onAssign={setAssignTarget}
                  onClear={(id) => clearBox.mutate(id)}
                  onRotateKey={setRotatingReader}
                  onDelete={setDeletingReader}
                />
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      <AssignBoxDialog reader={assignTarget} onClose={() => setAssignTarget(null)} />
      <AddReaderWizard
        open={addDialogOpen}
        onClose={() => setAddDialogOpen(false)}
        onClaimed={() => void refetch()}
      />

      {/* API-Key-Rotation: Bestätigungsdialog */}
      <AlertDialog open={rotatingReader !== null && rotatedKey === null} onOpenChange={(v) => { if (!v) setRotatingReader(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>API-Key rotieren?</AlertDialogTitle>
            <AlertDialogDescription>
              Durch die Rotation wird der bestehende API-Key des Readers{' '}
              <strong>sofort ungültig</strong>. Der Reader{' '}
              <span className="font-mono">{rotatingReader?.reader_id}</span> kann keine Karten
              mehr scannen, bis er mit dem neuen Key neu geflasht wird.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => setRotatingReader(null)}>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (!rotatingReader) return;
                rotateApiKey.mutate(rotatingReader.reader_id, {
                  onSuccess: (res) => {
                    setRotatedKey(res);
                  },
                });
              }}
              disabled={rotateApiKey.isPending}
            >
              {rotateApiKey.isPending ? <><Loader2 className="h-4 w-4 animate-spin" /> Rotiere…</> : 'Key rotieren'}
            </AlertDialogAction>
          </AlertDialogFooter>
          {rotateApiKey.isError && (
            <p className="px-6 pb-4 text-sm text-destructive">{(rotateApiKey.error as Error).message}</p>
          )}
        </AlertDialogContent>
      </AlertDialog>

      {/* Reader löschen: Bestätigungsdialog */}
      <AlertDialog open={deletingReader !== null} onOpenChange={(v) => !v && setDeletingReader(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Reader löschen?</AlertDialogTitle>
            <AlertDialogDescription>
              Reader <span className="font-mono font-medium">{deletingReader?.reader_id}</span> wird
              dauerhaft gelöscht, inklusive aller Scan-Ereignisse und Claims. Diese Aktion kann nicht
              rückgängig gemacht werden.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteReader.isPending}
              onClick={() => {
                if (deletingReader) {
                  deleteReader.mutate(deletingReader.reader_id, {
                    onSuccess: () => setDeletingReader(null),
                  });
                }
              }}
            >
              {deleteReader.isPending ? <><Loader2 className="h-4 w-4 animate-spin" /> Löschen…</> : 'Löschen'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* API-Key-Rotation: Neuen Key anzeigen */}
      <Dialog open={rotatedKey !== null} onOpenChange={(v) => { if (!v) { setRotatedKey(null); setRotatingReader(null); rotateApiKey.reset(); } }}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Neuer API-Key</DialogTitle>
            <DialogDescription>
              Kopiere den Key jetzt – er wird nur einmal angezeigt. Flashe den Reader{' '}
              <span className="font-mono">{rotatedKey?.reader_id}</span> anschließend mit diesem Key.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <pre className="rounded-md border bg-muted/30 p-3 text-xs font-mono break-all whitespace-pre-wrap select-all">
              {rotatedKey?.api_key}
            </pre>
            <Button
              variant="outline"
              size="sm"
              className="w-full"
              onClick={() => { if (rotatedKey) void navigator.clipboard.writeText(rotatedKey.api_key); }}
            >
              <Copy className="h-3.5 w-3.5" /> Key kopieren
            </Button>
          </div>
          <DialogFooter>
            <Button onClick={() => { setRotatedKey(null); setRotatingReader(null); rotateApiKey.reset(); }}>Schließen</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
