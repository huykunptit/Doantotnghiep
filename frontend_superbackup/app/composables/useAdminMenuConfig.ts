/**
 * Admin Menu Configuration
 * Ported from admin-ui/src/config/menuConfig.js
 * Dynamic menu items based on user permissions, with breadcrumb builder
 */

import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

// ─── Types ───────────────────────────────────────────────────
export interface MenuItem {
  key: string
  name?: string
  label: string
  icon: string
  to?: string
  items?: MenuChildItem[]
  badge?: number | string
}

export interface MenuChildItem {
  label: string
  to: string
}

export interface BreadcrumbItem {
  label: string
  to?: string
}

// ─── Child → Parent route map ────────────────────────────────
// Maps routes outside the menu (Create, Edit, Detail…) to their parent menu route
export const CHILD_PARENT_MAP: Record<string, string> = {
  // Quiz
  '/admin/quiz/create': '/admin/quiz',
  // Question Bank
  '/admin/question-bank/create': '/admin/question-bank',
  '/admin/question-bank/edit': '/admin/question-bank',
  // Enrollment
  '/admin/enrollments': '/admin/lnd/class-path-enrollment',
  '/admin/lnd/file-based-enrollment': '/admin/lnd/class-path-enrollment',
  // LnD
}

// ─── Build all menu groups (for breadcrumb resolution) ───────
function buildAllGroups(): MenuItem[] {
  return [
    {
      key: 'dashboard',
      label: 'Bảng điều khiển',
      icon: 'pi pi-home',
      to: '/admin',
    },
    {
      key: 'people',
      label: 'Người dùng',
      icon: 'pi pi-users',
      items: [
        { label: 'Người dùng', to: '/admin/users' },
        { label: 'Phân quyền', to: '/admin/roles' },
      ],
    },
    {
      key: 'academic',
      label: 'Đào tạo & Học vụ',
      icon: 'pi pi-graduation-cap',
      items: [
        { label: 'Danh mục đào tạo', to: '/admin/academic' },
        { label: 'Năm học & Học kỳ', to: '/admin/lnd/academic-calendar' },
        { label: 'Chương trình đào tạo', to: '/admin/lnd/learning-paths' },
        { label: 'Lớp hành chính', to: '/admin/lnd/classes' },
        { label: 'Lớp tín chỉ', to: '/admin/lnd/course-sections' },
        { label: 'Ghi danh học phần', to: '/admin/lnd/class-path-enrollment' },
        { label: 'Báo cáo đào tạo', to: '/admin/lnd/reports' },
      ],
    },
    {
      key: 'courses',
      label: 'Khóa học',
      icon: 'pi pi-book',
      items: [
        { label: 'Kiểm duyệt khóa học', to: '/admin/courses' },
        { label: 'Khóa học', to: '/admin/manage-courses' },
        { label: 'Danh mục', to: '/admin/categories' },
        { label: 'Đánh giá', to: '/admin/reviews' },
        { label: 'Chứng chỉ', to: '/admin/certificates' },
      ],
    },
    {
      key: 'assessment',
      label: 'Khảo thí',
      icon: 'pi pi-clipboard',
      items: [
        { label: 'Quiz / Đề thi', to: '/admin/quiz' },
        { label: 'Ngân hàng câu hỏi', to: '/admin/question-bank' },
        { label: 'Theo dõi kỳ thi', to: '/admin/reports/exam-tracking' },
        { label: 'Giám sát thi', to: '/admin/exam-monitor' },
      ],
    },
    {
      key: 'reports',
      label: 'Báo cáo',
      icon: 'pi pi-chart-line',
      items: [
        { label: 'Báo cáo khóa học', to: '/admin/reports/courses' },
        { label: 'Báo cáo kỳ thi', to: '/admin/reports/exams' },
        { label: 'Tiến độ học tập', to: '/admin/reports/progress' },
        { label: 'Nhật ký hoạt động', to: '/admin/reports/activity' },
        { label: 'Lịch sử báo lỗi', to: '/admin/reports/errors' },
      ],
    },
    {
      key: 'finance',
      label: 'Tài chính',
      icon: 'pi pi-wallet',
      items: [
        { label: 'Đơn hàng', to: '/admin/orders' },
        { label: 'Báo cáo thanh toán', to: '/admin/reports/payments' },
        { label: 'Yêu cầu rút tiền', to: '/admin/payouts' },
        { label: 'Điểm & Phần thưởng', to: '/admin/points' },
      ],
    },
    {
      key: 'system',
      label: 'AI & Hệ thống',
      icon: 'pi pi-cog',
      items: [
        { label: 'Quản lý AI', to: '/admin/ai' },
        { label: 'Trò chuyện', to: '/admin/chat' },
        { label: 'Mẫu email', to: '/admin/email-templates' },
        { label: 'Cài đặt', to: '/admin/settings' },
      ],
    },
  ]
}

// ─── Build breadcrumb from menu structure ─────────────────────
export function buildBreadcrumb(currentPath: string): BreadcrumbItem[] {
  const allGroups = buildAllGroups()

  // Find which group+item owns a path
  const findInMenu = (path: string) => {
    for (const group of allGroups) {
      if (group.to === path) return { group: null, item: group }
      if (group.items) {
        const item = group.items.find((c) => c.to === path)
        if (item) return { group, item }
      }
    }
    return null
  }

  // 1. Route is directly in the menu
  const direct = findInMenu(currentPath)
  if (direct) {
    const crumbs: BreadcrumbItem[] = []
    if (direct.group) crumbs.push({ label: direct.group.label })
    crumbs.push({ label: direct.item.label, to: currentPath })
    return crumbs
  }

  // 2. Route is outside the menu — find its declared parent
  const parentPath = CHILD_PARENT_MAP[currentPath]
  if (parentPath) {
    const parentMatch = findInMenu(parentPath)
    const crumbs: BreadcrumbItem[] = []
    if (parentMatch?.group) crumbs.push({ label: parentMatch.group.label })
    if (parentMatch?.item) crumbs.push({ label: parentMatch.item.label, to: parentPath })
    return crumbs
  }

  // 3. Try partial match (e.g. /admin/users/123 → /admin/users)
  for (const group of allGroups) {
    if (group.items) {
      for (const item of group.items) {
        if (currentPath.startsWith(item.to + '/')) {
          return [
            { label: group.label },
            { label: item.label, to: item.to },
          ]
        }
      }
    }
  }

  return []
}

// ─── Main composable ─────────────────────────────────────────
export function useAdminMenuConfig() {
  const auth = useAuthStore()
  const route = useRoute()

  const menuItems = computed<MenuItem[]>(() => {
    const isAdmin = auth.user?.roles?.includes('admin') ?? false

    // For now, all items visible. In future, filter by permissions.
    const items = buildAllGroups()

    // Only show system settings group for admin
    if (!isAdmin) {
      return items.filter((item) => item.key !== 'system')
    }

    return items
  })

  const breadcrumbs = computed(() => buildBreadcrumb(route.path))

  return {
    menuItems,
    breadcrumbs,
  }
}
