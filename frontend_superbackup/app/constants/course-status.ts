export const COURSE_STATUSES = {
  DRAFT: 'draft',
  PENDING: 'pending',
  PUBLISHED: 'published',
  REJECTED: 'rejected',
} as const

export type CourseStatus = (typeof COURSE_STATUSES)[keyof typeof COURSE_STATUSES]

export const COURSE_STATUS_LABELS: Record<CourseStatus, string> = {
  draft: 'Bản nháp',
  pending: 'Chờ duyệt',
  published: 'Đã xuất bản',
  rejected: 'Bị từ chối',
}

