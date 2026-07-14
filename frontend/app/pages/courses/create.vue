<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import MediaUpload from '~/components/common/MediaUpload.vue'
// Icons removed - using PrimeIcons

definePageMeta({ middleware: 'instructor', layout: 'instructor' })

const router = useRouter()
const courseStore = useCourseStore()
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(false)
const error = ref('')
const certificates = ref<any[]>([])

// pricing mode: 'free' | 'paid'
const priceMode = ref<'free' | 'paid'>('free')

const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: 0,
  certificate_template_id: 0,
  thumbnail: '',
})

const formattedPrice = ref('0')

// Function to format number as thousand separators (dots)
function formatNumberWithDots(val: number | string): string {
  const clean = String(val).replace(/\D/g, '')
  if (!clean || clean === '0') return '0'
  return new Intl.NumberFormat('vi-VN').format(Number(clean))
}

// Watch form.price to keep formattedPrice in sync
watch(() => form.price, (newVal) => {
  formattedPrice.value = formatNumberWithDots(newVal)
}, { immediate: true })

// Watch price mode changes
watch(priceMode, (newMode) => {
  if (newMode === 'free') {
    form.price = 0
  }
})

// Handle input change
function handlePriceInput(e: Event) {
  const target = e.target as HTMLInputElement
  const rawValue = target.value.replace(/\D/g, '') // remove non-digits
  const numValue = rawValue ? Number(rawValue) : 0
  form.price = numValue
  // Update input text with formatted dots
  target.value = formatNumberWithDots(numValue)
}
const isCategoryModalOpen = ref(false)
const categorySearch = ref('')

const selectedCategoryName = computed(() => {
  if (!form.category_id) return ''
  for (const cat of courseStore.categories) {
    if (cat.id === form.category_id) return cat.name
    for (const child of cat.children || []) {
      if (child.id === form.category_id) return `${cat.name} ➔ ${child.name}`
      for (const grand of child.children || []) {
        if (grand.id === form.category_id) return `${cat.name} ➔ ${child.name} ➔ ${grand.name}`
      }
    }
  }
  return `Danh mục #${form.category_id}`
})

const filteredTree = computed(() => {
  const query = categorySearch.value.trim().toLowerCase()
  if (!query) return courseStore.categories

  return courseStore.categories.map(cat => {
    const rootMatches = cat.name.toLowerCase().includes(query)
    
    const matchingChildren = (cat.children || []).map(child => {
      const childMatches = child.name.toLowerCase().includes(query)
      
      const matchingGrand = (child.children || []).filter(grand => 
        grand.name.toLowerCase().includes(query)
      )
      
      if (childMatches || matchingGrand.length > 0) {
        return { ...child, children: matchingGrand }
      }
      return null
    }).filter(Boolean)

    if (rootMatches || matchingChildren.length > 0) {
      return { ...cat, children: matchingChildren }
    }
    return null
  }).filter(Boolean)
})

