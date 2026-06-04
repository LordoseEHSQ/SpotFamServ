import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate, useLocation } from 'react-router-dom';
import { Loader2, Speaker } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function LoginPage() {
  const { user, isLoading, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: Location })?.from?.pathname ?? '/';

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isPending, setIsPending] = useState(false);

  // Bereits eingeloggt → sofort weiterleiten
  if (!isLoading && user) {
    return <Navigate to={from} replace />;
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsPending(true);
    try {
      await login(username, password);
      navigate(from, { replace: true });
    } catch (err: unknown) {
      const apiErr = err as Error & { status?: number };
      if (apiErr.status === 401) {
        setError('Benutzername oder Passwort ungültig.');
      } else {
        setError('Anmeldung fehlgeschlagen. Bitte versuche es erneut.');
      }
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Card className="w-full max-w-sm shadow-lg">
      <CardHeader className="space-y-1 text-center">
        <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-md bg-muted">
          <Speaker className="h-5 w-5 text-foreground" />
        </div>
        <CardTitle className="text-xl">SpotFam Admin</CardTitle>
        <CardDescription>Melde dich mit deinen Admin-Zugangsdaten an.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={(e) => void handleSubmit(e)} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="username">Benutzername</Label>
            <Input
              id="username"
              type="text"
              autoComplete="username"
              autoFocus
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              disabled={isPending}
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="password">Passwort</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              disabled={isPending}
              required
            />
          </div>

          {error && (
            <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {error}
            </p>
          )}

          <Button type="submit" className="w-full" disabled={isPending || isLoading}>
            {isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Anmelden…
              </>
            ) : (
              'Anmelden'
            )}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
