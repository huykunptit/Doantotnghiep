export interface AuthUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  role?: string | null
  roles?: string[]
  email_verified?: boolean
  email_verified_at?: string | null
  student_code?: string | null
  class_name?: string | null
  department?: string | null
}

export interface AuthResponse {
  access_token: string
  token_type?: string
  user: AuthUser
}

export interface RegisterResponse {
  message: string
  email: string
  requires_verification?: boolean
}

export function dashboardFor(user?: AuthUser | null) {
  const roles = user?.roles || (user?.role ? [user.role] : [])
  if (roles.includes('admin')) return '/admin'
  if (roles.includes('instructor')) return '/instructor'
  return '/student'
}
