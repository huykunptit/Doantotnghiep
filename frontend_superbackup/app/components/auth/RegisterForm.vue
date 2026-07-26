<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const googleLoading = ref(false)
const error = ref('')
const passwordVisible = ref(false)
const confirmVisible = ref(false)

async function handleRegister() {
  error.value = ''
  if (form.password !== form.password_confirmation) {
    error.value = 'Mật khẩu xác nhận không khớp.'
    return
  }
  loading.value = true
  try {
    const result = await auth.register({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    router.push(`/verify-email?email=${encodeURIComponent(result.email)}&registered=1`)
  } catch (e: any) {
    error.value = e?.data?.message || 'Đăng ký thất bại. Vui lòng thử lại.'
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
  <div class="rf">
    <!-- Error -->
    <div v-if="error" class="rf-alert rf-alert--error" role="alert">
      {{ error }}
    </div>

    <!-- Google SSO -->
    <button type="button" :disabled="googleLoading" class="rf-google-btn" @click="handleGoogleLogin">
      <svg class="rf-google-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
        <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.615 24 12.255 24z"/>
        <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/>
        <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.64 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
      </svg>
      <span>{{ googleLoading ? 'Đang chuyển hướng...' : 'Đăng ký nhanh với Google' }}</span>
    </button>

    <!-- Divider -->
    <div class="rf-divider" role="separator">
      <span>Hoặc tạo tài khoản bằng email</span>
    </div>

    <!-- Form -->
    <form class="rf-form" novalidate @submit.prevent="handleRegister">
      <!-- Name -->
      <div class="rf-field">
        <label class="rf-label" for="rf-name">Họ và tên</label>
        <input
          id="rf-name"
          v-model="form.name"
          class="rf-input"
          type="text"
          name="name"
          placeholder="Nguyễn Văn A"
          autocomplete="name"
          required
        >
      </div>

      <!-- Email -->
      <div class="rf-field">
        <label class="rf-label" for="rf-email">Email</label>
        <input
          id="rf-email"
          v-model="form.email"
          class="rf-input"
          type="email"
          name="email"
          placeholder="hocvien@eript.edu.vn"
          autocomplete="email"
          required
        >
      </div>

      <!-- Password -->
      <div class="rf-field">
        <label class="rf-label" for="rf-password">Mật khẩu</label>
        <div class="rf-password-wrap">
          <input
            id="rf-password"
            v-model="form.password"
            class="rf-input rf-input--padded"
            :type="passwordVisible ? 'text' : 'password'"
            name="password"
            placeholder="Tối thiểu 6 ký tự"
            autocomplete="new-password"
            required
          >
          <button type="button" class="rf-eye-btn" :aria-label="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="passwordVisible = !passwordVisible">
            <i v-if="passwordVisible" class="pi pi-eye-slash" style="font-size:1.0625rem" />
            <i v-else class="pi pi-eye" style="font-size:1.0625rem" />
          </button>
        </div>
      </div>

      <!-- Confirm -->
      <div class="rf-field">
        <label class="rf-label" for="rf-confirm">Xác nhận mật khẩu</label>
        <div class="rf-password-wrap">
          <input
            id="rf-confirm"
            v-model="form.password_confirmation"
            class="rf-input rf-input--padded"
            :type="confirmVisible ? 'text' : 'password'"
            name="password_confirmation"
            placeholder="Nhập lại mật khẩu"
            autocomplete="new-password"
            required
          >
          <button type="button" class="rf-eye-btn" :aria-label="confirmVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="confirmVisible = !confirmVisible">
            <i v-if="confirmVisible" class="pi pi-eye-slash" style="font-size:1.0625rem" />
            <i v-else class="pi pi-eye" style="font-size:1.0625rem" />
          </button>
        </div>
      </div>

      <!-- Notice -->
      <p class="rf-notice">
        Bằng việc tạo tài khoản, bạn đồng ý sử dụng hệ thống học tập, quản lý khóa học và theo dõi tiến độ của mình.
      </p>

      <!-- Submit -->
      <button type="submit" :disabled="loading" class="rf-submit">
        <i v-if="loading" class="pi pi-spin pi-spinner rf-spinner" style="font-size:1rem" />
        <span>{{ loading ? 'Đang tạo tài khoản...' : 'Tạo tài khoản' }}</span>
      </button>
    </form>
  </div>
</template>

<style scoped>
.rf { display: flex; flex-direction: column; gap: 10px; }

.rf-alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
}

.rf-alert--error {
  background: var(--danger-soft);
  color: var(--danger);
  border: 1px solid rgba(226, 75, 74, 0.2);
}

.rf-google-btn {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; height: 44px; border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface-strong, #fff);
  color: var(--text); font-size: 0.875rem; font-weight: 600;
  cursor: pointer; transition: background 150ms, transform 150ms;
}
.rf-google-btn:hover { background: var(--surface); transform: translateY(-1px); }
.rf-google-btn:disabled { opacity: 0.65; cursor: wait; }
.rf-google-icon { width: 18px; height: 18px; flex-shrink: 0; }

.rf-divider {
  position: relative; display: flex; align-items: center;
  justify-content: center; color: var(--muted);
}
.rf-divider::before {
  content: ''; position: absolute; inset: 50% 0 auto;
  border-top: 1px solid var(--line);
}
.rf-divider span {
  position: relative; padding: 0 12px;
  background: var(--surface-strong, #fff);
  font-size: 0.72rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.12em;
}

.rf-form { display: flex; flex-direction: column; gap: 9px; }
.rf-field { display: flex; flex-direction: column; gap: 4px; }
.rf-label { font-size: 0.875rem; font-weight: 600; color: var(--text); }

.rf-input {
  width: 100%; height: 44px; padding: 0 14px;
  border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface-strong, #fff); color: var(--text);
  font: inherit; font-size: 0.9rem; outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}
.rf-input::placeholder { color: var(--muted); }
.rf-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }
.rf-input--padded { padding-right: 48px; }

.rf-password-wrap { position: relative; }
.rf-eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: var(--muted); cursor: pointer; border-radius: 6px;
  transition: color 150ms, background 150ms;
}
.rf-eye-btn:hover { color: var(--text); background: rgba(var(--primary-rgb), 0.06); }

.rf-notice {
  margin: 0; padding: 10px 14px; border-radius: 8px;
  background: var(--surface); font-size: 0.8125rem;
  line-height: 1.6; color: var(--muted);
}

.rf-submit {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; height: 46px; border-radius: 8px; border: none;
  background: var(--green); color: #fff; font: inherit;
  font-size: 0.9375rem; font-weight: 700; cursor: pointer;
  transition: background 150ms, transform 150ms, box-shadow 150ms;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.rf-submit:hover { background: var(--green-deep); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(var(--primary-rgb), 0.25); }
.rf-submit:disabled { opacity: 0.65; cursor: wait; transform: none; }

.rf-spinner { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
