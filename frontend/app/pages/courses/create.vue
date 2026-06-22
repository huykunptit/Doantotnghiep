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
    <!-- Page Header -->
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên / Studio sáng tạo</p>
        <h2>Tạo khoá học mới</h2>
        <p>Phác thảo thông tin cơ bản trước khi xây dựng chương học và tải lên bài giảng.</p>
      </div>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Hủy thiết lập</NuxtLink>
    </header>

    <div v-if="error" class="crud-alert is-error" style="margin-bottom: 20px;">{{ error }}</div>

    <!-- Dual Column Layout -->
    <div class="create-layout-grid">
      <!-- Main Form Column -->
      <form class="create-form-main" @submit.prevent="handleSubmit">
        <!-- Basic Info Section -->
        <div class="dashboard-card form-section-card">
          <div class="section-card-head">
            <span class="material-symbols-outlined header-icon">info</span>
            <h3>Thông tin cơ bản</h3>
          </div>
          <div class="fields-stack">
            <!-- Title -->
            <label class="premium-field">
              <span class="field-label">Tên khoá học <span class="required-star">*</span></span>
              <input
                v-model="form.title"
                type="text"
                class="premium-input"
                placeholder="Ví dụ: Lập trình Vue.js thực chiến từ cơ bản tới nâng cao"
                required
              >
            </label>

            <!-- Description -->
            <label class="premium-field">
              <span class="field-label">Mô tả tóm tắt</span>
              <textarea
                v-model="form.description"
                rows="6"
                class="premium-textarea"
                placeholder="Mô tả ngắn gọn về mục tiêu, nội dung chính và đối tượng học viên hướng tới..."
              />
            </label>
          </div>
        </div>

        <!-- Pricing & Catalog Section -->
        <div class="dashboard-card form-section-card">
          <div class="section-card-head">
            <span class="material-symbols-outlined header-icon">sell</span>
            <h3>Phân mục & Học phí</h3>
          </div>
          <div class="form-row-grid">
            <!-- Category -->
            <label class="premium-field">
              <span class="field-label">Danh mục</span>
              <select v-model.number="form.category_id" class="premium-select">
                <option :value="0" disabled>— Chọn danh mục học —</option>
                <template v-for="cat in courseStore.categories" :key="cat.id">
                  <option :value="cat.id">{{ cat.name }}</option>
                  <option v-for="child in cat.children || []" :key="child.id" :value="child.id">└ {{ child.name }}</option>
                </template>
              </select>
            </label>

            <!-- Price -->
            <label class="premium-field">
              <span class="field-label">Học phí (VNĐ)</span>
              <div class="price-input-wrapper">
                <input
                  v-model.number="form.price"
                  type="number"
                  min="0"
                  class="premium-input price-input"
                  placeholder="0 = Miễn phí"
                >
                <span class="currency-label">đ</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Certification Section -->
        <div class="dashboard-card form-section-card">
          <div class="section-card-head">
            <span class="material-symbols-outlined header-icon">workspace_premium</span>
            <h3>Chứng nhận hoàn thành</h3>
          </div>
          <div class="fields-stack">
            <label class="premium-field">
              <span class="field-label">Mẫu chứng chỉ</span>
              <select v-model.number="form.certificate_template_id" class="premium-select">
                <option :value="0">— Không cấp chứng chỉ —</option>
                <option v-for="cert in certificates" :key="cert.id" :value="cert.id">{{ cert.name }}</option>
              </select>
            </label>
          </div>
        </div>

        <!-- Form Submit Actions -->
        <div class="submit-actions-panel">
          <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Quay lại</NuxtLink>
          <button type="submit" class="crud-primary-btn px-6" :disabled="loading">
            <span v-if="loading" class="material-symbols-outlined spin-icon">progress_activity</span>
            {{ loading ? 'Đang khởi tạo...' : 'Tạo khóa học & Tiếp tục →' }}
          </button>
        </div>
      </form>

      <!-- Sidebar Media Upload Column -->
      <div class="create-sidebar-column">
        <!-- Thumbnail Section -->
        <div class="dashboard-card form-section-card">
          <div class="section-card-head">
            <span class="material-symbols-outlined header-icon">image</span>
            <h3>Ảnh bìa khóa học</h3>
          </div>
          <p class="sidebar-info-desc">Ảnh bìa sẽ được hiển thị ở catalog khóa học. Kích thước khuyến nghị: 1280x720 (tỷ lệ 16:9).</p>
          <div class="thumbnail-uploader-wrapper">
            <MediaUpload
              v-model="form.thumbnail"
              folder="courses"
              variant="banner"
              label="Tải lên ảnh bìa"
              hint="Hỗ trợ JPG, PNG, WEBP. Tối đa 5MB."
            />
          </div>
        </div>

        <!-- Tips Section -->
        <div class="dashboard-card form-section-card tips-card">
          <div class="section-card-head">
            <span class="material-symbols-outlined header-icon color-warning">lightbulb</span>
            <h3>Lời khuyên thiết lập</h3>
          </div>
          <ul class="tips-list">
            <li>
              <strong>Tên khóa học rõ ràng:</strong> Tránh đặt tên chung chung. Nên nêu rõ công nghệ và đối tượng (VD: "Laravel Cơ Bản Cho Người Mới").
            </li>
            <li>
              <strong>Định giá hợp lý:</strong> Các khóa học chất lượng có mức giá vừa phải thường thu hút nhiều lượt đăng ký hơn từ 30% - 50%.
            </li>
            <li>
              <strong>Hình ảnh chất lượng:</strong> Thumbnail trực quan, đẹp mắt giúp tăng tỷ lệ nhấp chuột vào chi tiết khóa học.
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Responsive layout grid */
.create-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
@media (min-width: 1024px) {
  .create-layout-grid {
    grid-template-columns: minmax(0, 2fr) 360px;
  }
}

