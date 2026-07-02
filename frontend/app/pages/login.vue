<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import AuthPageShell from '~/components/auth/AuthPageShell.vue'
import { useAuthStore } from '~/stores/auth'
import { type AuthResponse, getDashboardPath, setAuthSession, useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: false })

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const googleLoading = ref(false)
const passwordVisible = ref(false)
const toast = useToast()

const token = useAuthTokenCookie()
const currentUser = useAuthUserCookie()
const auth = useAuthStore()

if (token.value && currentUser.value) {
  await navigateTo(getDashboardPath(currentUser.value.role), { replace: true })
}

const emailHint = computed(() => {
  if (!form.email) return 'Sử dụng email học tập hoặc email đã đăng ký.'
  return form.email.includes('@') ? 'Địa chỉ email hợp lệ.' : 'Email cần chứa ký tự @.'
})

async function handleLogin() {
  loading.value = true

  try {
    const data = await useApi<AuthResponse, typeof form>('/auth/login', {
      method: 'POST',
      body: form,
    })

    setAuthSession(data)
    auth.setToken(data.access_token)
    auth.setUser(data.user)
    auth.isReady = true

    toast.success(`Chào mừng ${data.user.name}, bạn đã sẵn sàng tiếp tục học tập.`)
    await navigateTo(getDashboardPath(data.user.role), { replace: true })
  } catch (error: any) {
    if (error?.statusCode === 403 && error?.data?.requires_verification && error?.data?.email) {
      await navigateTo(`/verify-email?email=${encodeURIComponent(error.data.email)}`)
      return
    }
    toast.error(error?.data?.message || 'Đăng nhập thất bại. Vui lòng kiểm tra email và mật khẩu.')
  } finally {
    loading.value = false
  }
}

async function handleGoogleLogin() {
  googleLoading.value = true

  try {
    const data = await useApi<{ url: string }>('/auth/google/url')
    await navigateTo(data.url, { external: true })
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể khởi tạo đăng nhập bằng Google.')
    googleLoading.value = false
  }
}
</script>

<template>
  <AuthPageShell
    panel-kicker="Xin chào"
    panel-title="Chào mừng trở lại"
    panel-description="Vui lòng đăng nhập vào tài khoản của bạn."
    foot-text="Chưa có tài khoản?"
    foot-link-text="Đăng ký"
    foot-link-to="/register"
    hero-title="Chào mừng bạn đến với hệ thống học tập trực tuyến"
  >
    <button class="google-button" type="button" :disabled="googleLoading" @click="handleGoogleLogin">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path
          fill="currentColor"
          d="M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z"
        />
      </svg>
      <span>{{ googleLoading ? 'Đang chuyển hướng với Google...' : 'Tiếp tục với Google' }}</span>
    </button>

    <div class="divider">
      <span>hoặc dùng email</span>
    </div>

    <form class="auth-form" @submit.prevent="handleLogin">
      <label class="field">
        <span>Email</span>
        <input
          v-model="form.email"
          type="email"
          name="email"
          autocomplete="email"
          placeholder="ban@ptit.edu.vn"
          required
        >
        <small>{{ emailHint }}</small>
      </label>

      <label class="field">
        <div class="field-row">
          <span>Mật khẩu</span>
          <NuxtLink to="/forgot-password">Quên mật khẩu?</NuxtLink>
        </div>
        <div class="password-wrap">
          <input
            v-model="form.password"
            :type="passwordVisible ? 'text' : 'password'"
            name="password"
            autocomplete="current-password"
            placeholder="Nhập mật khẩu"
            required
          >
          <button type="button" class="inline-action" @click="passwordVisible = !passwordVisible">
            {{ passwordVisible ? 'Ẩn' : 'Hiện' }}
          </button>
        </div>
      </label>

      <button class="primary-button" type="submit" :disabled="loading">
        {{ loading ? 'Đang đăng nhập...' : 'Đăng nhập vào hệ thống' }}
      </button>
    </form>
  </AuthPageShell>
</template>
