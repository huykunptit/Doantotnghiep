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
            <label class="block space-y-2 text-sm font-semibold text-slate-700">
              <span>Chứng chỉ hoàn thành</span>
              <select v-model.number="form.certificate_template_id" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary">
                <option :value="0">-- Không cấp chứng chỉ --</option>
                <option v-for="cert in certificates" :key="cert.id" :value="cert.id">{{ cert.name }}</option>
              </select>
            </label>
            <UiInput v-model="form.price" label="Giá (VNĐ)" type="number" />
          </div>

          <UiTextarea v-model="form.description" label="Mô tả khóa học" :rows="6" placeholder="Mô tả mục tiêu, nội dung chính và đối tượng phù hợp..." />

          <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-700">Ảnh bìa khóa học</p>
            <MediaUpload
              v-model="form.thumbnail"
              folder="courses"
              variant="banner"
              label="Ảnh bìa"
              hint="JPG, PNG, WEBP — tối đa 5MB. Tự động tải lên khi chọn tệp."
            />
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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'
import MediaUpload from '~/components/common/MediaUpload.vue'

definePageMeta({ middleware: 'instructor' })
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()
const loading = ref(false)
const error = ref('')
const certificates = ref<any[]>([])
const form = reactive({ title: '', description: '', price: 0, category_id: 0, certificate_template_id: 0, thumbnail: '' })
onMounted(async () => { if (auth.token && !auth.user) await auth.fetchMe(); await courseStore.fetchCategories(); try { certificates.value = await useApi<any[]>('/admin/certificates', { headers: { Authorization: `Bearer ${auth.token}` } }) } catch(e) {} if (!auth.user?.roles?.some((r) => ['admin', 'instructor'].includes(r))) router.push('/courses') })

async function handleSubmit() {
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
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tạo khóa học.'
  } finally {
    loading.value = false
  }
}
</script>
