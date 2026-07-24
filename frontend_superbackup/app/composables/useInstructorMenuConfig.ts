/**
 * Instructor Menu Configuration
 * Provides dynamic menu items for instructors using Nuxt route paths
 */

import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import type { MenuItem } from '~/composables/useAdminMenuConfig'

function buildAllInstructorGroups(): MenuItem[] {
  return [
    {
      key: 'overview',
      label: 'Tổng quan',
      icon: 'pi pi-home',
      to: '/instructor',
    },
    {
      key: 'courses',
      label: 'Khóa học',
      icon: 'pi pi-book',
      items: [
        { label: 'Khóa học của tôi', to: '/instructor/courses' },
        { label: 'Tạo khóa học mới', to: '/courses/create' },
      ],
    },
    {
      key: 'assessment',
      label: 'Khảo thí',
      icon: 'pi pi-clipboard',
      items: [
        { label: 'Ngân hàng câu hỏi', to: '/instructor/question-bank' },
        { label: 'Đợt thi', to: '/instructor/exams' },
      ],
    },
    {
      key: 'academic',
      label: 'Học vụ',
      icon: 'pi pi-graduation-cap',
      items: [
        { label: 'Lớp học phần & điểm', to: '/instructor/sections' },
      ],
    },
    {
      key: 'lnd',
      label: 'Quản trị L&D',
      icon: 'pi pi-map',
      items: [
        { label: 'Quản lý lớp học', to: '/admin/lnd/classes' },
        { label: 'Quản lý lộ trình', to: '/admin/lnd/learning-paths' },
        { label: 'Ghi danh lớp/lộ trình', to: '/admin/lnd/class-path-enrollment' },
        { label: 'Ghi danh bằng tệp', to: '/admin/lnd/file-based-enrollment' },
        { label: 'Báo cáo tiến độ L&D', to: '/admin/lnd/reports' },
      ],
    },
    {
      key: 'business',
      label: 'Kinh doanh',
      icon: 'pi pi-chart-line',
      items: [
        { label: 'Học viên', to: '/instructor/students' },
        { label: 'Doanh thu', to: '/instructor/revenue' },
      ],
    },
  ]
}

export function useInstructorMenuConfig() {
  const auth = useAuthStore()
  const route = useRoute()

  const menuItems = computed<MenuItem[]>(() => {
    return buildAllInstructorGroups()
  })

  return {
    menuItems,
  }
}
