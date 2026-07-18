/**
 * Student Menu Configuration
 * Provides dynamic menu items for students using Nuxt route paths
 */

import { computed } from 'vue'
import { useRoute } from 'vue-router'
import type { MenuItem } from '~/composables/useAdminMenuConfig'

function buildAllStudentGroups(): MenuItem[] {
  return [
    {
      key: 'overview',
      label: 'Tổng quan',
      icon: 'pi pi-home',
      to: '/student',
    },
    {
      key: 'calendar',
      label: 'Lịch học',
      icon: 'pi pi-calendar',
      to: '/student/calendar',
    },
    {
      key: 'learning',
      label: 'Học tập',
      icon: 'pi pi-book',
      items: [
        { label: 'Khóa học của tôi', to: '/student/courses' },
        { label: 'Lộ trình học', to: '/student/learning-path' },
        { label: 'Kỳ thi', to: '/student/exams' },
        { label: 'Nhiệm vụ', to: '/student/tasks' },
      ],
    },
    {
      key: 'academic',
      label: 'Học vụ',
      icon: 'pi pi-graduation-cap',
      items: [
        { label: 'Bảng điểm / GPA', to: '/student/transcript' },
        { label: 'Chứng chỉ', to: '/student/certificates' },
        { label: 'Học phí', to: '/student/tuition' },
        { label: 'Thành tích', to: '/student/achievements' },
        { label: 'Điểm & Phần thưởng', to: '/student/points' },
      ],
    },
    {
      key: 'community',
      label: 'Cộng đồng',
      icon: 'pi pi-comments',
      items: [
        { label: 'Diễn đàn', to: '/student/forum' },
        { label: 'Khảo sát', to: '/student/surveys' },
        { label: 'Helpdesk', to: '/student/helpdesk' },
      ],
    },
    {
      key: 'explore',
      label: 'Khám phá',
      icon: 'pi pi-compass',
      items: [
        { label: 'Gợi ý khóa học', to: '/student/recommendations' },
        { label: 'Thư viện tài liệu', to: '/student/library' },
      ],
    },
    {
      key: 'support',
      label: 'Kênh hỗ trợ',
      icon: 'pi pi-headphones',
      items: [
        { label: 'Thông báo', to: '/student/notifications' },
        { label: 'Chat với AI', to: '/student/ai-chat' },
      ],
    },
  ]
}

export function useStudentMenuConfig() {
  const route = useRoute()

  const menuItems = computed<MenuItem[]>(() => {
    return buildAllStudentGroups()
  })

  return {
    menuItems,
  }
}
