export const ROLES = {
  ADMIN: 'admin',
  INSTRUCTOR: 'instructor',
  STUDENT: 'student',
} as const

export type Role = (typeof ROLES)[keyof typeof ROLES]

export const ROLE_LABELS: Record<Role, string> = {
  admin: 'Quản trị viên',
  instructor: 'Giảng viên',
  student: 'Học viên',
}

export function hasRole(userRoles: string[] = [], role: Role) {
  return userRoles.includes(role)
}

