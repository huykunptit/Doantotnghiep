<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AuthPageShell from '~/components/auth/AuthPageShell.vue'

definePageMeta({ layout: false })

const route = useRoute()
const email = ref((route.query.email as string) || '')
const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
const message = ref('')
const resendLoading = ref(false)

const verificationParams = computed(() => ({
  id: route.query.id as string | undefined,
  hash: route.query.hash as string | undefined,
  expires: route.query.expires as string | undefined,
  signature: route.query.signature as string | undefined,
}))

const hasVerificationLink = computed(() => Boolean(
  verificationParams.value.id && verificationParams.value.hash && verificationParams.value.expires && verificationParams.value.signature,
))

async function verifyEmail() {
  if (!hasVerificationLink.value) return
  status.value = 'loading'
  message.value = ''
  try {
    const { id, hash, expires, signature } = verificationParams.value
    const result = await useApi<{ message: string }>(`/auth/verify-email/${id}/${hash}?expires=${expires}&signature=${signature}`)
    status.value = 'success'
    message.value = result.message
  } catch (error: any) {
    status.value = 'error'
    message.value = error?.data?.message || 'Không thể xác nhận email. Vui lòng thử gửi lại email xác nhận.'
  }
}

async function resendVerificationEmail() {
  if (!email.value) return
  resendLoading.value = true
  try {
    const result = await useApi<{ message: string }>('/auth/resend-verification-email', {
      method: 'POST',
      body: { email: email.value },
    })
    if (status.value !== 'success') status.value = 'idle'
    message.value = result.message
  } catch (error: any) {
    status.value = 'error'
    message.value = error?.data?.message || 'Không thể gửi lại email xác nhận.'
  } finally {
    resendLoading.value = false
  }
}

onMounted(() => {
  if (hasVerificationLink.value) verifyEmail()
  else if (route.query.registered) message.value = 'Tài khoản đã được tạo. Vui lòng kiểm tra email để xác nhận.'
})
</script>

<template>
  <AuthPageShell
    panel-kicker="Xác thực"
    panel-title="Xác nhận email"
    panel-description="Chúng tôi cần xác nhận địa chỉ email trước khi bạn đăng nhập và sử dụng đầy đủ hệ thống."
    foot-text="Đã xác nhận xong?"
    foot-link-text="Đăng nhập ngay"
    foot-link-to="/login"
  >
    <div class="auth-form">
      <div v-if="message" :class="[
        'feedback',
        status === 'success' ? 'feedback-success' : status === 'error' ? 'feedback-error' : '',
      ]">
        {{ message }}
      </div>

      <div v-if="status === 'loading'" class="feedback">
        Đang xác nhận email của bạn...
      </div>

      <label class="field">
        <span>Email đã đăng ký</span>
        <input v-model="email" type="email" placeholder="ban@ptit.edu.vn">
      </label>

      <button class="primary-button" type="button" :disabled="resendLoading || !email" @click="resendVerificationEmail">
        {{ resendLoading ? 'Đang gửi...' : 'Gửi lại email xác nhận' }}
      </button>
    </div>
  </AuthPageShell>
</template>

