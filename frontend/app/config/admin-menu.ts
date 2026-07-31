import { FEATURE_ATTENDANCE_ENABLED } from './feature-flags'

export interface AdminMenuChild {
  labelKey: string
  to: string
}

export interface AdminMenuItem {
  key: string
  labelKey: string
  icon: string
  to?: string
  children?: AdminMenuChild[]
}

/** Thứ tự giữ nguyên từ giao diện admin cũ. labelKey → i18n `admin.menu.*` */
const adminMenuRaw: AdminMenuItem[] = [
  {
    key: 'dashboard',
    labelKey: 'admin.menu.dashboard',
    icon: 'pi pi-home',
    to: '/admin',
  },
  {
    key: 'people',
    labelKey: 'admin.menu.people',
    icon: 'pi pi-users',
    children: [
      { labelKey: 'admin.menu.users', to: '/admin/users' },
      { labelKey: 'admin.menu.roles', to: '/admin/roles' },
    ],
  },
  {
    key: 'academic',
    labelKey: 'admin.menu.academic',
    icon: 'pi pi-graduation-cap',
    children: [
      { labelKey: 'admin.menu.academicCalendar', to: '/admin/lnd/academic-calendar' },
      { labelKey: 'admin.menu.academicCatalog', to: '/admin/academic' },
      { labelKey: 'admin.menu.learningPaths', to: '/admin/lnd/learning-paths' },
      { labelKey: 'admin.menu.adminClasses', to: '/admin/lnd/classes' },
      { labelKey: 'admin.menu.classSchedules', to: '/admin/lnd/schedules' },
      { labelKey: 'admin.menu.classPathEnrollment', to: '/admin/lnd/class-path-enrollment' },
      { labelKey: 'admin.menu.attendance', to: '/admin/lnd/attendance' },
    ],
  },
  {
    key: 'courses',
    labelKey: 'admin.menu.courses',
    icon: 'pi pi-book',
    children: [
      { labelKey: 'admin.menu.courseReview', to: '/admin/courses' },
      { labelKey: 'admin.menu.manageCourses', to: '/admin/manage-courses' },
      { labelKey: 'admin.menu.careerPaths', to: '/admin/career-paths' },
      { labelKey: 'admin.menu.categories', to: '/admin/categories' },
      { labelKey: 'admin.menu.reviews', to: '/admin/reviews' },
      { labelKey: 'admin.menu.certificates', to: '/admin/certificates' },
    ],
  },
  {
    key: 'assessment',
    labelKey: 'admin.menu.assessment',
    icon: 'pi pi-clipboard',
    children: [
      { labelKey: 'admin.menu.quiz', to: '/admin/quiz' },
      { labelKey: 'admin.menu.questionBank', to: '/admin/question-bank' },
      { labelKey: 'admin.menu.examTracking', to: '/admin/reports/exam-tracking' },
    ],
  },
  {
    key: 'reports',
    labelKey: 'admin.menu.reports',
    icon: 'pi pi-chart-line',
    children: [
      { labelKey: 'admin.menu.courseReports', to: '/admin/reports/courses' },
      { labelKey: 'admin.menu.examReports', to: '/admin/reports/exams' },
      { labelKey: 'admin.menu.progressReports', to: '/admin/reports/progress' },
      { labelKey: 'admin.menu.activityLogs', to: '/admin/reports/activity' },
    ],
  },
  {
    key: 'finance',
    labelKey: 'admin.menu.finance',
    icon: 'pi pi-wallet',
    children: [
      { labelKey: 'admin.menu.orders', to: '/admin/orders' },
      { labelKey: 'admin.menu.paymentReports', to: '/admin/reports/payments' },
    ],
  },
  {
    key: 'system',
    labelKey: 'admin.menu.system',
    icon: 'pi pi-cog',
    children: [
      { labelKey: 'admin.menu.notifications', to: '/admin/notifications' },
      { labelKey: 'admin.menu.news', to: '/admin/news' },
      { labelKey: 'admin.menu.ai', to: '/admin/ai' },
      { labelKey: 'admin.menu.chat', to: '/admin/chat' },
      { labelKey: 'admin.menu.settings', to: '/admin/settings' },
    ],
  },
]

function filterMenu(items: AdminMenuItem[]): AdminMenuItem[] {
  return items.map((item) => {
    if (!item.children?.length) return item
    const children = item.children.filter((child) => {
      if (!FEATURE_ATTENDANCE_ENABLED && child.to === '/admin/lnd/attendance') return false
      return true
    })
    return { ...item, children }
  })
}

export const adminMenu: AdminMenuItem[] = filterMenu(adminMenuRaw)

type TranslateFn = (key: string) => string

export function resolveAdminTitle(path: string, t: TranslateFn) {
  // Prefer longest/exact child match so /admin/academic/schedules does not
  // resolve as the parent /admin/academic catalog entry.
  let best: { labelKey: string, len: number } | null = null
  for (const item of adminMenu) {
    if (item.to === path) return t(item.labelKey)
    for (const entry of item.children || []) {
      if (entry.to === path || path.startsWith(`${entry.to}/`)) {
        if (!best || entry.to.length > best.len) {
          best = { labelKey: entry.labelKey, len: entry.to.length }
        }
      }
    }
  }
  return best ? t(best.labelKey) : t('admin.systemTitle')
}

export function resolveAdminBreadcrumb(path: string, t: TranslateFn) {
  let best: { parent: AdminMenuItem, child: AdminMenuChild, len: number } | null = null
  for (const item of adminMenu) {
    if (item.to === path) return [{ label: t(item.labelKey), to: item.to }]
    for (const entry of item.children || []) {
      if (entry.to === path || path.startsWith(`${entry.to}/`)) {
        if (!best || entry.to.length > best.len) {
          best = { parent: item, child: entry, len: entry.to.length }
        }
      }
    }
  }
  if (!best) return []
  return [
    { label: t(best.parent.labelKey) },
    { label: t(best.child.labelKey), to: best.child.to },
  ]
}
