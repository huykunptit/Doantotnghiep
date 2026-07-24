<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MediaUpload from '~/components/common/MediaUpload.vue'

definePageMeta({ middleware: 'instructor', layout: 'instructor' })

const route = useRoute()
const router = useRouter()
const courseStore = useCourseStore()
const authStore = useAuthStore()
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const courseId = Number(route.params.id)
const pageLoading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const certificates = ref<any[]>([])

const canPublish = computed(() => authStore.user?.roles?.includes('admin'))

const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: 0,
  certificate_template_id: 0,
  thumbnail: '',
  status: 'draft' as 'draft' | 'published' | 'closed' | 'pending_review' | 'rejected',
})

const statusOptions = [
  { value: 'draft', label: 'Bản nháp', help: 'Đang soạn thảo', color: '#f59e0b' },
  { value: 'pending_review', label: 'Chờ duyệt', help: 'Gửi để admin xem xét', color: '#3b82f6' },
  { value: 'published', label: 'Xuất bản', help: 'Hiển thị công khai (cần admin duyệt)', color: '#22c55e' },
  { value: 'closed', label: 'Đã đóng', help: 'Ngừng ghi danh mới', color: '#ef4444' },
]

onMounted(async () => {
  if (authStore.token && !authStore.user) await authStore.fetchMe()
  await courseStore.fetchCategories()
  try {
    const [course, certsRes] = await Promise.all([
      courseStore.fetchCourse(courseId),
      useApi<any[]>('/admin/certificates', { headers: authHeaders() }).catch(() => []),
    ])
    if (!authStore.user?.roles?.includes('admin') && Number(course.user_id) !== Number(authStore.user?.id)) {
      return router.push(`/courses/${courseId}`)
    }
    certificates.value = Array.isArray(certsRes) ? certsRes : []
    form.title = course.title
    form.description = course.description ?? ''
    form.price = course.price
    form.category_id = Number((course as any).category_id || (course as any).category?.id || 0)
    form.certificate_template_id = Number((course as any).certificate_template_id || 0)
    form.thumbnail = course.thumbnail ?? ''
    form.status = course.status
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải thông tin khoá học.'
  }
  finally { pageLoading.value = false }
})

async function handleSubmit() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const payload = new FormData()
    payload.append('title', form.title)
    payload.append('description', form.description || '')
    payload.append('price', String(Number(form.price)))
    payload.append('status', form.status)
    if (form.category_id) payload.append('category_id', String(form.category_id))
    if (form.certificate_template_id) payload.append('certificate_template_id', String(form.certificate_template_id))
    if (form.thumbnail) payload.append('thumbnail', form.thumbnail)
    await courseStore.updateCourse(courseId, payload)
    success.value = 'Đã cập nhật thông tin khoá học thành công!'
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu thay đổi.'
  }
  finally { saving.value = false }
}
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên</p>
        <h2>Chỉnh sửa khoá học</h2>
        <p>Cập nhật thông tin, trạng thái và hình ảnh minh hoạ của khoá học.</p>
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <NuxtLink :to="`/courses/${courseId}`" target="_blank" class="crud-secondary-btn">Xem trước</NuxtLink>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="crud-secondary-btn">Quản lý bài học</NuxtLink>
      </div>
    </header>

    <!-- Loading skeleton -->
    <div v-if="pageLoading" class="dashboard-card" style="height: 420px; display: flex; align-items: center; justify-content: center;">
      <p style="color: var(--muted);">Đang tải thông tin khoá học...</p>
    </div>

    <template v-else>
      <div v-if="error" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>
      <div v-if="success" class="crud-alert is-success" style="margin-bottom: 16px;">{{ success }}</div>

      <section class="dashboard-card crud-panel">
        <form @submit.prevent="handleSubmit">
          <div class="crud-form-grid">
            <!-- Title -->
            <label class="crud-field crud-field-full">
              <span>Tên khoá học <span style="color:#ef4444">*</span></span>
              <input v-model="form.title" type="text" placeholder="Tên khoá học">
            </label>

            <!-- Description -->
            <label class="crud-field crud-field-full">
              <span>Mô tả khoá học</span>
              <textarea v-model="form.description" rows="5" placeholder="Mô tả chi tiết về khoá học..." />
            </label>

            <!-- Price -->
            <label class="crud-field">
              <span>Giá (VNĐ)</span>
              <input v-model.number="form.price" type="number" min="0">
            </label>

            <!-- Category -->
            <label class="crud-field">
              <span>Danh mục</span>
              <select v-model.number="form.category_id" class="crud-select">
                <option :value="0">— Chưa chọn —</option>
                <template v-for="cat in courseStore.categories" :key="cat.id">
                  <option :value="cat.id">{{ cat.name }}</option>
                  <option v-for="child in cat.children || []" :key="child.id" :value="child.id">└ {{ child.name }}</option>
                </template>
              </select>
            </label>

            <!-- Certificate -->
            <label class="crud-field">
              <span>Chứng chỉ hoàn thành</span>
              <select v-model.number="form.certificate_template_id" class="crud-select">
                <option :value="0">— Không cấp chứng chỉ —</option>
                <option v-for="cert in certificates" :key="cert.id" :value="cert.id">{{ cert.name }}</option>
              </select>
            </label>

            <!-- Status -->
            <div class="crud-field crud-field-full">
              <span class="crud-field-label">Trạng thái xuất bản</span>
              <div class="status-grid">
                <label
                  v-for="opt in statusOptions"
                  :key="opt.value"
                  class="status-opt"
                  :class="{ active: form.status === opt.value }"
                  :style="form.status === opt.value ? { '--status-color': opt.color } : {}"
                >
                  <input
                    v-model="form.status"
                    type="radio"
                    :value="opt.value"
                    :disabled="opt.value === 'published' && !canPublish"
                    style="display: none;"
                  >
                  <div>
                    <strong>{{ opt.label }}</strong>
                    <p>{{ opt.help }}</p>
                    <p v-if="opt.value === 'published' && !canPublish" style="color: #f59e0b; font-size: 0.7rem;">Cần quyền Admin</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Thumbnail -->
            <div class="crud-field crud-field-full">
              <span class="crud-field-label">Ảnh bìa khoá học</span>
              <MediaUpload
                v-model="form.thumbnail"
                folder="courses"
                variant="banner"
                label="Ảnh bìa"
                hint="JPG, PNG, WEBP — tối đa 5MB. Khuyến nghị 1280×720."
              />
            </div>
          </div>

          <!-- Submit -->
          <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--line);">
            <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Huỷ bỏ</NuxtLink>
            <button type="submit" class="crud-primary-btn" :disabled="saving">
              {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </form>
      </section>
    </template>
  </section>
</template>

<style scoped>
.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
  margin-top: 8px;
}
.status-opt {
  padding: 12px 14px;
  border: 2px solid var(--line);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}
.status-opt:hover { border-color: rgba(17,17,17,0.2); }
.status-opt.active {
  border-color: var(--status-color, var(--green));
  background: color-mix(in srgb, var(--status-color, var(--green)) 8%, transparent);
}
.status-opt strong { font-size: 0.875rem; }
.status-opt p { font-size: 0.75rem; color: var(--muted); margin: 3px 0 0; }
</style>
