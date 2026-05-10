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
  <div>
    <div v-if="sent" class="feedback feedback-success">
      Yêu cầu đã được gửi. Vui lòng kiểm tra email của bạn.
    </div>
    <form v-else class="auth-form" @submit.prevent="handleSubmit">
      <div v-if="error" class="feedback feedback-error">{{ error }}</div>
      <label class="field">
        <span>Email của bạn</span>
        <input v-model="email" type="email" placeholder="Nhập email" required>
      </label>
      <button class="primary-button" type="submit" :disabled="loading">
        {{ loading ? 'Đang gửi...' : 'Gửi yêu cầu' }}
      </button>
    </form>
  </div>
</template>