function selectCategory(cat: any) {
  form.category_id = cat.id
  isCategoryModalOpen.value = false
}

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
  if (priceMode.value === 'paid' && form.price <= 0) { error.value = 'Vui lòng nhập học phí lớn hơn 0đ.'; return }
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
  <section class="create-course-shell">
    
    <!-- ══ HEADER BANNER ══ -->
    <header class="studio-header">
      <div class="header-content">
        <div class="badge-tag">Studio sáng tạo</div>
        <h1 class="header-title">Thiết lập khóa học</h1>
        <p class="header-subtitle">Khởi tạo thông tin cốt lõi để bắt đầu xây dựng chương trình giảng dạy của bạn.</p>
      </div>
      <NuxtLink to="/instructor/courses" class="btn-cancel">
        <i class="pi pi-arrow-left" style="font-size:1.0rem" />
        <span>Quay lại</span>
      </NuxtLink>
    </header>

    <!-- Error state banner -->
    <div v-if="error" class="alert-banner">
      <i class="pi pi-exclamation-circle" style="font-size:1.125rem" />
      <span>{{ error }}</span>
    </div>

    <!-- ══ TWO COLUMN WORKSPACE ══ -->
    <div class="workspace-grid mt-6">
      
      <!-- Left Column: Form Fields -->
      <form class="main-form" @submit.prevent="handleSubmit">
        
        <!-- CARD 1: BASIC INFO -->
        <div class="glass-card">
          <div class="card-head">
            <div class="card-icon-wrap">
              <i class="pi pi-book" style="font-size:1.125rem" />
            </div>
            <div>
              <h3 class="card-title">Thông tin cơ bản</h3>
              <p class="card-desc">Đặt tên và mô tả hấp dẫn để cuốn hút học viên.</p>
            </div>
          </div>
          
          <div class="card-body">
            <!-- Title field -->
            <div class="form-group">
              <label class="custom-label">
                <span>Tên khóa học</span>
                <span class="required-indicator">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                class="custom-input"
                placeholder="Ví dụ: Thiết kế giao diện Web với Nuxt.js & Tailwind CSS"
                required
              />
              <span class="group-hint">Tên khóa học nên ngắn gọn, chứa từ khóa chính của môn học.</span>
            </div>

            <!-- Description field -->
            <div class="form-group">
              <label class="custom-label">Mô tả tóm tắt</label>
              <textarea
                v-model="form.description"
                rows="5"
                class="custom-textarea"
                placeholder="Mô tả tóm tắt về khóa học, các kỹ năng chính học viên sẽ đạt được sau khi hoàn thành học phần này..."
              />
            </div>
          </div>
        </div>

        <!-- CARD 2: CATEGORY & PRICING -->
        <div class="glass-card">
          <div class="card-head">
            <div class="card-icon-wrap">
              <i class="pi pi-clone" style="font-size:1.125rem" />
            </div>
            <div>
              <h3 class="card-title">Phân mục & Học phí</h3>
              <p class="card-desc">Phân loại danh mục học thuật và định mức học phí khóa học.</p>
            </div>
          </div>
          
          <div class="card-body">
            
            <!-- Grid columns: Category & Pricing Mode -->
            <div class="fields-row-2">
              
              <!-- Category Tree Selector Trigger -->
              <div class="form-group">
                <label class="custom-label">Danh mục học tập</label>
                <button
                  type="button"
                  class="category-selector-trigger"
                  @click="isCategoryModalOpen = true"
                >
                  <i class="pi pi-clone" style="font-size:0.9375rem" />
                  <span class="selected-cat-name text-left flex-1 font-semibold text-slate-700 dark:text-slate-200">
                    {{ selectedCategoryName || '— Chọn danh mục học tập —' }}
                  </span>
                  <i class="pi pi-chevron-right" style="font-size:0.9375rem" />
                </button>
                <span class="group-hint mt-1 text-[11px] block text-slate-400">
                  * Hệ thống danh mục do Admin chuẩn hóa. Nhấp để xem cây danh mục và lựa chọn.
                </span>
              </div>

              <!-- Segmented pricing toggle -->
              <div class="form-group">
                <label class="custom-label">Chế độ học phí</label>
                <div class="pricing-tabs">
                  <button
                    type="button"
                    class="tab-item"
                    :class="{ 'is-active': priceMode === 'free' }"
                    @click="priceMode = 'free'"
                  >
                    <Gift :size="14" />
                    <span>Miễn phí</span>
                  </button>
                  <button
                    type="button"
                    class="tab-item"
                    :class="{ 'is-active': priceMode === 'paid' }"
                    @click="priceMode = 'paid'"
                  >
                    <DollarSign :size="14" />
                    <span>Có phí VNĐ</span>
                  </button>
                </div>
              </div>

            </div>

            <!-- Price input section (Smooth fade-in transition depending on mode) -->
            <Transition name="fade-slide">
              <div class="form-group price-input-block" v-if="priceMode === 'paid'">
                <label class="custom-label">
                  <span>Học phí khóa học</span>
                  <span class="required-indicator">*</span>
                </label>
                <div class="price-input-wrap">
                  <input
                    :value="formattedPrice"
                    @input="handlePriceInput"
                    type="text"
                    class="custom-input price-input-field"
                    placeholder="Nhập số tiền học phí..."
                  />
                  <span class="currency-badge">VNĐ</span>
                </div>
                <div class="price-indicators" v-if="form.price > 0">
                  <span class="indicator-success">
                    <i class="pi pi-check-circle" style="font-size:0.75rem" />
                    Giá trị truyền dữ liệu sạch: <strong>{{ form.price }}</strong> đ
                  </span>
                </div>
              </div>
            </Transition>

          </div>
        </div>

        <!-- CARD 3: CERTIFICATION -->
        <div class="glass-card">
          <div class="card-head">
            <div class="card-icon-wrap">
              <i class="pi pi-verified" style="font-size:1.125rem" />
            </div>
            <div>
              <h3 class="card-title">Chứng nhận tốt nghiệp</h3>
              <p class="card-desc">Cấp chứng nhận tự động khi học viên hoàn thành khóa học.</p>
            </div>
          </div>
          
          <div class="card-body">
            <div class="form-group">
              <label class="custom-label">Mẫu chứng chỉ cấp phát</label>
              <div class="select-wrapper">
                <select v-model.number="form.certificate_template_id" class="custom-select">
                  <option :value="0">— Không cấp chứng chỉ hoàn thành —</option>
                  <option v-for="cert in certificates" :key="cert.id" :value="cert.id">{{ cert.name }}</option>
                </select>
              </div>
              <span class="group-hint">Chứng nhận sẽ tự động tạo dưới định dạng PDF với chữ ký số khi học viên đạt đủ 100% tiến độ bài học.</span>
            </div>
          </div>
        </div>

        <!-- CARD 4: THUMBNAIL BANNER (Moved here!) -->
        <div class="glass-card">
          <div class="card-head">
            <div class="card-icon-wrap">
              <UploadCloud :size="18" />
            </div>
            <div>
              <h3 class="card-title">Ảnh bìa khóa học</h3>
              <p class="card-desc">Banner hiển thị ngoài danh mục chính.</p>
            </div>
          </div>
          
          <div class="card-body">
            <p class="sidebar-info-text mb-3">Chọn hoặc kéo thả ảnh chất lượng cao để tăng tỉ lệ nhấp chuột từ học viên. Kích thước khuyến nghị: 1280x720 (tỉ lệ 16:9).</p>
            <div class="uploader-container">
              <MediaUpload
                v-model="form.thumbnail"
                folder="courses"
                variant="banner"
                label="Chọn ảnh từ máy tính"
                hint="Định dạng JPG, PNG, WEBP. Tối đa 5MB."
              />
            </div>
          </div>
        </div>

        <!-- Submit control buttons -->
        <div class="actions-footer">
          <NuxtLink to="/instructor/courses" class="btn-secondary">Hủy bỏ</NuxtLink>
          <button type="submit" class="btn-primary" :disabled="loading">
            <span v-if="loading" class="spinner-icon"></span>
            <span>{{ loading ? 'Đang khởi tạo...' : 'Lưu & Xây dựng giáo trình →' }}</span>
          </button>
        </div>

      </form>

      <!-- Right Column: Sidebar Thumbnail & Tips -->
      <aside class="sidebar-column">

        <!-- TIPS CARD -->
        <div class="glass-card tips-card">
          <div class="card-head">
            <div class="card-icon-wrap tips-icon">
              <Lightbulb :size="18" />
            </div>
            <div>
              <h3 class="card-title text-amber-700">Studio Tips</h3>
              <p class="card-desc text-amber-600/80">Giúp tối ưu hóa thông tin học phần.</p>
            </div>
          </div>
          
          <div class="card-body">
            <ul class="tips-timeline">
              <li class="tip-item">
                <span class="tip-dot"></span>
                <p><strong>Tên chuẩn hóa:</strong> Nên mô tả đúng kỹ năng học được thay vì tên chung chung.</p>
              </li>
              <li class="tip-item">
                <span class="tip-dot"></span>
                <p><strong>Học phí:</strong> Định giá phù hợp với dung lượng bài học để tối ưu lượng ghi danh.</p>
              </li>
              <li class="tip-item">
                <span class="tip-dot"></span>
                <p><strong>Ảnh bìa:</strong> Tránh sử dụng quá nhiều chữ trên ảnh bìa để giữ sự tinh tế.</p>
              </li>
            </ul>
          </div>
        </div>

      </aside>

    </div>

    <!-- ══ CATEGORY SELECTOR MODAL ══ -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="isCategoryModalOpen" class="category-modal-backdrop" @click.self="isCategoryModalOpen = false">
          <div class="category-modal-card">
            <div class="modal-header">
              <h3 class="modal-title">Chọn danh mục học tập</h3>
              <button type="button" class="modal-close-btn" @click="isCategoryModalOpen = false">✕</button>
            </div>
            
            <div class="modal-search-box">
              <input 
                v-model="categorySearch" 
                type="text" 
                placeholder="Tìm nhanh danh mục..." 
                class="modal-search-input"
              />
            </div>

            <div class="modal-body-tree">
              <!-- Loading state -->
              <div v-if="!courseStore.categories.length" class="text-center py-8 text-slate-400">
                Đang tải danh sách danh mục...
              </div>

              <!-- Category Tree -->
              <div class="category-tree" v-else>
                <template v-for="cat in filteredTree" :key="cat.id">
                  
                  <!-- Category with children -->
                  <details v-if="cat.children && cat.children.length" class="tree-details">
                    <summary class="tree-summary">
                      <span class="toggle-indicator"></span>
                      <button 
                        type="button" 
                        class="node-label-btn" 
                        :class="{ 'is-selected': form.category_id === cat.id }"
                        @click.prevent="selectCategory(cat)"
                      >
                        {{ cat.name }}
                      </button>
                    </summary>
                    
                    <div class="tree-content-indent">
                      <template v-for="child in cat.children" :key="child.id">
                        
                        <!-- Sub-category with children -->
                        <details v-if="child.children && child.children.length" class="tree-details">
                          <summary class="tree-summary">
                            <span class="toggle-indicator"></span>
                            <button 
                              type="button" 
                              class="node-label-btn" 
                              :class="{ 'is-selected': form.category_id === child.id }"
                              @click.prevent="selectCategory(child)"
                            >
                              {{ child.name }}
                            </button>
                          </summary>
                          
                          <div class="tree-content-indent">
                            <div 
                              v-for="grand in child.children" 
                              :key="grand.id" 
                              class="tree-flat-item"
                            >
                              <button 
                                type="button" 
                                class="node-label-btn"
                                :class="{ 'is-selected': form.category_id === grand.id }"
                                @click="selectCategory(grand)"
                              >
                                {{ grand.name }}
                              </button>
                            </div>
                          </div>
                        </details>

                        <!-- Sub-category flat -->
                        <div v-else class="tree-flat-item">
                          <button 
                            type="button" 
                            class="node-label-btn"
                            :class="{ 'is-selected': form.category_id === child.id }"
                            @click="selectCategory(child)"
                          >
                            {{ child.name }}
                          </button>
                        </div>

                      </template>
                    </div>
                  </details>

                  <!-- Root category flat -->
                  <div v-else class="tree-flat-item root-flat">
                    <button 
                      type="button" 
                      class="node-label-btn"
                      :class="{ 'is-selected': form.category_id === cat.id }"
                      @click="selectCategory(cat)"
                    >
                      {{ cat.name }}
                    </button>
                  </div>

                </template>
              </div>
            </div>
            
            <div class="modal-footer">
              <button 
                type="button" 
                class="modal-btn-confirm" 
                @click="isCategoryModalOpen = false"
              >
                Hoàn tất chọn
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </section>
</template>

