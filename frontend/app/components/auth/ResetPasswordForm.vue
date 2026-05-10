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
  <div>
    <div v-if="success" class="feedback feedback-success">
      Đổi mật khẩu thành công. Bạn có thể đăng nhập lại ngay bây giờ.
    </div>
    <form v-else class="auth-form" @submit.prevent="handleReset">
      <div v-if="error" class="feedback feedback-error">{{ error }}</div>
      <label class="field">
        <span>Email</span>
        <input v-model="form.email" type="email" disabled>
      </label>
      <label class="field">
        <span>Mật khẩu mới</span>
        <input v-model="form.password" type="password" placeholder="Tối thiểu 6 ký tự" required>
      </label>
      <label class="field">
        <span>Xác nhận mật khẩu</span>
        <input v-model="form.password_confirmation" type="password" placeholder="Nhập lại mật khẩu" required>
      </label>
      <button class="primary-button" type="submit" :disabled="loading">
        {{ loading ? 'Đang lưu...' : 'Lưu mật khẩu mới' }}
      </button>
    </form>
  </div>
</template>

