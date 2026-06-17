<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import MediaUpload from '~/components/common/MediaUpload.vue'

definePageMeta({ middleware: 'instructor', layout: 'instructor' })

const router = useRouter()
const courseStore = useCourseStore()
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(false)
const error = ref('')
const certificates = ref<any[]>([])
const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: 0,
  certificate_template_id: 0,
  thumbnail: '',
})

onMounted(async () => {
  await courseStore.fetchCategories()
  try {
    const res = await useApi<any[]>('/admin/certificates', { headers: authHeaders() })
    certificates.value = Array.isArray(res) ? res : []
  }
  catch {}
})

async function handleSubmit() {
  if (!form.title.trim()) { error.value = 'Tên khoá học không được để trống.'; return }
  loading.value = true
  error.value = ''
  try {
    const payload = new FormData()
    payload.append('title', form.title)
    payload.append('description', form.description || '')
    payload.append('price', String(Number(form.price)))
    if (form.category_id) payload.append('category_id', String(form.category_id))
    if (form.certificate_template_id) payload.append('certificate_template_id', String(form.certificate_template_id))
    if (form.thumbnail) payload.append('thumbnail', form.thumbnail)
    const course = await courseStore.createCourse(payload)
    router.push(`/instructor/courses/${course.id}/curriculum`)
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tạo khoá học. Vui lòng thử lại.'
  }
  finally { loading.value = false }
}
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên</p>
        <h2>Tạo khoá học mới</h2>
        <p>Điền thông tin cơ bản trước khi thêm section, bài học và tài nguyên học tập.</p>
      </div>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Huỷ</NuxtLink>
    </header>

    <div v-if="error" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>

    <section class="dashboard-card crud-panel">
      <div class="card-head" style="margin-bottom: 24px;">
        <h3>Thông tin khoá học</h3>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="crud-form-grid">
          <!-- Title -->
          <label class="crud-field crud-field-full">
            <span>Tên khoá học <span style="color:#ef4444">*</span></span>
            <input
              v-model="form.title"
              type="text"
              placeholder="Ví dụ: Laravel thực chiến cho người mới bắt đầu"
            >
          </label>

          <!-- Description -->
          <label class="crud-field crud-field-full">
            <span>Mô tả khoá học</span>
            <textarea
              v-model="form.description"
              rows="5"
              placeholder="Mô tả mục tiêu, nội dung chính và đối tượng học viên phù hợp..."
            />
          </label>

          <!-- Category -->
          <label class="crud-field">
            <span>Danh mục</span>
            <select v-model.number="form.category_id" class="crud-select">
              <option :value="0" disabled>— Chọn danh mục —</option>
              <template v-for="cat in courseStore.categories" :key="cat.id">
                <option :value="cat.id">{{ cat.name }}</option>
                <option v-for="child in cat.children || []" :key="child.id" :value="child.id">└ {{ child.name }}</option>
              </template>
            </select>
          </label>

          <!-- Price -->
          <label class="crud-field">
            <span>Giá (VNĐ)</span>
            <input v-model.number="form.price" type="number" min="0" placeholder="0 = Miễn phí">
          </label>

          <!-- Certificate -->
          <label class="crud-field">
            <span>Chứng chỉ hoàn thành</span>
            <select v-model.number="form.certificate_template_id" class="crud-select">
              <option :value="0">— Không cấp chứng chỉ —</option>
              <option v-for="cert in certificates" :key="cert.id" :value="cert.id">{{ cert.name }}</option>
            </select>
          </label>

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
          <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Huỷ</NuxtLink>
          <button type="submit" class="crud-primary-btn" :disabled="loading">
            {{ loading ? 'Đang tạo...' : 'Tạo khoá học →' }}
          </button>
        </div>
      </form>
    </section>
  </section>
</template>
