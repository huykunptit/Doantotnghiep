export const LESSON_TYPES = {
  VIDEO: 'video',
  DOCUMENT: 'document',
  QUIZ: 'quiz',
  ASSIGNMENT: 'assignment',
  VIRTUAL_CLASS: 'virtual_class',
  SCORM: 'scorm',
  H5P: 'h5p',
} as const

export type LessonType = (typeof LESSON_TYPES)[keyof typeof LESSON_TYPES]

export const LESSON_TYPE_LABELS: Record<LessonType, string> = {
  video: 'Video',
  document: 'Tài liệu',
  quiz: 'Quiz',
  assignment: 'Bài tập',
  virtual_class: 'Lớp học trực tuyến',
  scorm: 'SCORM',
  h5p: 'H5P / Embed',
}
