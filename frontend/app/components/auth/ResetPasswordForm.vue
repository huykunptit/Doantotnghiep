<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Eye, EyeOff, Loader, CheckCircle } from 'lucide-vue-next'
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
const passwordVisible = ref(false)
const confirmVisible = ref(false)

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
  <div class="rpf">
    <!-- Success state -->
    <div v-if="success" class="rpf-success">
      <div class="rpf-success-icon">
        <CheckCircle :size="28" :stroke-width="1.75" />
      </div>
      <h3 class="rpf-success-title">Mật khẩu đã được cập nhật!</h3>
      <p class="rpf-success-body">Bạn có thể đăng nhập ngay bây giờ với mật khẩu mới.</p>
      <NuxtLink to="/login" class="rpf-login-btn">Đăng nhập ngay</NuxtLink>
    </div>

    <!-- Form -->
    <template v-else>
      <div v-if="error" class="rpf-alert" role="alert">
        {{ error }}
      </div>

      <form class="rpf-form" novalidate @submit.prevent="handleReset">
        <!-- Email (readonly) -->
        <div class="rpf-field">
          <label class="rpf-label" for="rpf-email">Email</label>
          <input
            id="rpf-email"
            v-model="form.email"
            class="rpf-input rpf-input--disabled"
            type="email"
            disabled
          >
        </div>

        <!-- New password -->
        <div class="rpf-field">
          <label class="rpf-label" for="rpf-password">Mật khẩu mới</label>
          <div class="rpf-password-wrap">
            <input
              id="rpf-password"
              v-model="form.password"
              class="rpf-input rpf-input--padded"
              :type="passwordVisible ? 'text' : 'password'"
              name="password"
              placeholder="Tối thiểu 6 ký tự"
              autocomplete="new-password"
              required
            >
            <button type="button" class="rpf-eye-btn" :aria-label="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="passwordVisible = !passwordVisible">
              <EyeOff v-if="passwordVisible" :size="17" :stroke-width="1.75" />
              <Eye v-else :size="17" :stroke-width="1.75" />
            </button>
          </div>
        </div>

        <!-- Confirm -->
        <div class="rpf-field">
          <label class="rpf-label" for="rpf-confirm">Xác nhận mật khẩu</label>
          <div class="rpf-password-wrap">
            <input
              id="rpf-confirm"
              v-model="form.password_confirmation"
              class="rpf-input rpf-input--padded"
              :type="confirmVisible ? 'text' : 'password'"
              name="password_confirmation"
              placeholder="Nhập lại mật khẩu mới"
              autocomplete="new-password"
              required
            >
            <button type="button" class="rpf-eye-btn" :aria-label="confirmVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="confirmVisible = !confirmVisible">
              <EyeOff v-if="confirmVisible" :size="17" :stroke-width="1.75" />
              <Eye v-else :size="17" :stroke-width="1.75" />
            </button>
          </div>
        </div>

        <button type="submit" :disabled="loading" class="rpf-submit">
          <Loader v-if="loading" :size="16" :stroke-width="2" class="rpf-spinner" />
          <span>{{ loading ? 'Đang lưu...' : 'Lưu mật khẩu mới' }}</span>
        </button>
      </form>
    </template>
  </div>
</template>

<style scoped>
.rpf { display: flex; flex-direction: column; gap: 18px; }

.rpf-alert {
  padding: 12px 16px; border-radius: 8px;
  font-size: 0.875rem; font-weight: 500; line-height: 1.5;
  background: var(--danger-soft); color: var(--danger);
  border: 1px solid rgba(226, 75, 74, 0.2);
}

.rpf-form { display: flex; flex-direction: column; gap: 13px; }
.rpf-field { display: flex; flex-direction: column; gap: 6px; }
.rpf-label { font-size: 0.875rem; font-weight: 600; color: var(--text); }

.rpf-input {
  width: 100%; height: 44px; padding: 0 14px;
  border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface-strong, #fff); color: var(--text);
  font: inherit; font-size: 0.9rem; outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}
.rpf-input::placeholder { color: var(--muted); }
.rpf-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }
.rpf-input--padded { padding-right: 48px; }
.rpf-input--disabled { opacity: 0.6; cursor: not-allowed; background: var(--surface); }

.rpf-password-wrap { position: relative; }
.rpf-eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: var(--muted); cursor: pointer; border-radius: 6px;
  transition: color 150ms, background 150ms;
}
.rpf-eye-btn:hover { color: var(--text); background: rgba(var(--primary-rgb), 0.06); }

.rpf-submit {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; height: 46px; border-radius: 8px; border: none;
  background: var(--green); color: #fff; font: inherit;
  font-size: 0.9375rem; font-weight: 700; cursor: pointer;
  transition: background 150ms, transform 150ms, box-shadow 150ms;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.rpf-submit:hover { background: var(--green-deep); transform: translateY(-1px); }
.rpf-submit:disabled { opacity: 0.65; cursor: wait; transform: none; }

.rpf-spinner { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Success ── */
.rpf-success {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; gap: 12px; padding: 12px 0;
}
.rpf-success-icon {
  display: flex; align-items: center; justify-content: center;
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--green-soft); color: var(--green);
}
.rpf-success-title {
  margin: 0; font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.25rem; font-weight: 700; color: var(--text);
}
.rpf-success-body {
  margin: 0; font-size: 0.875rem; line-height: 1.65;
  color: var(--muted); max-width: 300px;
}
.rpf-login-btn {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 6px; padding: 11px 28px; border-radius: 8px;
  border: none; background: var(--green); text-decoration: none;
  font-size: 0.9375rem; font-weight: 700; color: #fff;
  transition: background 150ms, transform 150ms;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.rpf-login-btn:hover { background: var(--green-deep); transform: translateY(-1px); }
</style>
