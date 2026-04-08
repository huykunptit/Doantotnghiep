<template>
  <div class="max-w-4xl mx-auto">
    <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8">
      <h1 class="text-2xl font-bold text-gray-900 mb-1">Tạo khóa học mới</h1>
      <p class="text-sm text-gray-500 mb-6">Điền đầy đủ thông tin cơ bản trước khi thêm bài giảng.</p>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div>
          <label class="label">Tên khóa học *</label>
          <input v-model="form.title" type="text" required class="input" placeholder="VD: Laravel thực chiến cho người mới bắt đầu" />
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="label">Danh mục *</label>
            <select v-model.number="form.category_id" class="input" required>
              <option :value="0" disabled>Chọn danh mục</option>
              <template v-for="cat in courseStore.categories" :key="cat.id">
                <option :value="cat.id">{{ cat.name }}</option>
                <option v-for="child in cat.children || []" :key="child.id" :value="child.id">&nbsp;&nbsp;└ {{ child.name }}</option>
              </template>
            </select>
          </div>

          <div>
            <label class="label">Giá (VNĐ) *</label>
            <input v-model.number="form.price" type="number" min="0" step="1000" required class="input" placeholder="0" />
            <p class="text-xs text-gray-500 mt-1">Nhập 0 nếu khóa học miễn phí.</p>
          </div>
        </div>

        <div>
          <label class="label">Mô tả khóa học *</label>
          <textarea v-model="form.description" rows="6" class="input" required placeholder="Nêu mục tiêu, nội dung chính, đối tượng phù hợp..." />
        </div>

        <div>
          <label class="label">URL ảnh bìa</label>
          <input v-model="form.thumbnail" type="url" class="input" placeholder="https://..." />
          <div v-if="form.thumbnail" class="mt-3 rounded-lg overflow-hidden border border-gray-200 h-44 bg-gray-50">
            <img :src="form.thumbnail" alt="preview" class="w-full h-full object-cover" @error="form.thumbnail = ''" />
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <NuxtLink to="/courses" class="btn-secondary text-sm">Hủy</NuxtLink>
          <button type="submit" :disabled="loading" class="btn-primary text-sm min-w-[120px]">
            {{ loading ? 'Đang tạo...' : 'Tạo khóa học' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const loading = ref(false)
const error = ref('')

const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: 0,
  thumbnail: '',
})

// Redirect if not instructor/admin
onMounted(async () => {
  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }
  await courseStore.fetchCategories()
  if (!auth.user?.roles?.some((r) => ['admin', 'instructor'].includes(r))) {
    router.push('/courses')
  }
})

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    const course = await courseStore.createCourse({
      title: form.title,
      description: form.description || null,
      price: form.price,
      category_id: form.category_id || null,
      thumbnail: form.thumbnail || null,
    })
    router.push(`/courses/${course.id}/lessons`)
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tạo khóa học.'
  } finally {
    loading.value = false
  }
}
</script>
