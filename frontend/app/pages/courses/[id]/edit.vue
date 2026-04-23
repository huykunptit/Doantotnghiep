<template>
  <NuxtLayout name="instructor">
    <section class="mx-auto max-w-5xl space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Chỉnh sửa khóa học" description="Cập nhật thông tin khóa học, trạng thái và hình ảnh minh họa.">
        <template #actions>
          <NuxtLink :to="`/courses/${courseId}`" target="_blank"><UiButton variant="secondary">Xem trước</UiButton></NuxtLink>
          <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`"><UiButton variant="secondary">Quản lý bài học</UiButton></NuxtLink>
        </template>
      </AppPageHeader>

      <UiCard v-if="pageLoading">
        <div class="h-96 animate-pulse rounded-3xl bg-slate-100" />
      </UiCard>

      <UiCard v-else>
        <form class="space-y-6" @submit.prevent="handleSubmit">
          <UiInput v-model="form.title" label="Tên khóa học" placeholder="Nhập tên khóa học" />

          <UiTextarea v-model="form.description" label="Mô tả khóa học" :rows="6" placeholder="Mô tả chi tiết về khóa học..." />

          <div class="grid gap-4 md:grid-cols-2">
            <UiInput v-model="form.price" label="Giá (VNĐ)" type="number" />
            <label class="block space-y-2 text-sm font-semibold text-slate-700">
              <span>Danh mục</span>
              <select v-model.number="form.category_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary">
                <option :value="0">-- Chưa chọn danh mục --</option>
                <template v-for="cat in courseStore.categories" :key="cat.id">
                  <option :value="cat.id">{{ cat.name }}</option>
                  <option v-for="child in cat.children || []" :key="child.id" :value="child.id">└ {{ child.name }}</option>
                </template>
              </select>
            </label>
          </div>

          <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-700">Trạng thái</p>
            <div class="grid gap-3 md:grid-cols-3">
              <label v-for="option in statusOptions" :key="option.value" class="flex items-center gap-3 rounded-2xl border px-4 py-4" :class="form.status === option.value ? option.activeClass : 'border-slate-200'">
                <input v-model="form.status" type="radio" :value="option.value" :disabled="option.value === 'published' && !canPublish">
                <div>
                  <p class="font-semibold text-slate-900">{{ option.label }}</p>
                  <p class="text-xs text-slate-500">{{ option.help }}</p>
                </div>
              </label>
            </div>
            <p v-if="!canPublish" class="text-xs text-amber-700">Chỉ Admin mới có quyền tự chuyển trạng thái sang xuất bản.</p>
          </div>

          <div class="space-y-3">
            <UiInput v-model="form.thumbnail" label="Thumbnail URL" type="url" placeholder="https://example.com/image.jpg" />
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition hover:border-primary/40 hover:bg-white">
              <span>{{ thumbnailFile ? thumbnailFile.name : 'Hoặc chọn file thumbnail mới' }}</span>
              <input type="file" accept="image/*" class="hidden" @change="handleThumbnailChange">
              <span class="font-semibold text-primary">Chọn ảnh</span>
            </label>
          </div>
          <div v-if="thumbnailPreview" class="h-52 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
            <img :src="thumbnailPreview" alt="thumbnail preview" class="h-full w-full object-cover" @error="thumbnailPreview = ''">
          </div>

          <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
          <div v-if="success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ success }}</div>

          <div class="flex justify-end gap-3">
            <NuxtLink to="/instructor/courses"><UiButton variant="ghost">Hủy bỏ</UiButton></NuxtLink>
            <UiButton type="submit" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}</UiButton>
          </div>
        </form>
      </UiCard>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'instructor', layout: false })
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()
const courseId = Number(route.params.id)
const pageLoading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const thumbnailFile = ref<File | null>(null)
const thumbnailPreview = ref('')
const canPublish = computed(() => auth.user?.roles?.includes('admin'))
const form = reactive({ title: '', description: '', price: 0, category_id: 0, thumbnail: '', status: 'draft' as 'draft' | 'published' | 'closed' | 'pending_review' | 'rejected' })
const statusOptions = [
  { value: 'draft', label: 'Bản nháp', help: 'Đang soạn thảo', activeClass: 'border-primary bg-primary/10' },
  { value: 'published', label: 'Xuất bản', help: 'Hiển thị công khai', activeClass: 'border-emerald-500 bg-emerald-50' },
  { value: 'closed', label: 'Đóng', help: 'Ngừng ghi danh', activeClass: 'border-rose-500 bg-rose-50' },
]
onMounted(async () => {
  if (auth.token && !auth.user) await auth.fetchMe()
  await courseStore.fetchCategories()
  const course = await courseStore.fetchCourse(courseId)
  if (!auth.user?.roles?.includes('admin') && Number(course.user_id) !== Number(auth.user?.id)) return router.push(`/courses/${courseId}`)
  form.title = course.title; form.description = course.description ?? ''; form.price = course.price; form.category_id = Number((course as any).category_id || (course as any).category?.id || 0); form.thumbnail = course.thumbnail ?? ''; form.status = course.status; thumbnailPreview.value = course.thumbnail ?? ''; pageLoading.value = false
})

watch(() => form.thumbnail, (value) => {
  if (!thumbnailFile.value) thumbnailPreview.value = value || ''
})

function handleThumbnailChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  thumbnailFile.value = file || null
  thumbnailPreview.value = file ? URL.createObjectURL(file) : (form.thumbnail || '')
}

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
    if (form.thumbnail) payload.append('thumbnail', form.thumbnail)
    if (thumbnailFile.value) payload.append('thumbnail_file', thumbnailFile.value)

    await courseStore.updateCourse(courseId, payload)
    success.value = 'Bạn đã cập nhật thông tin khóa học thành công!'
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu thay đổi.'
  } finally {
    saving.value = false
  }
}
</script>
