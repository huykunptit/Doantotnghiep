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
      label: 'Tổ chức & Học vụ',
      items: [
        { label: 'Tổ chức & Học vụ', icon: '◪', to: '/admin/academic' },
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
        { label: 'Theo dõi kỳ thi', icon: '◈', to: '/admin/reports/exam-tracking' },
      ],
    },
    {
      label: 'Báo cáo & Thống kê',
      items: [
        { label: 'Báo cáo khóa học', icon: '▣', to: '/admin/reports/courses' },
        { label: 'Báo cáo kỳ thi', icon: '▥', to: '/admin/reports/exams' },
        { label: 'Tiến độ học tập', icon: '▤', to: '/admin/reports/progress' },
        { label: 'Lịch sử báo lỗi', icon: '▲', to: '/admin/reports/errors' },
      ],
    },
    {
      label: 'Tài chính',
      items: [
        { label: 'Đơn hàng', icon: '◒', to: '/admin/orders' },
        { label: 'Báo cáo thanh toán', icon: '▩', to: '/admin/reports/payments' },
        { label: 'Yêu cầu rút tiền', icon: '◈', to: '/admin/payouts' },
      ],
    },
    {
      label: 'Hỗ trợ & Hệ thống',
      items: [
        { label: 'Trò chuyện', icon: '◓', to: '/admin/chat' },
        { label: 'Nhật ký hoạt động', icon: '▥', to: '/admin/reports/activity' },
        { label: 'Cài đặt', icon: '◌', to: '/admin/settings' },
      ],
    },
  ]

  const supportItems: AdminSupportItem[] = [
    { label: 'Thông báo hệ thống', comingSoon: true },
    { label: 'Trợ giúp kỹ thuật', comingSoon: true },
  ]

  return {
    groups,
    supportItems,
  }
}
