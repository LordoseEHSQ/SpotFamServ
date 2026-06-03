import { useState } from 'react';
import {
  Download, AudioLines, AlertTriangle, Loader2, Trash2, RefreshCw, FileAudio,
} from 'lucide-react';
import {
  useAudioExtractorConfig, useAudioFiles, useExtractAudio,
  useDeleteAudioFile, useUpdateEngine,
} from '@/hooks/useAudioExtractor';
import type { StoredAudioFileDto } from '@/api/endpoints/audioExtractor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { formatDateRelative } from '@/lib/utils';

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function AudioExtractorPage() {
  const { data: config } = useAudioExtractorConfig();
  const { data: filesData, isLoading: filesLoading } = useAudioFiles();
  const extract = useExtractAudio();
  const deleteFile = useDeleteAudioFile();
  const updateEngine = useUpdateEngine();

  const [url, setUrl] = useState('');
  const [format, setFormat] = useState('mp3');
  const [bitrate, setBitrate] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<StoredAudioFileDto | null>(null);

  const selectedFormat = config?.formats.find((f) => f.value === format);
  const showBitrate = selectedFormat?.supports_bitrate ?? false;
  const effectiveBitrate = bitrate ?? config?.default_bitrate_kbps ?? 192;

  const errorMessage = extract.error instanceof Error ? extract.error.message : null;
  const updateError = updateEngine.error instanceof Error ? updateEngine.error.message : null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!url.trim()) return;
    extract.mutate(
      {
        url: url.trim(),
        format,
        bitrate_kbps: showBitrate ? effectiveBitrate : undefined,
      },
      { onSuccess: () => setUrl('') },
    );
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight flex items-center gap-2">
            <AudioLines className="h-5 w-5" />
            Audio-Extractor
          </h1>
          <p className="text-sm text-muted-foreground">
            Audiospur aus einer Medien-URL extrahieren, speichern und herunterladen.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant="muted" className="font-mono text-xs">
            yt-dlp {config?.engine_version ?? '—'}
          </Badge>
          <Button
            variant="outline"
            size="sm"
            onClick={() => updateEngine.mutate()}
            disabled={updateEngine.isPending}
            title="yt-dlp aktualisieren"
          >
            <RefreshCw className={updateEngine.isPending ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
            {updateEngine.isPending ? 'Aktualisiere…' : 'Engine aktualisieren'}
          </Button>
        </div>
      </div>

      <div className="flex-1 overflow-auto p-6">
        <div className="max-w-2xl mx-auto space-y-4">
          <div className="rounded-md border border-warning/30 bg-warning/5 px-4 py-3 text-sm">
            <p className="flex gap-2 text-warning-foreground">
              <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0 text-warning" />
              <span>
                Nur für <strong>legale Quellen</strong> (eigene Uploads, Creative-Commons,
                gemeinfreie oder mit Download-Erlaubnis versehene Inhalte). Kein Umgehen von
                Kopierschutz. Die Verantwortung für die jeweilige URL liegt bei dir.
              </span>
            </p>
          </div>

          {updateError && (
            <p className="text-sm text-destructive flex items-start gap-1.5">
              <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
              Engine-Update fehlgeschlagen: {updateError}
            </p>
          )}

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Neue Extraktion</CardTitle>
              <CardDescription>
                Maximale Länge: {config ? Math.round(config.max_duration_seconds / 60) : 30} Minuten.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-1.5">
                  <Label htmlFor="url">Medien-URL</Label>
                  <Input
                    id="url"
                    type="url"
                    inputMode="url"
                    placeholder="https://…"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    required
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label>Format</Label>
                    <Select value={format} onValueChange={setFormat}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {(config?.formats ?? []).map((f) => (
                          <SelectItem key={f.value} value={f.value}>
                            {f.value.toUpperCase()}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  {showBitrate && (
                    <div className="space-y-1.5">
                      <Label>Bitrate</Label>
                      <Select value={String(effectiveBitrate)} onValueChange={(v) => setBitrate(Number(v))}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {(config?.bitrates_kbps ?? []).map((b) => (
                            <SelectItem key={b} value={String(b)}>
                              {b} kbps
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                </div>

                <Button type="submit" disabled={extract.isPending || !url.trim()} className="w-full">
                  {extract.isPending ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Extrahiere… (kann bis zu einigen Minuten dauern)
                    </>
                  ) : (
                    <>
                      <Download className="h-4 w-4" />
                      Extrahieren & speichern
                    </>
                  )}
                </Button>

                {errorMessage && (
                  <p className="text-sm text-destructive flex items-start gap-1.5">
                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                    {errorMessage}
                  </p>
                )}
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Gespeicherte Dateien</CardTitle>
              <CardDescription>
                {filesData
                  ? `${filesData.items.length} Datei(en) · ${formatBytes(filesData.total_size_bytes)} belegt`
                  : 'Lädt…'}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {filesLoading ? (
                <Skeleton className="h-24 w-full" />
              ) : !filesData || filesData.items.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-8 text-center">
                  <FileAudio className="h-8 w-8 text-muted-foreground mb-3" />
                  <p className="text-sm text-muted-foreground">Noch keine Dateien gespeichert.</p>
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow className="hover:bg-transparent">
                      <TableHead>Datei</TableHead>
                      <TableHead>Größe</TableHead>
                      <TableHead>Erstellt</TableHead>
                      <TableHead className="w-24 text-right">Aktionen</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filesData.items.map((file) => (
                      <TableRow key={file.name}>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <FileAudio className="h-4 w-4 text-muted-foreground shrink-0" />
                            <span className="font-medium break-all">{file.name}</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatBytes(file.size_bytes)}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDateRelative(file.created_at)}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1">
                            <a href={file.download_url} download>
                              <Button variant="ghost" size="sm" className="h-7" title="Herunterladen">
                                <Download className="h-3.5 w-3.5" />
                              </Button>
                            </a>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 text-destructive hover:text-destructive"
                              title="Löschen"
                              onClick={() => setDeleteTarget(file)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Datei löschen?</AlertDialogTitle>
            <AlertDialogDescription>
              <span className="font-medium break-all">{deleteTarget?.name}</span> wird endgültig gelöscht.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (deleteTarget) {
                  deleteFile.mutate(deleteTarget.name);
                }
                setDeleteTarget(null);
              }}
            >
              Löschen
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
