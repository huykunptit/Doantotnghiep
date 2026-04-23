<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { type AuthResponse, getDashboardPath, setAuthSession, useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const googleLoading = ref(false)
const passwordVisible = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const token = useAuthTokenCookie()
const currentUser = useAuthUserCookie()

if (token.value && currentUser.value) {
  await navigateTo(getDashboardPath(currentUser.value.role), { replace: true })
}

const emailHint = computed(() => {
  if (!form.email) return 'Sử dụng email học tập hoặc email đã đăng ký.'
  return form.email.includes('@') ? 'Địa chỉ email hợp lệ.' : 'Email cần chứa ký tự @.'
})

async function handleLogin() {
  errorMessage.value = ''
  successMessage.value = ''
  loading.value = true

  try {
    const data = await useApi<AuthResponse, typeof form>('/auth/login', {
      method: 'POST',
      body: form,
    })

    setAuthSession(data)

    successMessage.value = `Chào mừng ${data.user.name}, bạn đã sẵn sàng tiếp tục học tập.`
    await navigateTo(getDashboardPath(data.user.role), { replace: true })
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Đăng nhập thất bại. Vui lòng kiểm tra email và mật khẩu.'
  } finally {
    loading.value = false
  }
}

async function handleGoogleLogin() {
  errorMessage.value = ''
  googleLoading.value = true

  try {
    const data = await useApi<{ url: string }>('/auth/google/url')
    await navigateTo(data.url, { external: true })
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể khởi tạo đăng nhập bằng Google.'
    googleLoading.value = false
  }
}
</script>

<template>
  <main class="auth-shell">
    <section class="auth-stage">
      <div class="auth-copy">
        <div class="brand-line">
          <span class="brand-mark" />
          <span class="brand-name">PTIT LMS</span>
        </div>

        <div class="copy-stack">
          <p class="eyebrow">Learning platform</p>
          <h1>
            Đăng nhập vào
            <span>không gian học tập</span>
            ngắn gọn và thân thiện.
          </h1>
          <p class="lead">
            Giao diện mới ưu tiên sự tập trung, dễ sinh viên vào bài nhanh hơn và dễ giảng viên theo dõi lớp học nhẹ hơn.
          </p>
        </div>

        <div class="feature-strip">
          <article>
            <p class="feature-label">Môi trường</p>
            <strong>Trang sáng, ít nhiễu</strong>
            <span>Tập trung vào bài học và tiến độ.</span>
          </article>
          <article>
            <p class="feature-label">Truy cập</p>
            <strong>Email hoặc Google</strong>
            <span>Vào hệ thống nhanh trên mọi thiết bị.</span>
          </article>
        </div>
      </div>

      <section class="auth-panel">
        <div class="panel-head">
          <p class="panel-kicker">Xin chào</p>
          <h2>Đăng nhập</h2>
          <p>Nhập thông tin tài khoản để tiếp tục vào khu vực học tập của bạn.</p>
        </div>

        <div v-if="errorMessage" class="feedback feedback-error">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="feedback feedback-success">
          {{ successMessage }}
        </div>

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

        <div class="panel-foot">
          <p>Chưa có tài khoản?</p>
          <NuxtLink to="/register">Tạo tài khoản mới</NuxtLink>
        </div>
      </section>
    </section>
  </main>
</template>
