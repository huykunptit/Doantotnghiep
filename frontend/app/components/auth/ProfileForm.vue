<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const auth = useAuthStore()

const form = reactive({
  name: auth.user?.name ?? '',
  avatar: auth.user?.avatar ?? '',
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
  },
  { immediate: true },
)

async function handleSubmit() {
  loading.value = true
  success.value = ''
  error.value = ''
  try {
    await auth.updateProfile({ name: form.name, avatar: form.avatar || null })
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
    </div>

    <UiInput v-model="form.avatar" label="URL ảnh đại diện" type="url" placeholder="https://example.com/avatar.jpg" />

    <div v-if="form.avatar" class="rounded-2xl border border-surface-dim bg-surface-low p-4">
      <p class="mb-3 text-sm font-semibold text-on-surface-variant">Xem trước ảnh đại diện</p>
      <div class="flex items-center gap-4">
        <img :src="form.avatar" alt="avatar preview" class="h-14 w-14 rounded-2xl object-cover" @error="form.avatar = ''">
        <div>
          <p class="font-semibold text-on-surface">{{ form.name || auth.user?.name }}</p>
          <p class="text-sm text-on-surface-variant">{{ auth.user?.email }}</p>
        </div>
      </div>
    </div>

    <div v-if="success" class="rounded-2xl border border-secondary/20 bg-secondary-50 px-4 py-3 text-sm text-secondary">{{ success }}</div>
    <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>

    <div class="flex justify-end">
      <UiButton type="submit" :disabled="loading">{{ loading ? 'Đang lưu...' : 'Lưu thay đổi' }}</UiButton>
    </div>
  </form>
</template>

