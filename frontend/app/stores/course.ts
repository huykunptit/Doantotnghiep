import { defineStore } from 'pinia'

export interface Course {
  id: number
  user_id: number
  title: string
  description?: string | null
  price: number
  status: 'draft' | 'published' | 'closed'
  thumbnail?: string | null
  category?: { id: number; name: string; slug?: string } | string | null
  lessons_count?: number
  enrollments_count?: number
  is_enrolled?: boolean
  created_at?: string
  instructor?: {
    id: number
    name: string
    avatar?: string | null
  }
  lessons?: Lesson[]
}

export interface Lesson {
  id: number
  course_id: number
  section_id?: number | null
  title: string
  description?: string | null
  video_url?: string | null
  video_status?: 'pending' | 'processing' | 'ready' | 'failed'
  video_size?: string | null
  order: number
  duration: number
  is_preview?: boolean
  locked?: boolean
}

export interface LessonProgress {
  lesson_id: number
  completed: boolean
  watched_seconds: number
  last_watched_at?: string | null
}

export interface CourseProgress {
  total_lessons: number
  completed_lessons: number
  percent: number
  lessons: (Lesson & { completed: boolean; watched_seconds: number })[]
}

export interface Order {
  id: number
  user_id: number
  course_id: number
  amount: number
  status: 'pending' | 'paid' | 'failed'
  payment_ref?: string | null
  created_at?: string
  course?: Pick<Course, 'id' | 'title' | 'thumbnail' | 'price'>
  payment_url?: string | null
}

export interface Enrollment {
  id: number
  user_id: number
  course_id: number
  enrolled_at: string
  progress: number
  course: Pick<Course, 'id' | 'title' | 'thumbnail' | 'price' | 'status' | 'category'>
}

export interface CourseCategory {
  id: number
  name: string
  slug: string
  parent_id?: number | null
  children?: CourseCategory[]
}