.form-section-card {
  background: var(--color-neutral-0, #fff);
  padding: 24px;
  border-radius: 20px;
  border: 1px solid rgba(var(--green-rgb, 17, 51, 17), 0.05);
  box-shadow: 0 4px 20px rgba(var(--green-rgb, 17, 51, 17), 0.02);
  margin-bottom: 20px;
}

.section-card-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--color-neutral-200, #dde5e1);
  padding-bottom: 12px;
}
.section-card-head h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-neutral-800, #1f312b);
  font-family: 'Outfit', sans-serif;
}
.header-icon {
  color: #1d9e75; /* primary-400 */
  font-size: 20px;
}
.color-warning {
  color: var(--color-warning, #e9a23b);
}

.fields-stack {
  display: grid;
  gap: 18px;
}

.form-row-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}
@media (min-width: 640px) {
  .form-row-grid {
    grid-template-columns: 1fr 1fr;
  }
}

/* Premium Form Elements */
.premium-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.field-label {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--color-neutral-600, #4a6059);
}
.required-star {
  color: var(--color-error, #e24b4a);
}

.premium-input,
.premium-select,
.premium-textarea {
  background: var(--color-neutral-50, #f8faf9);
  border: 1.5px solid var(--color-neutral-200, #dde5e1);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.9rem;
  color: var(--color-neutral-900, #0e1a16);
  font-family: inherit;
  outline: none;
  transition: all 0.2s ease;
}

.premium-input:focus,
.premium-select:focus,
.premium-textarea:focus {
  border-color: #1d9e75;
  background: var(--color-neutral-0, #fff);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.12);
}

.premium-textarea {
  resize: vertical;
}

/* Price currency input wrapper */
.price-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.price-input {
  padding-right: 40px;
  width: 100%;
}
.currency-label {
  position: absolute;
  right: 16px;
  font-weight: 700;
  color: var(--color-neutral-600, #4a6059);
  pointer-events: none;
}

/* Sidebar Specifics */
.sidebar-info-desc {
  font-size: 0.8rem;
  color: var(--color-neutral-600, #4a6059);
  line-height: 1.5;
  margin: 0 0 16px;
}
.thumbnail-uploader-wrapper {
  border-radius: 14px;
  overflow: hidden;
}

.tips-card {
  background: #faece7; /* accent-50 background for warning/bulb card */
  border-color: rgba(216, 90, 48, 0.15);
}
.tips-list {
  margin: 0;
  padding-left: 20px;
  display: grid;
  gap: 12px;
}
.tips-list li {
  font-size: 0.8rem;
  color: var(--color-neutral-600, #4a6059);
  line-height: 1.6;
}
.tips-list li strong {
  color: var(--color-neutral-800, #1f312b);
}

.submit-actions-panel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  padding: 16px 0;
  border-top: 1px dashed var(--color-neutral-200, #dde5e1);
}

/* Spin animation */
.spin-icon {
  font-size: 16px;
  margin-right: 6px;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

[data-theme="dark"] .form-section-card {
  background: var(--color-neutral-100, #142d1f);
  border-color: rgba(255, 255, 255, 0.05);
}
[data-theme="dark"] .section-card-head {
  border-color: rgba(255, 255, 255, 0.08);
}
[data-theme="dark"] .premium-input,
[data-theme="dark"] .premium-select,
[data-theme="dark"] .premium-textarea {
  background: rgba(255, 255, 255, 0.02);
  border-color: rgba(255, 255, 255, 0.08);
  color: #fff;
}
[data-theme="dark"] .premium-input:focus,
[data-theme="dark"] .premium-select:focus,
[data-theme="dark"] .premium-textarea:focus {
  background: rgba(255, 255, 255, 0.04);
}
[data-theme="dark"] .tips-card {
  background: rgba(216, 90, 48, 0.06);
}
</style>
