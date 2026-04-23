<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '~/composables/useApi'

const route = useRoute()
const form = reactive({
  email: (route.query.email as string) || '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const error = ref('')
const success = ref(false)

async function handleReset() {
  error.value = ''
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }
  loading.value = true
  try {
    await useApi('/auth/reset-password', {
      method: 'POST',
      body: { token: route.query.token, ...form },
    })
    success.value = true
  } catch (e: any) {
    error.value = e?.data?.message || 'Cập nhật thất bại. Xin vui lòng thử lại sau.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthFormCard title="Tạo mật khẩu mới" description="Bảo mật tài khoản bằng một mật khẩu mới mạnh và dễ nhớ.">
    <div v-if="success" class="rounded-2xl border border-secondary/20 bg-secondary-50 px-4 py-5 text-sm text-secondary">
      Đổi mật khẩu thành công. Bạn có thể đăng nhập lại ngay bây giờ.
    </div>
    <form v-else class="space-y-4" @submit.prevent="handleReset">
      <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>
      <UiInput v-model="form.email" label="Email" type="email" disabled />
      <UiInput v-model="form.password" label="Mật khẩu mới" type="password" placeholder="Tối thiểu 6 ký tự" />
      <UiInput v-model="form.password_confirmation" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu" />
      <UiButton type="submit" block :disabled="loading">{{ loading ? 'Đang lưu...' : 'Lưu mật khẩu mới' }}</UiButton>
    </form>
    <div class="pt-4 text-center text-sm">
      <NuxtLink to="/login" class="font-semibold text-on-surface-variant hover:text-primary">Quay lại đăng nhập</NuxtLink>
    </div>
  </AuthFormCard>
</template>