export const useCourseStore = defineStore('course', {
  state: () => ({
    courses: [] as Course[],
    currentCourse: null as Course | null,
    currentLesson: null as Lesson | null,
    currentProgress: null as CourseProgress | null,
    enrollments: [] as Enrollment[],
    orders: [] as Order[],
    categories: [] as CourseCategory[],
    loading: false,
    error: null as string | null,
  }),

  getters: {
    enrolledCourseIds: (state) => state.enrollments.map((e) => e.course_id),
    isEnrolled: (state) => (courseId: number) =>
      state.enrollments.some((e) => e.course_id === courseId),
  },

  actions: {
    async fetchCourses(params?: {
      search?: string
      category?: string
      page?: number
      per_page?: number
    }) {
      this.loading = true
      this.error = null
      try {
        const query = new URLSearchParams()
        if (params?.search) query.set('search', params.search)
        if (params?.category) query.set('category', params.category)
        if (params?.page) query.set('page', String(params.page))
        if (params?.per_page) query.set('per_page', String(params.per_page))

        const data = await useApi<{ data: Course[]; total: number; last_page: number }>(
          `/courses?${query}`,
        )
        this.courses = data.data
        return data
      } catch (e: any) {
        this.error = e?.data?.message || 'Failed to load courses'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchMyCourses() {
      const auth = useAuthStore()
      const data = await useApi<{ data: Course[] }>('/my-courses', { token: auth.token })
      return data.data
    },

    async fetchCourse(id: number) {
      this.loading = true
      this.error = null
      try {
        const course = await useApi<Course>(`/courses/${id}`)
        this.currentCourse = course
        return course
      } catch (e: any) {
        this.error = e?.data?.message || 'Failed to load course'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchLessons(courseId: number) {
      const auth = useAuthStore()
      return await useApi<Lesson[]>(`/courses/${courseId}/lessons`, {
        token: auth.token,
      })
    },

    async fetchLesson(courseId: number, lessonId: number) {
      const auth = useAuthStore()
      const lesson = await useApi<Lesson>(`/courses/${courseId}/lessons/${lessonId}`, {
        token: auth.token,
      })
      this.currentLesson = lesson
      return lesson
    },

    async fetchVideoUrl(courseId: number, lessonId: number) {
      const auth = useAuthStore()
      return await useApi<{ url: string; expires_in: number }>(
        `/courses/${courseId}/lessons/${lessonId}/video-url`,
        { token: auth.token },
      )
    },

    async fetchCategories() {
      const cats = await useApi<CourseCategory[]>('/categories')
      this.categories = cats
      return cats
    },

    async createCourse(payload: {
      title: string
      description?: string | null
      price: number
      thumbnail?: string | null
      category_id?: number | null
    }) {
      const auth = useAuthStore()
      const data = await useApi<{ course: Course }>('/courses', {
        method: 'POST',
        body: payload,
        token: auth.token,
      })
      return data.course
    },

    async updateCourse(id: number, payload: {
      title?: string
      description?: string | null
      price?: number
      thumbnail?: string | null
      status?: 'draft' | 'published' | 'closed'
      category_id?: number | null
    }) {
      const auth = useAuthStore()
      const data = await useApi<{ course: Course }>(`/courses/${id}`, {
        method: 'PUT',
        body: payload,
        token: auth.token,
      })
      return data.course
    },

    async deleteCourse(id: number) {
      const auth = useAuthStore()
      await useApi(`/courses/${id}`, { method: 'DELETE', token: auth.token })
    },

    async publishCourse(id: number) {
      const auth = useAuthStore()
      const data = await useApi<{ course: Course }>(`/courses/${id}/publish`, {
        method: 'POST',
        token: auth.token,
      })
      return data.course
    },

    async createLesson(courseId: number, payload: Partial<Lesson>) {
      const auth = useAuthStore()
      const data = await useApi<{ lesson: Lesson }>(`/courses/${courseId}/lessons`, {
        method: 'POST',
        body: payload,
        token: auth.token,
      })
      return data.lesson
    },

    async updateLesson(courseId: number, lessonId: number, payload: Partial<Lesson>) {
      const auth = useAuthStore()
      const data = await useApi<{ lesson: Lesson }>(
        `/courses/${courseId}/lessons/${lessonId}`,
        { method: 'PUT', body: payload, token: auth.token },
      )
      return data.lesson
    },

    async deleteLesson(courseId: number, lessonId: number) {
      const auth = useAuthStore()
      await useApi(`/courses/${courseId}/lessons/${lessonId}`, {
        method: 'DELETE',
        token: auth.token,
      })
    },

    async saveProgress(courseId: number, lessonId: number, watchedSeconds: number) {
      const auth = useAuthStore()
      await useApi(`/courses/${courseId}/lessons/${lessonId}/progress`, {
        method: 'POST',
        body: { watched_seconds: watchedSeconds },
        token: auth.token,
      })
    },

    async fetchCourseProgress(courseId: number) {
      const auth = useAuthStore()
      const data = await useApi<CourseProgress>(`/courses/${courseId}/progress`, {
        token: auth.token,
      })
      this.currentProgress = data
      return data
    },

    async fetchEnrollments() {
      const auth = useAuthStore()
      const data = await useApi<Enrollment[]>('/enrollments', { token: auth.token })
      this.enrollments = data
      return data
    },

    async createOrder(courseId: number, paymentMethod: 'vnpay' | 'momo' | 'zalopay' | 'bank_transfer' = 'vnpay') {
      const auth = useAuthStore()
      return await useApi<{ order: Order; payment_url?: string; enrolled?: boolean; message: string }>(
        '/orders',
        { method: 'POST', body: { course_id: courseId, payment_method: paymentMethod }, token: auth.token },
      )
    },

    async fetchOrders() {
      const auth = useAuthStore()
      const data = await useApi<{ data: Order[] }>('/orders', { token: auth.token })
      this.orders = data.data
      return data.data
    },
  },
})
