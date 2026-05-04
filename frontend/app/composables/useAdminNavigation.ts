export interface AdminNavItem {
  label: string
  icon: string
  to: string
}

export interface AdminNavGroup {
  label: string
  items: AdminNavItem[]
}

export interface AdminSupportItem {
  label: string
  to?: string
  comingSoon?: boolean
}

export function useAdminNavigation() {
  const groups: AdminNavGroup[] = [
    {
      label: 'Tổng quan',
      items: [
        { label: 'Bảng điều khiển', icon: '◧', to: '/admin' },
      ],
    },
    {
      label: 'Quản lý người dùng',
      items: [
        { label: 'Người dùng', icon: '◎', to: '/admin/users' },
        { label: 'Phân quyền', icon: '◐', to: '/admin/roles' },
      ],
    },
    {
      label: 'Quản lý khóa học',
      items: [
        { label: 'Kiểm duyệt khóa học', icon: '◫', to: '/admin/courses' },
        { label: 'Khóa học', icon: '◧', to: '/admin/manage-courses' },
        { label: 'Danh mục', icon: '△', to: '/admin/categories' },
      ],
    },
    {
      label: 'Quản lý thi',
      items: [
        { label: 'Ngân hàng câu hỏi', icon: '◬', to: '/admin/question-bank' },
        { label: 'Quiz / Đề thi', icon: '◭', to: '/admin/quiz' },
      ],
    },
    {
      label: 'Quản trị hệ thống',
      items: [
        { label: 'Đơn hàng', icon: '◒', to: '/admin/orders' },
        { label: 'Cài đặt', icon: '◌', to: '/admin/settings' },
      ],
    },
  ]

  const supportItems: AdminSupportItem[] = [
    { label: 'Thông báo hệ thống', comingSoon: true },
    { label: 'Nhật ký hoạt động', comingSoon: true },
    { label: 'Trợ giúp kỹ thuật', comingSoon: true },
  ]

  return {
    groups,
    supportItems,
  }
}
