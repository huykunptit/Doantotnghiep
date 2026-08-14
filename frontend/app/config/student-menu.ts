export interface StudentMenuItem {
  key: string
  labelKey: string
  icon: string
  to: string
}

export const studentMenu: StudentMenuItem[] = [
  { key: 'dashboard', labelKey: 'student.menu.dashboard', icon: 'pi pi-home', to: '/student' },
  { key: 'account', labelKey: 'student.menu.account', icon: 'pi pi-user-edit', to: '/student/profile' },
  { key: 'idCard', labelKey: 'student.menu.idCard', icon: 'pi pi-id-card', to: '/student/id-card' },
  { key: 'courses', labelKey: 'student.menu.myCourses', icon: 'pi pi-book', to: '/student/courses' },
  { key: 'curriculum', labelKey: 'student.menu.curriculum', icon: 'pi pi-sitemap', to: '/student/curriculum' },
  { key: 'timetable', labelKey: 'student.menu.timetable', icon: 'pi pi-calendar', to: '/student/timetable' },
  { key: 'exams', labelKey: 'student.menu.exams', icon: 'pi pi-file-edit', to: '/student/exams' },
  { key: 'transcript', labelKey: 'student.menu.transcript', icon: 'pi pi-list-check', to: '/student/transcript' },
  { key: 'attendance', labelKey: 'student.menu.attendance', icon: 'pi pi-qrcode', to: '/student/attendance' },
  { key: 'tuition', labelKey: 'student.menu.tuition', icon: 'pi pi-wallet', to: '/student/tuition' },
  { key: 'paths', labelKey: 'student.menu.paths', icon: 'pi pi-map', to: '/paths' },
  { key: 'points', labelKey: 'student.menu.points', icon: 'pi pi-star', to: '/student/points' },
  { key: 'leaderboard', labelKey: 'student.menu.leaderboard', icon: 'pi pi-chart-bar', to: '/student/leaderboard' },
  { key: 'certificates', labelKey: 'student.menu.certificates', icon: 'pi pi-verified', to: '/student/certificates' },
  { key: 'news', labelKey: 'student.menu.news', icon: 'pi pi-megaphone', to: '/news' },
  { key: 'career', labelKey: 'student.menu.career', icon: 'pi pi-briefcase', to: '/career' },
  { key: 'studyAdvisor', labelKey: 'student.menu.studyAdvisor', icon: 'pi pi-sparkles', to: '/student/study-advisor' },
  { key: 'experienceSurvey', labelKey: 'student.menu.experienceSurvey', icon: 'pi pi-comments', to: '/student/experience-survey' },
]

type TranslateFn = (key: string) => string

export function resolveStudentTitle(path: string, t: TranslateFn) {
  if (path.startsWith('/learn')) return t('student.menu.learn')
  if (path.startsWith('/paths')) return t('student.menu.paths')
  if (path.startsWith('/career')) return t('student.menu.career')
  if (path.startsWith('/student/id-card')) return t('student.menu.idCard')
  if (path.startsWith('/student/profile') || path.startsWith('/student/account')) return t('student.menu.account')
  if (path.startsWith('/student/study-advisor')) return t('student.menu.studyAdvisor')
  if (path.startsWith('/student/experience-survey')) return t('student.menu.experienceSurvey')
  if (path.startsWith('/student/points')) return t('student.menu.points')
  if (path.startsWith('/student/notifications')) return t('student.notif.title')
  if (path.startsWith('/news')) return t('student.menu.news')
  const match = studentMenu.find(item => item.to === path || (item.to !== '/student' && path.startsWith(`${item.to}/`)))
  return match ? t(match.labelKey) : t('student.console')
}

export function resolveStudentBreadcrumb(path: string, t: TranslateFn) {
  // Trang gốc: chỉ giữ title, tránh trùng "Tổng quan" với breadcrumb.
  if (path === '/student') return []
  if (path.startsWith('/paths')) {
    return [
      { label: t('student.menu.dashboard'), to: '/student' },
      { label: t('student.menu.paths'), to: '/paths' },
    ]
  }
  const match = studentMenu.find(item => item.to === path)
  if (!match) return [{ label: t('student.menu.dashboard'), to: '/student' }]
  return [
    { label: t('student.menu.dashboard'), to: '/student' },
    { label: t(match.labelKey), to: match.to },
  ]
}