<style scoped>
.create-course-shell {
  max-width: 1200px;
  margin: 0 auto;
  padding-bottom: 48px;
}

/* ── Studio Header ── */
.studio-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  padding: 32px;
  background: linear-gradient(135deg, var(--surface-strong), rgba(var(--surface-strong-rgb), 0.7));
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  backdrop-filter: blur(8px);
}

.badge-tag {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 99px;
  background: var(--green-soft);
  color: var(--green);
  font-size: 0.72rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 8px;
  width: fit-content;
}

.header-title {
  margin: 0;
  font-size: 1.85rem;
  font-weight: 850;
  letter-spacing: -0.03em;
  color: var(--text);
}

.header-subtitle {
  margin: 6px 0 0;
  font-size: 0.88rem;
  color: var(--muted);
  font-weight: 500;
}

.btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text-secondary);
  font-size: 0.84rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: var(--shadow-sm);
}

.btn-cancel:hover {
  background: var(--surface);
  color: var(--text);
  transform: translateX(-2px);
}

/* ── Alert Banner ── */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 14px;
  color: #EF4444;
  font-size: 0.88rem;
  font-weight: 600;
  margin-top: 20px;
}

/* ── Two Column Workspace ── */
.workspace-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 1024px) {
  .workspace-grid {
    grid-template-columns: 1fr 340px;
  }
}

