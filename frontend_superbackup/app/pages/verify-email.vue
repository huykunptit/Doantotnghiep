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

<style scoped>
/* ── Auth Form ─────────────────────────────────────────────────────────── */
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Feedback ──────────────────────────────────────────────────────────── */
.feedback {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--line);
}

.feedback-success {
  background: rgba(29, 158, 117, 0.1);
  color: var(--green-deep, #085041);
  border-color: rgba(29, 158, 117, 0.25);
}

.feedback-error {
  background: var(--danger-soft);
  color: var(--danger);
  border: 1px solid rgba(226, 75, 74, 0.2);
}

/* ── Field ─────────────────────────────────────────────────────────────── */
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field > span {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text, #0d1f1a);
}

.field input[type="email"] {
  width: 100%;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--line, rgba(0,0,0,0.09));
  border-radius: 8px;
  background: var(--surface-strong, #fff);
  color: var(--text, #0d1f1a);
  font: inherit;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}

.field input::placeholder {
  color: var(--muted, #6b7c73);
}

.field input:focus {
  border-color: var(--green, #1D9E75);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.1);
}

/* ── Primary Button ────────────────────────────────────────────────────── */
.primary-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 46px;
  margin-top: 4px;
  border-radius: 8px;
  border: none;
  background: var(--green, #1D9E75);
  color: #fff;
  font: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms, transform 150ms, box-shadow 150ms;
  box-shadow: 0 4px 12px rgba(29, 158, 117, 0.25);
}

.primary-button:hover:not(:disabled) {
  background: var(--green-deep, #085041);
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(29, 158, 117, 0.3);
}

.primary-button:disabled {
  opacity: 0.65;
  cursor: wait;
  transform: none;
}
</style>

