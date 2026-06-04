import { api } from '../client';

export interface AuthUser {
  username: string;
  roles: string[];
}

export interface LoginRequest {
  username: string;
  password: string;
}

export const authApi = {
  /** GET /auth/csrf → setzt Nicht-HttpOnly-Cookie XSRF-TOKEN. */
  csrf: () => api.get<void>('/auth/csrf'),

  /** POST /auth/login → 204 (setzt Session-Cookie) | 401 */
  login: (body: LoginRequest) => api.post<void>('/auth/login', body),

  /** POST /auth/logout → 204 */
  logout: () => api.post<void>('/auth/logout', {}),

  /** GET /auth/me → 200 {username, roles} | 401 */
  me: () => api.get<AuthUser>('/auth/me'),
};
