export interface StudentMenuItem {
  key: string
  labelKey: string
  icon: string
  to: string
}

export const studentMenu: StudentMenuItem[] = [
  { key: 'dashboard', labelKey: 'student.menu.dashboard', icon: 'pi pi-home', to: '/student' },
  { key: 'courses', labelKey: 'student.menu.myCourses', icon: 'pi pi-book', to: '/student/courses' },
  { key: 'catalog', labelKey: 'student.menu.catalog', icon: 'pi pi-shop', to: '/courses' },
]

type TranslateFn = (key: string) => string

export function resolveStudentTitle(path: string, t: TranslateFn) {
  if (path.startsWith('/learn')) return t('student.menu.learn')
  const match = studentMenu.find(item => item.to === path || (item.to !== '/student' && path.startsWith(`${item.to}/`)))
  return match ? t(match.labelKey) : t('student.console')
}

export function resolveStudentBreadcrumb(path: string, t: TranslateFn) {
  if (path === '/student') return [{ label: t('student.menu.dashboard'), to: '/student' }]
  const match = studentMenu.find(item => item.to === path)
  if (!match) return [{ label: t('student.menu.dashboard'), to: '/student' }]
  return [
    { label: t('student.menu.dashboard'), to: '/student' },
    { label: t(match.labelKey), to: match.to },
  ]
}
