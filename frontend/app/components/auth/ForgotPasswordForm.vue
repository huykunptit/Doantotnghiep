<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '~/composables/useApi'

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await useApi('/auth/forgot-password', { method: 'POST', body: { email: email.value } })
    sent.value = true
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể gửi email. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthFormCard title="Quên mật khẩu?" description="Nhập email liên kết với tài khoản để nhận hướng dẫn đặt lại mật khẩu.">
    <div v-if="sent" class="rounded-2xl border border-secondary/20 bg-secondary-50 px-4 py-5 text-sm text-secondary">
      Yêu cầu đã được gửi. Vui lòng kiểm tra email của bạn.
    </div>
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>
      <UiInput v-model="email" label="Email của bạn" type="email" placeholder="Nhập email" />
      <UiButton type="submit" block :disabled="loading">{{ loading ? 'Đang gửi...' : 'Gửi yêu cầu' }}</UiButton>
    </form>
    <div class="pt-4 text-center text-sm">
      <NuxtLink to="/login" class="font-semibold text-on-surface-variant hover:text-primary">Quay lại đăng nhập</NuxtLink>
    </div>
  </AuthFormCard>
</template>

