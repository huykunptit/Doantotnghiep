export interface InstructorMenuItem {
  key: string
  labelKey: string
  icon: string
  to: string
  /** Spatie permission required (admin always passes). */
  permission?: string | string[]
}

/** Slim GV menu — thesis demo: overview, courses/builder, revenue, students. */
export const instructorMenu: InstructorMenuItem[] = [
  {
    key: 'dashboard',
    labelKey: 'instructor.menu.dashboard',
    icon: 'pi pi-home',
    to: '/instructor',
    permission: ['view_dashboard', 'manage_courses'],
  },
  {
    key: 'courses',
    labelKey: 'instructor.menu.courses',
    icon: 'pi pi-book',
    to: '/instructor/courses',
    permission: 'manage_courses',
  },
  {
    key: 'revenue',
    labelKey: 'instructor.menu.revenue',
    icon: 'pi pi-wallet',
    to: '/instructor/revenue',
    permission: 'manage_courses',
  },
  {
    key: 'students',
    labelKey: 'instructor.menu.students',
    icon: 'pi pi-users',
    to: '/instructor/students',
    permission: ['manage_courses', 'manage_grades'],
  },
  {
    key: 'grades',
    labelKey: 'instructor.menu.grades',
    icon: 'pi pi-list-check',
    to: '/instructor/sections',
    permission: ['manage_grades', 'view_grades'],
  },
  {
    key: 'exams',
    labelKey: 'instructor.menu.exams',
    icon: 'pi pi-file-edit',
    to: '/instructor/exams',
    permission: 'manage_exams',
  },
]

type TranslateFn = (key: string) => string

export function resolveInstructorTitle(path: string, t: TranslateFn) {
  if (path.includes('/edit')) return t('instructor.menu.builder')
  if (path.includes('/students') && path !== '/instructor/students') {
    return t('instructor.menu.courseStudents')
  }
  if (path.includes('/revenue') && path !== '/instructor/revenue') {
    return t('instructor.menu.courseRevenue')
  }
  if (/^\/instructor\/sections\/\d+\/grades/.test(path)) {
    return t('instructor.menu.gradebook')
  }
  const match = instructorMenu.find(item => item.to === path || path.startsWith(`${item.to}/`))
  return match ? t(match.labelKey) : t('instructor.console')
}

export function resolveInstructorBreadcrumb(path: string, t: TranslateFn) {
  if (path === '/instructor') return [{ label: t('instructor.menu.dashboard'), to: '/instructor' }]

  if (/^\/instructor\/courses\/\d+\/edit/.test(path)) {
    return [
      { label: t('instructor.menu.courses'), to: '/instructor/courses' },
      { label: t('instructor.menu.builder') },
    ]
  }
  if (/^\/instructor\/courses\/\d+\/students/.test(path)) {
    return [
      { label: t('instructor.menu.courses'), to: '/instructor/courses' },
      { label: t('instructor.menu.courseStudents') },
    ]
  }
  if (/^\/instructor\/courses\/\d+\/revenue/.test(path)) {
    return [
      { label: t('instructor.menu.courses'), to: '/instructor/courses' },
      { label: t('instructor.menu.courseRevenue') },
    ]
  }
  if (/^\/instructor\/sections\/\d+\/grades/.test(path)) {
    return [
      { label: t('instructor.menu.grades'), to: '/instructor/sections' },
      { label: t('instructor.menu.gradebook') },
    ]
  }

  const match = instructorMenu.find(item => item.to === path || path.startsWith(`${item.to}/`))
  if (!match) return []
  if (match.to === '/instructor') return [{ label: t(match.labelKey), to: match.to }]
  return [
    { label: t('instructor.menu.dashboard'), to: '/instructor' },
    { label: t(match.labelKey), to: match.to },
  ]
}
