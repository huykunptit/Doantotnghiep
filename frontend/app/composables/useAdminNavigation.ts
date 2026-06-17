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
        { label: 'Bảng điều khiển', icon: 'layout-dashboard', to: '/admin' },
      ],
    },
    {
      label: 'Người dùng',
      items: [
        { label: 'Người dùng', icon: 'users', to: '/admin/users' },
        { label: 'Phân quyền', icon: 'shield-check', to: '/admin/roles' },
      ],
    },
    {
      label: 'Tổ chức & Học vụ',
      items: [
        { label: 'Tổ chức & Học vụ', icon: 'building-2', to: '/admin/academic' },
      ],
    },
    {
      label: 'Khóa học',
      items: [
        { label: 'Kiểm duyệt khóa học', icon: 'clipboard-check', to: '/admin/courses' },
        { label: 'Khóa học', icon: 'book-open', to: '/admin/manage-courses' },
        { label: 'Danh mục', icon: 'folder-open', to: '/admin/categories' },
        { label: 'Đánh giá', icon: 'star', to: '/admin/reviews' },
        { label: 'Chứng chỉ', icon: 'award', to: '/admin/certificates' },
      ],
    },
    {
      label: 'Khảo thí',
      items: [
        { label: 'Quiz / Đề thi', icon: 'file-check', to: '/admin/quiz' },
        { label: 'Theo dõi kỳ thi', icon: 'monitor-check', to: '/admin/reports/exam-tracking' },
        { label: 'Giám sát thi', icon: 'eye', to: '/admin/exam-monitor' },
      ],
    },
    {
      label: 'Báo cáo',
      items: [
        { label: 'Báo cáo khóa học', icon: 'trending-up', to: '/admin/reports/courses' },
        { label: 'Báo cáo kỳ thi', icon: 'bar-chart-2', to: '/admin/reports/exams' },
        { label: 'Tiến độ học tập', icon: 'activity', to: '/admin/reports/progress' },
        { label: 'Nhật ký hoạt động', icon: 'list-checks', to: '/admin/reports/activity' },
        { label: 'Lịch sử báo lỗi', icon: 'triangle-alert', to: '/admin/reports/errors' },
      ],
    },
    {
      label: 'Tài chính',
      items: [
        { label: 'Đơn hàng', icon: 'receipt-text', to: '/admin/orders' },
        { label: 'Báo cáo thanh toán', icon: 'wallet', to: '/admin/reports/payments' },
        { label: 'Yêu cầu rút tiền', icon: 'banknote', to: '/admin/payouts' },
      ],
    },
    {
      label: 'AI & Hệ thống',
      items: [
        { label: 'Quản lý AI', icon: 'bot', to: '/admin/ai' },
        { label: 'Trò chuyện', icon: 'message-circle', to: '/admin/chat' },
        { label: 'Mẫu email', icon: 'mail', to: '/admin/email-templates' },
        { label: 'Cài đặt', icon: 'settings', to: '/admin/settings' },
      ],
    },
  ]

  const supportItems: AdminSupportItem[] = [
    { label: 'Thông báo hệ thống', comingSoon: true },
    { label: 'Trợ giúp kỹ thuật', comingSoon: true },
  ]

  return { groups, supportItems }
}
