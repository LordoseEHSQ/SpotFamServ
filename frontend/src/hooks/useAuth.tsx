import { createContext, useContext, useCallback, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { authApi, type AuthUser } from '@/api/endpoints/auth';
import { fetchCsrfToken } from '@/api/client';

// ─── Typen ────────────────────────────────────────────────────────────────────

export interface AuthContextValue {
  user: AuthUser | null;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

// ─── Context ──────────────────────────────────────────────────────────────────

const AuthContext = createContext<AuthContextValue | null>(null);

// ─── Query Keys ───────────────────────────────────────────────────────────────

export const authKeys = {
  me: ['auth', 'me'] as const,
};

// ─── Provider ─────────────────────────────────────────────────────────────────

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();

  const { data: user, isLoading } = useQuery({
    queryKey: authKeys.me,
    queryFn: async () => {
      try {
        return await authApi.me();
      } catch (e: unknown) {
        const err = e as Error & { status?: number };
        if (err.status === 401) return null;
        throw e;
      }
    },
    retry: false,
    staleTime: 5 * 60 * 1000,
  });

  // CSRF-Cookie beim App-Start holen, vor dem ersten mutierenden Request.
  useEffect(() => {
    void fetchCsrfToken();
  }, []);

  // Globaler 401-Handler: setzt Auth-Zustand zurück wenn irgendein Request 401 bekommt
  useEffect(() => {
    const handler = () => {
      queryClient.setQueryData<AuthUser | null>(authKeys.me, null);
    };
    window.addEventListener('auth:unauthorized', handler);
    return () => window.removeEventListener('auth:unauthorized', handler);
  }, [queryClient]);

  const loginMutation = useMutation({
    // Flow: GET /auth/csrf → POST /auth/login (mit X-XSRF-TOKEN) → onSuccess GET /auth/me
    mutationFn: async ({ username, password }: { username: string; password: string }) => {
      await fetchCsrfToken();
      await authApi.login({ username, password });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: authKeys.me });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: authApi.logout,
    onSuccess: () => {
      queryClient.setQueryData<AuthUser | null>(authKeys.me, null);
      queryClient.clear();
    },
  });

  const login = useCallback(
    async (username: string, password: string) => {
      await loginMutation.mutateAsync({ username, password });
    },
    [loginMutation],
  );

  const logout = useCallback(async () => {
    await logoutMutation.mutateAsync();
  }, [logoutMutation]);

  return (
    <AuthContext.Provider value={{ user: user ?? null, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

// ─── Hook ─────────────────────────────────────────────────────────────────────

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth muss innerhalb von AuthProvider verwendet werden');
  return ctx;
}