.main-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sidebar-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Premium Glass Card ── */
.glass-card {
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 24px;
  box-shadow: var(--shadow-sm);
  transition: all 250ms ease;
}

.glass-card:hover {
  box-shadow: var(--shadow);
}

.card-head {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  border-bottom: 1px solid var(--line);
  padding-bottom: 16px;
  margin-bottom: 20px;
}

.card-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(29, 158, 117, 0.06);
  color: var(--green);
  flex-shrink: 0;
}

.card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.02em;
}

.card-desc {
  margin: 4px 0 0;
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.4;
}

/* ── Form Inputs ── */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}
.form-group:last-child {
  margin-bottom: 0;
}

.custom-label {
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.required-indicator {
  color: #EF4444;
  font-weight: bold;
}

.custom-input,
.custom-textarea,
.custom-select {
  background: var(--surface);
  border: 1.5px solid var(--line);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.9rem;
  color: var(--text);
  outline: none;
  font-family: inherit;
  transition: all 0.2s ease;
  width: 100%;
}

.custom-input:focus,
.custom-textarea:focus,
.custom-select:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.12);
  background: var(--surface-strong);
}

.custom-textarea {
  resize: vertical;
}

.group-hint {
  font-size: 0.76rem;
  color: var(--muted);
  line-height: 1.4;
}

