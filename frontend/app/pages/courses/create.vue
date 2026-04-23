<template>
  <NuxtLayout name="instructor">
    <section class="mx-auto max-w-5xl space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Tạo khóa học mới" description="Điền thông tin cơ bản trước khi thêm section, lesson và tài nguyên học tập." />

      <UiCard>
        <form class="space-y-5" @submit.prevent="handleSubmit">
          <UiInput v-model="form.title" label="Tên khóa học" placeholder="VD: Laravel thực chiến cho người mới bắt đầu" />

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block space-y-2 text-sm font-semibold text-slate-700">
              <span>Danh mục</span>
              <select v-model.number="form.category_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary">
                <option :value="0" disabled>Chọn danh mục</option>
                <template v-for="cat in courseStore.categories" :key="cat.id">
                  <option :value="cat.id">{{ cat.name }}</option>
                  <option v-for="child in cat.children || []" :key="child.id" :value="child.id">└ {{ child.name }}</option>
                </template>
              </select>
            </label>
            <UiInput v-model="form.price" label="Giá (VNĐ)" type="number" />
          </div>

          <UiTextarea v-model="form.description" label="Mô tả khóa học" :rows="6" placeholder="Mô tả mục tiêu, nội dung chính và đối tượng phù hợp..." />

          <div class="space-y-3">
            <UiInput v-model="form.thumbnail" label="URL ảnh bìa" type="url" placeholder="https://..." />
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition hover:border-primary/40 hover:bg-white">
              <span>{{ thumbnailFile ? thumbnailFile.name : 'Hoặc chọn file ảnh từ máy' }}</span>
              <input type="file" accept="image/*" class="hidden" @change="handleThumbnailChange">
              <span class="font-semibold text-primary">Chọn ảnh</span>
            </label>
          </div>
          <div v-if="thumbnailPreview" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 h-52">
            <img :src="thumbnailPreview" alt="preview" class="h-full w-full object-cover" @error="thumbnailPreview = ''">
          </div>

          <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
          <div class="flex justify-end gap-3">
            <NuxtLink to="/instructor/courses"><UiButton variant="ghost">Hủy</UiButton></NuxtLink>
            <UiButton type="submit" :disabled="loading">{{ loading ? 'Đang tạo...' : 'Tạo khóa học' }}</UiButton>
          </div>
        </form>
      </UiCard>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'instructor' })
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()
const loading = ref(false)
const error = ref('')
const form = reactive({ title: '', description: '', price: 0, category_id: 0, thumbnail: '' })
const thumbnailFile = ref<File | null>(null)
const thumbnailPreview = ref('')
onMounted(async () => { if (auth.token && !auth.user) await auth.fetchMe(); await courseStore.fetchCategories(); if (!auth.user?.roles?.some((r) => ['admin', 'instructor'].includes(r))) router.push('/courses') })

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
  loading.value = true
  error.value = ''
  try {
    const payload = new FormData()
    payload.append('title', form.title)
    payload.append('description', form.description || '')
    payload.append('price', String(Number(form.price)))
    if (form.category_id) payload.append('category_id', String(form.category_id))
    if (form.thumbnail) payload.append('thumbnail', form.thumbnail)
    if (thumbnailFile.value) payload.append('thumbnail_file', thumbnailFile.value)

    const course = await courseStore.createCourse(payload)
    router.push(`/instructor/courses/${course.id}/curriculum`)
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tạo khóa học.'
  } finally {
    loading.value = false
  }
}
</script>
