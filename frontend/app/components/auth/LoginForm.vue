<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
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
  <div class="w-full animate-fade-in-up">
    <header class="mb-8">
      <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary/70">Đăng nhập</p>
      <h2 class="mt-3 font-headline text-3xl font-bold tracking-[-0.03em] text-on-surface sm:text-[2.2rem]">Chào mừng bạn quay trở lại</h2>
      <p class="mt-3 max-w-md text-sm leading-7 text-on-surface-variant sm:text-base">
        Đăng nhập để tiếp tục học, theo dõi tiến độ và truy cập lớp học của bạn.
      </p>
    </header>

    <div v-if="error" class="mb-6 rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">
      {{ error }}
    </div>

    <div class="mb-6">
      <button type="button" :disabled="googleLoading" @click="handleGoogleLogin" class="flex w-full items-center justify-center gap-3 rounded-2xl border border-outline-variant/30 bg-surface-lowest px-4 py-3 text-sm font-semibold text-on-surface shadow-sm transition-all hover:-translate-y-0.5 hover:border-outline/40 hover:bg-surface-low disabled:opacity-70">
        <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z"/></svg>
        <span>{{ googleLoading ? 'Đang chuyển hướng với Google...' : 'Tiếp tục với Google' }}</span>
      </button>
    </div>

    <div class="relative mb-6 flex items-center justify-center">
      <div class="w-full border-t border-outline-variant/30"></div>
      <span class="absolute bg-surface-lowest px-4 text-[11px] font-semibold uppercase tracking-[0.24em] text-outline">Hoặc đăng nhập bằng email</span>
    </div>

    <form class="space-y-5" @submit.prevent="handleLogin">
      <div class="space-y-2">
        <label class="text-sm font-semibold text-on-surface-variant" for="email">Email</label>
        <input v-model="form.email" class="w-full rounded-2xl border border-outline-variant/30 bg-surface-low px-4 py-3 text-on-surface outline-none transition-all placeholder:text-outline/70 focus:border-primary/40 focus:bg-surface-lowest focus:ring-4 focus:ring-primary/10" id="email" name="email" placeholder="ban@ptit.edu.vn" type="email" required />
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
          <label class="text-sm font-semibold text-on-surface-variant" for="password">Mật khẩu</label>
          <NuxtLink to="/forgot-password" class="text-xs font-semibold text-primary transition-colors hover:text-primary-light">Quên mật khẩu?</NuxtLink>
        </div>
        <div class="relative">
          <input v-model="form.password" :type="passwordVisible ? 'text' : 'password'" class="w-full rounded-2xl border border-outline-variant/30 bg-surface-low px-4 py-3 pr-12 text-on-surface outline-none transition-all placeholder:text-outline/70 focus:border-primary/40 focus:bg-surface-lowest focus:ring-4 focus:ring-primary/10" id="password" name="password" placeholder="Nhập mật khẩu" required />
          <button @click="passwordVisible = !passwordVisible" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline transition-colors hover:text-on-surface">
            <span class="material-symbols-outlined text-xl">{{ passwordVisible ? 'visibility_off' : 'visibility' }}</span>
          </button>
        </div>
      </div>

      <div class="flex items-start gap-3 rounded-2xl bg-surface-low px-4 py-3">
        <input v-model="remember" class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" id="remember" type="checkbox" />
        <label class="cursor-pointer select-none text-sm leading-6 text-on-surface-variant" for="remember">Giữ đăng nhập trên thiết bị này để vào lại nhanh hơn.</label>
      </div>

      <button type="submit" :disabled="loading" class="flex w-full items-center justify-center gap-2 rounded-2xl cta-gradient px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 disabled:opacity-70">
        <span v-if="loading" class="material-symbols-outlined animate-spin">progress_activity</span>
        <span>{{ loading ? 'Đang đăng nhập...' : 'Đăng nhập vào hệ thống' }}</span>
      </button>
    </form>

    <div class="mt-6 rounded-2xl border border-outline-variant/30 bg-surface-low px-4 py-4 text-center">
      <p class="text-sm text-on-surface-variant">
        Chưa có tài khoản?
        <NuxtLink to="/register" class="font-bold text-primary hover:underline underline-offset-4 decoration-2">Đăng ký ngay</NuxtLink>
      </p>
    </div>
  </div>
</template>
