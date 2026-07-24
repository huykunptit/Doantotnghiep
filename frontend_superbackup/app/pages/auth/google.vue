<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { type AuthResponse, getDashboardPath, setAuthSession } from '~/composables/useAuthSession'

definePageMeta({ layout: false })

const route = useRoute()
const auth = useAuthStore()
const errorMessage = ref('')

const queryString = new URLSearchParams(
  Object.entries(route.query).flatMap(([key, value]) => {
    if (Array.isArray(value)) {
      return value
        .filter((item): item is string => typeof item === 'string')
        .map((item) => [key, item] as [string, string])
    }
    return typeof value === 'string' ? [[key, value] as [string, string]] : []
  }),
).toString()

try {
  const data = await useApi<AuthResponse>(queryString ? `/auth/google/callback?${queryString}` : '/auth/google/callback')
  setAuthSession(data)
  auth.setToken(data.access_token)
  auth.setUser(data.user)
  auth.isReady = true
  await navigateTo(getDashboardPath(data.user.role), { replace: true })
}
catch (error: any) {
  errorMessage.value = error?.data?.message || 'Đăng nhập bằng Google thất bại. Vui lòng thử lại.'
}
</script>

<template>
  <div class="google-shell">
    <div class="google-card">
      <!-- Logo / Icon -->
      <div class="google-icon-wrap">
        <svg v-if="!errorMessage" width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="20" fill="#4285F4" fill-opacity="0.08" />
          <path d="M32 20.25c0-.77-.07-1.52-.2-2.25H20v4.26h6.74a5.77 5.77 0 0 1-2.5 3.78v3.14h4.05C30.73 27.09 32 23.92 32 20.25Z" fill="#4285F4" />
          <path d="M20 33c3.38 0 6.22-1.12 8.29-3.04l-4.05-3.14c-1.12.75-2.55 1.2-4.24 1.2-3.26 0-6.02-2.2-7-5.17H8.8v3.24A12.5 12.5 0 0 0 20 33Z" fill="#34A853" />
          <path d="M13 22.85A7.5 7.5 0 0 1 12.6 20c0-.99.17-1.95.4-2.85V13.9H8.8A12.5 12.5 0 0 0 7.5 20c0 2.02.48 3.94 1.3 5.59L13 22.85Z" fill="#FBBC04" />
          <path d="M20 12.98c1.84 0 3.49.63 4.79 1.87l3.59-3.59C26.22 9.2 23.38 8 20 8A12.5 12.5 0 0 0 8.8 13.91l4.2 3.24c.98-2.97 3.74-5.17 7-5.17Z" fill="#EA4335" />
        </svg>
        <i v-else class="pi pi-exclamation-circle" style="font-size:2.5rem;color: #ef4444" />
      </div>

      <template v-if="!errorMessage">
        <!-- Spinner -->
        <div class="google-spinner" />
        <h1>Đang xác thực tài khoản Google...</h1>
        <p>Hệ thống đang kiểm tra vai trò và chuyển đến dashboard phù hợp.</p>
      </template>

      <template v-else>
        <h1 style="color: #ef4444;">Không thể hoàn tất đăng nhập</h1>
        <p class="error-msg">{{ errorMessage }}</p>
        <NuxtLink to="/login" class="back-btn">Quay lại đăng nhập</NuxtLink>
      </template>
    </div>
  </div>
</template>

<style scoped>
.google-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface, #f8fafc);
  padding: 24px;
}

.google-card {
  background: #fff;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 20px;
  padding: 40px 36px;
  max-width: 440px;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 32px rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.google-icon-wrap {
  width: 64px; height: 64px;
  background: rgba(66,133,244,0.06);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 4px;
}

h1 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.4;
}

p {
  font-size: 0.875rem;
  color: var(--muted, #6b7280);
  margin: 0;
  line-height: 1.6;
}

.google-spinner {
  width: 32px; height: 32px;
  border: 3px solid rgba(66,133,244,0.15);
  border-top-color: #4285F4;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.error-msg { color: #ef4444; }

.back-btn {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  background: var(--green, #16a34a);
  color: #fff;
  font-weight: 700; font-size: 0.875rem;
  text-decoration: none;
  transition: opacity 0.2s;
}
.back-btn:hover { opacity: 0.88; }

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .google-card { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] h1 { color: var(--text); }
</style>
