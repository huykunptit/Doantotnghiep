<template>
  <div class="form-page">
    <div class="form-card">
      <div class="card-header">
        <h1>Chỉnh sửa khóa học</h1>
        <NuxtLink :to="`/courses/${courseId}`" class="back-link">← Xem khóa học</NuxtLink>
      </div>

      <div v-if="pageLoading" class="loading">Đang tải...</div>

      <form v-else @submit.prevent="handleSubmit">
        <div class="field">
          <label>Tên khóa học *</label>
          <input v-model="form.title" type="text" required />
        </div>

        <div class="field">
          <label>Mô tả</label>
          <textarea v-model="form.description" rows="4" />
        </div>

        <div class="row-2">
          <div class="field">
            <label>Giá (VNĐ) *</label>
            <input v-model.number="form.price" type="number" min="0" step="1000" required />
          </div>
          <div class="field">
            <label>Danh mục</label>
            <select v-model.number="form.category_id">
              <option :value="0">-- Chưa chọn --</option>
              <template v-for="cat in courseStore.categories" :key="cat.id">
                <option :value="cat.id">{{ cat.name }}</option>
                <option v-for="child in cat.children || []" :key="child.id" :value="child.id">&nbsp;&nbsp;└ {{ child.name }}</option>
              </template>
            </select>
          </div>
        </div>

        <div class="field">
          <label>Trạng thái</label>
          <select v-model="form.status">
            <option value="draft">Nháp</option>
            <option value="published">Xuất bản</option>
            <option value="closed">Đóng</option>
          </select>
        </div>

        <div class="field">
          <label>URL ảnh bìa</label>
          <input v-model="form.thumbnail" type="url" placeholder="https://..." />
          <div v-if="form.thumbnail" class="thumb-preview">
            <img :src="form.thumbnail" alt="preview" @error="form.thumbnail = ''" />
          </div>
        </div>

        <p v-if="error" class="error-msg">{{ error }}</p>
        <p v-if="success" class="success-msg">{{ success }}</p>

        <div class="form-actions">
          <NuxtLink :to="`/courses/${courseId}/lessons`" class="btn-secondary">
            Quản lý bài học
          </NuxtLink>
          <button type="submit" :disabled="saving" class="btn-submit">
            {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const courseId = Number(route.params.id)
const pageLoading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: 0,
  thumbnail: '',
  status: 'draft' as 'draft' | 'published' | 'closed',
})

onMounted(async () => {
  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }

  await courseStore.fetchCategories()
  const course = await courseStore.fetchCourse(courseId)

  if (!auth.user?.roles?.includes('admin') && Number(course.user_id) !== Number(auth.user?.id)) {
    router.push(`/courses/${courseId}`)
    return
  }

  form.title = course.title
  form.description = course.description ?? ''
  form.price = course.price
  form.category_id = Number((course as any).category_id || (course as any).category?.id || 0)
  form.thumbnail = course.thumbnail ?? ''
  form.status = course.status
  pageLoading.value = false
})

async function handleSubmit() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await courseStore.updateCourse(courseId, {
      title: form.title,
      description: form.description || null,
      price: form.price,
      category_id: form.category_id || null,
      thumbnail: form.thumbnail || null,
      status: form.status,
    })
    success.value = 'Đã lưu thay đổi.'
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu thay đổi.'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.form-page { max-width: 700px; margin: 0 auto; }
.form-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 36px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
h1 { font-size: 22px; font-weight: 700; color: #111827; }
.back-link { font-size: 14px; color: #6b7280; text-decoration: none; }
.back-link:hover { color: #111827; }
.loading { text-align: center; padding: 40px; color: #6b7280; }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.field label { font-size: 14px; font-weight: 600; color: #374151; }
.field input, .field textarea, .field select { padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; resize: vertical; }
.field input:focus, .field textarea:focus, .field select:focus { border-color: #111827; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.thumb-preview { margin-top: 8px; border-radius: 8px; overflow: hidden; height: 140px; background: #f3f4f6; }
.thumb-preview img { width: 100%; height: 100%; object-fit: cover; }
.error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.success-msg { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; }
.btn-secondary { padding: 10px 20px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; text-decoration: none; font-weight: 600; font-size: 14px; }
.btn-submit { padding: 10px 24px; background: #111827; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