/* Row wrapper */
.fields-row-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 640px) {
  .fields-row-2 {
    grid-template-columns: 1fr 1fr;
  }
}

/* Dropdown arrow custom styling */
.select-wrapper {
  position: relative;
  width: 100%;
}

.select-wrapper::after {
  content: '▼';
  font-size: 10px;
  color: var(--muted);
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.custom-select {
  padding-right: 40px;
  appearance: none;
}

.option-bold {
  font-weight: 700;
}

/* ── Segmented Pricing Tab Toggles ── */
.pricing-tabs {
  display: flex;
  background: var(--surface);
  border: 1.5px solid var(--line);
  border-radius: 12px;
  padding: 4px;
  height: 48px;
}

.tab-item {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 200ms ease;
}

.tab-item:hover {
  color: var(--text);
}

.tab-item.is-active {
  background: var(--green);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(29, 158, 117, 0.2);
}

/* ── Pricing input wrap ── */
.price-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.price-input-field {
  padding-right: 60px;
}

.currency-badge {
  position: absolute;
  right: 16px;
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--green);
  background: var(--green-soft);
  padding: 4px 10px;
  border-radius: 6px;
  pointer-events: none;
}

.price-indicators {
  margin-top: 6px;
}

.indicator-success {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.76rem;
  color: var(--green);
  font-weight: 600;
}

