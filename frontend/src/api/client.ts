const API_BASE = '/api/v1';
const CSRF_COOKIE = 'XSRF-TOKEN';
const CSRF_HEADER = 'X-XSRF-TOKEN';
const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

export type ApiError = {
  type?: string;
  title?: string;
  status: number;
  detail?: string;
  error?: string;
};

/** Liest den Wert eines Cookies aus document.cookie (kein Storage-API). */
function readCookie(name: string): string | null {
  const prefix = `${name}=`;
  for (const part of document.cookie.split(';')) {
    const c = part.trim();
    if (c.startsWith(prefix)) {
      return decodeURIComponent(c.slice(prefix.length));
    }
  }
  return null;
}

/**
 * Holt ein frisches CSRF-Cookie (XSRF-TOKEN). Wird beim App-Start, vor dem
 * Login und beim 403-Retry aufgerufen. credentials:'include' für das Cookie.
 */
export async function fetchCsrfToken(): Promise<void> {
  await fetch(`${API_BASE}/auth/csrf`, {
    method: 'GET',
    credentials: 'include',
  });
}

async function request<T>(
  path: string,
  options: RequestInit = {},
  retriedCsrf = false,
): Promise<T> {
  const url = path.startsWith('http') ? path : `${API_BASE}${path}`;
  const isFormData = options.body instanceof FormData;
  const method = (options.method ?? 'GET').toUpperCase();

  const headers: Record<string, string> = {
    ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
    ...(options.headers as Record<string, string> | undefined),
  };

  // Double-Submit: CSRF-Header bei mutierenden Requests (inkl. Upload + Login).
  if (MUTATING_METHODS.has(method)) {
    const token = readCookie(CSRF_COOKIE);
    if (token) headers[CSRF_HEADER] = token;
  }

  const res = await fetch(url, {
    ...options,
    credentials: 'include',
    headers,
  });

  if (res.status === 401) {
    window.dispatchEvent(new Event('auth:unauthorized'));
  }

  const data = res.status === 204 ? {} : await res.json().catch(() => ({}));

  // CSRF abgelaufen/fehlend → einmal Token erneuern und Request wiederholen.
  if (
    res.status === 403 &&
    (data as ApiError).error === 'invalid_csrf' &&
    MUTATING_METHODS.has(method) &&
    !retriedCsrf
  ) {
    await fetchCsrfToken();
    return request<T>(path, options, true);
  }

  if (!res.ok) {
    const err = new Error((data as ApiError).detail ?? res.statusText) as Error & { status?: number; body?: ApiError };
    err.status = res.status;
    err.body = data as ApiError;
    throw err;
  }
  return data as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, body: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(body) }),
  put: <T>(path: string, body: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(body) }),
  delete: <T = void>(path: string) => request<T>(path, { method: 'DELETE' }),
  /** Multipart/form-data Upload – kein manuelles Content-Type, Browser setzt boundary. */
  upload: <T>(path: string, formData: FormData) =>
    request<T>(path, { method: 'POST', body: formData }),
};
