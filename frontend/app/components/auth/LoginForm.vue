<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff, Loader } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const remember = ref(false)
const loading = ref(false)
const googleLoading = ref(false)
const error = ref('')
const passwordVisible = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(form)
    router.push('/')
  } catch (e: any) {
    error.value = e?.data?.message || 'Đăng nhập thất bại. Email hoặc mật khẩu chưa đúng.'
  } finally {
    loading.value = false
  }
}

async function handleGoogleLogin() {
  error.value = ''
  googleLoading.value = true
  try {
    const googleUrl = await auth.getGoogleLoginUrl()
    await navigateTo(googleUrl, { external: true })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể khởi tạo đăng nhập Google. Vui lòng thử lại.'
    googleLoading.value = false
  }
}
</script>

<template>
  <div class="lf">
    <!-- Error -->
    <div v-if="error" class="lf-alert lf-alert--error" role="alert">
      {{ error }}
    </div>

    <!-- Google SSO -->
    <button
      type="button"
      :disabled="googleLoading"
      class="lf-google-btn"
      @click="handleGoogleLogin"
    >
      <svg class="lf-google-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
        <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.615 24 12.255 24z"/>
        <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/>
        <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.64 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
      </svg>
      <span>{{ googleLoading ? 'Đang chuyển hướng...' : 'Tiếp tục với Google' }}</span>
    </button>

    <!-- Divider -->
    <div class="lf-divider" role="separator">
      <span>Hoặc đăng nhập bằng email</span>
    </div>

    <!-- Form -->
    <form class="lf-form" novalidate @submit.prevent="handleLogin">
      <!-- Email -->
      <div class="lf-field">
        <label class="lf-label" for="lf-email">Email</label>
        <input
          id="lf-email"
          v-model="form.email"
          class="lf-input"
          type="email"
          name="email"
          placeholder="hocvien@sylva.edu.vn"
          autocomplete="email"
          required
        >
      </div>

      <!-- Password -->
      <div class="lf-field">
        <div class="lf-field-row">
          <label class="lf-label" for="lf-password">Mật khẩu</label>
          <NuxtLink to="/forgot-password" class="lf-forgot">Quên mật khẩu?</NuxtLink>
        </div>
        <div class="lf-password-wrap">
          <input
            id="lf-password"
            v-model="form.password"
            class="lf-input lf-input--padded"
            :type="passwordVisible ? 'text' : 'password'"
            name="password"
            placeholder="Nhập mật khẩu của bạn"
            autocomplete="current-password"
            required
          >
          <button type="button" class="lf-eye-btn" :aria-label="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="passwordVisible = !passwordVisible">
            <EyeOff v-if="passwordVisible" :size="17" :stroke-width="1.75" />
            <Eye v-else :size="17" :stroke-width="1.75" />
          </button>
        </div>
      </div>

      <!-- Remember -->
      <label class="lf-remember">
        <input v-model="remember" type="checkbox" class="lf-checkbox">
        <span>Giữ đăng nhập trên thiết bị này</span>
      </label>

      <!-- Submit -->
      <button type="submit" :disabled="loading" class="lf-submit">
        <Loader v-if="loading" :size="16" :stroke-width="2" class="lf-spinner" />
        <span>{{ loading ? 'Đang đăng nhập...' : 'Đăng nhập vào hệ thống' }}</span>
      </button>
    </form>

    <!-- Register link -->
    <p class="lf-register-link">
      Chưa có tài khoản?
      <NuxtLink to="/register" class="lf-register-cta">Đăng ký ngay</NuxtLink>
    </p>
  </div>
</template>

<style scoped>
.lf {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* ── Alert ── */
.lf-alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
}

.lf-alert--error {
  background: var(--danger-soft);
  color: var(--danger);
  border: 1px solid rgba(226, 75, 74, 0.2);
}

/* ── Google ── */
.lf-google-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  height: 44px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface-strong, #fff);
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms, transform 150ms;
}

.lf-google-btn:hover {
  background: var(--surface);
  transform: translateY(-1px);
}

.lf-google-btn:disabled { opacity: 0.65; cursor: wait; }

.lf-google-icon { width: 18px; height: 18px; flex-shrink: 0; }

/* ── Divider ── */
.lf-divider {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted);
}

.lf-divider::before {
  content: '';
  position: absolute;
  inset: 50% 0 auto;
  border-top: 1px solid var(--line);
}

.lf-divider span {
  position: relative;
  padding: 0 12px;
  background: var(--surface-strong, #fff);
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

/* ── Form ── */
.lf-form { display: flex; flex-direction: column; gap: 14px; }

.lf-field { display: flex; flex-direction: column; gap: 6px; }

.lf-field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.lf-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.lf-forgot {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--green-deep);
  text-decoration: none;
}

.lf-forgot:hover { text-decoration: underline; }

.lf-input {
  width: 100%;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface-strong, #fff);
  color: var(--text);
  font: inherit;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}

.lf-input::placeholder { color: var(--muted); }

.lf-input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px var(--green-soft);
}

.lf-input--padded { padding-right: 48px; }

.lf-password-wrap { position: relative; }

.lf-eye-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px; height: 28px;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  border-radius: 6px;
  transition: color 150ms, background 150ms;
}

.lf-eye-btn:hover {
  color: var(--text);
  background: rgba(var(--primary-rgb), 0.06);
}

/* ── Remember ── */
.lf-remember {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 8px;
  background: var(--surface);
  cursor: pointer;
}

.lf-checkbox {
  width: 16px; height: 16px;
  border-radius: 4px;
  accent-color: var(--green);
  cursor: pointer;
  flex-shrink: 0;
}

.lf-remember span {
  font-size: 0.875rem;
  color: var(--muted);
  line-height: 1.5;
}

/* ── Submit ── */
.lf-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 46px;
  border-radius: 8px;
  border: none;
  background: var(--green);
  color: #fff;
  font: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms, transform 150ms, box-shadow 150ms;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}

.lf-submit:hover {
  background: var(--green-deep);
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(var(--primary-rgb), 0.25);
}

.lf-submit:disabled { opacity: 0.65; cursor: wait; transform: none; }

.lf-spinner { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Register link ── */
.lf-register-link {
  text-align: center;
  font-size: 0.875rem;
  color: var(--muted);
  margin: 0;
  padding-top: 4px;
}

.lf-register-cta {
  font-weight: 700;
  color: var(--green-deep);
  text-decoration: none;
  margin-left: 4px;
}

.lf-register-cta:hover { text-decoration: underline; }
</style>