/* ── Submit actions ── */
.actions-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 20px;
  border-top: 1px dashed var(--line);
  margin-top: 12px;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  border-radius: 12px;
  border: 1.5px solid var(--line);
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 200ms;
}

.btn-secondary:hover {
  background: var(--surface);
  color: var(--text);
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  border-radius: 12px;
  border: none;
  background: var(--green);
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(29, 158, 117, 0.25);
  transition: all 200ms;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(29, 158, 117, 0.35);
}

/* Spinner icon */
.spinner-icon {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: rotate-spinner 0.8s linear infinite;
}

@keyframes rotate-spinner {
  to { transform: rotate(360deg); }
}

/* ── Sidebar specific ── */
.sidebar-info-text {
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.5;
  margin: 0 0 16px;
}

.uploader-container {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid var(--line);
  background: var(--surface);
}

.tips-card {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.04), rgba(245, 158, 11, 0.01));
  border-color: rgba(245, 158, 11, 0.15);
}

.tips-icon {
  background: rgba(245, 158, 11, 0.08);
  color: #D97706;
}

.tips-timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.tip-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.tip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #D97706;
  flex-shrink: 0;
  margin-top: 6px;
}

.tip-item p {
  margin: 0;
  font-size: 0.8rem;
  color: var(--text-secondary);
  line-height: 1.5;
}

.tip-item p strong {
  color: var(--text);
  font-weight: 700;
}

/* Fade Slide Transitions */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* ── Category Tree Selector & Modal ── */
.category-selector-trigger {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--surface);
  border: 1.5px solid var(--line);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.9rem;
  width: 100%;
  cursor: pointer;
  outline: none;
  transition: all 0.2s ease;
}

.category-selector-trigger:focus,
.category-selector-trigger:hover {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.12);
  background: var(--surface-strong);
}

.selected-cat-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.category-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.category-modal-card {
  width: 100%;
  max-width: 500px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
  animation: modal-enter 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modal-enter {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--line);
}

.modal-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 850;
  color: var(--text);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
}

.modal-search-box {
  padding: 16px 24px 8px;
}

.modal-search-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--line);
  background: var(--surface);
  color: var(--text);
  outline: none;
  font-size: 0.88rem;
  transition: all 0.2s ease;
}

.modal-search-input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.08);
}

.modal-body-tree {
  flex: 1;
  overflow-y: auto;
  padding: 12px 24px 24px;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--line);
  display: flex;
  justify-content: flex-end;
}

.modal-btn-confirm {
  background: var(--green);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 10px 20px;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  transition: background 150ms;
}

.modal-btn-confirm:hover {
  background: var(--green-deep);
}

/* Tree Styling */
.category-tree {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tree-details {
  display: flex;
  flex-direction: column;
  width: 100%;
}

.tree-details summary::-webkit-details-marker {
  display: none;
}
.tree-details summary {
  list-style: none;
}

.tree-summary {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  transition: background 150ms;
}

.tree-summary:hover {
  background: var(--surface);
}

.toggle-indicator::before {
  content: '+';
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 5px;
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--muted);
  font-size: 11px;
  font-weight: 800;
  transition: all 150ms;
}

details[open] > summary > .toggle-indicator::before {
  content: '−'; /* Minus sign */
  background: var(--green-soft);
  border-color: var(--green);
  color: var(--green);
}

.node-label-btn {
  background: transparent;
  border: none;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  text-align: left;
  transition: all 150ms;
}

.node-label-btn:hover {
  color: var(--green);
  background: var(--green-soft);
}

.node-label-btn.is-selected {
  background: var(--green);
  color: #fff !important;
}

.tree-content-indent {
  padding-left: 24px;
  border-left: 1px dashed var(--line);
  margin-left: 16px;
  margin-top: 4px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tree-flat-item {
  display: flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 8px;
}

.tree-flat-item.root-flat {
  padding-left: 28px;
}
</style>
