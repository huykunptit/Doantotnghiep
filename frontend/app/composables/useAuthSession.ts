export interface AuthUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  role?: string | null
  roles?: string[]
}

export interface AuthResponse {
  access_token: string
  token_type?: string
  user: AuthUser
}

export function normalizeRole(role?: string | null): 'admin' | 'instructor' | 'student' {
  if (role === 'admin' || role === 'instructor') return role
  return 'student'
}

export function getDashboardPath(role?: string | null) {
  const normalized = normalizeRole(role)

  if (normalized === 'admin') return '/admin'
  if (normalized === 'instructor') return '/instructor'
  return '/student'
}

export function useAuthTokenCookie() {
  return useCookie<string | null>('token', {
    path: '/',
    maxAge: 60 * 60 * 24 * 7,
    watch: true,
  })
}

export function useAuthUserCookie() {
  return useCookie<AuthUser | null>('auth_user', {
    path: '/',
    maxAge: 60 * 60 * 24 * 7,
    watch: true,
    default: () => null,
  })
}

export function setAuthSession(payload: AuthResponse) {
  useAuthTokenCookie().value = payload.access_token
  useAuthUserCookie().value = payload.user
}

export function clearAuthSession() {
  useAuthTokenCookie().value = null
  useAuthUserCookie().value = null
}
