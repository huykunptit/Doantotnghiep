<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import MediaUpload from '~/components/common/MediaUpload.vue'

const auth = useAuthStore()

const form = reactive({
  name: auth.user?.name ?? '',
  avatar: auth.user?.avatar ?? '',
  student_code: auth.user?.student_code ?? '',
  class_name: auth.user?.class_name ?? '',
  department: auth.user?.department ?? '',
})
const loading = ref(false)
const success = ref('')
const error = ref('')

watch(
  () => auth.user,
  (user) => {
    if (!user) return
    form.name = user.name
    form.avatar = user.avatar ?? ''
    form.student_code = user.student_code ?? ''
    form.class_name = user.class_name ?? ''
    form.department = user.department ?? ''
  },
  { immediate: true },
)

async function handleSubmit() {
  loading.value = true
  success.value = ''
  error.value = ''
  try {
    await auth.updateProfile({ 
      name: form.name, 
      avatar: form.avatar || null,
      student_code: form.student_code || null,
      class_name: form.class_name || null,
      department: form.department || null,
    })
    success.value = 'Cập nhật hồ sơ thành công.'
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể cập nhật hồ sơ.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <div class="grid gap-4 md:grid-cols-2">
      <UiInput v-model="form.name" label="Họ và tên" placeholder="Nguyễn Văn A" />
      <UiInput :model-value="auth.user?.email || ''" label="Email" type="email" disabled />
      
      <UiInput v-model="form.student_code" label="Mã sinh viên" placeholder="Ví dụ: CT123456" />
      <UiInput v-model="form.class_name" label="Lớp học" placeholder="Ví dụ: D20CQCN01-N" />
      <UiInput v-model="form.department" label="Khoa/Viện" placeholder="Ví dụ: Khoa CNTT" />
    </div>

    <div class="space-y-2">
      <p class="text-sm font-semibold text-on-surface-variant">Ảnh đại diện</p>
      <MediaUpload
        v-model="form.avatar"
        folder="users"
        variant="avatar"
        label="Ảnh đại diện"
        hint="JPG, PNG, WEBP — tối đa 5MB. Tự động tải lên khi chọn tệp."
        :placeholder-initial="(form.name || auth.user?.name || '?').charAt(0).toUpperCase()"
      />
    </div>

    <div v-if="success" class="rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">{{ success }}</div>
    <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>

    <div class="flex justify-end">
      <UiButton type="submit" :disabled="loading">{{ loading ? 'Đang lưu...' : 'Lưu thay đổi' }}</UiButton>
    </div>
  </form>
</template>

