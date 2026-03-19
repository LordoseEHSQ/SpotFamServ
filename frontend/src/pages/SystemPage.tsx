import { useState } from 'react';
import {
  Settings, Music2, CheckCircle2, XCircle, AlertCircle, RefreshCw, Save, Eye, EyeOff,
} from 'lucide-react';
import { useSpotifyAppConfig, useSaveSpotifyAppConfig, useValidateSpotifyAppConfig } from '@/hooks/useSpotifyAppConfig';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/utils';
import type { SpotifyConfigStatus } from '@/api/endpoints/spotify';

function statusBadge(status: SpotifyConfigStatus) {
  switch (status) {
    case 'validated':
      return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200"><CheckCircle2 className="h-3 w-3 mr-1" />Validiert</Badge>;
    case 'configured':
      return <Badge className="bg-blue-100 text-blue-800 border-blue-200"><CheckCircle2 className="h-3 w-3 mr-1" />Konfiguriert</Badge>;
    case 'error':
      return <Badge className="bg-red-100 text-red-800 border-red-200"><XCircle className="h-3 w-3 mr-1" />Fehler</Badge>;
    default:
      return <Badge variant="outline"><AlertCircle className="h-3 w-3 mr-1" />Nicht konfiguriert</Badge>;
  }
}

export function SystemPage() {
  const { data: config, isLoading } = useSpotifyAppConfig();
  const save = useSaveSpotifyAppConfig();
  const validate = useValidateSpotifyAppConfig();

  const [clientId, setClientId] = useState('');
  const [clientSecret, setClientSecret] = useState('');
  const [redirectUri, setRedirectUri] = useState('');
  const [showSecret, setShowSecret] = useState(false);
  const [initialized, setInitialized] = useState(false);

  if (config && !initialized) {
    setClientId(config.spotify_client_id ?? '');
    setRedirectUri(config.redirect_uri ?? '');
    setInitialized(true);
  }

  const handleSave = async () => {
    const payload: Record<string, string> = {
      spotify_client_id: clientId,
      redirect_uri: redirectUri,
    };
    if (clientSecret.trim() !== '') {
      payload.spotify_client_secret = clientSecret;
    }
    await save.mutateAsync(payload);
    setClientSecret('');
  };

  return (
    <div className="p-6 max-w-3xl space-y-6">
      <div className="flex items-center gap-3 mb-2">
        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
          <Settings className="h-5 w-5 text-primary" />
        </div>
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Systemeinstellungen</h1>
          <p className="text-sm text-muted-foreground">Globale Integrationen und Systemkonfiguration</p>
        </div>
      </div>

      <Separator />

      {/* Spotify App-Konfiguration */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Music2 className="h-5 w-5 text-[#1DB954]" />
              <CardTitle className="text-base">Spotify App-Konfiguration</CardTitle>
            </div>
            {isLoading ? (
              <Skeleton className="h-6 w-24" />
            ) : config ? (
              statusBadge(config.config_status)
            ) : null}
          </div>
          <CardDescription>
            Zentrale Spotify-App-Credentials (Client ID &amp; Secret). Diese Konfiguration gilt systemweit
            für alle Teilnehmer. Jeder Teilnehmer verbindet sein eigenes Spotify-Konto gegen diese App.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          {isLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : (
            <>
              {config?.source === 'env' && (
                <div className="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                  <AlertCircle className="h-4 w-4 inline mr-1.5 align-middle" />
                  Konfiguration wird aktuell aus Umgebungsvariablen gelesen.
                  Änderungen hier werden in der Datenbank gespeichert und überschreiben die Env-Werte.
                </div>
              )}

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="client-id">Spotify Client ID</Label>
                  <Input
                    id="client-id"
                    value={clientId}
                    onChange={(e) => setClientId(e.target.value)}
                    placeholder="z. B. abc123def456..."
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="client-secret">
                    Spotify Client Secret
                    {config?.has_client_secret && (
                      <span className="ml-2 text-xs text-muted-foreground">(bereits gesetzt, zum Ändern neu eingeben)</span>
                    )}
                  </Label>
                  <div className="relative">
                    <Input
                      id="client-secret"
                      type={showSecret ? 'text' : 'password'}
                      value={clientSecret}
                      onChange={(e) => setClientSecret(e.target.value)}
                      placeholder={config?.has_client_secret ? '••••••••••••••••' : 'Client Secret eingeben'}
                    />
                    <button
                      type="button"
                      onClick={() => setShowSecret(!showSecret)}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="redirect-uri">Redirect URI</Label>
                <Input
                  id="redirect-uri"
                  value={redirectUri}
                  onChange={(e) => setRedirectUri(e.target.value)}
                  placeholder="https://example.com/api/v1/spotify/callback"
                />
                <p className="text-xs text-muted-foreground">
                  Muss exakt im Spotify Developer Dashboard als Redirect URI eingetragen sein.
                </p>
              </div>

              <Separator />

              <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground space-y-0.5">
                  {config?.last_check_at && (
                    <p>Letzter Check: {formatDate(config.last_check_at)}</p>
                  )}
                  {config?.last_check_note && (
                    <p className="text-xs">{config.last_check_note}</p>
                  )}
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => validate.mutate()}
                    disabled={validate.isPending}
                  >
                    <RefreshCw className={`h-4 w-4 ${validate.isPending ? 'animate-spin' : ''}`} />
                    Validieren
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSave}
                    disabled={save.isPending || (!clientId.trim() && !redirectUri.trim())}
                  >
                    <Save className="h-4 w-4" />
                    {save.isPending ? 'Speichert…' : 'Speichern'}
                  </Button>
                </div>
              </div>

              {validate.data && (
                <div className={`rounded-md px-3 py-2 text-sm border ${
                  validate.data.valid
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-red-50 border-red-200 text-red-800'
                }`}>
                  {validate.data.valid ? <CheckCircle2 className="h-4 w-4 inline mr-1.5" /> : <XCircle className="h-4 w-4 inline mr-1.5" />}
                  {validate.data.note}
                </div>
              )}

              {/* Status-Übersicht */}
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 pt-1">
                <StatusItem label="Client ID" ok={!!config?.spotify_client_id} />
                <StatusItem label="Client Secret" ok={!!config?.has_client_secret} />
                <StatusItem label="Redirect URI" ok={!!config?.redirect_uri} />
                <StatusItem label="Vollständig" ok={!!config?.is_complete} />
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function StatusItem({ label, ok }: { label: string; ok: boolean }) {
  return (
    <div className="flex items-center gap-2 text-sm">
      {ok ? (
        <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
      ) : (
        <XCircle className="h-4 w-4 text-muted-foreground shrink-0" />
      )}
      <span className={ok ? 'text-foreground' : 'text-muted-foreground'}>{label}</span>
    </div>
  );
}
